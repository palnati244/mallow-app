<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $customer = Customer::query()
            ->where('email', strtolower($validated['email']))
            ->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found.',
            ], 404);
        }

        $orders = $customer->orders()
            ->with(['items.product'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'customer' => $customer,
            'orders' => $orders,
        ]);
    }
}
