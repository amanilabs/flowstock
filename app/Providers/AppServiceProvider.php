<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $tenantId = $request->user()?->tenant_id;

            return Limit::perMinute(config('rate_limiting.per_tenant_per_minute'))
                ->by($tenantId ? "tenant:{$tenantId}" : 'ip:'.$request->ip());
        });

        $this->documentRateLimiting();
        $this->configureTrustedProxies();
    }

    /**
     * See config/security.php for what TRUSTED_PROXIES actually controls.
     */
    private function configureTrustedProxies(): void
    {
        $trustedProxies = trim((string) config('security.trusted_proxies', ''));

        if ($trustedProxies === '') {
            return;
        }

        TrustProxies::at(
            $trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)),
        );
    }

    /**
     * Rate limiting is enforced by global middleware, not by anything a
     * controller method throws, so Scramble can't infer it from a method
     * body — document the 429 response on every operation explicitly.
     */
    private function documentRateLimiting(): void
    {
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $tooManyRequests = Response::make(429)
                ->setDescription('Too many requests — the tenant or IP rate limit was exceeded.')
                ->setContent(
                    'application/json',
                    Schema::fromType(
                        (new ObjectType)
                            ->addProperty('message', new StringType)
                            ->setRequired(['message'])
                    ),
                )
                ->addHeader('Retry-After', new Header(
                    description: 'Seconds to wait before retrying.',
                    schema: Schema::fromType(new IntegerType),
                ));

            foreach ($openApi->paths as $path) {
                foreach ($path->operations as $operation) {
                    $operation->addResponse(clone $tooManyRequests);
                }
            }
        });
    }
}
