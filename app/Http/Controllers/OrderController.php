<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_email' => 'required|email',
            'username' => 'required|string|max:255',
            'user_address' => 'required|string',
            'product_name' => 'required|string',
            'product_color' => 'nullable|string',
            'product_description' => 'nullable|string',
            'product_price' => 'required|numeric|min:0',
        ]);

        $order = Order::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'data' => $order,
        ], 201);
    }
}
