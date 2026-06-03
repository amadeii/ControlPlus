<?php

namespace App\Providers;

use App\Models\MovimentacaoProduto;
use App\Observers\MovimentacaoProdutoObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
         Sanctum::ignoreMigrations();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        MovimentacaoProduto::observe(MovimentacaoProdutoObserver::class);

        Paginator::useBootstrap();
        View::composer(['layouts.menu.vertical', 'layouts.menu.horizontal'], function ($view) {
            $view->with('produtosAvaliacaoPendentes', 0);
        });
    }
}
