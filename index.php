<?php
// =====================================================================
// index.php — Portal de impresión en red (sin controladores) con
// REGISTRO VISIBLE de impresiones y reimpresión.
// =====================================================================
require __DIR__ . '/config.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/impresora.php';

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
    $cssV = @filemtime(__DIR__ . '/assets/estilo.css');
    ?><!doctype html><html lang="es"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso · Imprimir en red</title>
    <link rel="stylesheet" href="assets/estilo.css?v=<?= (int) $cssV ?>"></head>
    <body><main class="wrap"><section class="card">
        <h1>🔒 Acceso</h1>
        <?php if ($errAcceso): ?><div class="msg msg-err"><?= e($errAcceso) ?></div><?php endif; ?>
        <form method="post">
            <input type="password" name="clave_acceso" placeholder="Clave" autofocus required>
            <button type="submit">Entrar</button>
        </form>
    </section></main></body></html><?php
    exit;
}

// --- Procesar acciones (POST) → patrón PRG ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!csrf_ok()) {
        set_flash('err', 'Token de seguridad inválido. Recarga la página.');
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }

    // ---- Imprimir un archivo subido ----
    if ($_POST['accion'] === 'imprimir') {
        if (empty($_FILES['doc']) || $_FILES['doc']['error'] !== UPLOAD_ERR_OK) {
            set_flash('err', 'No se recibió ningún archivo (¿o supera el límite del servidor?).');
        } elseif ($_FILES['doc']['size'] > MAX_MB * 1024 * 1024) {
            set_flash('err', 'Archivo demasiado grande (máx ' . MAX_MB . ' MB).');
        } else {
            $orig = limpiar_nombre($_FILES['doc']['name']);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $work = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'imp_' . bin2hex(random_bytes(5));
            @mkdir($work, 0775, true);
            $entrada = $work . DIRECTORY_SEPARATOR . 'archivo.' . ($ext ?: 'bin');
            move_uploaded_file($_FILES['doc']['tmp_name'], $entrada);

            [$okPrep, $listo, $errPrep] = preparar_listo($entrada, $ext, $work);
            if (!$okPrep) {
                set_flash('err', $errPrep);
            } else {
                [$ok, $r, $via] = imprimir_listo($listo);
                if ($ok) {
                    if (GUARDAR_COPIA) {
                        registrar_impresion($entrada, $orig, [
                            'metodo' => $via, 'estado' => 'impreso',
                            'bytes'  => (int) $_FILES['doc']['size'],
                        ]);
                    }
                    set_flash('ok', "✅ $orig — $r"
                        . (GUARDAR_COPIA ? ' · Se envio exitosamente a la impresora' : ''));
                } else {
                    set_flash('err', "❌ $r");
                }
            }
            borrar_dir($work);
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }

    // ---- Reimprimir un documento del historial ----
    if ($_POST['accion'] === 'reimprimir' && GUARDAR_COPIA) {
        $rel  = (string) ($_POST['guardado'] ?? '');
        $full = ruta_guardado_segura($rel);
        if ($full === null) {
            set_flash('err', 'Ese documento ya no está disponible.');
        } else {
            $ext  = strtolower(pathinfo($full, PATHINFO_EXTENSION));
            $work = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'rip_' . bin2hex(random_bytes(5));
            @mkdir($work, 0775, true);
            $copia = $work . DIRECTORY_SEPARATOR . 'archivo.' . ($ext ?: 'bin');
            @copy($full, $copia);
            [$okPrep, $listo, $errPrep] = preparar_listo($copia, $ext, $work);
            if (!$okPrep) {
                set_flash('err', $errPrep);
            } else {
                [$ok, $r, $via] = imprimir_listo($listo);
                set_flash($ok ? 'ok' : 'err', ($ok ? '✅ Reimpreso — ' : '❌ ') . $r);
            }
            borrar_dir($work);
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }
}

// --- Vista (GET) -----------------------------------------------------
$flash     = tomar_flash();
$historial = GUARDAR_COPIA ? leer_historial(HISTORIAL_MAX) : [];
$aceptar   = '.' . implode(',.', array_merge(EXT_DIRECTAS, EXT_CONVERTIBLES));
$cssV      = @filemtime(__DIR__ . '/assets/estilo.css');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imprimir en red</title>
    <link rel="stylesheet" href="assets/estilo.css?v=<?= (int) $cssV ?>">
</head>
<body>
<main class="wrap">
    <header class="head">
        <h1>🖨️ Imprimir en la oficina</h1>
        <span class="sub">Impresora <?= e(IMPRESORA_IP) ?> · sin instalar controladores</span>
    </header>

    <?php if ($flash): ?>
        <div class="msg <?= $flash['tipo'] === 'ok' ? 'msg-ok' : 'msg-err' ?>"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <section class="card">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="accion" value="imprimir">
            <label class="drop">
                <input type="file" name="doc" accept="<?= e($aceptar) ?>" required>
                <span>Elige un archivo (PDF, texto o documento de Office)</span>
            </label>
            <button type="submit">Enviar a imprimir</button>
        </form>
  
    </section>

    <footer class="pie muted">Herramienta interna de red · usa solo dentro de la oficina.</footer>
</main>
</body>
</html>
