<?php

namespace App\Http\Controllers;

use App\Models\StockAlert;
use App\Services\StockAlertService;
use App\Support\PosTerminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Paneldəki bildiriş zəngi: açıq stok bildirişləri və "hamısı oxundu". */
class StockAlertController extends Controller
{
    public function index(Request $request, PosTerminal $terminal, StockAlertService $alerts): JsonResponse
    {
        $alerts->sync();
        // Keçid yalnız açıla bilən səhifəyə: kassir anbara daxil ola bilmir, terminal rejimində isə bu səhifələr bağlıdır
        $user = $terminal->active() ? null : $request->user();
        $canOpen = ['ready' => $user?->hasScope('KITCHEN') || $user?->hasScope('INVENTORY'), 'inventory' => (bool) $user?->hasScope('INVENTORY')];
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
