# PLAN — Portal de impresión

Tickets técnicos `T-xx` (Claude/desarrollo) y tareas del dueño `H-xx` (Eduardo).
Formato: `- [ ]` abierto, `- [x]` hecho con fecha.

## Historia
- jul-2026: primera versión (subir y enviar a la impresora por RAW 9100 o FTP, copias en `archivo/`).
- 20-nov-2026: el proyecto se mueve a `C:\Proyectos\HP` con un acceso directo en `www`: la página da 403.
- 20-nov-2026: entra al repo `EJGR1906/HP_INTER_INGYPROY`.
- 20-nov-2026: en producción, unión de directorio, límites de fcgid y borrado de las copias viejas. Un PDF grande se sube e imprime bien; la doble cara **no** funcionó (salieron 2 hojas por un solo lado).

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
- [x] T-10 Sección «Recientes» con botón Reimprimir. ✅ 2026-11-20 (quitada en T-14)
- [x] T-14 Se quita `GUARDAR_COPIA`: sin copias, sin registro, sin «Recientes» ni reimpresión; la carpeta temporal se borra aunque la petición se corte. ✅ 2026-11-20
- [x] T-13 La vista previa muestra cada hoja como saldrá (papel, orientación y escala) y la doble cara explica cómo se voltean las hojas. ✅ 2026-11-20

## Tareas del dueño
- [x] H-01 Borrar el acceso directo `C:\wamp64\www\HP` y crear la unión `mklink /J C:\wamp64\www\HP C:\Proyectos\HP`.
- [x] H-02 En `httpd.conf`: `FcgidMaxRequestLen 230686720`, `FcgidIOTimeout 600`, `FcgidBusyTimeout 600`; reiniciar Apache. ✅ 2026-11-20 (`httpd -t`: Syntax OK)
- [x] H-03 `git pull` en `C:\Proyectos\HP`. ✅ 2026-11-20
- [ ] H-04 Probar un PDF de varias páginas con rango, 2 copias e intercalar activado y desactivado; confirmar que la M601 respeta copias, papel y orden. (20-nov: PDF grande impreso bien; falta revisar copias e intercalar.)
- [ ] H-05 Confirmar si la M601 tiene unidad dúplex (modelo «M601dn», o «Accesorio dúplex» en la página web de la impresora); si no, `DUPLEX_DISPONIBLE = false`. Hoy está en `true` sin confirmar. **20-nov: se probó doble cara y salieron 2 hojas por un solo lado**: la impresora ignora `@PJL SET DUPLEX=ON`, lo más probable porque es M601n sin unidad dúplex.
- [x] H-06 Decidir si «Recientes» se muestra a todos: se decidió quitar copias e historial (T-14).
- [x] H-07 Borrar en el servidor las copias viejas de `C:\Proyectos\HP\archivo` (menos `.htaccess`). ✅ 2026-11-20. Se conservan `archivo/.htaccess` y las reglas del `.gitignore` por si quedó alguna copia en otra ruta.
- [ ] H-08 En GitHub, cambiar la rama principal a `main` (Settings > General > Default branch) y borrar `claude/nice-dirac-2asnl0`.

## Pendiente técnico
- [ ] T-11 Varias hojas por cara (2 o 4 páginas por hoja).
- [ ] T-12 Pruebas automáticas (Playwright + impresora falsa) en CI.
- [ ] T-15 Según H-05: si no hay unidad dúplex, ocultar la opción (`DUPLEX_DISPONIBLE = false`) y ofrecer «doble cara manual» (imprime impares, se voltea el papel, imprime pares en orden inverso).
