<?php
// =====================================================================
// includes/iconos.php — Catálogo único de íconos SVG (sin emojis).
// Mismos trazos que el Dashboard FTTM (EJGR1906/Dashboard-FTTM) más los
// propios del portal (impresora, copias, paginas, menos).
// icono('nombre') devuelve el <svg> en línea con fill="currentColor"
// (toma el color del texto) y aria-hidden. Los mismos trazos están en
// assets/js/comun.js (window.Iconos) para lo que se dibuja en el navegador:
// si cambias o agregas uno, hazlo en los dos.
// =====================================================================

const ICONOS = [
    'alerta'        => 'M12 2 1 21h22L12 2Zm0 4.3L19.5 19h-15L12 6.3ZM11 10v5h2v-5h-2Zm0 6v2h2v-2h-2Z',
    'info'          => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm-1 3v2h2V7h-2Zm0 4v6h2v-6h-2Z',
    'documento'     => 'M6 2h8l6 6v14H6V2Zm2 2v16h10V9h-5V4H8Zm7 .8V7h2.2L15 4.8ZM10 12h6v2h-6v-2Zm0 4h6v2h-6v-2Z',
    'ajustes'       => 'M19.4 13a7.6 7.6 0 0 0 0-2l2.1-1.6-2-3.5-2.5 1a7.4 7.4 0 0 0-1.7-1L15 3h-4l-.4 2.9a7.4 7.4 0 0 0-1.7 1l-2.5-1-2 3.5L6.6 11a7.6 7.6 0 0 0 0 2l-2.1 1.6 2 3.5 2.5-1c.5.4 1.1.7 1.7 1L11 21h4l.4-2.9c.6-.3 1.2-.6 1.7-1l2.5 1 2-3.5-2.2-1.6ZM13 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z',
    'sol'           => 'M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6ZM11 1h2v3h-2V1Zm0 19h2v3h-2v-3ZM1 11h3v2H1v-2Zm19 0h3v2h-3v-2ZM4.2 2.8l2.1 2.1-1.4 1.4-2.1-2.1 1.4-1.4Zm14.9 14.9 2.1 2.1-1.4 1.4-2.1-2.1 1.4-1.4ZM2.8 19.8l2.1-2.1 1.4 1.4-2.1 2.1-1.4-1.4ZM17.7 4.9l2.1-2.1 1.4 1.4-2.1 2.1-1.4-1.4Z',
    'luna'          => 'M12.3 2a9.5 9.5 0 0 0 0 19 9.6 9.6 0 0 0 8.7-5.6A7.5 7.5 0 0 1 12.3 2Zm-2.6 2.7a9.5 9.5 0 0 0 8.2 11.9A7.5 7.5 0 1 1 9.7 4.7Z',
    'cerrar'        => 'M18.3 5.7 16.9 4.3 12 9.2 7.1 4.3 5.7 5.7l4.9 4.9-4.9 4.9 1.4 1.4 4.9-4.9 4.9 4.9 1.4-1.4-4.9-4.9 4.9-4.9Z',
    'mas'           => 'M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5Z',
    'subir'         => 'M11 16h2V6.8l3.6 3.6L18 9l-6-6-6 6 1.4 1.4L11 6.8V16Zm-7 3h16v2H4v-2Z',
    'check'         => 'M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z',
    'check-circulo' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm4.3 3.8L10.5 13.6 7.7 10.8l-1.4 1.4 4.2 4.2 7.2-7.2-1.4-1.4Z',
    'error'         => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm-1 3v6h2V7h-2Zm0 8v2h2v-2h-2Z',
    'chevron-abajo' => 'M7.4 8.6 12 13.2l4.6-4.6L18 10l-6 6-6-6 1.4-1.4Z',
    'basura'        => 'M9 3h6l1 2h4v2H4V5h4l1-2ZM6 9h12l-1 12H7L6 9Zm2.2 2 .7 8h6.2l.7-8H8.2Z',
    'refrescar'     => 'M17.7 6.3A8 8 0 1 0 20 12h-2a6 6 0 1 1-1.8-4.3L13 11h7V4l-2.3 2.3Z',
    'menos'         => 'M5 11h14v2H5v-2Z',
    'impresora'     => 'M6 2h12v6h-2V4H8v4H6V2ZM4 8h16a2 2 0 0 1 2 2v8h-4v-2h2v-6H4v6h2v2H2v-8a2 2 0 0 1 2-2Zm2 6h12v8H6v-8Zm2 2v4h8v-4H8Zm8-5h2v2h-2v-2Z',
    'copias'        => 'M8 2h10l4 4v12H8V2Zm2 2v12h10V7h-3V4h-7ZM4 6h2v14h10v2H4V6Z',
    'paginas'       => 'M3 3h7v9H3V3Zm2 2v5h3V5H5Zm9-2h7v9h-7V3Zm2 2v5h3V5h-3ZM3 14h7v7H3v-7Zm2 2v3h3v-3H5Zm9-2h7v7h-7v-7Zm2 2v3h3v-3h-3Z',
];

/** Devuelve el SVG del ícono (cadena vacía si no existe). */
function icono(string $nombre, string $clase = 'icon'): string
{
    $d = ICONOS[$nombre] ?? null;
    if ($d === null) return '';
    return '<svg class="' . htmlspecialchars($clase) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
         . '<path fill="currentColor" fill-rule="evenodd" d="' . $d . '"/></svg>';
}
