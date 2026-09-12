<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /** Requires the view-customers permission. */
    public function index(Request $request)
    {
        $customers = Customer::query()->paginate($request->integer('per_page', 15));

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
        return (new CustomerResource(Customer::create($request->validated())))
            ->response()->setStatusCode(201);
    }

    /** Requires the manage-customers permission. */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return new CustomerResource($customer);
    }

    /** Requires the manage-customers permission. */
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->noContent();
    }
}
