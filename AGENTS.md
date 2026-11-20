# AGENTS.md — Guía obligatoria para agentes de IA

Esta guía aplica a Codex, Jules, Copilot y cualquier otro agente que trabaje en este repo.
Es copia de las reglas de `CLAUDE.md`: **`CLAUDE.md` es la fuente de verdad**. Si cambias
una norma, cámbiala en los dos archivos en el mismo commit; si hay diferencias, este
archivo se sincroniza con `CLAUDE.md`.

## Qué es este proyecto
Portal web interno (PHP 8 sin framework ni Composer) para imprimir en la HP LaserJet
600 M601 de la oficina (`10.2.200.74`) sin instalar controladores: envía el documento
por RAW 9100 o FTP con un envoltorio PJL (copias, papel, doble cara). La selección de
páginas, la orientación y el ajuste al papel se hacen en el navegador con pdf.js y
pdf-lib (en `assets/vendor/`). **Está en producción** en un servidor Windows con WAMP64
(Apache 2.4.65 + PHP 8.3 con mod_fcgid), proyecto en `C:\Proyectos\HP` publicado en
`C:\wamp64\www\HP`. Dueño: Eduardo González (GitHub `EJGR1906`).
Lee antes: [README.md](README.md), [PRD.md](PRD.md), [Architecture.md](Architecture.md),
[Design-System.md](Design-System.md) y [PLAN.md](PLAN.md).

## Reglas de comportamiento
1. **Español** en respuestas, código nuevo (nombres y comentarios), commits y documentos.
2. **Cero suposiciones.** Si un dato no está en el código, en Git o en la documentación,
   pregunta o escribe «no documentado». Nunca inventes rutas del servidor, versiones,
   IPs ni credenciales.
3. **Investiga antes de tocar.** Lee los archivos afectados y busca funciones existentes
   para reutilizar (`grep`), antes de escribir una línea.
4. **Plan primero.** Si la tarea tiene varios pasos, presenta un plan con preguntas
   numeradas y espera la aprobación antes de modificar archivos.
5. **Honestidad radical.** Si el enfoque pedido es inseguro, rompe patrones del proyecto o
   está equivocado, dilo claramente, explica por qué y propone la alternativa.
6. **El agente ejecuta.** Cambios, pruebas y configuraciones los hace el agente; al dueño
   solo se le pide lo que únicamente él puede hacer (acceso al servidor, `httpd.conf`,
   reiniciar Apache, probar con la impresora real).
7. **Nunca usar el separador « · »** (punto medio) en nada que vea el usuario: títulos,
   textos de la interfaz, mensajes, documentos. Usa comas, dos puntos o guion largo.

## Datos y seguridad (estricto)
- **Nunca versionar `archivo/`**: guarda copias de documentos personales impresos
  (informes médicos, cédulas, RIF...) y `_registro.jsonl` con IPs. Solo se versiona
  `archivo/.htaccess`. No debilites el `.gitignore`; nunca leas ni muestres esos archivos.
- Nunca versionar credenciales (`CLAVE_ACCESO`, `FTP_CLAVE` reales) ni un `config.php`
  apuntando a `127.0.0.1` de pruebas.
- Todo POST exige **CSRF** (`csrf_ok()`); toda salida HTML pasa por `e()` (PHP) o `esc()` (JS).
- Las opciones que llegan del navegador se validan con listas blancas en
  `opciones_impresion()`; nunca se concatenan en el PJL sin validar.
- Nombres de archivo con `limpiar_nombre()`; rutas guardadas con `ruta_guardado_segura()`.
- `includes/`, `tools/` y `archivo/` llevan `Require all denied`. Las herramientas de
  `tools/` solo corren por consola (`PHP_SAPI === 'cli'`). Mantén ambas protecciones.
- Con mod_fcgid **no** se usa `php_value`/`php_flag` en `.htaccess` (da error 500):
  los límites de PHP van en `.user.ini`.
- Los archivos grandes se leen y envían **por bloques** (`stream_copy_to_stream`,
  `fread`, `ftp_fput`). No uses `file_get_contents` sobre el documento.
- No tocar producción directamente: los cambios llegan por PR, merge a `main` y copia o
  `git pull` en el servidor, hecho por Eduardo.

## Reglas de impresión (núcleo del sistema)
- El navegador sube **un PDF ya recortado** a las páginas elegidas; el servidor no
  vuelve a seleccionar páginas. Copias, intercalar, papel y dúplex van en PJL
  (`cabecera_pjl()`), que funciona igual por RAW y por FTP.
- `PAPELES` usa como clave el nombre PJL (`LETTER`, `LEGAL`, `A4`, `EXECUTIVE`) y el
  mismo tamaño en puntos debe existir en `PAPEL_PT` de `assets/js/imprimir.js`.
  Si agregas un papel, agrégalo en los dos.
- `MAX_MB`, `.user.ini` (`upload_max_filesize`, `post_max_size`) y `FcgidMaxRequestLen`
  deben ser coherentes; si cambias uno, documenta los otros en `docs/instalacion.md`.
- Las librerías de `assets/vendor/` se publican con extensión `.js` (no `.mjs`) para que
  Apache las sirva como JavaScript. Para actualizarlas: `npm pack` de la versión exacta,
  copiar los `.min` y su licencia, y cambiar la versión en README y Architecture.
- La interfaz debe seguir funcionando sin JavaScript (imprime el archivo completo).

## Flujo de trabajo con Git
- Ramas y Pull Requests para todo; `main` se actualiza solo por merge de PR.
- Commits en español, en imperativo o 3.ª persona («Agrega…», «Corrige…»).
- Antes de trabajar: `git status` y `git fetch` para no pisar cambios.
- Al terminar una tarea, actualizar [PLAN.md](PLAN.md) (`T-xx` técnicos, `H-xx` del dueño).

## Comandos
| Para | Comando |
|---|---|
| Revisar sintaxis | `find . -name "*.php" -not -path "./.git/*" -exec php -l {} \;` |
| Revisar JS | `node --check assets/js/comun.js && node --check assets/js/imprimir.js` |
| Impresora falsa | `php tools/impresora_falsa.php /tmp/trabajos` (escucha en 127.0.0.1:9100) |
| Servidor local | copiar el repo a una carpeta de prueba, poner `IMPRESORA_IP = '127.0.0.1'` e `IMPRESORA_METODO = 'raw'` en esa copia y `php -d upload_max_filesize=200M -d post_max_size=210M -S 127.0.0.1:8080 -t <carpeta_padre>`; abrir `http://127.0.0.1:8080/HP/` |

No hay tests automatizados ni CI todavía (T-12 en PLAN.md). Verifica a mano:
- un PDF de varias páginas con rango `2-3, 5`, 2 copias, A4 y doble cara: la impresora
  falsa debe mostrar `QTY=2`, `PAPER=A4`, `DUPLEX=ON` y el PDF con 3 páginas;
- un PDF de más de 100 MB llega completo y sin error de memoria;
- un archivo mayor al límite da un mensaje claro (no una página en blanco);
- tema claro y oscuro, y 390 px de ancho sin scroll horizontal.

## Estilo de código
- PHP sin framework, funciones con nombres en español (`preparar_listo`, `envolver_pjl`).
- Comentario de cabecera en cada archivo explicando qué hace.
- JS sin build (IIFE, `'use strict'`); CSS con los tokens de `app.css`.
- Diseño: el del Dashboard FTTM ([Design-System.md](Design-System.md)). Sin bordes de
  color, sombras ni degradados; un solo azul de acción; íconos SVG con `icono()` /
  `Iconos()` (agregarlos en los dos catálogos), **nunca emojis**; probar claro y oscuro.
