<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Display a listing of available products for customers.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Product::query()
            ->where('availability', true)
            ->latest('id');

        // Logika pencarian sederhana
        if (! empty($validated['search'])) {
            $query->where('name', 'like', '%'.$validated['search'].'%');
        }

        $products = $query->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'message' => 'Data produk berhasil diambil.',
        ], 200);
    }

    /**
     * Display the specified product.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->availability, 404, 'Produk tidak ditemukan atau tidak tersedia.');

        return response()->json([
            'data' => new ProductResource($product),
            'message' => 'Data produk berhasil diambil.',
        ], 200);
    }

    /**
     * Display a listing of all products for Owner.
     */
    public function ownerIndex(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $products = Product::query()
            ->latest('id')
            ->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'message' => 'Data semua produk berhasil diambil.',
        ], 200);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'], // Batas atas untuk mencegah typo ekstrem
            'availability' => ['sometimes', 'boolean'],
        ]);

        $validated['availability'] = $validated['availability'] ?? true;

        $product = Product::create($validated);

        Log::info('Product created by owner', [
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new ProductResource($product),
            'message' => 'Produk berhasil dibuat.',
        ], 201);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:99999999'],
            'availability' => ['sometimes', 'boolean'],
        ]);

        $product->update($validated);

        Log::info('Product updated by owner', [
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'changes' => $product->getChanges(), // Log perubahan spesifik
        ]);

        return response()->json([
            'data' => new ProductResource($product->fresh()),
            'message' => 'Produk berhasil diperbarui.',
        ], 200);
    }

    /**
     * Soft-disable the specified product.
     * Menjaga integritas referensial dengan order_items yang sudah ada.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorizeOwner($request);

        $product->update(['availability' => false]);

        Log::info('Product disabled by owner', [
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => null,
            'message' => 'Produk berhasil dinonaktifkan.',
        ], 200);
    }

    /**
     * Helper method to enforce Owner role authorization (Defense in Depth).
     */
    private function authorizeOwner(Request $request): void
    {
        $user = $request->user();

        // Null-safe check untuk mencegah Fatal Error jika middleware bypass
        abort_unless(
            $user !== null && $user->role?->name === 'OWNER',
            403,
            'Akses ditolak. Hanya Owner yang dapat melakukan tindakan ini.'
        );
    }
}
