<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\CachesTenantScopedLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use CachesTenantScopedLists;

    /** Requires the view-customers permission. */
    #[QueryParameter('search', description: 'Match against customer name or email.', type: 'string')]
    #[QueryParameter('per_page', description: 'Items per page.', type: 'int', default: 15, example: 25)]
    public function index(Request $request)
    {
        $customers = $this->rememberTenantList('customers', $request, fn () => Customer::query()
            ->withCount('orders as order_count')
            // total_spent excludes cancelled/refunded orders — those aren't real revenue.
            ->withSum(['orders as total_spent' => fn ($q) => $q->whereNotIn('status', ['cancelled', 'refunded'])], 'total_amount')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(fn ($q) => $q
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%"));
            })
            ->paginate($request->integer('per_page', 15))
        );

        return CustomerResource::collection($customers);
    }

    /** Requires the view-customers permission. */
    public function show(Customer $customer)
    {
        return new CustomerResource($customer);
    }

    /** Requires the manage-customers permission. */
    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        $this->flushTenantList('customers', $customer->tenant_id);

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    /** Requires the manage-customers permission. */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        $this->flushTenantList('customers', $customer->tenant_id);

        return new CustomerResource($customer);
    }

    /** Requires the manage-customers permission. */
    public function destroy(Customer $customer)
    {
        $customer->delete();

        $this->flushTenantList('customers', $customer->tenant_id);

        return response()->noContent();
    }
}
