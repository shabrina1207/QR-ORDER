<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\OrderDetail;
use Illuminate\Http\Request;

class AdminMenuController extends Controller
{
    public function index()
    {
        $menus = Menu::with('category')->orderBy('nama_menu')->get();

        return view('admin.menus.index', compact('menus'));
    }

    public function create()
    {
        return view('admin.menus.form', [
            'menu' => new Menu(['is_available' => true]),
            'categories' => Category::orderBy('nama_kategori')->get(),
            'pageTitle' => 'Tambah Menu',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedMenu($request);
        $data['is_available'] = $request->boolean('is_available', true);

        Menu::create($data);

        return redirect()->route('admin.menus.index')->with('success', 'Menu berhasil ditambahkan.');
    }

    public function edit(Menu $menu)
    {
        return view('admin.menus.form', [
            'menu' => $menu,
            'categories' => Category::orderBy('nama_kategori')->get(),
            'pageTitle' => 'Ubah Menu',
        ]);
    }

    public function update(Request $request, Menu $menu)
    {
        $data = $this->validatedMenu($request);
        $data['is_available'] = $request->boolean('is_available');
        $menu->update($data);

        return redirect()->route('admin.menus.index')->with('success', 'Menu berhasil diperbarui.');
    }

    public function destroy(Menu $menu)
    {
        if (OrderDetail::where('menu_id', $menu->id)->exists()) {
            return back()->withErrors([
                'menu' => 'Menu sudah tercatat pada transaksi. Ubah menjadi tidak tersedia agar riwayat penjualan tetap utuh.',
            ]);
        }

        $menu->delete();

        return redirect()->route('admin.menus.index')->with('success', 'Menu berhasil dihapus.');
    }

    private function validatedMenu(Request $request): array
    {
        return $request->validate([
            'category_id' => 'required|exists:categories,id',
            'nama_menu' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'harga' => 'required|integer|min:0',
            'is_available' => 'nullable|boolean',
        ]);
    }
}
