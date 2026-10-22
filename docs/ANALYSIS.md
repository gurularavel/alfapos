# AlfaPOS — Restro PRO SaaS analizi və yol xəritəsi

Mənbə: https://restroprosaas.uiflow.in (demo hesab), 2026-10-22 analiz edilib.
Orijinal stack: React SPA (Vite, PWA) + Node API `/api/v1` + socket.io (real-time), MySQL.
Bizim stack: Laravel 13 + Blade + Alpine.js + Tailwind v4, MySQL; real-time üçün sonra Laravel Reverb.

## Modullar (demo menyusu)

| Bölmə | Nə edir | Əsas API (orijinal) |
|---|---|---|
| Dashboard | Günlük satış hədəfi, bugünkü gəlir/sifariş (dünənlə müqayisə), 7 günlük trend, top məhsullar, sifariş tipi və ödəniş qarışığı, az qalan stok, son rəylər, bugünkü rezervlər, vergi/servis haqqı. Widget-lər sürüklənir, rol şablonları (Owner, Cashier, Kitchen Head, Inventory, HQ) | `GET /dashboard`, `/dashboard/layout` |
| POS | Kateqoriya çipləri + axtarış, məhsul kartları (variant/addon sayı), səbət, müştəri (WALKIN və ya axtarış/yeni), xidmət növü (Dine-In / Delivery / Take Away), masa seçimi, qeyd, endirim (sabit/faiz), Draft, Send to Kitchen, Create Receipt & Pay (ödəniş növü seçimi), token çapı. QR sifarişləri, Drafts, Table Orders, Dual Screen (müştəri ekranı) | `GET /pos/init`, `POST /pos/create-order`, `/pos/create-order-and-invoice`, `/pos/qrorders*` |
| Orders | Aktiv sifarişlər masa üzrə qruplanmış kartlar: token, vaxt, status, məhsul siyahısı; tamamla, ödə, ləğv et, statusu dəyiş | `GET /orders`, `POST /orders/complete-and-pay-order`, `/orders/cancel`, `/orders/update-status/:id` |
| Kitchen (KDS) | Mətbəx ekranı: sifariş kartı, məhsul üzrə "Start Making" → "Ready", hamısını işarələ | `GET /kitchen`, `POST /kitchen/:id`, `/kitchen/order/:id/mark-all` |
| Reservation | Rezerv CRUD, axtarış | `/reservations/*` |
| Customers | Müştəri CRUD (telefon, ad, email, doğum tarixi, cins), Excel import/export | `/customers/*` |
| Invoices | Faktura siyahısı, axtarış, ləğv (void), audit log | `/invoices/*`, `/invoice-audit-logs/*` |
| Inventory | Stok məhsulları (vahid, cari və minimum miqdar), stok hərəkətləri (in/out/wastage), resept (menyu məhsulu/variant/addon → inqrediyent miqdarı), satışda avtomatik silinmə | `/inventory/*` |
| Feedbacks | QR menyudan gələn rəylər (reytinq, şərh) | `/feedback/*` |
| Users | İşçilər: ad, login, parol, rol (admin/user) + scope-lar | `/users/*` |
| Reports | 53 hesabat, 9 kateqoriya: Sales(11), Payments(5), Menu(7), Customers(6), Operations(6), Inventory(6), Accounting(6), Reservations(3), Feedback(3) | — |
| Audit Logs | Faktura/əməliyyat tarixçəsi | — |
| Settings | Store Details (ad, ünvan, telefon, email, valyuta, logo, QR menu/QR order/feedback açarları), Print Settings (format, header/footer, KOT, kağız 58/80mm), Tables (ad, mərtəbə, tutum), Table Assignments (ofisiant→masa), Menu Items + Categories (variant, addon, şəkil, import/export), Tax Setup (faiz, inclusive/exclusive), Payment Types, servis haqqı | `/settings/*` |
| Public | QR menyu `/:qrcode`, QR-dan sifariş, rəy, sifariş statusu ekranı, müştəri ekranı | `/qrmenu/:id/*` |
| SaaS | Qeydiyyat (biznes yaradır), planlar (Stripe/Paystack), abunəlik bitəndə `inactive-subscription`, superadmin: tenant-lar, planlar, ödəniş gateway, biznes qrupları (bir sahibin bir neçə filialı, `switch-business`) | `/auth/*`, `/plans/*`, `/superadmin/*` |

## İcazələr (scopes)
DASHBOARD, POS, ORDERS, ORDER_STATUS, KITCHEN, KITCHEN_DISPLAY, RESERVATIONS (VIEW/MANAGE), CUSTOMERS (VIEW/MANAGE), INVOICES, INVENTORY (VIEW/MANAGE), FEEDBACK, REPORTS, SETTINGS. Rollar: superadmin, admin (hər şey), user (scope-lara görə).

## Status axınları
- Order: `created` → `preparing` → `ready` → `completed` / `cancelled`; payment_status: `pending` / `paid`.
- Order item: `created` → `preparing` → `ready` → `completed` / `cancelled`.
- delivery_type: `dinein`, `takeaway`, `delivery`. customer_type: `WALKIN`, `CUSTOMER`.
- Hər gün token nömrəsi 1-dən başlayır (tenant üzrə).

## Mərhələlər
1. **Əsas (hazır)**: multi-tenant baza, qeydiyyat/giriş, rollar+scope-lar, plan/trial, Settings (mağaza, masalar, kateqoriya, menyu + variant/addon, vergi, ödəniş növləri), POS, Orders, Kitchen, Invoices, Customers, Reservations, Inventory + resept, Users, Dashboard, əsas hesabatlar, QR menyu + QR sifariş + rəy, superadmin (tenant və planlar).
2. Real-time (Reverb): POS/Kitchen/Orders canlı yenilənmə, müştəri ekranı (dual screen), sifariş statusu ekranı.
3. Çap: qəbz/KOT şablonları, 58/80mm, ESC/POS.
4. Ödəniş gateway (Stripe / yerli bank), avtomatik abunə yenilənməsi.
5. Hesabatların tam siyahısı (53), Excel/PDF export, audit log, dashboard widget-lərinin fərdiləşdirilməsi.
6. Biznes qrupları (çox filial), mobil sahib tətbiqi üçün API (Sanctum).
