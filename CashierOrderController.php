<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class CashierOrderController extends Controller
{
    // Menampilkan daftar pesanan untuk kasir
    public function index()
    {
        // Ambil semua pesanan, diurutkan dari yang terbaru
        $orders = Order::with('orderDetails.menu')->latest()->get();

        return view('cashier.orders', compact('orders'));
    }
}