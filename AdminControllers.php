<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        // Cek sederhana apakah kasir sudah login via session
        if (!session('is_logged_in')) {
            return redirect()->route('login')->withErrors(['username' => 'Silakan login terlebih dahulu!']);
        }

        $query = Order::with('orderDetails.menu')->latest();

        // Filter berdasarkan tanggal jika ada input dari kasir
        if ($request->filled('tanggal')) {
            $query->whereDate('created_at', $request->tanggal);
        }

        $orders = $query->get();

        return view('admin.dashboard', compact('orders'));
    }

    public function updateStatus(Request $request, $id)
    {
        if (!session('is_logged_in')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $order = Order::findOrFail($id);
        $order->status = $request->status; // Contoh: 'selesai' atau 'lunas'
        $order->save();

        return redirect()->back()->with('success', 'Status pesanan berhasil diperbarui!');
    }
}