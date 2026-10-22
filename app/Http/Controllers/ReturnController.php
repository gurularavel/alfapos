<?php

namespace App\Http\Controllers;

use App\Models\ItemReturn;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ApprovalService;
use App\Services\ReturnService;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Ləğv və qaytarmalar: menecer təsdiqi ilə sətir ləğvi / pul qaytarma, mətbəx ləğv qəbzi, qaytarma qəbzi, jurnal. */
class ReturnController extends Controller
{
    public function store(Request $request, OrderItem $orderItem, ApprovalService $approval, ReturnService $returns, Tenancy $tenancy): JsonResponse
    {
        // OrderItem tenant scope-suz modeldir — sifariş vasitəsilə yoxlayırıq (başqa biznesin sətiri 404)
        $order = Order::findOrFail($orderItem->order_id);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(array_keys(ItemReturn::REASONS))],
            'note' => ['nullable', 'required_if:reason,other', 'string', 'max:255'],
            'stock_action' => ['required', Rule::in(array_keys(ItemReturn::STOCK_ACTIONS))],
            'refund_amount' => ['nullable', 'numeric', 'gt:0'],
            'payment_type_id' => ['nullable', Rule::exists('payment_types', 'id')->where('tenant_id', $tenancy->id())],
            'approver_pin' => ['nullable', 'string'],
        ], ['note.required_if' => 'Səbəbi qısa yazın.']);

        // Pulu geri vermək kassanın işidir — ofisiant ödənilmiş məhsulu qaytara bilməz
        abort_if($order->payment_status === 'paid' && ! $approval->operator()?->hasScope('PAYMENTS'), 403, 'Pul qaytarmaq üçün ödəniş icazəsi lazımdır.');
        $approver = $approval->approve($data['approver_pin'] ?? null);

        $return = $returns->returnItem($orderItem, $data, $approver);

        return response()->json([
            'message' => $return->kind === 'refund'
                ? "Qaytarıldı: {$return->title} × {$return->quantity} · ".money($return->amount).' müştəriyə qaytarın.'
                : "Ləğv edildi: {$return->title} × {$return->quantity}.",
            'id' => $return->id,
            'kind' => $return->kind,
            'slip_url' => route('orders.returns.slip', $return),
            'receipt_url' => $return->kind === 'refund' ? route('orders.returns.receipt', $return) : null,
        ]);
    }

    /** Mətbəx üçün ləğv qəbzi — hazırlanan yemək dayandırılsın. */
    public function slip(ItemReturn $itemReturn, Tenancy $tenancy): View
    {
        return view('print.return-slip', ['return' => $itemReturn->load('order.table', 'requester', 'approver'), 'tenant' => $tenancy->tenant()]);
    }

    /** Müştəriyə qaytarma qəbzi (imza ilə). */
    public function receipt(ItemReturn $itemReturn, Tenancy $tenancy): View
    {
        abort_unless($itemReturn->kind === 'refund', 404);

        return view('print.refund-receipt', ['return' => $itemReturn->load('invoice', 'order.table', 'paymentType', 'requester', 'approver'), 'tenant' => $tenancy->tenant()]);
    }

    public function index(Request $request): View
    {
        $from = Carbon::parse($request->query('from', today()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->endOfDay();

        $returns = ItemReturn::with(['order:id,token_no,store_table_id', 'order.table:id,title', 'orderCreator:id,name', 'requester:id,name', 'approver:id,name', 'paymentType:id,title'])
            ->whereBetween('created_at', [$from, $to])
            ->when($request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->when($request->integer('staff'), fn ($q, $id) => $q->where('order_creator_id', $id))
            ->latest('id')->get();

        return view('returns.index', [
            'returns' => $returns,
            'from' => $from,
            'to' => $to,
            'stats' => (object) [
                'voids' => $returns->where('kind', 'void')->count(),
                'void_amount' => (float) $returns->where('kind', 'void')->sum('amount'),
                'refunds' => $returns->where('kind', 'refund')->count(),
                'refund_amount' => (float) $returns->where('kind', 'refund')->sum('amount'),
                'waste_cost' => (float) $returns->sum('cost'),
                'after_bill' => $returns->where('after_bill', true)->count(),
            ],
            'staff' => \App\Models\User::whereIn('id', ItemReturn::whereBetween('created_at', [$from, $to])->whereNotNull('order_creator_id')->distinct()->pluck('order_creator_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
