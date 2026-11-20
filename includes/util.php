<?php
// =====================================================================
// includes/util.php — Sesión, CSRF, flash, respuestas (JSON o redirect),
// límites de subida y REGISTRO VISIBLE de impresiones.
// ---------------------------------------------------------------------
// El registro es TRANSPARENTE: la página avisa de que se guarda una copia
// de cada documento y muestra el historial a quien use el portal. La
// carpeta /archivo está bloqueada para descargas por HTTP (.htaccess).
// =====================================================================

/** Arranca la sesión con cookie endurecida (una sola vez). */
function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => $https,
    ]);
    session_start();
}

/** htmlspecialchars corto para las vistas. */
function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Token CSRF de la sesión (se crea si no existe). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

/** ¿El token CSRF enviado por POST es válido? */
function csrf_ok(): bool
{
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''));
}

/** Guarda un mensaje flash para mostrarlo tras el redirect (patrón PRG). */
function set_flash(string $tipo, string $msg): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'msg' => $msg];
}

/** Lee y borra el mensaje flash. */
function tomar_flash(): ?array
{
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
}

/** ¿La petición viene del JS de la página (XHR/fetch)? Entonces se responde JSON. */
function es_xhr(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

/**
 * Termina la petición POST con un mensaje: JSON si viene del JS, o flash +
 * redirect (patrón PRG) si viene de un formulario sin JS.
 */
function responder(string $tipo, string $msg, int $codigo = 200): void
{
    if (es_xhr()) {
        http_response_code($tipo === 'ok' ? 200 : ($codigo !== 200 ? $codigo : 422));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $tipo === 'ok', 'msg' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    set_flash($tipo, $msg);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/** Convierte "200M", "1G", "512K" de php.ini a bytes. */
function ini_bytes(string $valor): int
{
    $valor = trim($valor);
    if ($valor === '') return 0;
    $n = (float) $valor;
    switch (strtolower(substr($valor, -1))) {
        case 'g': $n *= 1024;
        // no break
        case 'm': $n *= 1024;
        // no break
        case 'k': $n *= 1024;
    }
    return (int) $n;
}

/** Tamaño máximo real de subida: el menor entre MAX_MB y los límites de PHP. */
function limite_subida_bytes(): int
{
    $limites = [MAX_MB * 1024 * 1024];
    foreach (['upload_max_filesize', 'post_max_size'] as $k) {
        $b = ini_bytes((string) ini_get($k));
        if ($b > 0) $limites[] = $b;
    }
    return min($limites);
}

/** Mensaje claro para cada código de error de subida de PHP. */
function mensaje_error_subida(int $codigo): string
{
    $max = humano_bytes(limite_subida_bytes());
    switch ($codigo) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:  return "El archivo supera el límite del servidor ($max).";
        case UPLOAD_ERR_PARTIAL:    return 'La subida se cortó a medias. Vuelve a intentarlo.';
        case UPLOAD_ERR_NO_FILE:    return 'No se recibió ningún archivo.';
        case UPLOAD_ERR_NO_TMP_DIR: return 'El servidor no tiene carpeta temporal para subidas (revisa upload_tmp_dir en php.ini).';
        case UPLOAD_ERR_CANT_WRITE: return 'El servidor no pudo guardar el archivo (¿disco lleno?).';
        default:                    return 'No se pudo recibir el archivo (código ' . $codigo . ').';
    }
}

/** Crea una carpeta temporal de trabajo única. */
function dir_trabajo(string $prefijo): string
{
    $dir = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . $prefijo . bin2hex(random_bytes(5));
    @mkdir($dir, 0775, true);
    return $dir;
}

/** Deja un nombre de archivo seguro (sin rutas ni caracteres raros). */
function limpiar_nombre(string $nombre): string
{
    $nombre = basename($nombre);
    $nombre = preg_replace('/[^\p{L}\p{N}._\- ]+/u', '_', $nombre) ?? '';
    $nombre = trim($nombre);
    return $nombre === '' ? 'documento' : $nombre;
}

/** Tamaño legible por humanos. */
function humano_bytes(int $n): string
{
    $u = ['B', 'KB', 'MB', 'GB']; $i = 0; $v = (float) $n;
    while ($v >= 1024 && $i < count($u) - 1) { $v /= 1024; $i++; }
    return ($i >= 2 ? str_replace('.', ',', (string) round($v, 1)) : (string) (int) $v) . ' ' . $u[$i];
}

/** Carpeta raíz del proyecto (lib/ está un nivel por debajo). */
function raiz(): string
{
    return dirname(__DIR__);
}

/** Carpeta del archivo de documentos. */
function dir_archivo(): string
{
    return raiz() . DIRECTORY_SEPARATOR . 'archivo';
}

/**
 * Guarda una copia del documento y añade una línea al registro visible.
 * @return string  Ruta relativa guardada (para el historial / reimpresión).
 */
function registrar_impresion(string $rutaOrigen, string $nombreMostrar, array $meta): string
{
    $mes     = date('Y-m');
    $destino = dir_archivo() . DIRECTORY_SEPARATOR . $mes;
    if (!is_dir($destino)) @mkdir($destino, 0775, true);

    // El nombre visible puede ser "foto.png" aunque lo impreso sea el PDF que armó
    // el navegador: la copia lleva la extensión del archivo real para poder reimprimirla.
    $extReal  = strtolower(pathinfo($rutaOrigen, PATHINFO_EXTENSION));
    $baseNom  = limpiar_nombre(pathinfo($nombreMostrar, PATHINFO_FILENAME));
    $seguro   = date('Ymd_His') . '_' . bin2hex(random_bytes(2)) . '__' . $baseNom . ($extReal !== '' ? '.' . $extReal : '');
    @copy($rutaOrigen, $destino . DIRECTORY_SEPARATOR . $seguro);

    $fila = [
        'ts'       => date('Y-m-d H:i:s'),
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
        'original' => $nombreMostrar,
        'guardado' => $mes . '/' . $seguro,
        'metodo'   => $meta['metodo'] ?? '',
        'estado'   => $meta['estado'] ?? '',
        'bytes'    => (int) ($meta['bytes'] ?? 0),
        'opciones' => (string) ($meta['opciones'] ?? ''),
    ];
    anotar_registro($fila);
    return $fila['guardado'];
}

/** Agrega una línea al registro (archivo/_registro.jsonl). */
function anotar_registro(array $fila): void
{
    @file_put_contents(
        dir_archivo() . DIRECTORY_SEPARATOR . '_registro.jsonl',
        json_encode($fila, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/** Anota una reimpresión (no guarda otra copia: apunta a la misma). */
function registrar_reimpresion(string $guardado, string $nombreMostrar, array $meta): void
{
    anotar_registro([
        'ts'       => date('Y-m-d H:i:s'),
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
        'original' => $nombreMostrar,
        'guardado' => $guardado,
        'metodo'   => $meta['metodo'] ?? '',
        'estado'   => 'reimpreso',
        'bytes'    => (int) ($meta['bytes'] ?? 0),
        'opciones' => (string) ($meta['opciones'] ?? ''),
    ]);
}

/** Últimas N entradas del registro (más recientes primero). */
function leer_historial(int $max): array
{
    $ruta = dir_archivo() . DIRECTORY_SEPARATOR . '_registro.jsonl';
    if (!is_file($ruta)) return [];
    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lineas === false) return [];
    $filas = [];
    foreach (array_reverse(array_slice($lineas, -$max)) as $l) {
        $d = json_decode($l, true);
        if (is_array($d)) $filas[] = $d;
    }
    return $filas;
}

/**
 * Ruta absoluta y segura de un documento guardado, validando que esté
 * dentro de /archivo (evita salir de la carpeta con "../").
 */
function ruta_guardado_segura(string $rel): ?string
{
    $base = realpath(dir_archivo());
    $full = realpath(dir_archivo() . DIRECTORY_SEPARATOR . str_replace('\\', '/', $rel));
    if ($base === false || $full === false) return null;
    if (strncmp($full, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) return null;  // fuera de /archivo
    return is_file($full) ? $full : null;
}

/** Borra recursivamente una carpeta temporal de trabajo. */
function borrar_dir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $f;
        is_dir($p) ? borrar_dir($p) : @unlink($p);
    }
    @rmdir($dir);
}
