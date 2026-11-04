<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KasirController extends Controller
{
    public function dashboard(Request $request)
    {
        $request->validate(['tanggal' => 'nullable|date_format:Y-m-d']);
        $totalPesananHariIni = Order::whereDate('created_at', Carbon::today())->count();
        $belumVerifikasi = Order::where('status_verifikasi', 'pending')->count();
        $pesananSelesai = Order::where('status', 'selesai')->count();
        $penjualanHariIni = Order::where('status_verifikasi', 'verified')
            ->whereDate('created_at', Carbon::today())->sum('total_harga');
        $penjualanBulanan = Order::where('status_verifikasi', 'verified')
            ->whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)->sum('total_harga');
        $menuTerlaris = OrderDetail::query()
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('menus', 'menus.id', '=', 'order_details.menu_id')
            ->where('orders.status_verifikasi', 'verified')
            ->whereYear('orders.created_at', Carbon::now()->year)
            ->whereMonth('orders.created_at', Carbon::now()->month)
            ->select('menus.id', 'menus.nama_menu', DB::raw('SUM(order_details.quantity) as jumlah_terjual'), DB::raw('SUM(order_details.subtotal) as omzet'))
            ->groupBy('menus.id', 'menus.nama_menu')
            ->orderByDesc('jumlah_terjual')->limit(5)->get();

        $query = Order::with('orderDetails.menu')->latest();

        if ($request->filled('tanggal')) {
            $query->whereDate('created_at', $request->tanggal);
        }

        $orders = $query->get();

        return view('admin.dashboard', compact('orders', 'totalPesananHariIni', 'belumVerifikasi', 'pesananSelesai', 'penjualanHariIni', 'penjualanBulanan', 'menuTerlaris'));
    }

    public function pembayaran()
    {
        $pesananMenunggu = Order::with('orderDetails.menu')
            ->where('status_verifikasi', 'pending')->latest()->get();

        return view('admin.pembayaran', compact('pesananMenunggu'));
    }

    public function verifikasi(Request $request, $id)
    {
        $request->validate(['status_verifikasi' => 'required|in:verified,rejected']);
        $order = Order::findOrFail($id);
        $order->status_verifikasi = $request->status_verifikasi;
        if($request->status_verifikasi == 'verified') {
            $order->status = 'diproses';
        }
        $order->save();

        if ($request->status_verifikasi === 'verified') {
            return redirect()->route('admin.order.struk', $order->id)->with('success', 'Pembayaran terverifikasi. Struk siap dicetak.');
        }

        return back()->with('success', 'Status verifikasi berhasil diperbarui!');
    }

    public function bayarTunai(Request $request, $id)
    {
        $request->validate(['jumlah_bayar' => 'required|numeric|min:0']);
        
        $order = Order::findOrFail($id);
        $metode = strtolower($order->metode_pembayaran ?? $order->payment_method ?? '');
        if (!in_array($metode, ['tunai', 'cash'], true) || $order->status_verifikasi === 'verified') {
            return back()->withErrors(['jumlah_bayar' => 'Pesanan ini tidak dapat diproses sebagai pembayaran tunai.']);
        }
        $bayar = $request->jumlah_bayar;
        $total = $order->total_harga ?: ($order->total ?: $order->total_amount);

        if ($bayar < $total) {
            return back()->withErrors(['jumlah_bayar' => 'Jumlah uang tunai kurang dari total tagihan!']);
        }

        $order->jumlah_bayar = $bayar;
        $order->kembalian = $bayar - $total;
        $order->status_verifikasi = 'verified';
        $order->status = 'diproses';
        $order->save();

        return redirect()->route('admin.order.struk', $order->id)
            ->with('success', 'Pembayaran tunai berhasil dicatat. Kembalian: Rp ' . number_format($order->kembalian, 0, ',', '.') . '. Struk siap dicetak.');
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:pending,diproses,selesai']);
        $order = Order::findOrFail($id);
        $order->status = $request->status;
        $order->save();

        return back()->with('success', 'Status pesanan diperbarui menjadi ' . ucfirst($request->status));
    }

    public function rekap(Request $request)
    {
        $request->validate([
            'periode' => 'nullable|in:harian,bulanan',
            'tanggal' => 'nullable|date_format:Y-m-d',
            'bulan' => 'nullable|date_format:Y-m',
        ]);

        $jenis = $request->input('periode', 'bulanan');
        $filterTanggal = $request->input('tanggal', Carbon::today()->format('Y-m-d'));
        $filterBulan = $request->input('bulan', Carbon::now()->format('Y-m'));

        if ($jenis === 'harian') {
            $periode = Carbon::parse($filterTanggal);
            $judul = 'Rekap penjualan harian';
            $periodeLabel = $periode->translatedFormat('d F Y');
            $transaksi = Order::with('orderDetails.menu')->where('status_verifikasi', 'verified')
                ->whereDate('created_at', $filterTanggal)->latest()->get();
        } else {
            $periode = Carbon::createFromFormat('Y-m', $filterBulan);
            $judul = 'Rekap penjualan bulanan';
            $periodeLabel = $periode->translatedFormat('F Y');
            $transaksi = Order::with('orderDetails.menu')->where('status_verifikasi', 'verified')
                ->whereYear('created_at', $periode->year)->whereMonth('created_at', $periode->month)->latest()->get();
        }

        return view('admin.rekap', [
            'jenis' => $jenis,
            'judul' => $judul,
            'periodeLabel' => $periodeLabel,
            'pendapatan' => $transaksi->sum('total_harga'),
            'daftarTransaksi' => $transaksi,
            'filterTanggal' => $filterTanggal,
            'filterBulan' => $filterBulan,
        ]);
    }

    public function struk($id)
    {
        $order = Order::with('orderDetails.menu')->findOrFail($id);
        abort_unless($order->status_verifikasi === 'verified', 404);

        return view('admin.struk', compact('order'));
    }

    public function rekapHarian(Request $request)
    {
        return redirect()->route('admin.rekap', array_merge(['periode' => 'harian'], $request->only('tanggal')));
    }

    public function rekapBulanan(Request $request)
    {
        return redirect()->route('admin.rekap', array_merge(['periode' => 'bulanan'], $request->only('bulan')));
    }
}
