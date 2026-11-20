<?php
// =====================================================================
// config.php — Ajustes del Portal de Impresión en red.
// ---------------------------------------------------------------------
// Edita SOLO este archivo para adaptar el portal a tu oficina.
// No hace falta tocar el resto del código.
// =====================================================================

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

// Cuántas entradas del historial mostrar en la página.
const HISTORIAL_MAX = 25;

// --- LÍMITES / SEGURIDAD ---------------------------------------------
// Tamaño máximo por archivo (MB).
const MAX_MB = 25;

// Extensiones que se envían tal cual (ya son "listas para imprimir").
const EXT_DIRECTAS = ['pdf', 'ps', 'prn', 'pcl', 'txt', 'text', 'log'];

// Extensiones que se intentan convertir a PDF antes de imprimir
// (requiere LibreOffice instalado en el servidor; es opcional).
const EXT_CONVERTIBLES = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf'];

// Contraseña simple de acceso al portal (opcional). Deja '' para NO pedir
// contraseña (herramienta interna de LAN). Si pones algo, se pedirá al entrar.
const CLAVE_ACCESO = '';
