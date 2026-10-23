<?php

namespace App\Http\Controllers;

use App\Models\StockAlert;
use App\Services\StockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Paneldəki bildiriş zəngi: açıq stok bildirişləri və "hamısı oxundu". */
class StockAlertController extends Controller
{
    public function index(Request $request, StockAlertService $alerts): JsonResponse
    {
        $alerts->sync();
        // Kassir anbar / hazır yemək səhifələrinə daxil ola bilmir — keçid yalnız icazəsi olana verilir
        $user = $request->user();
        $canOpen = ['ready' => $user->hasScope('KITCHEN') || $user->hasScope('INVENTORY'), 'inventory' => $user->hasScope('INVENTORY')];
        $open = StockAlert::open()->orderByRaw("level = 'out' desc")->latest('id')->get();

        return response()->json([
            'unread' => $open->whereNull('read_at')->count(),
            'out' => $open->where('level', 'out')->count(),
            'items' => $open->take(30)->map(fn (StockAlert $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'level' => $a->level,
                'source' => StockAlert::SOURCES[$a->source],
                'quantity' => qty($a->quantity).' '.$a->unit,
                'url' => ($canOpen[$a->source] ?? false) ? $a->url() : null,
                'time' => $a->created_at->diffForHumans(),
                'unread' => $a->read_at === null,
            ])->values(),
        ]);
    }

    public function read(): JsonResponse
    {
        StockAlert::open()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
