<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // "Estar autenticado" não é sinônimo de "ter permissão administrativa"
        // (Fase 4A, item 4): o painel checa este Gate, não só o guard "web".
        // Hoje só existe o papel "admin" (users.role), mas o seam já fica
        // pronto para outros perfis: basta ajustar esta condição.
        Gate::define('access-admin-panel', function (User $user): bool {
            return $user->role === 'admin';
        });
    }
}
