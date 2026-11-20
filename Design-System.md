# Sistema de diseño — Portal de impresión

Es **el mismo sistema del Dashboard FTTM** ([EJGR1906/Dashboard-FTTM](https://github.com/EJGR1906/Dashboard-FTTM),
`Design-System.md` y `assets/css/app.css`). Este portal copia los tokens y los
componentes que usa; si el Dashboard cambia un token, se copia aquí igual.

Todo vive en `assets/css/app.css` (un solo archivo, sin preprocesador). Sigue el
lenguaje de apple.com y de las Human Interface Guidelines de Apple: superficies
planas, un solo azul de acción, tipografía del sistema, mucho aire y **sin bordes de
color, sin sombras, sin brillos y sin degradados**. La separación entre bloques la da
el fondo (tarjetas blancas sobre gris Parchment; en oscuro, grafito sobre negro).

## Reglas (las mismas del Dashboard)
- **Sin bordes ni sombras en tarjetas, botones o campos.** La única excepción del
  portal: el filete gris neutro `--outline` de las miniaturas de página (son hojas
  blancas y sin él se pierden sobre la tarjeta blanca).
- **Un solo color de acción**: `--primary` / `--btn` (`#0066cc`). Botón Imprimir,
  enlaces, foco, selección de páginas.
- Contraste AA: texto ≥ 4.5:1 en claro y oscuro (verificado con axe-core, regla `color-contrast`).
- Pesos 400, 600 y 700. Títulos en 600 con tracking negativo.
- Todo lo pulsable se encoge al presionar (`transform: scale(.95)`), miniaturas incluidas.
- Íconos: SVG en línea con `fill="currentColor"`, **nunca emojis** ni flechas de texto
  (← → ✓ ⚠ ▾ ×). En PHP con `icono('nombre')` (`includes/iconos.php`); en JS con
  `Iconos('nombre')` (`assets/js/comun.js`). Si agregas uno, agrégalo en los dos.
- Usar siempre los tokens; no poner colores sueltos en el HTML ni en el JS.
- Probar cada cambio en tema claro **y** oscuro, y a 390 px de ancho.
- Textos en español. **No usar el separador « · »** (punto medio) en nada visible.

## Tokens
Los mismos de la tabla del Dashboard (`--primary`, `--btn`, `--focus`, `--primary-soft`,
`--bg`, `--surface`, `--surface-2`, `--surface-3`, `--on-surface`, `--on-surface-variant`,
`--outline`, `--outline-strong`, `--global-nav`, `--nav-bg`, `--ok`/`--error`/`--warn`
con `-text` y `-bg`), radios (`--radius-sm` 8, `--radius` 11, `--radius-lg` 18, `--pill`)
y fuentes (`--font`, `--font-display`). **No** se copian los colores de fase `--st-*`.

## Tema claro/oscuro
Sigue al sistema (`prefers-color-scheme`). El botón sol/luna del header lo fuerza con
`<html data-theme="light|dark">` y lo guarda en `localStorage('tema')`. `pagina_head()`
aplica la preferencia antes de pintar.

## Logo
`assets/img/logo.svg`: cuadrado redondeado en `--logo-fondo` (azul `--btn`) con el
ícono `impresora` en `currentColor` (blanco en el header). `assets/img/favicon.svg`:
lo mismo con colores fijos. **No** se usa el logo de HP.

## Componentes
| Clase | Origen | Qué es |
|---|---|---|
| `.topbar` > `.global-nav` + `.sub-nav` | Dashboard | Header de dos filas (`includes/cabecera.php`). El portal no tiene menú de usuario. |
| `.sub-detalle` | Portal | Texto secundario a la derecha del título (IP de la impresora) |
| `.card`, `.card-head`, `.count-badge` | Dashboard | Tarjetas Documento y Opciones; contador de páginas |
| `.btn`, `.btn-primary`, `.btn-ghost`, `.btn-sm`, `.btn-block`, `.icon-btn`, `.spinner` | Dashboard | Botones |
| `.file-drop`, `.file-input`, `.file-drop-area` | Dashboard | Subir archivo con arrastrar y soltar |
| `.field`, `.field-label`, `.field-box`, `.input-select` | Dashboard | Campos |
| `.seg`, `.seg-btn` | Dashboard | Control segmentado: páginas, orientación, escala, doble cara |
| `.alert`, `.alert-ok`, `.alert-error`, `.alert-warn` | Dashboard | Mensajes con ícono |
| `.imprimir-grid`, `.opciones-card` | Portal | Dos columnas (documento y opciones fijas al hacer scroll); una sola bajo 960 px |
| `.seg-full`, `.field-hint` | Portal | Segmentado a todo el ancho; ayuda o error bajo un campo |
| `.stepper`, `.check` | Portal | Copias con botones menos/más; casilla Intercalar |
| `.pag-grid`, `.pag-mini`, `.pag-lienzo`, `.pag-marca`, `.pag-num` | Portal | Miniaturas; `aria-pressed="true"` = página elegida (fondo `--primary-soft`) |
| `.resumen-imp` | Portal | Resumen: páginas, copias y hojas |
| `.progreso`, `.progreso-barra` | Portal | Progreso de subida (`.indeterminado` mientras imprime) |
