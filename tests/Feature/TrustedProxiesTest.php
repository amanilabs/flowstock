<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

afterEach(function () {
    // TrustProxies::at() sets a static property that would otherwise leak
    // into every other test running in this process.
    TrustProxies::at([]);
});

it('ignores a spoofed X-Forwarded-For header when no proxy is trusted (the default)', function () {
    Route::get('/api/v1/__test-ip', fn (Request $r) => response()->json(['ip' => $r->ip()]));

    $response = $this->withHeader('X-Forwarded-For', '6.6.6.6')->getJson('/api/v1/__test-ip');

    expect($response->json('ip'))->not->toBe('6.6.6.6');
});

it('honors X-Forwarded-For once the connecting peer is a trusted proxy', function () {
    Route::get('/api/v1/__test-ip', fn (Request $r) => response()->json(['ip' => $r->ip()]));

    // Simulates TRUSTED_PROXIES=127.0.0.1 — trusting the immediate peer that
    // PHPUnit's test client connects as.
    TrustProxies::at(['127.0.0.1']);

    $response = $this->withHeader('X-Forwarded-For', '6.6.6.6')->getJson('/api/v1/__test-ip');

    expect($response->json('ip'))->toBe('6.6.6.6');
});
