<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Requests\UpdateProductCategoryRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    /** Requires the view-categories permission. */
    public function index(Request $request)
    {
        $categories = ProductCategory::query()->paginate($request->integer('per_page', 15));

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
        return (new ProductCategoryResource(ProductCategory::create($request->validated())))
            ->response()->setStatusCode(201);
    }

    /** Requires the manage-categories permission. */
    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory)
    {
        $productCategory->update($request->validated());

        return new ProductCategoryResource($productCategory);
    }

    /** Requires the manage-categories permission. */
    public function destroy(ProductCategory $productCategory)
    {
        $productCategory->delete();

        return response()->noContent();
    }
}
