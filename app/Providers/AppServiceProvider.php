<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Services\LabelPdfService;
use App\Services\SerialGeneratorService;
use App\Services\ZebraZplService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SerialGeneratorService::class, function ($app) {
            return new SerialGeneratorService();
        });

        $this->app->singleton(ZebraZplService::class, function ($app) {
            return new ZebraZplService();
        });

        $this->app->singleton(LabelPdfService::class, function ($app) {
            return new LabelPdfService();
        });
    }

    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Force APP_URL as base for URL generation (fixes port issues behind Nginx)
        URL::forceRootUrl(config('app.url'));

        // Límite de 3 registros de garantía por IP por minuto
        RateLimiter::for('warranty-register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Consultas públicas de etiqueta. El token es de 20 caracteres sobre un
        // alfabeto de 32, así que adivinarlo es inviable; este límite existe
        // para que nadie pueda barrer el sistema a ciegas ni saturarlo con
        // millones de intentos. Un cliente que escanea el QR de su colchón hace
        // una consulta, no sesenta.
        RateLimiter::for('public-label', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}