<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\CachesTenantScopedLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    use CachesTenantScopedLists;

    /** Requires the view-warehouses permission. */
    #[QueryParameter('active_only', description: 'Only return active warehouses.', type: 'bool', default: false)]
    #[QueryParameter('per_page', description: 'Items per page.', type: 'int', default: 15, example: 25)]
    public function index(Request $request)
    {
        $warehouses = $this->rememberTenantList('warehouses', $request, fn () => Warehouse::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->paginate($request->integer('per_page', 15))
        );

        return WarehouseResource::collection($warehouses);
    }

    /** Requires the view-warehouses permission. */
    public function show(Warehouse $warehouse)
    {
        return new WarehouseResource($warehouse);
    }

    /** Requires the manage-warehouses permission (Admin only). */
    public function store(StoreWarehouseRequest $request)
    {
        $warehouse = Warehouse::create($request->validated())->fresh();

        $this->flushTenantList('warehouses', $warehouse->tenant_id);

        return (new WarehouseResource($warehouse))->response()->setStatusCode(201);
    }

    /** Requires the manage-warehouses permission (Admin only). */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        $warehouse->update($request->validated());

        $this->flushTenantList('warehouses', $warehouse->tenant_id);

        return new WarehouseResource($warehouse);
    }

    /** Requires the manage-warehouses permission (Admin only). */
    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();

        $this->flushTenantList('warehouses', $warehouse->tenant_id);

        return response()->noContent();
    }
}
