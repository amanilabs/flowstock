<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Resources\ProductStockResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\StockService;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stockService) {}

    /** Requires the view-stock permission. */
    public function index(Product $product)
    {
        return ProductStockResource::collection(
            $product->stock()->with('warehouse')->get()
        );
    }

    /** Requires the adjust-stock permission. */
    public function adjust(AdjustStockRequest $request, Product $product)
    {
        $warehouse = Warehouse::findOrFail($request->validated('warehouse_id'));

        $movement = $this->stockService->recordMovement(
            product: $product,
            warehouse: $warehouse,
            quantityChange: $request->validated('quantity_change'),
            type: StockMovementType::from($request->validated('type')),
            note: $request->validated('note'),
        );

        return (new StockMovementResource($movement))->response()->setStatusCode(201);
    }
}
