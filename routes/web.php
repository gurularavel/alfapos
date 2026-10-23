<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerDisplayController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrMenuController;
use App\Http\Controllers\ReadyStockController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\Settings;
use App\Http\Controllers\StaffMealController;
use App\Http\Controllers\StockAlertController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\UserController;
use App\Models\SitePage;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

// İctimai səhifələr (footer)
Route::view('/haqqimizda', 'pages.about')->name('about');
Route::view('/elaqe', 'pages.contact')->name('contact');
Route::view('/sual-cavab', 'pages.faq')->name('faq');
Route::view('/yenilikler', 'pages.changelog')->name('changelog');
Route::view('/mexfilik-siyaseti', 'pages.privacy')->name('privacy');
Route::view('/istifade-sertleri', 'pages.terms')->name('terms');

// Müştəriyə baxan ekran (girişsiz, gizli token ilə)
Route::get('/d/{token}', [CustomerDisplayController::class, 'show'])->name('display.show');
Route::get('/d/{token}/state', [CustomerDisplayController::class, 'state'])->middleware('throttle:120,1')->name('display.state');

// Public QR menyu (müştəri telefonu)
Route::get('/m/{code}', [QrMenuController::class, 'show'])->name('qr.show');
Route::get('/m/{code}/credits', [QrMenuController::class, 'credits'])->name('qr.credits');
Route::post('/m/{code}/order', [QrMenuController::class, 'order'])->middleware('throttle:10,1')->name('qr.order');
Route::post('/m/{code}/feedback', [QrMenuController::class, 'feedback'])->middleware('throttle:5,1')->name('qr.feedback');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Superadmin
    Route::prefix('admin')->name('admin.')->middleware('can:superadmin')->group(function () {
        Route::resource('tenants', Admin\TenantController::class)->except(['show', 'destroy']);
        Route::post('tenants/{tenant}/extend', [Admin\TenantController::class, 'extend'])->name('tenants.extend');
        Route::resource('plans', Admin\PlanController::class)->except(['show']);

        // Sayt: əlaqə/rekvizitlər, səhifələr (Markdown), sual-cavab
        Route::get('site', [Admin\SiteController::class, 'index'])->name('site.index');
        Route::put('site/settings', [Admin\SiteController::class, 'updateSettings'])->name('site.settings');
        Route::post('site/preview', [Admin\SiteController::class, 'preview'])->name('site.preview');
        Route::get('site/pages/{slug}', [Admin\SiteController::class, 'editPage'])->whereIn('slug', array_keys(SitePage::SLUGS))->name('site.pages.edit');
        Route::put('site/pages/{slug}', [Admin\SiteController::class, 'updatePage'])->whereIn('slug', array_keys(SitePage::SLUGS))->name('site.pages.update');
        Route::post('site/faqs', [Admin\SiteController::class, 'storeFaq'])->name('site.faqs.store');
        Route::put('site/faqs/{faq}', [Admin\SiteController::class, 'updateFaq'])->name('site.faqs.update');
        Route::delete('site/faqs/{faq}', [Admin\SiteController::class, 'destroyFaq'])->name('site.faqs.destroy');
        Route::post('site/faqs/{faq}/move', [Admin\SiteController::class, 'moveFaq'])->name('site.faqs.move');
        Route::post('site/changelog', [Admin\SiteController::class, 'storeRelease'])->name('site.changelog.store');
        Route::put('site/changelog/{release}', [Admin\SiteController::class, 'updateRelease'])->name('site.changelog.update');
        Route::delete('site/changelog/{release}', [Admin\SiteController::class, 'destroyRelease'])->name('site.changelog.destroy');
    });

    Route::middleware('subscribed')->prefix('dashboard')->group(function () {
        Route::get('/', DashboardController::class)->middleware('scope:DASHBOARD')->name('dashboard');

        Route::middleware('scope:POS')->group(function () {
            Route::get('/pos', [PosController::class, 'index'])->name('pos');
            Route::post('/pos/orders', [PosController::class, 'store'])->name('pos.store');
            Route::get('/pos/drafts', [PosController::class, 'drafts'])->name('pos.drafts');
            Route::delete('/pos/drafts/{order}', [PosController::class, 'destroyDraft'])->name('pos.drafts.destroy');
            Route::get('/pos/qr-orders', [PosController::class, 'qrOrders'])->name('pos.qr');
            Route::get('/pos/customers', [PosController::class, 'searchCustomers'])->name('pos.customers');
            Route::post('/pos/customers', [PosController::class, 'storeCustomer'])->name('pos.customers.store');
            Route::post('/pos/display/{display}/push', [CustomerDisplayController::class, 'push'])->middleware('throttle:240,1')->name('pos.display.push');
        });

        Route::prefix('pos/terminal')->name('pos.terminal.')->middleware('scope:POS,ORDERS')->group(function () {
            Route::post('/enter', [TerminalController::class, 'enter'])->name('enter');
            Route::post('/exit', [TerminalController::class, 'exit'])->middleware('throttle:10,1')->name('exit');
            Route::post('/unlock', [TerminalController::class, 'unlock'])->middleware('throttle:15,1')->name('unlock');
            Route::post('/lock', [TerminalController::class, 'lock'])->name('lock');
            Route::get('/ping', [TerminalController::class, 'ping'])->name('ping');
        });

        Route::middleware('scope:ORDERS,POS')->group(function () {
            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/board', [OrderController::class, 'board'])->name('orders.board');
            Route::post('/orders/pay', [OrderController::class, 'pay'])->name('orders.pay');
            Route::get('/orders/bill', [OrderController::class, 'bill'])->name('orders.bill');
            Route::post('/orders/transfer', [OrderController::class, 'transfer'])->name('orders.transfer');
            Route::get('/orders/transfer-slip', [OrderController::class, 'transferSlip'])->name('orders.transfer-slip');
            Route::post('/orders/{order}/status', [OrderController::class, 'status'])->name('orders.status');
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
            Route::post('/orders/items/{orderItem}/return', [ReturnController::class, 'store'])->name('orders.items.return');
            Route::get('/orders/returns/{itemReturn}/slip', [ReturnController::class, 'slip'])->name('orders.returns.slip');
            Route::get('/orders/returns/{itemReturn}/receipt', [ReturnController::class, 'receipt'])->name('orders.returns.receipt');
            Route::get('/orders/{order}/token', [OrderController::class, 'token'])->name('orders.token');
        });

        Route::middleware('scope:KITCHEN')->group(function () {
            Route::get('/kitchen', [KitchenController::class, 'index'])->name('kitchen');
            Route::get('/kitchen/feed', [KitchenController::class, 'feed'])->name('kitchen.feed');
            Route::post('/kitchen/items/{item}', [KitchenController::class, 'updateItem'])->name('kitchen.item');
            Route::post('/kitchen/orders/{order}/ready', [KitchenController::class, 'markAll'])->name('kitchen.ready');
        });

        Route::prefix('ready-stock')->name('ready-stock.')->middleware('scope:KITCHEN,INVENTORY')->controller(ReadyStockController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'track')->name('track');
            Route::put('/{menuItem}', 'update')->name('update');
            Route::delete('/{menuItem}', 'untrack')->name('untrack');
            Route::post('/{menuItem}/produce', 'produce')->name('produce');
            Route::post('/{menuItem}/waste', 'waste')->name('waste');
            Route::post('/{menuItem}/count', 'count')->name('count');
        });

        Route::prefix('staff-meals')->name('staff-meals.')->middleware('scope:STAFF_MEALS,KITCHEN')->controller(StaffMealController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('/{staffMeal}/cancel', 'cancel')->name('cancel');
        });

        Route::middleware('scope:INVOICES')->group(function () {
            Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        });
        Route::get('/invoices/{invoice}/receipt', [InvoiceController::class, 'receipt'])->middleware('scope:INVOICES,POS,ORDERS')->name('invoices.receipt');

        Route::resource('customers', CustomerController::class)->except(['create', 'edit'])->middleware('scope:CUSTOMERS');
        Route::get('customers-export', [CustomerController::class, 'export'])->middleware('scope:CUSTOMERS')->name('customers.export');
        Route::resource('reservations', ReservationController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('scope:RESERVATIONS');

        Route::middleware('scope:INVENTORY')->group(function () {
            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
            Route::put('/inventory/{inventoryItem}', [InventoryController::class, 'update'])->name('inventory.update');
            Route::delete('/inventory/{inventoryItem}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
            Route::post('/inventory/{inventoryItem}/movement', [InventoryController::class, 'movement'])->name('inventory.movement');
            Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
        });

        Route::get('/feedbacks', [FeedbackController::class, 'index'])->middleware('scope:FEEDBACK')->name('feedbacks.index');
        Route::get('/notifications', [StockAlertController::class, 'index'])->middleware('can:stock-alerts')->name('notifications.index');
        Route::post('/notifications/read', [StockAlertController::class, 'read'])->middleware('can:stock-alerts')->name('notifications.read');
        Route::get('/returns', [ReturnController::class, 'index'])->middleware('scope:APPROVE,REPORTS')->name('returns.index');
        Route::get('/reports', [ReportController::class, 'index'])->middleware('scope:REPORTS')->name('reports.index');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->middleware('scope:REPORTS')->name('reports.show');

        Route::middleware('scope:ADMIN')->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'create', 'edit']);
            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        });

        Route::prefix('settings')->name('settings.')->middleware('scope:SETTINGS')->group(function () {
            Route::view('/', 'settings.index')->name('index');
            Route::get('/store', [Settings\StoreController::class, 'edit'])->name('store');
            Route::put('/store', [Settings\StoreController::class, 'update'])->name('store.update');
            Route::get('/print', [Settings\StoreController::class, 'editPrint'])->name('print');
            Route::put('/print', [Settings\StoreController::class, 'updatePrint'])->name('print.update');
            Route::get('/print/preview', [Settings\StoreController::class, 'printPreview'])->name('print.preview');
            Route::get('/print/launcher', [Settings\StoreController::class, 'printLauncher'])->name('print.launcher');
            Route::get('/terminal', [Settings\TerminalController::class, 'edit'])->name('terminal');
            Route::put('/terminal', [Settings\TerminalController::class, 'update'])->name('terminal.update');
            Route::delete('/terminal/images/{index}', [Settings\TerminalController::class, 'destroyImage'])->whereNumber('index')->name('terminal.images.destroy');
            Route::get('/branches', [Settings\BranchController::class, 'index'])->name('branches.index');
            Route::post('/branches', [Settings\BranchController::class, 'storeBranch'])->name('branches.store');
            Route::put('/branches/{branch}', [Settings\BranchController::class, 'updateBranch'])->name('branches.update');
            Route::delete('/branches/{branch}', [Settings\BranchController::class, 'destroyBranch'])->name('branches.destroy');
            Route::post('/terminals', [Settings\BranchController::class, 'storeTerminal'])->name('terminals.store');
            Route::put('/terminals/{terminal}', [Settings\BranchController::class, 'updateTerminal'])->name('terminals.update');
            Route::delete('/terminals/{terminal}', [Settings\BranchController::class, 'destroyTerminal'])->name('terminals.destroy');
            Route::post('/device', [Settings\BranchController::class, 'bindDevice'])->name('device.bind');
            Route::get('/tables/plan', [Settings\TableController::class, 'plan'])->name('tables.plan');
            Route::put('/tables/plan', [Settings\TableController::class, 'savePlan'])->name('tables.plan.save');
            Route::resource('tables', Settings\TableController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('displays', Settings\DisplayController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::get('/tables/{table}/qr', [Settings\TableController::class, 'qr'])->name('tables.qr');
            Route::resource('categories', Settings\CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('taxes', Settings\TaxController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::put('/charges', [Settings\TaxController::class, 'updateCharges'])->name('charges.update');
            Route::resource('payment-types', Settings\PaymentTypeController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('menu-items', Settings\MenuItemController::class)->except(['show']);
        });
    });
});
