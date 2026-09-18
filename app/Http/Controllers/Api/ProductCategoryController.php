<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\CachesTenantScopedLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Requests\UpdateProductCategoryRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    use CachesTenantScopedLists;

    /** Requires the view-categories permission. */
    #[QueryParameter('search', description: 'Match against category name.', type: 'string')]
    #[QueryParameter('per_page', description: 'Items per page.', type: 'int', default: 15, example: 25)]
    public function index(Request $request)
    {
        $categories = $this->rememberTenantList('categories', $request, fn () => ProductCategory::query()
            ->withCount('products')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'ilike', '%'.$request->string('search').'%'))
            ->paginate($request->integer('per_page', 15))
        );

        return ProductCategoryResource::collection($categories);
    }

    /** Requires the view-categories permission. */
    public function show(ProductCategory $productCategory)
    {
        return new ProductCategoryResource($productCategory);
    }

    /** Requires the manage-categories permission. */
    public function store(StoreProductCategoryRequest $request)
    {
        $category = ProductCategory::create($request->validated());

        $this->flushCategoryAndProductLists($category->tenant_id);

        return (new ProductCategoryResource($category))->response()->setStatusCode(201);
    }

    /** Requires the manage-categories permission. */
    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory)
    {
        $productCategory->update($request->validated());

        $this->flushCategoryAndProductLists($productCategory->tenant_id);

        return new ProductCategoryResource($productCategory);
    }

    /** Requires the manage-categories permission. */
    public function destroy(ProductCategory $productCategory)
    {
        $productCategory->delete();

        $this->flushCategoryAndProductLists($productCategory->tenant_id);

        return response()->noContent();
    }

    /**
     * A category name/slug change is embedded in the product resource, so a
     * category write must invalidate cached product lists too, not just its
     * own.
     */
    private function flushCategoryAndProductLists(int $tenantId): void
    {
        $this->flushTenantList('categories', $tenantId);
        $this->flushTenantList('products', $tenantId);
    }
}
