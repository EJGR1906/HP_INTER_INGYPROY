# Instalación en WAMP64 (Apache + PHP con mod_fcgid)

Servidor actual (según la página de error de Apache): Apache 2.4.65 (Win64),
PHP 8.3.28, mod_fcgid 2.3.10. Proyecto en `C:\Proyectos\HP`, publicado en
`C:\wamp64\www\HP`. La IP del servidor web en la LAN es `10.14.9.229`.

## 1. Publicar la carpeta en `www`
Apache **no sigue accesos directos de Windows** (`.lnk`): un acceso directo
llamado `HP` en `www` produce **403 Forbidden**. Hay que usar una unión de
directorio (*junction*), como ya están `DCIM` y `FTTM`:

```bat
:: 1) Borrar el acceso directo HP de C:\wamp64\www (es el archivo HP.lnk).
del "C:\wamp64\www\HP.lnk"

:: 2) Crear la unión (no requiere permisos de administrador).
mklink /J C:\wamp64\www\HP C:\Proyectos\HP
```

Alternativa sin unión: un `Alias` de WAMP (menú WAMP > Apache > Directorios Alias)
o a mano en `httpd.conf`:

```apache
Alias /HP "C:/Proyectos/HP"
<Directory "C:/Proyectos/HP">
    Options -Indexes +FollowSymLinks +ExecCGI
    AllowOverride All
    Require all granted
</Directory>
```

## 2. Permisos de la carpeta
El servicio de Apache (`wampapache64`) corre normalmente como *Sistema local*.
Debe poder **leer** `C:\Proyectos\HP` y **escribir** en `C:\Proyectos\HP\archivo`
(copias e historial) y en la carpeta temporal de PHP (`upload_tmp_dir` o la de Windows).

## 3. Límites para archivos grandes (200 MB)
Hay **tres** límites y deben estar de acuerdo:

| Dónde | Directiva | Valor | Lo cambia |
|---|---|---|---|
| `config.php` | `MAX_MB` | `200` | este repo |
| `.user.ini` | `upload_max_filesize`, `post_max_size`, `max_execution_time`, `max_input_time`, `memory_limit` | `200M`, `210M`, `600`, `600`, `256M` | este repo |
| `httpd.conf` (o el `.conf` de fcgid que incluya WAMP) | `FcgidMaxRequestLen` | `230686720` (220 MB) | el dueño, en el servidor |

Además, en el mismo bloque de mod_fcgid, para que no corte trabajos lentos:

```apache
FcgidMaxRequestLen 230686720
FcgidIOTimeout 600
FcgidBusyTimeout 600
```

- `.user.ini` funciona porque PHP corre como FastCGI. **No** usar `php_value` en
  `.htaccess`: con mod_fcgid da error 500.
- PHP relee `.user.ini` cada 5 minutos; para aplicarlo al instante, reiniciar Apache.
- Si `FcgidMaxRequestLen` no está, el valor por defecto es 128 KB y cualquier
  subida mayor falla con error 500.
- Para ver qué valores usa PHP de verdad: crear temporalmente un `info.php` con
  `<?php phpinfo();` en la carpeta, abrirlo, revisar y **borrarlo**.

## 4. Extensiones de PHP
- `php_ftp` (solo si se usa el método FTP). WAMP: menú PHP > Extensiones.
- `php_sockets` no es necesaria (RAW usa `fsockopen`).

## 5. LibreOffice (opcional)
Para imprimir DOCX, XLSX, PPTX, ODT, RTF... con vista previa, instalar LibreOffice
en la ruta por defecto (`C:\Program Files\LibreOffice`). Sin él, esos formatos se
rechazan con un mensaje claro y se pide subirlos como PDF.

## 6. Configurar `config.php`
| Constante | Para qué |
|---|---|
| `IMPRESORA_IP` | IP de la impresora (hoy `10.2.200.74`) |
| `IMPRESORA_METODO` | `auto` (RAW y si falla FTP), `raw` o `ftp` |
| `RAW_PUERTO` | Puerto JetDirect (9100) |
| `FTP_USUARIO`, `FTP_CLAVE`, `FTP_PUERTO_DIR` | Acceso al FTP de impresión (`PORT` o `PORT1`) |
| `GUARDAR_COPIA`, `HISTORIAL_MAX` | Copias de lo impreso en `archivo/` |
| `MAX_MB` | Tamaño máximo por archivo |
| `EXT_DIRECTAS`, `EXT_CONVERTIBLES` | Formatos aceptados |
| `PAPELES`, `PAPEL_DEFECTO` | Tamaños de papel que se ofrecen (clave PJL => nombre) |
| `COPIAS_MAX` | Máximo de copias |
| `DUPLEX_DISPONIBLE` | Muestra la opción de doble cara |
| `CLAVE_ACCESO` | Clave simple para entrar (vacía = sin clave) |

## 7. Comprobar
1. Abrir `http://10.14.9.229/HP/`: debe verse la página, no 403.
2. Imprimir un PDF de varias páginas eligiendo un rango y 2 copias.
3. Si algo falla: `C:\wamp64\logs\apache_error.log` y [solucion-problemas.md](solucion-problemas.md).
