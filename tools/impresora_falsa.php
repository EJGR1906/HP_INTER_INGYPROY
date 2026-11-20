<?php
// =====================================================================
// tools/impresora_falsa.php — Impresora de mentira para pruebas locales.
// Escucha en 127.0.0.1:9100 (como JetDirect) y guarda cada trabajo
// recibido en un archivo trabajoN.prn para revisar la cabecera PJL y el
// documento. Solo por consola:
//   php tools/impresora_falsa.php [carpeta_salida] [puerto]
// y en config.php (copia local, sin commitear): IMPRESORA_IP = '127.0.0.1',
// IMPRESORA_METODO = 'raw'.
// =====================================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$dir    = $argv[1] ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'trabajos_impresora');
$puerto = (int) ($argv[2] ?? 9100);
if (!is_dir($dir)) mkdir($dir, 0775, true);

$srv = @stream_socket_server("tcp://127.0.0.1:$puerto", $errno, $errstr);
if (!$srv) { fwrite(STDERR, "No se pudo escuchar en el puerto $puerto: $errstr\n"); exit(1); }
echo "Impresora falsa en 127.0.0.1:$puerto. Trabajos en $dir (Ctrl+C para salir)\n";

for ($n = 1; ; $n++) {
    $c = @stream_socket_accept($srv, -1);
    if (!$c) continue;
    $ruta = $dir . DIRECTORY_SEPARATOR . "trabajo$n.prn";
    $out = fopen($ruta, 'wb');
    $bytes = stream_copy_to_stream($c, $out);
    fclose($out); fclose($c);
    $cab = file_get_contents($ruta, false, null, 0, 400);
    preg_match_all('/@PJL [^\r\n]*/', (string) $cab, $m);
    echo date('H:i:s') . " trabajo$n: $bytes bytes\n  " . implode("\n  ", $m[0]) . "\n";
}
