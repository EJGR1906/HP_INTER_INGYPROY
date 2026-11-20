<?php
// =====================================================================
// includes/historial.php — Sección «Recientes»: últimas impresiones del
// registro (archivo/_registro.jsonl) con botón para reimprimir la copia
// guardada. Cada fila es un <form> que funciona sin JavaScript (reimprime
// con 1 copia y el papel por defecto); assets/js/imprimir.js lo intercepta
// y le agrega las opciones elegidas en el panel.
// =====================================================================

/** Fecha del registro lista para mostrar ("19/11/2026 16:10"). */
function fecha_registro(array $fila): string
{
    $ts = (string) ($fila['ts'] ?? '');
    if ($ts === '') return '';
    try {
        // Las filas anteriores a nov-2026 (sin la clave 'opciones') se anotaron en UTC.
        $zona = array_key_exists('opciones', $fila) ? null : new DateTimeZone('UTC');
        $d = new DateTime($ts, $zona);
        $d->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $d->format('d/m/Y H:i');
    } catch (Exception $e) {
        return $ts;
    }
}

/** HTML de la lista de recientes (o del estado vacío). */
function pintar_historial(array $filas, string $csrf): string
{
    if (!$filas) {
        return '<p class="muted">Todavía no hay impresiones registradas.</p>';
    }
    ob_start();
    ?>
    <ul class="recientes">
        <?php foreach ($filas as $f):
            $rel = (string) ($f['guardado'] ?? '');
            $existe = $rel !== '' && ruta_guardado_segura($rel) !== null;
            $meta = array_filter([
                fecha_registro($f),
                ($f['estado'] ?? '') === 'reimpreso' ? 'reimpresión' : '',
                (string) ($f['opciones'] ?? ''),
                !empty($f['bytes']) ? humano_bytes((int) $f['bytes']) : '',
            ]);
            ?>
            <li class="reciente">
                <span class="reciente-ico"><?= icono('documento') ?></span>
                <div class="reciente-txt">
                    <span class="reciente-nombre"><?= e($f['original'] ?? 'documento') ?></span>
                    <span class="muted"><?= e(implode(', ', $meta)) ?></span>
                </div>
                <?php if ($existe): ?>
                    <form method="post" class="form-reimprimir">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="accion" value="reimprimir">
                        <input type="hidden" name="guardado" value="<?= e($rel) ?>">
                        <button type="submit" class="btn btn-outline btn-sm"><?= icono('impresora') ?><span>Reimprimir</span></button>
                    </form>
                <?php else: ?>
                    <span class="chip chip-warn">Copia no disponible</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
    return (string) ob_get_clean();
}
