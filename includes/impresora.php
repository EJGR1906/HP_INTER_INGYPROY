<?php
// =====================================================================
// includes/impresora.php — Envío a la impresora sin controladores.
// Métodos: RAW 9100 (JetDirect) y FTP (HP FTP Print Server).
// Los archivos deben ir "listos para imprimir" (PDF/PS/PCL/TXT);
// los documentos de Office se convierten a PDF con LibreOffice (opcional).
//
// Opciones de impresión (copias, papel, doble cara): se aplican con un
// envoltorio PJL (Printer Job Language) alrededor del trabajo, que la
// impresora HP lee antes del documento. La selección de páginas, la
// orientación y el ajuste al papel los hace el navegador (assets/js/imprimir.js)
// antes de subir el PDF.
//
// Todo se envía por bloques (streaming): un archivo de 200 MB no se carga
// entero en memoria.
// =====================================================================

/** Tamaño de bloque para leer y enviar archivos. */
const BLOQUE_ENVIO = 65536;

/** Secuencia UEL (Universal Exit Language) que abre y cierra un trabajo PJL. */
const PJL_UEL = "\x1B%-12345X";

/**
 * Lee y valida las opciones de impresión enviadas por el formulario.
 * Todo lo que no esté en las listas blancas toma el valor por defecto.
 * @return array{copias:int,intercalar:bool,papel:string,duplex:string}
 */
function opciones_impresion(array $entrada): array
{
    $copias = (int) ($entrada['copias'] ?? 1);
    $copias = max(1, min(COPIAS_MAX, $copias));

    $papel = strtoupper((string) ($entrada['papel'] ?? PAPEL_DEFECTO));
    if (!array_key_exists($papel, PAPELES)) $papel = PAPEL_DEFECTO;

    $duplex = (string) ($entrada['duplex'] ?? 'no');
    if (!DUPLEX_DISPONIBLE || !in_array($duplex, ['no', 'largo', 'corto'], true)) $duplex = 'no';

    // Un checkbox sin marcar no llega; el formulario envía "0" o "1".
    $intercalar = !isset($entrada['intercalar']) || (string) $entrada['intercalar'] !== '0';

    return ['copias' => $copias, 'intercalar' => $intercalar, 'papel' => $papel, 'duplex' => $duplex];
}

/** Texto corto de las opciones para mensajes y registro. */
function describir_opciones(array $op): string
{
    $partes = [];
    $partes[] = $op['copias'] === 1 ? '1 copia' : $op['copias'] . ' copias' . ($op['intercalar'] ? ' intercaladas' : '');
    $partes[] = explode(' (', PAPELES[$op['papel']] ?? $op['papel'])[0];
    if ($op['duplex'] === 'largo') $partes[] = 'doble cara (borde largo)';
    if ($op['duplex'] === 'corto') $partes[] = 'doble cara (borde corto)';
    return implode(', ', $partes);
}

/** Lenguaje PJL según la extensión del archivo listo ('' = que la impresora lo detecte). */
function lenguaje_pjl(string $ext): string
{
    switch (strtolower($ext)) {
        case 'pdf': return 'PDF';
        case 'ps':  return 'POSTSCRIPT';
        default:    return '';   // txt, pcl, prn: detección automática
    }
}

/** Cabecera PJL con las opciones del trabajo. */
function cabecera_pjl(array $op, string $lenguaje, string $nombre): string
{
    // El nombre del trabajo va entre comillas: sin comillas ni caracteres de control.
    $nombreJob = substr(preg_replace('/[^\x20-\x7E]/', '_', str_replace('"', "'", $nombre)) ?? 'documento', 0, 80);
    $l = [];
    $l[] = PJL_UEL . '@PJL';
    $l[] = '@PJL JOB NAME="' . $nombreJob . '"';
    if ($op['intercalar']) {
        // QTY = copias del trabajo completo (intercaladas: 1,2,3 1,2,3).
        $l[] = '@PJL SET QTY=' . $op['copias'];
        $l[] = '@PJL SET COPIES=1';
    } else {
        // COPIES = copias de cada página (sin intercalar: 1,1 2,2 3,3).
        $l[] = '@PJL SET QTY=1';
        $l[] = '@PJL SET COPIES=' . $op['copias'];
    }
    $l[] = '@PJL SET PAPER=' . $op['papel'];
    if ($op['duplex'] === 'no') {
        $l[] = '@PJL SET DUPLEX=OFF';
    } else {
        $l[] = '@PJL SET DUPLEX=ON';
        $l[] = '@PJL SET BINDING=' . ($op['duplex'] === 'corto' ? 'SHORTEDGE' : 'LONGEDGE');
    }
    if ($lenguaje !== '') $l[] = '@PJL ENTER LANGUAGE=' . $lenguaje;
    return implode("\r\n", $l) . "\r\n";
}

/** Cierre PJL del trabajo. */
function pie_pjl(): string
{
    return PJL_UEL . '@PJL EOJ' . "\r\n" . PJL_UEL;
}

/**
 * Crea en $dirTmp el archivo final del trabajo: cabecera PJL + documento + cierre.
 * Copia por bloques, sin cargar el documento en memoria.
 * @return array{0:bool,1:?string,2:string} [ok, rutaTrabajo, mensajeError]
 */
function envolver_pjl(string $listo, array $op, string $nombre, string $dirTmp): array
{
    $ext = strtolower(pathinfo($listo, PATHINFO_EXTENSION));
    $trabajo = $dirTmp . DIRECTORY_SEPARATOR . 'trabajo.prn';
    $in  = @fopen($listo, 'rb');
    $out = @fopen($trabajo, 'wb');
    if (!$in || !$out) {
        if ($in) fclose($in);
        if ($out) fclose($out);
        return [false, null, 'No se pudo preparar el trabajo de impresión (disco temporal).'];
    }
    fwrite($out, cabecera_pjl($op, lenguaje_pjl($ext), $nombre));
    $copiado = stream_copy_to_stream($in, $out);
    fwrite($out, pie_pjl());
    fclose($in);
    fclose($out);
    if ($copiado === false) return [false, null, 'No se pudo preparar el trabajo de impresión.'];
    return [true, $trabajo, ''];
}

/** Envía un archivo al puerto 9100 (AppSocket/JetDirect) por bloques. */
function enviar_raw(string $host, int $puerto, string $ruta): array
{
    $in = @fopen($ruta, 'rb');
    if (!$in) return [false, 'RAW: no se pudo leer el archivo a imprimir.'];
    $fp = @fsockopen($host, $puerto, $errno, $errstr, 8);
    if (!$fp) { fclose($in); return [false, "RAW $host:$puerto no responde ($errstr)"]; }
    // La impresora deja de leer mientras procesa páginas: margen amplio por bloque.
    stream_set_timeout($fp, 120);
    $n = 0;
    while (!feof($in)) {
        $bloque = fread($in, BLOQUE_ENVIO);
        if ($bloque === false) break;
        $pos = 0; $largo = strlen($bloque);
        while ($pos < $largo) {
            $w = @fwrite($fp, substr($bloque, $pos));
            if ($w === false || $w === 0) {
                $meta = stream_get_meta_data($fp);
                @fclose($fp); fclose($in);
                return [false, ($meta['timed_out'] ?? false) ? 'RAW: la impresora dejó de responder (timeout).' : 'RAW: error al enviar.'];
            }
            $pos += $w;
        }
        $n += $largo;
    }
    fclose($in);
    @fflush($fp); @fclose($fp);
    return [true, 'Enviado por RAW 9100 (' . humano_bytes($n) . ')'];
}

/** Envía un archivo por FTP al servidor de impresión HP (modo binario, por bloques). */
function enviar_ftp(string $ruta): array
{
    if (!function_exists('ftp_connect')) return [false, 'FTP: falta la extensión php_ftp en PHP.'];
    $c = @ftp_connect(IMPRESORA_IP, 21, 12);
    if (!$c) return [false, 'FTP: no conecta a ' . IMPRESORA_IP . ':21'];
    @ftp_set_option($c, FTP_TIMEOUT_SEC, 120);

    // El FTP de impresión de HP suele aceptar cualquier usuario o anónimo.
    $logins = [[FTP_USUARIO !== '' ? FTP_USUARIO : 'anonymous', FTP_CLAVE], ['anonymous', 'anonymous@']];
    $dentro = false;
    foreach ($logins as $u) { if (@ftp_login($c, $u[0], $u[1])) { $dentro = true; break; } }
    if (!$dentro) { @ftp_close($c); return [false, 'FTP: login rechazado por la impresora.']; }

    @ftp_pasv($c, true);
    if (FTP_PUERTO_DIR !== '') @ftp_chdir($c, FTP_PUERTO_DIR);   // si falla, queda en la raíz
    $remoto = 'trabajo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    $in = @fopen($ruta, 'rb');
    if (!$in) { @ftp_close($c); return [false, 'FTP: no se pudo leer el archivo a imprimir.']; }
    $r = @ftp_fput($c, $remoto, $in, FTP_BINARY);                // BINARIO: obligatorio para PDF/PS
    fclose($in);
    @ftp_close($c);
    return $r ? [true, 'Enviado por FTP'] : [false, 'FTP: el envío falló (¿puerto o carpeta correctos?).'];
}

/** Ruta a soffice.exe (LibreOffice) si está instalado, o null. */
function localizar_soffice(): ?string
{
    foreach (['C:\\Program Files\\LibreOffice\\program\\soffice.exe',
              'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
              '/usr/bin/soffice', '/usr/bin/libreoffice'] as $p) {
        if (is_file($p)) return $p;
    }
    return null;
}

/** Convierte un documento de Office a PDF con LibreOffice headless. */
function convertir_pdf(string $entrada, string $dir): ?string
{
    $s = localizar_soffice();
    if ($s === null) return null;
    $perfil = 'file:///' . ltrim(str_replace('\\', '/', rtrim(sys_get_temp_dir(), '\\/')), '/') . '/lo_portal';
    $cmd = escapeshellarg($s) . ' ' . escapeshellarg('-env:UserInstallation=' . $perfil)
         . ' --headless --norestore --convert-to pdf --outdir ' . escapeshellarg($dir)
         . ' ' . escapeshellarg($entrada);
    @exec($cmd . ' 2>&1');
    $pdf = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . pathinfo($entrada, PATHINFO_FILENAME) . '.pdf';
    return is_file($pdf) ? $pdf : null;
}

/**
 * Deja un archivo "listo para imprimir" a partir de la subida.
 * @return array{0:bool,1:?string,2:string} [ok, rutaListo, mensajeError]
 */
function preparar_listo(string $entrada, string $ext, string $dirTmp): array
{
    $ext = strtolower($ext);
    if (in_array($ext, EXT_DIRECTAS, true)) {
        if (in_array($ext, ['txt', 'text', 'log'], true)) {
            // Copia por bloques y agrega un salto de página (form feed) que expulsa la última hoja.
            $listo = $dirTmp . DIRECTORY_SEPARATOR . 'listo.txt';
            if (!@copy($entrada, $listo)) return [false, null, 'No se pudo leer el archivo.'];
            @file_put_contents($listo, "\f", FILE_APPEND);
            return [true, $listo, ''];
        }
        return [true, $entrada, ''];                              // pdf/ps/prn/pcl -> tal cual
    }
    if (in_array($ext, EXT_CONVERTIBLES, true)) {
        $pdf = convertir_pdf($entrada, $dirTmp);
        if ($pdf === null) return [false, null, 'No se pudo convertir a PDF (¿LibreOffice instalado?). Sube el documento como PDF.'];
        return [true, $pdf, ''];
    }
    return [false, null, 'Formato no soportado. Conviértelo a PDF y vuelve a subirlo.'];
}

/**
 * Imprime un archivo ya "listo" con las opciones dadas.
 * @return array{0:bool,1:string,2:string} [ok, mensaje, metodoUsado]
 */
function imprimir_listo(string $ruta, array $op, string $nombre, string $dirTmp): array
{
    [$okEnv, $trabajo, $errEnv] = envolver_pjl($ruta, $op, $nombre, $dirTmp);
    if (!$okEnv) return [false, $errEnv, ''];

    if (IMPRESORA_METODO === 'raw') { [$o, $m] = enviar_raw(IMPRESORA_IP, RAW_PUERTO, $trabajo); return [$o, $m, 'raw']; }
    if (IMPRESORA_METODO === 'ftp') { [$o, $m] = enviar_ftp($trabajo); return [$o, $m, 'ftp']; }

    // auto: RAW y, si falla, FTP.
    [$o, $m] = enviar_raw(IMPRESORA_IP, RAW_PUERTO, $trabajo);
    if ($o) return [true, $m, 'raw'];
    [$o2, $m2] = enviar_ftp($trabajo);
    if ($o2) return [true, $m2 . '. RAW no disponible: ' . $m, 'ftp'];
    return [false, "No se pudo imprimir. RAW: $m. FTP: $m2", ''];
}
