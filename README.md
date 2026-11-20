# Portal de impresión en red (HP LaserJet)

Página web interna para que cualquier persona de la oficina imprima en la
impresora HP LaserJet 600 M601 **sin instalar controladores**: abre la página,
elige el archivo, ajusta las opciones y pulsa **Imprimir**.

- PHP 8 sin framework ni Composer, servido por Apache con mod_fcgid (WAMP64 en Windows).
- Envío directo a la impresora por **RAW 9100** (JetDirect) o **FTP** (HP FTP Print Server).
- Mismo sistema de diseño que el [Dashboard FTTM](https://github.com/EJGR1906/Dashboard-FTTM)
  (estilo apple.com, tema claro y oscuro).

## Qué se puede hacer
| Función | Cómo |
|---|---|
| Archivos grandes | Hasta **200 MB** (`MAX_MB`, `.user.ini` y `FcgidMaxRequestLen`). Barra de progreso al subir. |
| Vista previa | Miniaturas de cada página (pdf.js, incluido en `assets/vendor/`). |
| Elegir páginas | Todas, rango (`1-3, 5, 8-`), impares, pares, o pulsando las miniaturas. |
| Copias | 1 a 99, intercaladas o no. |
| Tamaño de papel | Carta, Oficio, A4, Ejecutivo (lista en `config.php`). |
| Orientación y escala | Automática, vertical u horizontal; ajustar al papel o tamaño real. |
| Doble cara | No, borde largo o borde corto. |
| Recientes | Últimas impresiones (`HISTORIAL_MAX`) con botón **Reimprimir**. |
| Formatos | PDF, JPG, PNG, TXT, PS, PCL/PRN. Office (DOCX, XLSX, PPTX...) si el servidor tiene LibreOffice. |

## Instalación rápida
1. Clonar o copiar este repo en la carpeta del proyecto (hoy `C:\Proyectos\HP`).
2. Publicarlo en `C:\wamp64\www\HP` con una **unión de directorio** (no un acceso directo):
   ```bat
   mklink /J C:\wamp64\www\HP C:\Proyectos\HP
   ```
3. Revisar `config.php` (IP de la impresora, método, papel por defecto).
4. Ajustar los límites de mod_fcgid en `httpd.conf` (ver [docs/instalacion.md](docs/instalacion.md)) y reiniciar Apache.
5. Abrir `http://<servidor>/HP/`.

Si la página da **403 Forbidden** o no sube archivos grandes, ver
[docs/solucion-problemas.md](docs/solucion-problemas.md).

## Documentación
| Documento | Contenido |
|---|---|
| [PRD.md](PRD.md) | Qué hace el portal y para quién (requisitos) |
| [Architecture.md](Architecture.md) | Flujo, archivos, funciones y seguridad |
| [Design-System.md](Design-System.md) | Tokens, componentes y reglas visuales |
| [PLAN.md](PLAN.md) | Tareas hechas y pendientes |
| [docs/instalacion.md](docs/instalacion.md) | Instalación en WAMP64 con mod_fcgid |
| [docs/solucion-problemas.md](docs/solucion-problemas.md) | 403, 500, archivos grandes, impresora sin respuesta |
| [CLAUDE.md](CLAUDE.md) / [AGENTS.md](AGENTS.md) | Reglas para agentes de IA |

## Privacidad
Con `GUARDAR_COPIA = true` se guarda una copia de cada documento impreso en
`archivo/AAAA-MM/` y una línea en `archivo/_registro.jsonl`. La página lo avisa.
La sección «Recientes» muestra a **todos** los que abren el portal los nombres de esos
documentos (no su contenido); `HISTORIAL_MAX = 0` la oculta. La carpeta está bloqueada por
HTTP (`archivo/.htaccess`) y **no se versiona**.

## Licencias de terceros
- [pdf.js](https://github.com/mozilla/pdf.js) 4.10.38, Apache 2.0 (`assets/vendor/LICENSE-pdfjs.txt`).
- [pdf-lib](https://github.com/Hopding/pdf-lib) 1.17.1, MIT (`assets/vendor/LICENSE-pdf-lib.txt`).
