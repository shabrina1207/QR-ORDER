<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        $menus = Menu::where('is_available', true)->with('category')->get();

        return view('order', compact('categories', 'menus'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nomor_meja'        => 'required',
            'nama_pemesan'      => 'required',
            'metode_pembayaran' => 'required',
            'items'             => 'required|array',
        ]);

        DB::beginTransaction();

        try {
            $totalHarga = collect($request->items)->sum(function ($item) {
                return $item['price'] * $item['qty'];
            });

            $table = Table::where('number', $request->nomor_meja)
                        ->orWhere('name', $request->nomor_meja)
                        ->first();
            $tableId = $table ? $table->id : 1;

            $kodeTransaksi = 'TRX-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $orderDataCandidate = [
                'kode_transaksi'   => $kodeTransaksi,
                'transaction_code' => $kodeTransaksi,
                'nama_pemesan'     => $request->nama_pemesan,
                'customer_name'    => $request->nama_pemesan,
                'metode_pembayaran'=> $request->metode_pembayaran,
                'payment_method'   => $request->metode_pembayaran,
                'nomor_meja'       => $request->nomor_meja,
                'table_id'         => $tableId,
                'total_harga'      => $totalHarga,
                'total_amount'     => $totalHarga,
                'total'            => $totalHarga,
                'status'           => 'pending',
            ];

            $orderColumns = Schema::getColumnListing('orders');
            $filteredOrderData = [];
            foreach ($orderDataCandidate as $key => $value) {
                if (in_array($key, $orderColumns)) {
                    $filteredOrderData[$key] = $value;
                }
            }

            $order = Order::create($filteredOrderData);

            $detailColumns = Schema::getColumnListing('order_details');

            foreach ($request->items as $item) {
                $detailCandidate = [
                    'order_id'     => $order->id,
                    'menu_id'      => $item['id'],
                    'quantity'     => $item['qty'],
                    'qty'          => $item['qty'],
                    'jumlah'       => $item['qty'],
                    'harga_satuan' => $item['price'],
                    'harga'        => $item['price'],
                    'price'        => $item['price'],
                    'subtotal'     => $item['price'] * $item['qty'],
                ];

                $filteredDetailData = [];
                foreach ($detailCandidate as $k => $v) {
                    if (in_array($k, $detailColumns)) {
                        $filteredDetailData[$k] = $v;
                    }
                }

                OrderDetail::create($filteredDetailData);
            }

            DB::commit();

            $metode = strtolower($request->metode_pembayaran);
            $isQris = str_contains($metode, 'qris');

            return response()->json([
                'status'       => 'success',
                'is_qris'      => $isQris,
                'message'      => 'Pesanan berhasil dibuat',
                'redirect_url' => '/order/success/' . $order->id
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memproses pesanan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function success($id)
    {
        $order = Order::with('orderDetails.menu')->findOrFail($id);
        return view('order_success', compact('order'));
    }
}