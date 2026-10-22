# Demo menyu şəkilləri

Wikimedia Commons-dan götürülmüş, kommersiya istifadəsinə icazə verən lisenziyalı şəkillər
(CC0, Public domain, CC BY, CC BY-SA). 800×600, mərkəzdən 4:3 kəsilib.

Hər faylın müəllifi, lisenziyası və mənbə linki `credits.json`-dadır. Yükləyəndə bu məlumat
`menu_items.image_credit` sahəsinə yazılır və QR menyuda **"Şəkil müəllifləri"** səhifəsində
(`/m/{code}/credits`) göstərilir — CC BY / CC BY-SA bunu tələb edir, silməyin.

Yükləmək: `php artisan app:import-menu-images database/seeders/demo-images --tenant="Demo Restoran"`
(DemoSeeder bunu avtomatik edir).
