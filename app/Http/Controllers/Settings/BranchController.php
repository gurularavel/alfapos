<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Terminal;
use App\Models\User;
use App\Support\PosTerminal;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Filiallar və terminallar (kassa/POS cihazları), cihazın terminala bağlanması. */
class BranchController extends Controller
{
    public function __construct(protected Tenancy $tenancy, protected PosTerminal $terminal) {}

    public function index()
    {
        return view('settings.branches', [
            'branches' => Branch::with(['terminals.users:id,name'])->withCount('tables')->orderBy('id')->get(),
            'staff' => User::where('tenant_id', $this->tenancy->id())->whereNotIn('role', ['admin'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']),
            'device' => $this->terminal->device(),
        ]);
    }

    public function storeBranch(Request $request)
    {
        Branch::create($this->branchData($request));

        return back()->with('success', 'Filial əlavə edildi.');
    }

    public function updateBranch(Request $request, Branch $branch)
    {
        $branch->update($this->branchData($request));

        return back()->with('success', 'Filial yeniləndi.');
    }

    public function destroyBranch(Branch $branch)
    {
        abort_if(Branch::count() <= 1, 422, 'Ən azı bir filial olmalıdır.');
        abort_if($branch->tables()->exists(), 422, 'Bu filiala bağlı masalar var — əvvəlcə masaları başqa filiala keçirin.');
        $branch->delete();

        return back()->with('success', 'Filial silindi.');
    }

    public function storeTerminal(Request $request)
    {
        $terminal = Terminal::create($this->terminalData($request));
        $terminal->users()->sync($this->staffIds($request));

        return back()->with('success', 'Terminal əlavə edildi.');
    }

    public function updateTerminal(Request $request, Terminal $terminal)
    {
        $terminal->update($this->terminalData($request));
        $terminal->users()->sync($this->staffIds($request));

        return back()->with('success', 'Terminal yeniləndi.');
    }

    public function destroyTerminal(Terminal $terminal)
    {
        // Köhnə sifariş/çeklər qalır (terminal_id null olur), hesabatda "Terminalsız" kimi görünür
        $terminal->delete();

        return back()->with('success', 'Terminal silindi.');
    }

    /** Bu cihazı (brauzeri) terminala bağlayır və ya ayırır. */
    public function bindDevice(Request $request)
    {
        $data = $request->validate(['terminal_id' => ['nullable', Rule::exists('terminals', 'id')->where('tenant_id', $this->tenancy->id())->where('is_active', true)]]);
        $terminal = $data['terminal_id'] ? Terminal::with('branch')->find($data['terminal_id']) : null;
        $this->terminal->bindDevice($terminal);
        $message = $terminal ? 'Bu cihaz artıq "'.$terminal->label().'" terminalıdır.' : 'Cihaz terminaldan ayrıldı.';

        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('success', $message);
    }

    protected function branchData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    protected function terminalData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('tenant_id', $this->tenancy->id())],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    protected function staffIds(Request $request): array
    {
        return User::where('tenant_id', $this->tenancy->id())->whereIn('id', (array) $request->input('user_ids', []))->pluck('id')->all();
    }
}
