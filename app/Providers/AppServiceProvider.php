<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
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
        // Confia nos proxies configurados (Nginx/balanceador) para detectar HTTPS
        // e o IP real do cliente. Sem configuração, nenhum proxy é confiado.
        // Feito aqui (e não em bootstrap/app.php) para funcionar com config:cache.
        $proxies = config('protocolo.trusted_proxies');

        if (filled($proxies)) {
            TrustProxies::at($proxies);
        }
    }
}
