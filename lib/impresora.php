<?php
// =====================================================================
// lib/impresora.php — Envío a la impresora sin controladores.
// Métodos: RAW 9100 (JetDirect) y FTP (HP FTP Print Server).
// Los archivos deben ir "listos para imprimir" (PDF/PS/PCL/TXT);
// los documentos de Office se convierten a PDF con LibreOffice (opcional).
// =====================================================================

/** Envía bytes crudos al puerto 9100 (AppSocket/JetDirect). */
function enviar_raw(string $host, int $puerto, string $datos): array
{
    $fp = @fsockopen($host, $puerto, $errno, $errstr, 8);
    if (!$fp) return [false, "RAW $host:$puerto no responde ($errstr)"];
    stream_set_timeout($fp, 15);
    $n = 0; $t = strlen($datos);
    while ($n < $t) {
        $w = @fwrite($fp, substr($datos, $n, 8192));
        if ($w === false || $w === 0) {
            $meta = stream_get_meta_data($fp); @fclose($fp);
            return [false, ($meta['timed_out'] ?? false) ? 'RAW: timeout al enviar' : 'RAW: error al enviar'];
        }
        $n += $w;
    }
    @fflush($fp); @fclose($fp);
    return [true, "Enviado por RAW 9100 ($n bytes)"];
}

/** Envía un archivo por FTP al servidor de impresión HP (modo binario). */
function enviar_ftp(string $ruta): array
{
    if (!function_exists('ftp_connect')) return [false, 'FTP: falta la extensión php_ftp en PHP.'];
    $c = @ftp_connect(IMPRESORA_IP, 21, 12);
    if (!$c) return [false, 'FTP: no conecta a ' . IMPRESORA_IP . ':21'];

    // El FTP de impresión de HP suele aceptar cualquier usuario o anónimo.
    $logins = [[FTP_USUARIO !== '' ? FTP_USUARIO : 'anonymous', FTP_CLAVE], ['anonymous', 'anonymous@']];
    $dentro = false;
    foreach ($logins as $u) { if (@ftp_login($c, $u[0], $u[1])) { $dentro = true; break; } }
    if (!$dentro) { @ftp_close($c); return [false, 'FTP: login rechazado por la impresora.']; }

    @ftp_pasv($c, true);
    if (FTP_PUERTO_DIR !== '') @ftp_chdir($c, FTP_PUERTO_DIR);   // si falla, queda en la raíz
    $remoto = 'trabajo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    $r = @ftp_put($c, $remoto, $ruta, FTP_BINARY);               // BINARIO: obligatorio para PDF/PS
    @ftp_close($c);
    return $r ? [true, 'Enviado por FTP'] : [false, 'FTP: ftp_put falló (¿puerto/dir correcto?).'];
}

/** Ruta a soffice.exe (LibreOffice) si está instalado, o null. */
function localizar_soffice(): ?string
{
    foreach (['C:\\Program Files\\LibreOffice\\program\\soffice.exe',
              'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe'] as $p) {
        if (is_file($p)) return $p;
    }
    return null;
}

/** Convierte un documento de Office a PDF con LibreOffice headless. */
function convertir_pdf(string $entrada, string $dir): ?string
{
    $s = localizar_soffice();
    if ($s === null) return null;
    $perfil = 'file:///' . str_replace('\\', '/', rtrim(sys_get_temp_dir(), '\\/')) . '/lo_portal';
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
            $listo = $dirTmp . DIRECTORY_SEPARATOR . 'listo.txt';
            $bytes = @file_get_contents($entrada);
            if ($bytes === false) return [false, null, 'No se pudo leer el archivo.'];
            @file_put_contents($listo, $bytes . "\f");           // form feed: expulsa la última hoja
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
 * Imprime un archivo ya "listo".
 * @return array{0:bool,1:string,2:string} [ok, mensaje, metodoUsado]
 */
function imprimir_listo(string $ruta): array
{
    $datos = @file_get_contents($ruta);
    if ($datos === false) return [false, 'No se pudo leer el archivo a imprimir.', ''];

    if (IMPRESORA_METODO === 'raw') { [$o, $m] = enviar_raw(IMPRESORA_IP, RAW_PUERTO, $datos); return [$o, $m, 'raw']; }
    if (IMPRESORA_METODO === 'ftp') { [$o, $m] = enviar_ftp($ruta); return [$o, $m, 'ftp']; }

    // auto: RAW y, si falla, FTP.
    [$o, $m] = enviar_raw(IMPRESORA_IP, RAW_PUERTO, $datos);
    if ($o) return [true, $m, 'raw'];
    [$o2, $m2] = enviar_ftp($ruta);
    if ($o2) return [true, $m2 . ' — RAW no disponible (' . $m . ')', 'ftp'];
    return [false, "No se pudo imprimir. RAW: $m | FTP: $m2", ''];
}
