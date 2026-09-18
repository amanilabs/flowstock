<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Resources\InventoryResource;
use App\Http\Resources\ProductStockResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

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

    /**
     * Cross-product inventory listing. Deliberately never cached (unlike
     * the other tenant list endpoints) — stock levels must always be
     * read live, the same rule that kept caching off StockService/
     * OrderService in the caching phase.
     *
     * Requires the view-stock permission.
     */
    #[QueryParameter('warehouse_id', description: 'Only this warehouse.', type: 'int')]
    #[QueryParameter('product_id', description: 'Only this product.', type: 'int')]
    #[QueryParameter('category_id', description: 'Only products in this category.', type: 'int')]
    #[QueryParameter('low_stock', description: 'Only rows at or below the product\'s reorder point.', type: 'bool', default: false)]
    #[QueryParameter('search', description: 'Match against product name or SKU.', type: 'string')]
    #[QueryParameter('per_page', description: 'Items per page.', type: 'int', default: 15, example: 25)]
    public function inventory(Request $request)
    {
        $rows = ProductStock::query()
            ->join('products', 'products.id', '=', 'product_stock.product_id')
            ->select('product_stock.*')
            ->with(['product.category', 'warehouse'])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('product_stock.warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_stock.product_id', $request->integer('product_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('products.category_id', $request->integer('category_id')))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('product_stock.quantity', '<=', 'products.reorder_point'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(fn ($q) => $q
                    ->where('products.name', 'ilike', "%{$search}%")
                    ->orWhere('products.sku', 'ilike', "%{$search}%"));
            })
            ->paginate($request->integer('per_page', 15));

        return InventoryResource::collection($rows);
    }

    /**
     * Movement history for a single product, optionally scoped to one
     * warehouse. Requires the view-stock permission.
     */
    #[QueryParameter('warehouse_id', description: 'Only movements at this warehouse.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page.', type: 'int', default: 15, example: 25)]
    public function movements(Request $request, Product $product)
    {
        $movements = StockMovement::where('product_id', $product->id)
            ->with('user')
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return StockMovementResource::collection($movements);
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
