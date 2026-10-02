<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()->where('availability', true)->latest('id')->get();

        return [
            'data' => ProductResource::collection($products),
            'meta' => [],
            'message' => 'Data produk berhasil diambil.',
        ];
    }

    public function show(Product $product)
    {
        abort_unless($product->availability, 404, 'Produk tidak ditemukan.');

        return ['data' => new ProductResource($product), 'message' => 'Data produk berhasil diambil.'];
    }

    public function ownerIndex()
    {
        return [
            'data' => ProductResource::collection(Product::query()->latest('id')->get()),
            'meta' => [],
            'message' => 'Data produk berhasil diambil.',
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'availability' => ['sometimes', 'boolean'],
        ]);
        $product = Product::create($validated + ['availability' => $validated['availability'] ?? true]);

        return response()->json(['data' => new ProductResource($product), 'message' => 'Produk berhasil dibuat.'], 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'availability' => ['sometimes', 'boolean'],
        ]);
        $product->update($validated);

        return ['data' => new ProductResource($product->fresh()), 'message' => 'Produk berhasil diperbarui.'];
    }

    public function destroy(Product $product)
    {
        $product->update(['availability' => false]);

        return response()->json(['message' => 'Produk berhasil dinonaktifkan.']);
    }
}
