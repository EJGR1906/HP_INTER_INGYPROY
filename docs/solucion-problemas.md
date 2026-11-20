# Solución de problemas

Primero, siempre: abrir `C:\wamp64\logs\apache_error.log` y buscar la última
línea con la hora del error. Casi siempre dice la causa exacta.

## 403 Forbidden en `/HP/`
| Línea en `apache_error.log` | Causa | Arreglo |
|---|---|---|
| Cualquier 403 sobre `C:/wamp64/www/HP` (la línea exacta no está documentada; depende de MultiViews y de la versión) | `HP` en `www` es un **acceso directo (.lnk)**, no una carpeta. Pasó al mover el proyecto a `C:\Proyectos\HP` (20-nov-2026). | Borrar el acceso directo y crear una unión: `mklink /J C:\wamp64\www\HP C:\Proyectos\HP` ([instalacion.md](instalacion.md), paso 1). |
| `Options ExecCGI is off in this directory` | Con mod_fcgid, la carpeta no tiene `ExecCGI` | El `.htaccess` del repo ya lo pide (`Options -Indexes +ExecCGI`); si `AllowOverride` no lo permite, agregarlo en el `<Directory>` de `httpd.conf`. |
| `AH01276: Cannot serve directory ... No matching DirectoryIndex` | Apache no sabe que `index.php` es la portada | El `.htaccess` del repo trae `DirectoryIndex index.php`. |
| `Permission denied` / `(13)` | El servicio de Apache no puede leer `C:\Proyectos\HP` | Dar permiso de lectura a la cuenta del servicio (Sistema local) en la carpeta. |

Cómo saber si `HP` es acceso directo: en el Explorador, la columna *Tipo* dice
**Acceso directo** (mal) en vez de **Carpeta de archivos** (bien). En `cmd`,
`dir C:\wamp64\www` muestra `<JUNCTION>` para las uniones.

## 500 Internal Server Error
- `Invalid command 'php_value'` en el log: alguien puso `php_value` en `.htaccess`.
  Con mod_fcgid no se puede; usar `.user.ini`.
- `mod_fcgid: HTTP request length ... exceeds MaxRequestLen`: subir
  `FcgidMaxRequestLen` en `httpd.conf` ([instalacion.md](instalacion.md), paso 3).
- `Invalid command 'Options'` o `not allowed here`: `AllowOverride` de la carpeta
  no permite `Options`; poner `AllowOverride All` en su `<Directory>`.

## No sube archivos grandes
| Síntoma | Causa | Arreglo |
|---|---|---|
| «El archivo supera el límite del servidor (X MB)» con X menor a 200 | `.user.ini` no se aplicó todavía | Esperar 5 minutos o reiniciar Apache; confirmar con `phpinfo()` |
| Error 500 apenas empieza a subir | `FcgidMaxRequestLen` en 128 KB por defecto | Ver instalación, paso 3 |
| Error 413 | `LimitRequestBody` de Apache | Subirlo o quitarlo en `httpd.conf` |
| Se corta a los 40 o 60 s | `FcgidIOTimeout` / `max_input_time` | 600 en ambos |

## La impresora no imprime
- «RAW 10.2.200.74:9100 no responde»: desde el servidor, `Test-NetConnection 10.2.200.74 -Port 9100`
  en PowerShell. Si falla, es red o firewall, no el portal.
- «FTP: login rechazado» o «el envío falló»: revisar `FTP_PUERTO_DIR` (`PORT` o `PORT1`).
- Sale texto raro (`%PDF-1.7...`) en vez del documento: la impresora no tiene
  intérprete PDF o le falta memoria. Probar con otro método o convertir a PostScript.
- Ignora las copias o el papel: el firmware puede no aplicar `@PJL SET QTY`/`PAPER`
  a PDF directo; probar con `intercalar` desactivado (usa `COPIES`) o actualizar el firmware.

## Vista previa o selección de páginas no disponible
- «Este PDF está protegido»: el PDF tiene contraseña o restricciones; se imprime
  completo. Quitar la protección (imprimir a PDF desde el visor) para poder elegir páginas.
- Office sin vista previa: el servidor no tiene LibreOffice. Guardar como PDF.
- La página queda en blanco: el navegador es muy antiguo (pdf.js 4 necesita
  Chrome, Edge o Firefox de 2023 en adelante).
