// =====================================================================
// imprimir.js — Lógica del formulario de impresión (sin build).
//   - Vista previa del PDF con miniaturas (pdf.js, assets/vendor/pdf.min.js).
//   - Selección de páginas: todas, rango ("1-3, 5, 8-"), impares, pares, o
//     pulsando las miniaturas.
//   - Arma en el navegador un PDF nuevo solo con las páginas elegidas,
//     ajustadas al papel y la orientación (pdf-lib, window.PDFLib).
//   - Cada miniatura es la hoja que saldrá: proporción del papel elegido y la
//     página encajada según orientación y escala (hojaPara(), la misma cuenta
//     que construirPdf()).
//   - Imágenes JPG/PNG: se convierten a PDF aquí mismo.
//   - Office: se convierte a PDF en el servidor (accion=convertir) si hay
//     LibreOffice; si no, se imprime completo sin vista previa.
//   - Sube con barra de progreso (XHR) y muestra la respuesta JSON.
// Copias, intercalar, papel y doble cara viajan como campos del formulario
// y el servidor los aplica con PJL (includes/impresora.php).
// =====================================================================
(function () {
    'use strict';

    const form = document.getElementById('formImprimir');
    if (!form) return;
    const cfg = JSON.parse(form.dataset.config || '{}');
    const $ = (id) => document.getElementById(id);

    const input = $('doc');
    const vista = $('vista'), grid = $('pagGrid'), contador = $('contadorPaginas');
    const aviso = $('avisoDoc'), estadoVista = $('estadoVista');
    const campoRango = $('campoRango'), rango = $('rango'), rangoAyuda = $('rangoAyuda');
    const copias = $('copias'), intercalar = $('intercalar'), papel = $('papel'), duplex = $('duplex');
    const resumen = $('resumen'), btn = $('btnImprimir'), mensaje = $('mensaje');
    const progreso = $('progreso'), progresoRelleno = $('progresoRelleno'), progresoTxt = $('progresoTxt');
    const AYUDA_RANGO = rangoAyuda.textContent;

    // Tamaños de papel en puntos (1/72 pulgada), vertical.
    const PAPEL_PT = { LETTER: [612, 792], LEGAL: [612, 1008], A4: [595.28, 841.89], EXECUTIVE: [522, 756] };
    const EXT_IMAGEN = ['jpg', 'jpeg', 'png'];

    const estado = {
        archivo: null,      // File elegido
        tipo: '',           // 'pdf' | 'directo'
        bytes: null,        // ArrayBuffer del PDF (pdf, imagen o Office convertido)
        editable: false,    // pdf-lib puede rearmarlo
        total: 0,           // páginas
        sel: new Set(),     // índices 0..total-1 seleccionados
        dims: [],           // [ancho, alto] de cada página tal como se ve (con /Rotate)
        paginas: 'todas', orientacion: 'auto', escala: 'ajustar',
        ocupado: false,
        version: 0,         // descarta cargas viejas si se cambia de archivo
    };

    // ------------------------------------------------------------ util
    const ext = (nombre) => (nombre.split('.').pop() || '').toLowerCase();
    const base = (nombre) => nombre.replace(/\.[^.]+$/, '');

    function mostrarMensaje(tipo, texto) {
        const clase = tipo === 'ok' ? 'alert-ok' : (tipo === 'warn' ? 'alert-warn' : 'alert-error');
        const ico = tipo === 'ok' ? 'check-circulo' : (tipo === 'warn' ? 'alerta' : 'error');
        mensaje.innerHTML = '<div class="alert ' + clase + '">' + window.Iconos(ico)
            + '<div class="alert-body">' + window.esc(texto) + '</div></div>';
        mensaje.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function avisar(texto) {
        aviso.hidden = !texto;
        if (texto) aviso.querySelector('.alert-body').textContent = texto;
    }

    function ponerProgreso(texto, pct) {
        progreso.hidden = texto === null;
        if (texto === null) return;
        progresoTxt.textContent = texto;
        progreso.classList.toggle('indeterminado', pct === null);
        progresoRelleno.style.width = pct === null ? '' : pct + '%';
    }

    function ocupado(si, texto) {
        estado.ocupado = si;
        btn.disabled = si || !estado.archivo;
        btn.innerHTML = si
            ? '<span class="spinner" aria-hidden="true"></span><span>' + window.esc(texto || 'Procesando') + '</span>'
            : window.Iconos('impresora') + '<span>Imprimir</span>';
    }

    // Control segmentado genérico: data-<clave>="valor".
    function seg(clave, alCambiar) {
        const botones = form.querySelectorAll('[data-' + clave + ']');
        botones.forEach((b) => b.addEventListener('click', () => {
            if (b.disabled) return;
            marcarSeg(clave, b.dataset[clave]);
            alCambiar(b.dataset[clave]);
        }));
    }
    function marcarSeg(clave, valor) {
        form.querySelectorAll('[data-' + clave + ']').forEach((b) => {
            const activo = b.dataset[clave] === valor;
            b.classList.toggle('active', activo);
            b.setAttribute('aria-checked', String(activo));
        });
    }
    function habilitarSeg(clave, si) {
        form.querySelectorAll('[data-' + clave + ']').forEach((b) => { b.disabled = !si; });
    }

    // ---------------------------------------------------- rango de páginas
    /** "1-3, 5, 8-" -> Set de índices 0-based. Lanza Error con mensaje si es inválido. */
    function parsearRango(texto, total) {
        const sel = new Set();
        const partes = texto.split(/[,;]+/).map((p) => p.trim()).filter(Boolean);
        if (!partes.length) throw new Error('Escribe las páginas a imprimir.');
        for (const p of partes) {
            const m = p.match(/^(\d+)?\s*-\s*(\d+)?$/) || p.match(/^(\d+)$/);
            if (!m) throw new Error('No entiendo «' + p + '». Usa por ejemplo 1-3, 5.');
            let a, b;
            if (p.includes('-')) { a = m[1] ? +m[1] : 1; b = m[2] ? +m[2] : total; }
            else { a = b = +m[1]; }
            if (a < 1 || b < 1 || a > total || b > total) throw new Error('El documento tiene ' + total + ' páginas.');
            if (a > b) [a, b] = [b, a];
            for (let i = a; i <= b; i++) sel.add(i - 1);
        }
        return sel;
    }

    /** Set de índices -> "1-3, 5, 8-10". */
    function comprimirRango(sel) {
        const v = [...sel].sort((x, y) => x - y).map((i) => i + 1);
        const out = [];
        for (let i = 0; i < v.length; i++) {
            let j = i;
            while (j + 1 < v.length && v[j + 1] === v[j] + 1) j++;
            out.push(j > i ? v[i] + '-' + v[j] : String(v[i]));
            i = j;
        }
        return out.join(', ');
    }

    function calcularSeleccion() {
        const t = estado.total;
        const sel = new Set();
        rangoAyuda.textContent = AYUDA_RANGO;
        rangoAyuda.classList.remove('error');
        if (estado.paginas === 'rango') {
            try { return parsearRango(rango.value, t); }
            catch (e) {
                if (rango.value.trim()) { rangoAyuda.textContent = e.message; rangoAyuda.classList.add('error'); }
                return sel;
            }
        }
        for (let i = 0; i < t; i++) {
            if (estado.paginas === 'todas'
                || (estado.paginas === 'impares' && i % 2 === 0)
                || (estado.paginas === 'pares' && i % 2 === 1)) sel.add(i);
        }
        return sel;
    }

    function aplicarSeleccion() {
        estado.sel = calcularSeleccion();
        grid.querySelectorAll('.pag-mini').forEach((b) => {
            b.setAttribute('aria-pressed', String(estado.sel.has(+b.dataset.i)));
        });
        pintarResumen();
        pintarDuplexAyuda();
    }

    // ----------------------------------------------------------- resumen
    function pintarResumen() {
        const n = Math.max(1, Math.min(cfg.copiasMax || 99, parseInt(copias.value, 10) || 1));
        const dobles = duplex && duplex.value !== 'no';
        if (!estado.archivo) { resumen.hidden = true; return; }
        resumen.hidden = false;
        if (estado.tipo !== 'pdf' || !estado.total) {
            resumen.innerHTML = '<strong>Documento completo</strong><span class="muted">'
                + (n === 1 ? '1 copia' : n + ' copias') + (dobles ? ', a doble cara' : '') + '</span>';
            return;
        }
        const pags = estado.sel.size;
        if (!pags) {
            resumen.innerHTML = '<strong>Ninguna página elegida</strong><span class="muted">Marca al menos una página.</span>';
            btn.disabled = true;
            return;
        }
        if (!estado.ocupado) btn.disabled = false;
        const hojas = Math.ceil(pags / (dobles ? 2 : 1)) * n;
        resumen.innerHTML = '<strong>' + (pags === 1 ? '1 página' : pags + ' páginas')
            + (n > 1 ? ', ' + n + ' copias' : '') + '</strong><span class="muted">'
            + (hojas === 1 ? '1 hoja' : hojas + ' hojas') + ' de papel ' + window.esc(papel.options[papel.selectedIndex].text.split(' (')[0])
            + (dobles ? ', a doble cara' : '') + '</span>';
    }

    // ------------------------------------------------ hoja de papel
    /**
     * Cómo queda una página de dw x dh puntos (tal como se ve) en la hoja:
     * tamaño de la hoja (W x H, ya girada si va horizontal) y escala s.
     * La usan la vista previa y construirPdf(), así lo que se ve es lo que sale.
     */
    function hojaPara(dw, dh) {
        const [pw, ph] = PAPEL_PT[papel.value] || PAPEL_PT.LETTER;
        const horizontal = estado.orientacion === 'horizontal' || (estado.orientacion === 'auto' && dw > dh);
        const W = horizontal ? Math.max(pw, ph) : Math.min(pw, ph);
        const H = horizontal ? Math.min(pw, ph) : Math.max(pw, ph);
        const s = estado.escala === 'ajustar' ? Math.min(W / dw, H / dh) : 1;
        return { W, H, s, horizontal };
    }

    /** Dibuja la miniatura como la hoja que saldrá: proporción del papel y página encajada. */
    function ajustarMiniatura(b) {
        const lienzo = b.querySelector('.pag-lienzo');
        const c = lienzo.querySelector('canvas');
        const d = estado.dims[+b.dataset.i];
        if (!estado.editable) {
            // PDF protegido: se imprime tal cual, la miniatura es la página original.
            if (d) lienzo.style.aspectRatio = d[0] + ' / ' + d[1];
            if (c) { c.style.width = '100%'; c.style.height = '100%'; }
            return;
        }
        if (!d) return;
        const { W, H, s } = hojaPara(d[0], d[1]);
        lienzo.style.aspectRatio = W + ' / ' + H;
        if (c) {
            c.style.width = (d[0] * s / W * 100) + '%';
            c.style.height = (d[1] * s / H * 100) + '%';
        }
    }

    function refrescarMiniaturas() {
        grid.querySelectorAll('.pag-mini').forEach(ajustarMiniatura);
        pintarDuplexAyuda();
    }

    /** Explica cómo se voltean las hojas según la orientación que más se repite. */
    const duplexAyuda = $('duplexAyuda');
    function pintarDuplexAyuda() {
        if (!duplexAyuda || !duplex) return;
        const modo = duplex.value;
        if (modo === 'no') { duplexAyuda.textContent = 'Imprime solo por un lado de la hoja.'; return; }
        let horiz = 0, vert = 0;
        if (estado.tipo === 'pdf' && estado.editable) {
            estado.sel.forEach((i) => {
                const d = estado.dims[i];
                if (d) { if (hojaPara(d[0], d[1]).horizontal) horiz++; else vert++; }
            });
        }
        const horizontal = horiz > vert;
        const comoLibro = (modo === 'largo') !== horizontal;   // vertical+largo o horizontal+corto
        let txt = 'Imprime por los dos lados. ' + (comoLibro
            ? 'Las hojas se pasan de lado, como un libro.'
            : 'Las hojas se pasan hacia arriba, como un calendario o un bloc.');
        if (estado.tipo === 'pdf' && estado.editable && (horiz || vert)) {
            const sugerido = horizontal ? 'corto' : 'largo';
            if (modo !== sugerido) txt += ' Para leerlas como un libro, elige «Borde ' + sugerido + '».';
        }
        duplexAyuda.textContent = txt;
    }

    // ------------------------------------------------------- vista previa
    let pdfjs = null;
    async function cargarPdfjs() {
        if (pdfjs) return pdfjs;
        const url = (r) => new URL(r, document.baseURI).href;
        pdfjs = await import(url('assets/vendor/pdf.min.js'));
        pdfjs.GlobalWorkerOptions.workerSrc = url('assets/vendor/pdf.worker.min.js');
        return pdfjs;
    }

    async function mostrarVista(version) {
        let doc;
        try {
            const lib = await cargarPdfjs();
            doc = await lib.getDocument({ data: new Uint8Array(estado.bytes.slice(0)) }).promise;
        } catch (e) {
            if (version !== estado.version) return;
            estadoVista.hidden = false;
            estadoVista.textContent = 'No se pudo mostrar la vista previa' + (e && e.name === 'PasswordException' ? ' (PDF protegido con contraseña).' : '.');
            return null;
        }
        if (version !== estado.version) return null;
        estado.total = doc.numPages;
        contador.hidden = false;
        contador.textContent = doc.numPages === 1 ? '1 página' : doc.numPages + ' páginas';
        grid.innerHTML = '';
        const frag = document.createDocumentFragment();
        for (let i = 0; i < doc.numPages; i++) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'pag-mini';
            b.dataset.i = i;
            b.setAttribute('aria-pressed', 'true');
            b.setAttribute('aria-label', 'Página ' + (i + 1));
            b.innerHTML = '<span class="pag-lienzo"></span><span class="pag-marca">' + window.Iconos('check', 'icon-sm')
                + '</span><span class="pag-num">' + (i + 1) + '</span>';
            frag.appendChild(b);
        }
        grid.appendChild(frag);
        vista.hidden = false;
        refrescarMiniaturas();

        // Tamaño de todas las páginas (rápido, sin dibujar) para la forma de cada hoja.
        (async () => {
            for (let i = 0; i < doc.numPages && version === estado.version; i++) {
                try {
                    const v = (await doc.getPage(i + 1)).getViewport({ scale: 1 });
                    estado.dims[i] = [v.width, v.height];
                    const b = grid.children[i];
                    if (b) ajustarMiniatura(b);
                } catch (e) { /* sin tamaño: queda la hoja por defecto */ }
            }
            if (version === estado.version) pintarDuplexAyuda();
        })();

        // Render perezoso: solo las miniaturas visibles, de a una.
        const cola = [];
        let trabajando = false;
        const siguiente = async () => {
            if (trabajando) return;
            trabajando = true;
            while (cola.length && version === estado.version) {
                const b = cola.shift();
                try {
                    const pag = await doc.getPage(+b.dataset.i + 1);
                    const v1 = pag.getViewport({ scale: 1 });
                    const escala = Math.min(2, (240 / Math.max(v1.width, v1.height)));
                    const vp = pag.getViewport({ scale: escala });
                    const c = document.createElement('canvas');
                    c.width = Math.ceil(vp.width); c.height = Math.ceil(vp.height);
                    await pag.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
                    estado.dims[+b.dataset.i] = [v1.width, v1.height];
                    b.querySelector('.pag-lienzo').appendChild(c);
                    ajustarMiniatura(b);
                } catch (e) { /* miniatura en blanco */ }
            }
            trabajando = false;
        };
        const obs = new IntersectionObserver((entradas) => {
            entradas.forEach((en) => {
                if (en.isIntersecting) { obs.unobserve(en.target); cola.push(en.target); }
            });
            siguiente();
        }, { rootMargin: '300px' });
        grid.querySelectorAll('.pag-mini').forEach((b) => obs.observe(b));
        return doc;
    }

    grid.addEventListener('click', (ev) => {
        const b = ev.target.closest('.pag-mini');
        if (!b || !estado.editable) return;
        const i = +b.dataset.i;
        if (estado.sel.has(i)) estado.sel.delete(i); else estado.sel.add(i);
        if (estado.sel.size === estado.total) {
            estado.paginas = 'todas'; marcarSeg('paginas', 'todas'); campoRango.hidden = true;
        } else {
            estado.paginas = 'rango'; marcarSeg('paginas', 'rango'); campoRango.hidden = false;
            rango.value = comprimirRango(estado.sel);
        }
        aplicarSeleccion();
    });
    $('selTodas').addEventListener('click', () => {
        estado.paginas = 'todas'; marcarSeg('paginas', 'todas'); campoRango.hidden = true; aplicarSeleccion();
    });
    $('selNinguna').addEventListener('click', () => {
        estado.paginas = 'rango'; marcarSeg('paginas', 'rango'); campoRango.hidden = false;
        rango.value = ''; aplicarSeleccion();
    });

    // -------------------------------------------------- elegir un archivo
    function reiniciar() {
        estado.version++;
        Object.assign(estado, { archivo: null, tipo: '', bytes: null, editable: false, total: 0, sel: new Set(), dims: [] });
        grid.innerHTML = '';
        vista.hidden = true; contador.hidden = true; estadoVista.hidden = true;
        avisar('');
        estado.paginas = 'todas'; marcarSeg('paginas', 'todas'); campoRango.hidden = true; rango.value = '';
        habilitarSeg('paginas', true); habilitarSeg('orientacion', true); habilitarSeg('escala', true);
    }

    /** Modo sin vista previa: se imprime el archivo tal cual. */
    function modoDirecto(texto) {
        estado.tipo = 'directo';
        estado.editable = false;
        habilitarSeg('paginas', false); habilitarSeg('orientacion', false); habilitarSeg('escala', false);
        if (texto) avisar(texto);
    }

    async function imagenAPdf(file) {
        const { PDFDocument } = window.PDFLib;
        const doc = await PDFDocument.create();
        const datos = await file.arrayBuffer();
        const img = ext(file.name) === 'png' ? await doc.embedPng(datos) : await doc.embedJpg(datos);
        // 96 ppp: un píxel = 0,75 puntos. "Ajustar al papel" la escala después.
        const w = img.width * 0.75, h = img.height * 0.75;
        doc.addPage([w, h]).drawImage(img, { x: 0, y: 0, width: w, height: h });
        return (await doc.save()).buffer;
    }

    function convertirEnServidor(file, version) {
        return new Promise((resolve, reject) => {
            const fd = new FormData();
            fd.append('csrf', form.elements.csrf.value);
            fd.append('accion', 'convertir');
            fd.append('doc', file, file.name);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.pathname);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.responseType = 'arraybuffer';
            xhr.upload.onprogress = (e) => {
                if (version === estado.version && e.lengthComputable) ponerProgreso('Subiendo para convertir a PDF', Math.round(e.loaded / e.total * 100));
            };
            xhr.upload.onload = () => { if (version === estado.version) ponerProgreso('Convirtiendo a PDF en el servidor', null); };
            xhr.onload = () => {
                const tipo = xhr.getResponseHeader('Content-Type') || '';
                if (xhr.status === 200 && tipo.indexOf('application/pdf') === 0) return resolve(xhr.response);
                let msg = 'No se pudo convertir el documento (error ' + xhr.status + ').';
                try { msg = JSON.parse(new TextDecoder().decode(xhr.response)).msg || msg; } catch (e) { /* no era JSON */ }
                reject(new Error(msg));
            };
            xhr.onerror = () => reject(new Error('Se perdió la conexión con el servidor.'));
            xhr.send(fd);
        });
    }

    async function alElegir(file) {
        reiniciar();
        mensaje.innerHTML = '';
        if (!file) { pintarResumen(); ocupado(false); return; }
        const version = estado.version;
        estado.archivo = file;
        const e = ext(file.name);

        if (file.size > cfg.limite) {
            avisar('El archivo pesa ' + window.tamanoLegible(file.size) + ' y el límite es ' + window.tamanoLegible(cfg.limite) + '.');
        }

        try {
            if (e === 'pdf') {
                estado.bytes = await file.arrayBuffer();
            } else if (EXT_IMAGEN.includes(e)) {
                estado.bytes = await imagenAPdf(file);
            } else if ((cfg.convertibles || []).includes(e)) {
                if (!cfg.convertir) {
                    modoDirecto('El servidor no tiene LibreOffice: no hay vista previa ni selección de páginas. Se imprimirá el documento completo; o guárdalo como PDF para elegir páginas.');
                    pintarResumen(); ocupado(false);
                    return;
                }
                ocupado(true, 'Convirtiendo');
                estado.bytes = await convertirEnServidor(file, version);
                if (version !== estado.version) return;
                ponerProgreso(null);
            } else {
                modoDirecto('Este formato se envía tal cual a la impresora: sin vista previa ni selección de páginas.');
                pintarResumen(); ocupado(false);
                return;
            }
        } catch (err) {
            if (version !== estado.version) return;
            ponerProgreso(null);
            modoDirecto(err.message || 'No se pudo leer el archivo.');
            pintarResumen(); ocupado(false);
            return;
        }
        if (version !== estado.version) return;

        estado.tipo = 'pdf';
        // ¿pdf-lib puede rearmarlo? (falla con PDFs cifrados)
        try {
            await window.PDFLib.PDFDocument.load(estado.bytes, { updateMetadata: false });
            estado.editable = true;
        } catch (err) {
            estado.editable = false;
        }
        if (version !== estado.version) return;
        ocupado(true, 'Abriendo');
        const doc = await mostrarVista(version);
        if (version !== estado.version) return;
        ocupado(false);
        if (!doc) { modoDirecto(''); pintarResumen(); return; }
        if (!estado.editable) {
            habilitarSeg('paginas', false); habilitarSeg('orientacion', false); habilitarSeg('escala', false);
            $('vistaAyuda').textContent = 'Vista previa solamente.';
            avisar('Este PDF está protegido: se imprimirá completo, sin elegir páginas ni ajustar al papel.');
        } else {
            $('vistaAyuda').textContent = 'Así saldrá cada hoja. Pulsa una para quitarla o agregarla.';
        }
        aplicarSeleccion();
    }

    form.addEventListener('archivocambiado', (ev) => {
        alElegir(ev.detail.archivo).catch((err) => {
            ocupado(false);
            mostrarMensaje('err', err.message || 'No se pudo abrir el archivo.');
        });
    });

    // ----------------------------------------------------- opciones
    seg('paginas', (v) => {
        estado.paginas = v;
        campoRango.hidden = v !== 'rango';
        if (v === 'rango') {
            if (!rango.value && estado.sel.size && estado.sel.size < estado.total) rango.value = comprimirRango(estado.sel);
            rango.focus();
        }
        aplicarSeleccion();
    });
    rango.addEventListener('input', aplicarSeleccion);
    seg('orientacion', (v) => { estado.orientacion = v; refrescarMiniaturas(); });
    seg('escala', (v) => { estado.escala = v; refrescarMiniaturas(); });
    if (duplex) seg('duplex', (v) => { duplex.value = v; pintarResumen(); pintarDuplexAyuda(); });

    const fijarCopias = (n) => {
        copias.value = Math.max(1, Math.min(cfg.copiasMax || 99, n || 1));
        intercalar.disabled = +copias.value === 1;
        pintarResumen();
    };
    $('copiasMenos').addEventListener('click', () => fijarCopias((parseInt(copias.value, 10) || 1) - 1));
    $('copiasMas').addEventListener('click', () => fijarCopias((parseInt(copias.value, 10) || 1) + 1));
    copias.addEventListener('change', () => fijarCopias(parseInt(copias.value, 10)));
    copias.addEventListener('input', pintarResumen);
    papel.addEventListener('change', () => { pintarResumen(); refrescarMiniaturas(); });
    fijarCopias(1);

    // ------------------------------------------ armar el PDF a imprimir
    /** PDF nuevo con las páginas elegidas, ajustadas a papel y orientación. */
    async function construirPdf() {
        const { PDFDocument, degrees } = window.PDFLib;
        const src = await PDFDocument.load(estado.bytes, { updateMetadata: false });
        const indices = [...estado.sel].sort((a, b) => a - b);
        const out = await PDFDocument.create();

        const embebidas = await out.embedPages(indices.map((i) => src.getPage(i)));
        indices.forEach((i, k) => {
            const emb = embebidas[k];
            const rot = (((src.getPage(i).getRotation().angle || 0) % 360) + 360) % 360;
            const w = emb.width, h = emb.height;
            const girada = rot === 90 || rot === 270;
            const dw = girada ? h : w, dh = girada ? w : h;      // tamaño como se ve

            const { W, H, s } = hojaPara(dw, dh);
            const ox = (W - dw * s) / 2, oy = (H - dh * s) / 2;

            // /Rotate del PDF gira en sentido horario; drawPage rota antihorario.
            let x = ox, y = oy, r = 0;
            if (rot === 90) { r = -90; y = oy + dh * s; }
            else if (rot === 180) { r = 180; x = ox + dw * s; y = oy + dh * s; }
            else if (rot === 270) { r = 90; x = ox + dw * s; }

            out.addPage([W, H]).drawPage(emb, { x, y, xScale: s, yScale: s, rotate: degrees(r) });
        });
        return out.save();
    }

    // ------------------------------------------------------- enviar
    function subir(blob, nombre, descPaginas) {
        return new Promise((resolve, reject) => {
            const fd = new FormData();
            fd.append('csrf', form.elements.csrf.value);
            fd.append('accion', 'imprimir');
            fd.append('copias', copias.value);
            fd.append('intercalar', intercalar.checked ? '1' : '0');
            fd.append('papel', papel.value);
            fd.append('duplex', duplex ? duplex.value : 'no');
            fd.append('nombre_original', estado.archivo.name);
            fd.append('paginas_desc', descPaginas);
            fd.append('doc', blob, nombre);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.pathname);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const pct = Math.round(e.loaded / e.total * 100);
                    ponerProgreso('Subiendo ' + window.tamanoLegible(e.loaded) + ' de ' + window.tamanoLegible(e.total) + ' (' + pct + ' %)', pct);
                }
            };
            xhr.upload.onload = () => ponerProgreso('Enviando a la impresora', null);
            xhr.onload = () => {
                let r = null;
                try { r = JSON.parse(xhr.responseText); } catch (e) { /* no era JSON */ }
                if (r && typeof r.msg === 'string') return resolve(r);
                if (xhr.status === 413) return reject(new Error('El servidor rechazó el archivo por tamaño (413). Revisa FcgidMaxRequestLen y LimitRequestBody en httpd.conf.'));
                reject(new Error('El servidor respondió con un error (' + xhr.status + '). Revisa el error.log de Apache.'));
            };
            xhr.onerror = () => reject(new Error('Se perdió la conexión con el servidor.'));
            xhr.send(fd);
        });
    }

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (estado.ocupado || !estado.archivo) return;
        if (estado.tipo === 'pdf' && estado.editable && !estado.sel.size) {
            mostrarMensaje('err', 'Elige al menos una página.');
            return;
        }
        try {
            let blob = estado.archivo, nombre = estado.archivo.name, desc = '';
            if (estado.tipo === 'pdf') {
                nombre = base(estado.archivo.name) + '.pdf';
                if (estado.editable) {
                    ocupado(true, 'Preparando');
                    ponerProgreso('Preparando el documento', null);
                    blob = new Blob([await construirPdf()], { type: 'application/pdf' });
                    if (estado.sel.size !== estado.total) desc = comprimirRango(estado.sel);
                } else {
                    blob = new Blob([estado.bytes], { type: 'application/pdf' });
                }
            }
            if (blob.size > cfg.limite) {
                throw new Error('El documento a enviar pesa ' + window.tamanoLegible(blob.size) + ' y el límite del servidor es ' + window.tamanoLegible(cfg.limite) + '.');
            }
            ocupado(true, 'Imprimiendo');
            const r = await subir(blob, nombre, desc);
            mostrarMensaje(r.ok ? 'ok' : 'err', r.msg);
        } catch (err) {
            mostrarMensaje('err', err.message || 'No se pudo imprimir.');
        } finally {
            ponerProgreso(null);
            ocupado(false);
            pintarResumen();
        }
    });

    // Si el navegador recordó un archivo al volver a la página.
    if (input.files && input.files[0]) alElegir(input.files[0]);
    else pintarResumen();
})();
