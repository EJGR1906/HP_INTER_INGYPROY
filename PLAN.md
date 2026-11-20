# PLAN — Portal de impresión

Tickets técnicos `T-xx` (Claude/desarrollo) y tareas del dueño `H-xx` (Eduardo).
Formato: `- [ ]` abierto, `- [x]` hecho con fecha.

## Historia
- jul-2026: primera versión (subir y enviar a la impresora por RAW 9100 o FTP, copias en `archivo/`).
- 20-nov-2026: el proyecto se mueve a `C:\Proyectos\HP` con un acceso directo en `www`: la página da 403.
- 20-nov-2026: entra al repo `EJGR1906/HP_INTER_INGYPROY`.

## Entrega 1 (20-nov-2026): opciones de impresión y archivos grandes
- [x] T-01 Subir al repo el código tal como estaba en el servidor (sin `archivo/`). ✅ 2026-11-20
- [x] T-02 `.htaccess` con `DirectoryIndex index.php` y `Options -Indexes +ExecCGI`. ✅ 2026-11-20
- [x] T-03 Archivos de hasta 200 MB: `.user.ini`, `MAX_MB`, envío por bloques (RAW y FTP), mensajes por código de error. ✅ 2026-11-20
- [x] T-04 Opciones con PJL: copias, intercalar, papel, doble cara. ✅ 2026-11-20
- [x] T-05 Vista previa y selección de páginas en el navegador (pdf.js + pdf-lib en `assets/vendor/`). ✅ 2026-11-20
- [x] T-06 Orientación y ajuste al papel; imágenes JPG/PNG; Office con LibreOffice (`accion=convertir`). ✅ 2026-11-20
- [x] T-07 Interfaz con el sistema de diseño del Dashboard FTTM, sin emojis ni « · ». ✅ 2026-11-20
- [x] T-08 Documentación: README, PRD, Architecture, Design-System, PLAN, CLAUDE, AGENTS, docs/. ✅ 2026-11-20
- [x] T-09 `tools/impresora_falsa.php` para probar sin la impresora. ✅ 2026-11-20

## Tareas del dueño
- [ ] H-01 Borrar el acceso directo `C:\wamp64\www\HP` y crear la unión `mklink /J C:\wamp64\www\HP C:\Proyectos\HP`.
- [ ] H-02 En `httpd.conf`: `FcgidMaxRequestLen 230686720`, `FcgidIOTimeout 600`, `FcgidBusyTimeout 600`; reiniciar Apache.
- [ ] H-03 Copiar al servidor los archivos de este repo (o `git pull` en `C:\Proyectos\HP`) conservando `archivo/`.
- [ ] H-04 Probar un PDF de varias páginas con rango, 2 copias y doble cara; confirmar que la M601 respeta copias y papel.
- [ ] H-05 Confirmar si la M601 tiene unidad dúplex; si no, `DUPLEX_DISPONIBLE = false`.

## Pendiente técnico
- [ ] T-10 Botón de reimprimir en la página (el backend `accion=reimprimir` ya existe) e historial visible.
- [ ] T-11 Varias hojas por cara (2 o 4 páginas por hoja).
- [ ] T-12 Pruebas automáticas (Playwright + impresora falsa) en CI.
