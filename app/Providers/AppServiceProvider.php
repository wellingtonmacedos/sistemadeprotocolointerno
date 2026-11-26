<?php

namespace App\Providers;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

        // O endpoint /up passa a validar a conexão com o banco de dados,
        // retornando 500 quando o banco estiver indisponível (uso em monitoramento).
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::connection()->getPdo();
        });
    }
}
