<?php
// =====================================================================
// config.php — Ajustes del Portal de Impresión en red.
// ---------------------------------------------------------------------
// Edita SOLO este archivo para adaptar el portal a tu oficina.
// No hace falta tocar el resto del código.
// =====================================================================

// --- ZONA HORARIA ----------------------------------------------------
// Hora con la que se anotan y muestran las impresiones. Sin esto PHP usa
// UTC y el historial sale 4 horas adelantado (el registro de nov-2026 lo
// muestra: archivo creado a las 16:10, anotado 20:10).
const ZONA_HORARIA = 'America/Caracas';

// --- IMPRESORA -------------------------------------------------------
// IP de la impresora (la misma con la que probaste el FTP).
const IMPRESORA_IP = '10.2.200.74';

// Método de envío:
//   'auto' -> intenta RAW 9100 y, si falla, usa FTP (recomendado).
//   'raw'  -> solo puerto 9100 (JetDirect / AppSocket). Suele ser el más limpio.
//   'ftp'  -> solo FTP (es el que ya verificaste que funciona).
const IMPRESORA_METODO = 'auto';

// Puerto RAW 9100 (JetDirect). Por defecto 9100.
const RAW_PUERTO = 9100;

// --- FTP (impresora HP LaserJet 600 M601, FTP Print Server) ----------
// El servidor FTP de impresión de HP suele aceptar cualquier usuario/clave
// (o anónimo). Déjalos vacíos si con Enter te dejó entrar.
const FTP_USUARIO = '';
const FTP_CLAVE   = '';
// Carpeta/puerto dentro del FTP donde se deja el archivo. En tu salida
// aparecía el directorio "PORT". Si tu impresora usa "PORT1", cámbialo.
const FTP_PUERTO_DIR = 'PORT';

// --- ARCHIVO DE DOCUMENTOS (transparente) ----------------------------
// Si es true, se guarda una copia de cada documento impreso en /archivo,
// se muestra un AVISO visible en la página y se lleva un historial.
// La carpeta /archivo está bloqueada para descargas por HTTP (.htaccess).
// Pon false si NO quieres guardar copias.
const GUARDAR_COPIA = true;

// Cuántas impresiones recientes mostrar en la página (sección «Recientes»,
// con botón para reimprimir). Ojo: todos los que abren el portal ven los
// nombres de esos documentos. Pon 0 para no mostrar la lista.
const HISTORIAL_MAX = 25;

// --- LÍMITES / SEGURIDAD ---------------------------------------------
// Tamaño máximo por archivo (MB). Debe caber en upload_max_filesize y
// post_max_size de .user.ini y en FcgidMaxRequestLen de httpd.conf.
const MAX_MB = 200;

// Extensiones que se envían tal cual (ya son "listas para imprimir").
const EXT_DIRECTAS = ['pdf', 'ps', 'prn', 'pcl', 'txt', 'text', 'log'];

// Extensiones que se intentan convertir a PDF antes de imprimir
// (requiere LibreOffice instalado en el servidor; es opcional).
const EXT_CONVERTIBLES = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf'];

// --- OPCIONES DE IMPRESIÓN -------------------------------------------
// Tamaños de papel que se ofrecen (clave PJL => nombre visible). La clave
// es la que entiende la impresora en "@PJL SET PAPER=...".
const PAPELES = [
    'LETTER'    => 'Carta (216 x 279 mm)',
    'LEGAL'     => 'Oficio (216 x 356 mm)',
    'A4'        => 'A4 (210 x 297 mm)',
    'EXECUTIVE' => 'Ejecutivo (184 x 267 mm)',
];
// Papel elegido por defecto (una clave de PAPELES).
const PAPEL_DEFECTO = 'LETTER';

// Máximo de copias por trabajo.
const COPIAS_MAX = 99;

// ¿La impresora tiene unidad de doble cara (dúplex)? Si es false, la
// opción no se muestra.
const DUPLEX_DISPONIBLE = true;

// Contraseña simple de acceso al portal (opcional). Deja '' para NO pedir
// contraseña (herramienta interna de LAN). Si pones algo, se pedirá al entrar.
const CLAVE_ACCESO = '';
