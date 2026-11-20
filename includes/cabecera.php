<?php
// =====================================================================
// includes/cabecera.php — Piezas comunes de las páginas (mismo patrón que
// el Dashboard FTTM):
//   pagina_head()  <head> (meta, CSS con versión, tema guardado antes de pintar)
//   cabecera()     header en dos filas, como apple.com:
//                  global nav negra (marca y botón de tema) y sub-nav
//                  esmerilada con el título de la página.
// La interacción (tema, subir archivo) vive en assets/js/comun.js.
// =====================================================================
require_once __DIR__ . '/iconos.php';

/** Versión del archivo para romper la caché del navegador. */
function version_asset(string $ruta): string
{
    return (string) @filemtime(__DIR__ . '/../' . $ruta);
}

/** Imprime el contenido común del <head>. */
function pagina_head(string $titulo): void
{
    ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?></title>
    <script>try { var t = localStorage.getItem('tema'); if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t); } catch (e) {}</script>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg?v=<?= version_asset('assets/img/favicon.svg') ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= version_asset('assets/css/app.css') ?>">
    <?php
}

/** Barra superior: global nav (marca y tema) y sub-nav con el título. */
function cabecera(string $titulo, string $detalle = ''): void
{
    ?>
    <header class="topbar">
        <div class="global-nav">
            <div class="global-row">
                <a class="brand-btn" href="./" title="Ir al inicio">
                    <span class="brand-badge brand-badge-sm"><?php include __DIR__ . '/../assets/img/logo.svg'; ?></span>
                    <span class="brand-txt">Impresión en red</span>
                </a>
                <div class="global-actions">
                    <button type="button" class="nav-icon-btn theme-btn" id="themeBtn" title="Cambiar tema claro u oscuro" aria-label="Cambiar tema claro u oscuro">
                        <?= icono('luna', 'icon t-luna') ?><?= icono('sol', 'icon t-sol') ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="sub-nav">
            <div class="sub-row">
                <h1 class="sub-title"><?= e($titulo) ?></h1>
                <?php if ($detalle !== ''): ?><span class="sub-detalle"><?= e($detalle) ?></span><?php endif; ?>
            </div>
        </div>
    </header>
    <?php
}

/** Etiqueta <script> de un archivo JS con versión. */
function script_js(string $ruta): string
{
    return '<script src="' . e($ruta) . '?v=' . version_asset($ruta) . '"></script>';
}
