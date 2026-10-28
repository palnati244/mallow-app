<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LowStockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'threshold' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ]);

        $threshold = (int) (
            $validated['threshold']
            ?? config('inventory.low_stock_threshold', 10)
        );

        $threshold_products = Product::query()
            ->where('stock_on_hand', '<', $threshold)
            ->orderBy('stock_on_hand')
            ->get();
        
        $products = Product::latest()->get();

        return response()->json([
            'threshold' => $threshold,
            'products' => $products,
            'threshold_products' => $threshold_products,
        ]);
    }
}