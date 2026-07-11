<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paraíso — Sistema de Garantías</title>
    <meta name="description" content="Registra la garantía de tu colchón Paraíso.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif;
            background: #fff;
            color: #111;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        .w { max-width: 1040px; margin: 0 auto; padding: 0 24px; }

        /* nav */
        .nv { display: flex; align-items: center; justify-content: space-between; height: 56px; border-bottom: 1px solid #e5e7eb; }
        .nv-l { font-size: 1rem; font-weight: 700; letter-spacing: .12em; color: #C73A3A; }
        .nv-r { display: flex; gap: 20px; }
        .nv-r a { font-size: .8rem; color: #6b7280; font-weight: 500; }
        .nv-r a:hover { color: #111; }

        /* hero */
        .h { padding: 80px 0 56px; }
        .h h1 { font-size: 2.5rem; font-weight: 700; line-height: 1.1; letter-spacing: -.02em; max-width: 680px; margin-bottom: 16px; }
        @media (min-width: 600px) { .h h1 { font-size: 3rem; } }
        .h p { font-size: 1rem; color: #6b7280; max-width: 500px; line-height: 1.6; margin-bottom: 28px; }
        .ha { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .b { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; font-size: .85rem; font-weight: 600; border: none; cursor: pointer; }
        .b1 { background: #111; color: #fff; }
        .b1:hover { background: #000; }
        .b2 { background: transparent; color: #111; border: 1px solid #d1d5db; }
        .b2:hover { border-color: #111; }

        /* code block */
        .cb {
            background: #f9fafb; border: 1px solid #e5e7eb;
            padding: 10px 14px; margin-top: 28px;
            font-family: 'SF Mono', 'Fira Code', 'Courier New', monospace;
            font-size: .8rem;
            display: inline-flex; align-items: center; gap: 10px;
        }
        .cb .p { color: #9ca3af; user-select: none; }
        .cb .c { color: #111; font-weight: 600; }

        /* sections */
        .s { padding: 64px 0; border-bottom: 1px solid #e5e7eb; }
        .sl {
            font-size: .65rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: .08em; color: #C73A3A; margin-bottom: 8px;
        }
        .st { font-size: 1.35rem; font-weight: 700; margin-bottom: 8px; letter-spacing: -.01em; }
        @media (min-width: 600px) { .st { font-size: 1.5rem; } }
        .ss { font-size: .9rem; color: #6b7280; margin-bottom: 36px; max-width: 500px; line-height: 1.5; }

        /* info grid */
        .ig { display: grid; grid-template-columns: 1fr; gap: 24px; }
        @media (min-width: 600px) { .ig { grid-template-columns: repeat(3, 1fr); } }
        .ig h3 { font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #111; margin-bottom: 6px; }
        .ig p { font-size: .85rem; color: #6b7280; line-height: 1.55; }

        /* process grid */
        .pg { display: grid; grid-template-columns: 1fr; gap: 32px; margin-top: 36px; }
        @media (min-width: 700px) { .pg { grid-template-columns: repeat(3, 1fr); gap: 48px; } }
        .pg-num { font-size: 2rem; font-weight: 700; color: #C73A3A; line-height: 1; margin-bottom: 6px; }
        .pg-ln { display: block; width: 24px; height: 2px; background: #e5e7eb; margin-bottom: 14px; }
        .pg-t { font-size: .95rem; font-weight: 700; color: #111; margin-bottom: 6px; }
        .pg-d { font-size: .85rem; color: #6b7280; line-height: 1.55; }

        /* dark */
        .dk { background: #111; color: #fff; padding: 56px 0; }
        .dk .sl { color: rgba(255,255,255,.5); }
        .dk .st { color: #fff; }
        .dk .ss { color: #9ca3af; }
        .dk .ig h3 { color: #fff; }
        .dk .ig p { color: #9ca3af; }
        .dk .b1 { background: #fff; color: #111; }
        .dk .b1:hover { background: #f3f4f6; }
        .dk .b2 { border-color: rgba(255,255,255,.25); color: #fff; }
        .dk .b2:hover { border-color: #fff; }
        /* contact grid */
        .cg { display: grid; grid-template-columns: 1fr; gap: 20px; }
        @media (min-width: 600px) { .cg { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); } }

        /* footer */
        .ft { background: #f9fafb; padding: 48px 0; }
        .fg { display: grid; gap: 32px; }
        @media (min-width: 600px) { .fg { grid-template-columns: 2fr 1fr 1fr; } }
        .ft h4 { font-size: .65rem; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: #111; margin-bottom: 12px; }
        .ft p, .ft li { font-size: .8rem; color: #6b7280; line-height: 1.6; }
        .ft ul { list-style: none; }
        .ft ul li { margin-bottom: 4px; }
        .ft a:hover { color: #111; }
        .ft .brand { font-size: 1rem; font-weight: 700; letter-spacing: .12em; color: #C73A3A; margin-bottom: 8px; }
        .ft .brand + p { max-width: 300px; }
        .ft .sc { display: flex; gap: 8px; margin-top: 12px; }
        .ft .sc a { width: 30px; height: 30px; background: #e5e7eb; display: flex; align-items: center; justify-content: center; }
        .ft .sc a:hover { background: #d1d5db; }
        .ft .sc svg { width: 13px; height: 13px; fill: #111; }
        .ft .cp { border-top: 1px solid #e5e7eb; margin-top: 28px; padding-top: 20px; font-size: .7rem; color: #9ca3af; text-align: center; }
.ft .cp a { color: #6b7280; }
.ft .cp a:hover { color: #C73A3A; }

/* cookie banner */
#cb {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: #111; color: #fff;
    padding: 16px 24px;
    display: flex; align-items: center; justify-content: center;
    gap: 24px; flex-wrap: wrap;
    font-size: .8rem; line-height: 1.5;
    z-index: 100;
}
#cb a { color: rgba(255,255,255,.6); text-decoration: underline; }
#cb a:hover { color: #fff; }
#cb button {
    background: #fff; color: #111; border: none; padding: 8px 20px;
    font-size: .8rem; font-weight: 600; cursor: pointer; white-space: nowrap;
}
#cb button:hover { background: #e5e7eb; }
#cb.h { display: none; }
    </style>
    @include('partials.pwa')
</head>
<body>

    <div class="w">
        <div class="nv">
            <a href="/" class="nv-l">PARAÍSO</a>
            <div class="nv-r">
                <a href="#proceso">Proceso</a>
                <a href="#cobertura">Cobertura</a>
                <a href="#contacto">Contacto</a>
            </div>
        </div>
    </div>

    <div class="w h">
        <h1>Registra la garantía de tu colchón Paraíso</h1>
        <p>Escanea el código QR de la etiqueta de tu colchón. Ingresa tus datos. Recibe tu certificado digital al instante.</p>
        <div class="ha">
            <a href="#proceso" class="b b1">Ver cómo funciona</a>
        </div>
        <div class="cb">
            <span class="p">$</span>
            <span class="c">escanea el código QR en la etiqueta de tu colchón</span>
        </div>
    </div>

    <div class="s">
        <div class="w">
            <div class="sl">Información</div>
            <div class="st">Lo que necesitas saber</div>
            <div class="ss">El proceso es simple y está diseñado para que lo hagas desde tu celular en minutos.</div>
            <div class="ig">
                <div>
                    <h3>Ubicación del QR</h3>
                    <p>En la costura lateral de tu colchón hay una etiqueta cosida con un código QR y el número de serie del producto.</p>
                </div>
                <div>
                    <h3>Qué necesitas</h3>
                    <p>Tu celular para escanear el QR y la factura de compra para completar el formulario de registro.</p>
                </div>
                <div>
                    <h3>Qué recibes</h3>
                    <p>Un certificado digital de garantía con todos tus datos, el producto y la vigencia de la cobertura.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="s" id="proceso">
        <div class="w">
            <div class="sl">Proceso</div>
            <div class="st">Tres pasos, un par de minutos</div>
            <div class="ss">Sin formularios largos ni vueltas. Escaneas, completas, listo.</div>
            <div class="pg">
                <div>
                    <div class="pg-num">01</div>
                    <span class="pg-ln"></span>
                    <div class="pg-t">Busca la etiqueta</div>
                    <div class="pg-d">La etiqueta con el QR está cosida en la costura lateral de tu colchón. Si compraste online, viene en la bolsa junto al producto.</div>
                </div>
                <div>
                    <div class="pg-num">02</div>
                    <span class="pg-ln"></span>
                    <div class="pg-t">Escanea el código</div>
                    <div class="pg-d">Con la cámara de tu celular escanea el QR. Te lleva directo a la página del producto con todos los detalles y el botón para registrar.</div>
                </div>
                <div>
                    <div class="pg-num">03</div>
                    <span class="pg-ln"></span>
                    <div class="pg-t">Completa y listo</div>
                    <div class="pg-d">Ingresa tus datos, los de la compra y acepta los términos. Al instante generamos tu certificado digital de garantía.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="dk" id="cobertura">
        <div class="w">
            <div class="sl">Cobertura</div>
            <div class="st">Por qué registrar tu garantía</div>
            <div class="ss">Tu colchón tiene respaldo oficial. Al registrar te aseguras cobertura total.</div>
            <div class="ig">
                <div>
                    <h3>Garantía oficial</h3>
                    <p>Respaldada por Productos Paraíso del Ecuador. Más de 45 años de trayectoria en el mercado ecuatoriano.</p>
                </div>
                <div>
                    <h3>Cobertura nacional</h3>
                    <p>Hacemos válida tu garantía sin costo en todo el territorio ecuatoriano. Llegamos donde estés.</p>
                </div>
                <div>
                    <h3>Sin complicaciones</h3>
                    <p>Registras una vez y tienes toda la información disponible: certificado, vigencia y datos del producto.</p>
                </div>
            </div>
            <div style="margin-top:32px; font-size:.85rem; color:rgba(255,255,255,.5);">1800 – 727 247 · Línea de servicio Paraíso</div>
        </div>
    </div>

    <div class="s" id="contacto">
        <div class="w">
            <div class="sl">Contacto</div>
            <div class="st">¿Tienes dudas? Habla con nosotros</div>
            <div class="ss">Estamos en todo Ecuador con fábrica, oficinas y tiendas físicas.</div>
            <div class="cg">
                <div>
                    <h3 style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;">Línea de servicio</h3>
                    <p style="font-size:.85rem; color:#6b7280; line-height:1.6;"><strong style="color:#111;">1800 – 727 247</strong><br><a href="mailto:servicioalcliente@paraiso.com.ec" style="color:#C73A3A;">servicioalcliente@paraiso.com.ec</a></p>
                </div>
                <div>
                    <h3 style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;">Fábrica</h3>
                    <p style="font-size:.85rem; color:#6b7280; line-height:1.6;">Panamericana Sur Km. 25<br>Tambillo · 02 2317-012</p>
                </div>
                <div>
                    <h3 style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;">Quito</h3>
                    <p style="font-size:.85rem; color:#6b7280; line-height:1.6;">Av. De los Shyris N37-313 y El Telégrafo<br>02 2264-843</p>
                </div>
                <div>
                    <h3 style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;">Guayaquil</h3>
                    <p style="font-size:.85rem; color:#6b7280; line-height:1.6;">Av. Juan Tanca Marengo Km. 4.5<br>04 2658-342</p>
                </div>
            </div>
        </div>
    </div>

    <div class="ft">
        <div class="w fg">
            <div>
                <div class="brand">PARAÍSO</div>
                <p>Donde empiezan tus sueños. Más de 45 años brindando el mejor descanso a las familias ecuatorianas.</p>
                <div class="sc">
                    <a href="https://instagram.com/colchonesparaiso" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                        <svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                    <a href="https://facebook.com/colchonesparaiso" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                        <svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="https://youtube.com/@colchonesparaiso" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                        <svg viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                </div>
            </div>
            <div>
                <h4>Compañía</h4>
                <ul>
                    <li><a href="https://colchonesparaiso.com.ec/" target="_blank" rel="noopener noreferrer">Tienda oficial</a></li>
                    <li><a href="https://colchonesparaiso.com.ec/promociones/" target="_blank" rel="noopener noreferrer">Promociones</a></li>
                    <li><a href="#">Sostenibilidad Paraíso</a></li>
                </ul>
            </div>
            <div>
                <h4>Legales</h4>
                <ul>
                    <li><a href="{{ route('public.terms') }}">Términos y condiciones</a></li>
                    <li><a href="{{ route('public.privacy') }}">Protección de datos</a></li>
                    <li><a href="{{ route('public.cookies') }}">Política de cookies</a></li>
                </ul>
            </div>
        </div>
        <div class="w">
            <div class="cp">&copy; {{ date('Y') }} Productos Paraíso del Ecuador · Sistema de Garantías · www.paraiso.com.ec<br>Desarrollado por <a href="https://monkeycomputer-landing.vercel.app/" target="_blank" rel="noopener noreferrer">MonkeyComputer</a></div>
        </div>
    </div>

<div id="cb">
    <p>Este sistema usa cookies técnicas para funcionar. Al continuar aceptás nuestra <a href="{{ route('public.cookies') }}">Política de cookies</a> y <a href="{{ route('public.privacy') }}">Protección de datos</a>.</p>
    <button id="ca">Aceptar</button>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){if(localStorage.getItem('c'))document.getElementById('cb').classList.add('h')});
document.getElementById('ca').addEventListener('click',function(){localStorage.setItem('c','1');document.getElementById('cb').classList.add('h')});
</script>

</body>
</html>
