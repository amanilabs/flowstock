<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /** Requires the view-products permission. */
    #[QueryParameter('active_only', description: 'Only return active products.', type: 'bool', default: false)]
    #[QueryParameter('per_page', description: 'Items per page.', type: 'int', default: 15, example: 25)]
    public function index(Request $request)
    {
        $products = Product::query()
            ->with('category')
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->paginate($request->integer('per_page', 15));

        return ProductResource::collection($products);
    }

    /** Requires the view-products permission. */
    public function show(Product $product)
    {
        return new ProductResource($product->load('category'));
    }

    /** Requires the create-products permission. */
    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated())->fresh();

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    /** Requires the update-products permission. */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    /** Requires the delete-products permission. */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }
}
