<?php

namespace App\Http\Controllers;

use App\Support\Realtime;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\ApprovalService;
use App\Services\OrderService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with(['customer:id,name,phone', 'paymentType:id,title', 'creator:id,name', 'orders:id,invoice_id,token_no,delivery_type', 'orders.items:id,order_id,title,variant_title,quantity,price,status', 'terminal:id,name'])
            ->when($request->integer('terminal_id'), fn ($q, $t) => $q->where('terminal_id', $t))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(25)->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'terminals' => \App\Models\Terminal::orderBy('name')->get(['id', 'name']),
            'canRefund' => $request->user()->hasScope('PAYMENTS'), // pulu kassa qaytarır
        ]);
    }

    public function receipt(Request $request, Invoice $invoice, Tenancy $tenancy)
    {
        // Hər çap sayılır: ikinci və sonrakı çaplar "NÜSXƏ" kimi çıxır (eyni qəbzlə ikinci dəfə pul yığılmasın)
        if (! $request->boolean('noprint')) {
            $invoice->increment('print_count');
        }

        return view('print.receipt', [
            'invoice' => $invoice->load('orders.items', 'orders.table', 'orders.creator', 'customer', 'paymentType', 'creator'),
            'tenant' => $tenancy->tenant(),
        ]);
    }

    /** Ödənilmiş çekin tam ləğvi: səbəb və menecer təsdiqi; qismən qaytarılmış çek ləğv edilmir (ikiqat qaytarma olmasın). */
    public function void(Request $request, Invoice $invoice, OrderService $service, ApprovalService $approval)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'approver_pin' => ['nullable', 'string']]);
        abort_if($invoice->status === 'void', 422, 'Faktura artıq ləğv edilib.');
        if ($invoice->refunded_total > 0) {
            throw ValidationException::withMessages(['reason' => 'Bu çekdə qaytarma var — qalan məhsulları ayrıca qaytarın.']);
        }
        $approver = $approval->approve($data['approver_pin'] ?? null);
        unset($data['approver_pin']);

        DB::transaction(function () use ($invoice, $data, $service, $approver) {
            $invoice->update(['status' => 'void', 'void_reason' => $data['reason'], 'void_approved_by' => $approver->id]);
            foreach ($invoice->orders as $order) {
                $order->update(['status' => 'cancelled', 'payment_status' => 'pending']);
                $order->items()->update(['status' => 'cancelled']);
                $service->restoreStock($order);
                Realtime::order($order, 'cancelled');
            }
            AuditLog::record('invoice.void', $invoice, $data + ['approved_by' => $approver->name]);
        });

        return back()->with('success', 'Faktura ləğv edildi.');
    }
}
