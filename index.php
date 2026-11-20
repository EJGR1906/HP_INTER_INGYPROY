<?php
// =====================================================================
// index.php — Portal de impresión en red (sin controladores).
// ---------------------------------------------------------------------
// GET  : página con el formulario (vista previa, páginas, copias, papel,
//        doble cara). La lógica del navegador está en assets/js/imprimir.js.
// POST : accion=imprimir   recibe el documento (ya recortado por el
//                          navegador a las páginas elegidas) y lo envía
//                          a la impresora con las opciones en PJL.
//        accion=convertir  convierte un documento de Office a PDF con
//                          LibreOffice y lo devuelve para la vista previa.
//        accion=reimprimir reimprime una copia guardada en /archivo.
// Las respuestas son JSON si la petición viene del JS (X-Requested-With)
// y flash + redirect (patrón PRG) si viene de un formulario sin JS.
// =====================================================================
require __DIR__ . '/config.php';
require __DIR__ . '/includes/util.php';
require __DIR__ . '/includes/impresora.php';
require __DIR__ . '/includes/cabecera.php';

iniciar_sesion();
$csrf = csrf_token();

// --- Puerta de acceso opcional (CLAVE_ACCESO en config.php) -----------
if (CLAVE_ACCESO !== '' && empty($_SESSION['portal_ok'])) {
    $errAcceso = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clave_acceso'])) {
        if (hash_equals(CLAVE_ACCESO, (string) $_POST['clave_acceso'])) {
            $_SESSION['portal_ok'] = true;
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
        }
        $errAcceso = 'Clave incorrecta.';
    }
    if (es_xhr()) responder('err', 'La sesión expiró. Recarga la página.', 401);
    ?><!doctype html><html lang="es"><head><?php pagina_head('Acceso, Imprimir en la oficina'); ?></head>
    <body class="app-body">
    <?php cabecera('Acceso'); ?>
    <main class="app-main app-estrecha">
        <section class="card">
            <div class="card-head"><h3><?= icono('ajustes') ?>Clave del portal</h3></div>
            <?php if ($errAcceso): ?><div class="alert alert-error mb-14"><?= icono('error') ?><div class="alert-body"><?= e($errAcceso) ?></div></div><?php endif; ?>
            <form method="post" class="stack">
                <label class="field mb-0">
                    <span class="field-label">Clave</span>
                    <span class="field-box"><input type="password" name="clave_acceso" autofocus required></span>
                </label>
                <div><button type="submit" class="btn btn-primary">Entrar</button></div>
            </form>
        </section>
    </main>
    <?= script_js('assets/js/comun.js') ?>
    </body></html><?php
    exit;
}

// --- Procesar acciones (POST) ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si el cuerpo supera post_max_size, PHP descarta $_POST y $_FILES enteros.
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        responder('err', 'El archivo supera el límite del servidor (' . humano_bytes(limite_subida_bytes()) . ').', 413);
    }
    if (!csrf_ok()) {
        responder('err', 'Token de seguridad inválido. Recarga la página.', 403);
    }
    $accion = (string) ($_POST['accion'] ?? '');

    // ---- Convertir un documento de Office a PDF (para la vista previa) ----
    if ($accion === 'convertir') {
        if (empty($_FILES['doc']) || $_FILES['doc']['error'] !== UPLOAD_ERR_OK) {
            responder('err', mensaje_error_subida((int) ($_FILES['doc']['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        $orig = limpiar_nombre($_FILES['doc']['name']);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, EXT_CONVERTIBLES, true)) responder('err', 'Ese formato no se convierte a PDF.');
        $work = dir_trabajo('conv_');
        $entrada = $work . DIRECTORY_SEPARATOR . 'archivo.' . $ext;
        move_uploaded_file($_FILES['doc']['tmp_name'], $entrada);
        $pdf = convertir_pdf($entrada, $work);
        if ($pdf === null) {
            borrar_dir($work);
            responder('err', 'No se pudo convertir a PDF (¿LibreOffice instalado en el servidor?). Guarda el documento como PDF y súbelo.');
        }
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($pdf));
        header('Cache-Control: no-store');
        readfile($pdf);
        borrar_dir($work);
        exit;
    }

    // ---- Imprimir un archivo subido ----
    if ($accion === 'imprimir') {
        if (empty($_FILES['doc']) || $_FILES['doc']['error'] !== UPLOAD_ERR_OK) {
            responder('err', mensaje_error_subida((int) ($_FILES['doc']['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        if ($_FILES['doc']['size'] > MAX_MB * 1024 * 1024) {
            responder('err', 'Archivo demasiado grande (máximo ' . MAX_MB . ' MB).', 413);
        }
        $orig = limpiar_nombre($_FILES['doc']['name']);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $op   = opciones_impresion($_POST);
        // Nombre visible: el del archivo original (el navegador sube el PDF ya recortado).
        $mostrar = limpiar_nombre((string) ($_POST['nombre_original'] ?? '')) ?: $orig;
        if ($mostrar === 'documento') $mostrar = $orig;
        $paginas = trim((string) ($_POST['paginas_desc'] ?? ''));
        $paginas = preg_match('/^[0-9 ,\-a-záéíóúñ]{0,120}$/iu', $paginas) ? $paginas : '';

        $work = dir_trabajo('imp_');
        $entrada = $work . DIRECTORY_SEPARATOR . 'archivo.' . ($ext ?: 'bin');
        move_uploaded_file($_FILES['doc']['tmp_name'], $entrada);

        [$okPrep, $listo, $errPrep] = preparar_listo($entrada, $ext, $work);
        if (!$okPrep) { borrar_dir($work); responder('err', $errPrep); }

        [$ok, $r, $via] = imprimir_listo($listo, $op, $mostrar, $work);
        $desc = describir_opciones($op) . ($paginas !== '' ? ', páginas: ' . $paginas : '');
        if ($ok && GUARDAR_COPIA) {
            registrar_impresion($entrada, $mostrar, [
                'metodo' => $via, 'estado' => 'impreso',
                'bytes'  => (int) $_FILES['doc']['size'], 'opciones' => $desc,
            ]);
        }
        borrar_dir($work);
        responder($ok ? 'ok' : 'err', $ok ? "$mostrar enviado a la impresora ($desc). $r." : $r);
    }

    // ---- Reimprimir un documento del historial ----
    if ($accion === 'reimprimir' && GUARDAR_COPIA) {
        $full = ruta_guardado_segura((string) ($_POST['guardado'] ?? ''));
        if ($full === null) responder('err', 'Ese documento ya no está disponible.');
        $ext  = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $op   = opciones_impresion($_POST);
        $work = dir_trabajo('rip_');
        $copia = $work . DIRECTORY_SEPARATOR . 'archivo.' . ($ext ?: 'bin');
        @copy($full, $copia);
        [$okPrep, $listo, $errPrep] = preparar_listo($copia, $ext, $work);
        if (!$okPrep) { borrar_dir($work); responder('err', $errPrep); }
        [$ok, $r] = imprimir_listo($listo, $op, basename($full), $work);
        borrar_dir($work);
        responder($ok ? 'ok' : 'err', ($ok ? 'Reimpreso. ' : '') . $r);
    }

    responder('err', 'Acción no reconocida.', 400);
}

// --- Vista (GET) -----------------------------------------------------
$flash      = tomar_flash();
$aceptar    = '.' . implode(',.', array_merge(EXT_DIRECTAS, EXT_CONVERTIBLES, ['jpg', 'jpeg', 'png']));
$limite     = limite_subida_bytes();
$hayOffice  = localizar_soffice() !== null;
$config = [
    'limite'      => $limite,
    'convertir'   => $hayOffice,
    'convertibles'=> EXT_CONVERTIBLES,
    'directas'    => EXT_DIRECTAS,
    'copiasMax'   => COPIAS_MAX,
];
?>
<!doctype html>
<html lang="es">
<head>
    <?php pagina_head('Imprimir en la oficina'); ?>
</head>
<body class="app-body">
<?php cabecera('Imprimir en la oficina', 'Impresora ' . IMPRESORA_IP . ', sin instalar controladores'); ?>

<main class="app-main">
    <div id="mensaje" aria-live="polite">
        <?php if ($flash): ?>
            <div class="alert <?= $flash['tipo'] === 'ok' ? 'alert-ok' : 'alert-error' ?>">
                <?= icono($flash['tipo'] === 'ok' ? 'check-circulo' : 'error') ?>
                <div class="alert-body"><?= e($flash['msg']) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <form id="formImprimir" class="imprimir-grid" method="post" enctype="multipart/form-data"
          data-config="<?= e(json_encode($config)) ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="accion" value="imprimir">

        <!-- Documento y vista previa -->
        <section class="card doc-card">
            <div class="card-head">
                <h3><?= icono('documento') ?>Documento</h3>
                <span class="count-badge" id="contadorPaginas" hidden></span>
            </div>

            <div class="file-drop" data-file-drop>
                <input class="file-input" type="file" name="doc" id="doc" accept="<?= e($aceptar) ?>" required>
                <label class="file-drop-area" for="doc">
                    <span class="file-drop-ico"><?= icono('subir', 'icon-lg') ?></span>
                    <span class="file-drop-txt">
                        <span class="file-drop-title">Elige o arrastra un archivo</span>
                        <span class="file-drop-sub">PDF, imagen (JPG, PNG), texto<?= $hayOffice ? ' o documento de Office' : '' ?>. Hasta <?= e(humano_bytes($limite)) ?>.</span>
                    </span>
                </label>
            </div>

            <div class="alert alert-warn mt-14" id="avisoDoc" hidden><?= icono('alerta') ?><div class="alert-body"></div></div>

            <div class="vista" id="vista" hidden>
                <div class="vista-head">
                    <p class="muted" id="vistaAyuda">Pulsa una página para quitarla o agregarla a la impresión.</p>
                    <div class="vista-acciones">
                        <button type="button" class="btn btn-ghost btn-sm" id="selTodas">Todas</button>
                        <button type="button" class="btn btn-ghost btn-sm" id="selNinguna">Ninguna</button>
                    </div>
                </div>
                <div class="pag-grid" id="pagGrid" role="group" aria-label="Páginas del documento"></div>
            </div>
            <p class="muted mt-14" id="estadoVista" hidden></p>
        </section>

        <!-- Opciones de impresión -->
        <section class="card opciones-card">
            <div class="card-head"><h3><?= icono('impresora') ?>Opciones de impresión</h3></div>

            <fieldset class="opcion" id="grupoPaginas">
                <legend class="field-label">Páginas</legend>
                <div class="seg seg-full" role="radiogroup" aria-label="Páginas a imprimir">
                    <button type="button" class="seg-btn active" data-paginas="todas" role="radio" aria-checked="true">Todas</button>
                    <button type="button" class="seg-btn" data-paginas="rango" role="radio" aria-checked="false">Rango</button>
                    <button type="button" class="seg-btn" data-paginas="impares" role="radio" aria-checked="false">Impares</button>
                    <button type="button" class="seg-btn" data-paginas="pares" role="radio" aria-checked="false">Pares</button>
                </div>
                <label class="field mt-10 mb-0" id="campoRango" hidden>
                    <span class="sr-only">Rango de páginas</span>
                    <span class="field-box"><input type="text" id="rango" inputmode="numeric" placeholder="Ejemplo: 1-3, 5, 8-10" autocomplete="off"></span>
                    <span class="field-hint" id="rangoAyuda">Separa con comas; usa guion para un intervalo.</span>
                </label>
            </fieldset>

            <div class="opcion">
                <span class="field-label" id="lblCopias">Copias</span>
                <div class="fila-copias">
                    <div class="stepper" role="group" aria-labelledby="lblCopias">
                        <button type="button" class="icon-btn" id="copiasMenos" aria-label="Una copia menos"><?= icono('menos', 'icon-sm') ?></button>
                        <input type="number" name="copias" id="copias" value="1" min="1" max="<?= (int) COPIAS_MAX ?>" aria-labelledby="lblCopias">
                        <button type="button" class="icon-btn" id="copiasMas" aria-label="Una copia más"><?= icono('mas', 'icon-sm') ?></button>
                    </div>
                    <label class="check">
                        <input type="hidden" name="intercalar" value="0">
                        <input type="checkbox" name="intercalar" value="1" id="intercalar" checked>
                        <span>Intercalar</span>
                    </label>
                </div>
            </div>

            <label class="opcion">
                <span class="field-label">Tamaño de papel</span>
                <select class="input-select input-full" name="papel" id="papel">
                    <?php foreach (PAPELES as $clave => $nombre): ?>
                        <option value="<?= e($clave) ?>"<?= $clave === PAPEL_DEFECTO ? ' selected' : '' ?>><?= e($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <fieldset class="opcion" id="grupoOrientacion">
                <legend class="field-label">Orientación</legend>
                <div class="seg seg-full" role="radiogroup" aria-label="Orientación">
                    <button type="button" class="seg-btn active" data-orientacion="auto" role="radio" aria-checked="true">Automática</button>
                    <button type="button" class="seg-btn" data-orientacion="vertical" role="radio" aria-checked="false">Vertical</button>
                    <button type="button" class="seg-btn" data-orientacion="horizontal" role="radio" aria-checked="false">Horizontal</button>
                </div>
            </fieldset>

            <fieldset class="opcion" id="grupoEscala">
                <legend class="field-label">Escala</legend>
                <div class="seg seg-full" role="radiogroup" aria-label="Escala">
                    <button type="button" class="seg-btn active" data-escala="ajustar" role="radio" aria-checked="true">Ajustar al papel</button>
                    <button type="button" class="seg-btn" data-escala="real" role="radio" aria-checked="false">Tamaño real</button>
                </div>
            </fieldset>

            <?php if (DUPLEX_DISPONIBLE): ?>
            <fieldset class="opcion">
                <legend class="field-label">Doble cara</legend>
                <input type="hidden" name="duplex" id="duplex" value="no">
                <div class="seg seg-full" role="radiogroup" aria-label="Doble cara">
                    <button type="button" class="seg-btn active" data-duplex="no" role="radio" aria-checked="true">No</button>
                    <button type="button" class="seg-btn" data-duplex="largo" role="radio" aria-checked="false">Borde largo</button>
                    <button type="button" class="seg-btn" data-duplex="corto" role="radio" aria-checked="false">Borde corto</button>
                </div>
            </fieldset>
            <?php endif; ?>

            <div class="resumen-imp" id="resumen" hidden></div>

            <div class="progreso" id="progreso" hidden>
                <div class="progreso-barra"><span id="progresoRelleno"></span></div>
                <p class="muted" id="progresoTxt"></p>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="btnImprimir" data-requiere-archivo>
                <?= icono('impresora') ?><span>Imprimir</span>
            </button>
            <noscript><p class="muted mt-10">Sin JavaScript se imprime el documento completo con copias, papel y doble cara.</p></noscript>
        </section>
    </form>

    <?php if (GUARDAR_COPIA): ?>
        <p class="pie muted"><?= icono('info', 'icon-sm') ?> Se guarda una copia de cada documento impreso en el servidor (no se puede descargar desde la red).</p>
    <?php endif; ?>
    <p class="pie muted">Herramienta interna de red. Úsala solo dentro de la oficina.</p>
</main>

<?= script_js('assets/js/comun.js') ?>
<script src="assets/vendor/pdf-lib.min.js?v=1.17.1"></script>
<?= script_js('assets/js/imprimir.js') ?>
</body>
</html>
