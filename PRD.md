# PRD — Portal de impresión en red

## Problema
La oficina tiene una HP LaserJet 600 M601 en red (`10.2.200.74`). Instalar el
controlador en cada equipo es lento y a veces imposible (equipos sin permisos de
administrador, equipos personales). Se necesita imprimir desde el navegador.

## Usuarios
Personal de la oficina en la LAN. Sin cuentas: el portal es abierto dentro de la
red (opcionalmente con una clave común, `CLAVE_ACCESO`).

## Requisitos funcionales
| ID | Requisito | Estado |
|---|---|---|
| RF-01 | Subir un archivo y enviarlo a la impresora sin controladores (RAW 9100 o FTP). | Hecho |
| RF-02 | Aceptar archivos de hasta 200 MB, con progreso de subida y mensajes claros si se pasa del límite. | Hecho |
| RF-03 | Vista previa del PDF con miniaturas. | Hecho |
| RF-04 | Elegir páginas: todas, rango, impares, pares o marcando miniaturas. | Hecho |
| RF-05 | Copias (1 a 99), intercaladas o no. | Hecho |
| RF-06 | Tamaño de papel: Carta, Oficio, A4, Ejecutivo. | Hecho |
| RF-07 | Orientación (automática, vertical, horizontal) y escala (ajustar al papel o tamaño real). | Hecho |
| RF-08 | Doble cara por borde largo o corto. | Hecho |
| RF-09 | Imprimir imágenes JPG y PNG. | Hecho |
| RF-10 | Office (DOCX, XLSX, PPTX...) convertido a PDF con LibreOffice si está instalado. | Hecho (depende del servidor) |
| RF-11 | No guardar copias ni historial de lo impreso: el documento se borra del servidor al enviarlo. | Hecho (antes había `GUARDAR_COPIA`, quitado en nov-2026) |
| RF-13 | Tema claro y oscuro, usable en el teléfono. | Hecho |

## Requisitos no funcionales
- Sin dependencias en el servidor más allá de Apache + PHP 8 (LibreOffice opcional).
- Sin internet: las librerías del navegador están en `assets/vendor/`.
- Archivos grandes por bloques: el servidor no carga el documento entero en memoria.
- Privacidad: ningún documento queda en el servidor después de imprimirse.
- Seguridad: CSRF en todo POST, nombres de archivo saneados, `includes/` y `tools/`
  bloqueadas por HTTP, opciones validadas con listas blancas.
- Diseño: mismo sistema que el Dashboard FTTM; contraste AA en claro y oscuro.

## Fuera de alcance
- Cola de impresión, estado de la impresora o niveles de tóner.
- Varias impresoras (se configura una en `config.php`).
- Cuentas de usuario por persona.
- Historial o reimpresión de documentos (se descartó: son archivos personales).
