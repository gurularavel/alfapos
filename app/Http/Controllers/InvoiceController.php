<?php

namespace App\Http\Controllers;

use App\Support\Realtime;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\OrderService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with(['customer:id,name,phone', 'paymentType:id,title', 'creator:id,name', 'orders:id,invoice_id,token_no,delivery_type', 'terminal:id,name'])
            ->when($request->integer('terminal_id'), fn ($q, $t) => $q->where('terminal_id', $t))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(25)->withQueryString();

        return view('invoices.index', ['invoices' => $invoices, 'terminals' => \App\Models\Terminal::orderBy('name')->get(['id', 'name'])]);
    }

    public function receipt(Invoice $invoice, Tenancy $tenancy)
    {
        return view('print.receipt', [
            'invoice' => $invoice->load('orders.items', 'orders.table', 'orders.creator', 'customer', 'paymentType', 'creator'),
            'tenant' => $tenancy->tenant(),
        ]);
    }

    public function void(Request $request, Invoice $invoice, OrderService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        abort_if($invoice->status === 'void', 422, 'Faktura artıq ləğv edilib.');

        DB::transaction(function () use ($invoice, $data, $service) {
            $invoice->update(['status' => 'void', 'void_reason' => $data['reason']]);
            foreach ($invoice->orders as $order) {
                $order->update(['status' => 'cancelled', 'payment_status' => 'pending']);
                $order->items()->update(['status' => 'cancelled']);
                $service->restoreStock($order);
                Realtime::order($order, 'cancelled');
            }
            AuditLog::record('invoice.void', $invoice, $data);
        });

        return back()->with('success', 'Faktura ləğv edildi.');
    }
}
