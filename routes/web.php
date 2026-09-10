<?php

use Illuminate\Support\Facades\Route;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-qr', function () {
    $qr = QrCode::size(200)->generate('https://paraiso.com.ec/test');
    return response($qr)->header('Content-Type', 'image/svg+xml');
});

Route::get('/test-pdf', function () {
    $html = '<h1>Productos Paraiso</h1><p>PDF de prueba funcionando correctamente.</p>';
    $pdf  = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
    return $pdf->download('prueba.pdf');
});

use App\Http\Controllers\PublicController;

// Consultas por token. Llevan límite por IP para que nadie pueda barrer el
// sistema probando códigos a ciegas, aunque adivinar uno sea inviable.
Route::middleware('throttle:public-label')->group(function () {
    Route::get('/qr-img/{token}', [PublicController::class, 'qrImage'])->name('public.qr.image');
    Route::get('/p/{token}', [PublicController::class, 'product'])->name('public.product');
    Route::get('/garantia/{token}/registrar', [PublicController::class, 'warrantyForm'])->name('public.warranty.form');
    Route::get('/garantia/{token}/certificado', [PublicController::class, 'warrantyCertificate'])->name('public.warranty.certificate');
});

Route::post('/garantia/{token}/registrar', [PublicController::class, 'warrantyStore'])
    ->middleware('throttle:warranty-register')
    ->name('public.warranty.store');

Route::view('/terminos-condiciones', 'public.terms')->name('public.terms');
Route::view('/proteccion-datos', 'public.privacy')->name('public.privacy');
Route::view('/cookies', 'public.cookies')->name('public.cookies');

