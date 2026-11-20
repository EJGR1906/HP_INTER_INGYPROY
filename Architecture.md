# Arquitectura

## Flujo de una impresión
```
Navegador (assets/js/imprimir.js)
  1. Elige archivo ─┬─ PDF ───────────────► bytes del PDF
                    ├─ JPG/PNG ──(pdf-lib)─► PDF de 1 página
                    ├─ Office ──POST accion=convertir──► LibreOffice ──► PDF
                    └─ TXT/PS/PCL/PRN ─────► se envía tal cual (sin vista previa)
  2. Vista previa con pdf.js (miniaturas perezosas)
  3. Elige páginas, orientación y escala
  4. pdf-lib arma un PDF nuevo: solo las páginas elegidas, ajustadas al papel
  5. POST accion=imprimir (XHR con progreso): PDF + copias, intercalar, papel, duplex
        │
index.php
  6. Valida CSRF, tamaño y opciones (opciones_impresion)
  7. preparar_listo(): deja el archivo listo (TXT + salto de página, Office -> PDF)
  8. imprimir_listo(): envolver_pjl() crea trabajo.prn = cabecera PJL + documento + EOJ
  9. enviar_raw() a 10.2.200.74:9100, o enviar_ftp() (método 'auto': RAW y si falla FTP)
 10. borrar_dir(): borra la carpeta temporal (también al cortarse la petición,
     con register_shutdown_function en dir_trabajo). No se guarda ninguna copia.
 11. Responde JSON {ok, msg} (o flash + redirect si no hay JS)
```

## Archivos
| Archivo | Qué hace |
|---|---|
| `index.php` | Entrada única: acceso con clave opcional, acciones POST (`imprimir`, `convertir`) y la vista |
| `config.php` | Todos los ajustes (impresora, método, límites, papeles, copias, dúplex, clave) |
| `includes/impresora.php` | `opciones_impresion`, `describir_opciones`, `cabecera_pjl`, `envolver_pjl`, `enviar_raw`, `enviar_ftp`, `convertir_pdf`, `preparar_listo`, `imprimir_listo` |
| `includes/util.php` | Sesión, CSRF, flash, `responder` (JSON o PRG), `limite_subida_bytes`, `mensaje_error_subida`, `dir_trabajo` (carpeta temporal que se borra sola), `borrar_dir` |
| `includes/cabecera.php` | `pagina_head()` y `cabecera()` (header de dos filas), `script_js()` |
| `includes/iconos.php` | Catálogo de íconos SVG (`icono()`) |
| `assets/css/app.css` | Sistema de diseño (copiado del Dashboard FTTM) + piezas del portal |
| `assets/js/comun.js` | Tema claro/oscuro, caja de subir archivo, `Iconos()`, `esc()`, `tamanoLegible()` |
| `assets/js/imprimir.js` | Vista previa, rangos, armado del PDF, subida con progreso |
| `assets/vendor/` | pdf.js 4.10.38 (`pdf.min.js`, `pdf.worker.min.js`) y pdf-lib 1.17.1 (`pdf-lib.min.js`) |
| `.htaccess` | `DirectoryIndex`, `Options -Indexes +ExecCGI`, bloquea `.md`, `.ini`, `.jsonl` y ocultos |
| `.user.ini` | Límites de PHP para subidas de 200 MB |
| `archivo/` | Ya no se usa. Solo queda `archivo/.htaccess` (`Require all denied`) para proteger las copias viejas que pueda tener el servidor hasta que se borren |

Las librerías de pdf.js se publican con extensión `.js` (no `.mjs`) para que Apache
las sirva con tipo JavaScript sin tocar `mime.types`.

## PJL que se envía
```
ESC%-12345X@PJL
@PJL JOB NAME="documento.pdf"
@PJL SET QTY=2          (intercaladas)  |  @PJL SET COPIES=2 (sin intercalar)
@PJL SET PAPER=A4
@PJL SET DUPLEX=ON
@PJL SET BINDING=LONGEDGE
@PJL ENTER LANGUAGE=PDF (PDF) | POSTSCRIPT (PS) | (nada: TXT, PCL, PRN)
...documento...
ESC%-12345X@PJL EOJ
ESC%-12345X
```

## Orientación y escala (pdf-lib)
La cuenta vive en `hojaPara()` de `assets/js/imprimir.js` y la usan tanto la vista previa
(cada miniatura es la hoja que saldrá) como `construirPdf()`, así lo que se ve es lo que se imprime.
Cada página elegida se incrusta (`embedPages`) y se dibuja centrada en una hoja del
papel elegido. *Automática* pone la hoja horizontal si la página se ve más ancha
que alta. Se respeta el giro `/Rotate` de la página original.

## Seguridad
- CSRF en todo POST (`csrf_token`, `csrf_ok`).
- Nombres de archivo saneados (`limpiar_nombre`); el archivo se guarda con nombre propio en una carpeta temporal única.
- Opciones por listas blancas (`PAPELES`, `COPIAS_MAX`, dúplex `no|largo|corto`).
- Privacidad: no se guarda copia ni registro de lo impreso; la carpeta temporal se borra al terminar.
- `includes/`, `tools/` y `archivo/` con `Require all denied`; documentación e `.ini` bloqueados por `.htaccess`.
- Todo lo que se imprime en HTML pasa por `e()` (PHP) o `esc()` (JS).

## Pruebas locales
Ver la sección *Comandos* de [CLAUDE.md](CLAUDE.md): servidor `php -S` e impresora falsa en el puerto 9100.
