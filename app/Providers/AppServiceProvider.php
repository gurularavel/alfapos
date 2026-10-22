<?php

namespace App\Providers;

use App\Models\User;
use App\Support\PosTerminal;
use App\Support\Tenancy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Tenancy::class);
        $this->app->scoped(PosTerminal::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('superadmin', fn (User $user) => $user->isSuperAdmin());
        Gate::define('scope', fn (User $user, string $scope) => $user->hasScope($scope));

        Paginator::useTailwind();

        // Cloudflare → nginx → apache zənciri X-Forwarded-Proto-nu "http" edir; linklər https olsun
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // @money(12.5) → "12.50 ₼" (tenant valyutası ilə)
        Blade::directive('money', fn ($expr) => "<?php echo e(money($expr)); ?>");
    }
}
