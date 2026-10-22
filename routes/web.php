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
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\Settings;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

// Müştəriyə baxan ekran (girişsiz, gizli token ilə)
Route::get('/d/{token}', [CustomerDisplayController::class, 'show'])->name('display.show');
Route::get('/d/{token}/state', [CustomerDisplayController::class, 'state'])->middleware('throttle:120,1')->name('display.state');

// Public QR menyu (müştəri telefonu)
Route::get('/m/{code}', [QrMenuController::class, 'show'])->name('qr.show');
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
            Route::post('/orders/{order}/status', [OrderController::class, 'status'])->name('orders.status');
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
            Route::get('/orders/{order}/token', [OrderController::class, 'token'])->name('orders.token');
        });

        Route::middleware('scope:KITCHEN')->group(function () {
            Route::get('/kitchen', [KitchenController::class, 'index'])->name('kitchen');
            Route::get('/kitchen/feed', [KitchenController::class, 'feed'])->name('kitchen.feed');
            Route::post('/kitchen/items/{item}', [KitchenController::class, 'updateItem'])->name('kitchen.item');
            Route::post('/kitchen/orders/{order}/ready', [KitchenController::class, 'markAll'])->name('kitchen.ready');
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
            Route::get('/terminal', [Settings\TerminalController::class, 'edit'])->name('terminal');
            Route::put('/terminal', [Settings\TerminalController::class, 'update'])->name('terminal.update');
            Route::delete('/terminal/images/{index}', [Settings\TerminalController::class, 'destroyImage'])->whereNumber('index')->name('terminal.images.destroy');
            Route::resource('tables', Settings\TableController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('displays', Settings\DisplayController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::get('/tables/{table}/qr', [Settings\TableController::class, 'qr'])->name('tables.qr');
            Route::resource('categories', Settings\CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('taxes', Settings\TaxController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('payment-types', Settings\PaymentTypeController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('menu-items', Settings\MenuItemController::class)->except(['show']);
        });
    });
});
