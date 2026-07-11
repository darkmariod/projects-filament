{{-- PWA — Productos Paraíso (incluir en el <head>) --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#c1272d">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Paraíso Garantías">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function (e) {
                console.warn('SW no registrado:', e);
            });
        });
    }
</script>
