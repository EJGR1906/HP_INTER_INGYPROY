<?php
// =====================================================================
// lib/util.php — Sesión, CSRF, flash y REGISTRO VISIBLE de impresiones.
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
    $u = ['B', 'KB', 'MB', 'GB']; $i = 0;
    while ($n >= 1024 && $i < count($u) - 1) { $n = (int) ($n / 1024); $i++; }
    return $n . ' ' . $u[$i];
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

    $seguro   = date('Ymd_His') . '_' . bin2hex(random_bytes(2)) . '__' . limpiar_nombre($nombreMostrar);
    @copy($rutaOrigen, $destino . DIRECTORY_SEPARATOR . $seguro);

    $fila = [
        'ts'       => date('Y-m-d H:i:s'),
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
        'original' => $nombreMostrar,
        'guardado' => $mes . '/' . $seguro,
        'metodo'   => $meta['metodo'] ?? '',
        'estado'   => $meta['estado'] ?? '',
        'bytes'    => (int) ($meta['bytes'] ?? 0),
    ];
    @file_put_contents(
        dir_archivo() . DIRECTORY_SEPARATOR . '_registro.jsonl',
        json_encode($fila, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
    return $fila['guardado'];
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
