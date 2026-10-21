/**
 * Comportamiento compartido del sistema visual SEIEM (manual unificado §7.8–§7.10, §6.3).
 * Confirmación declarativa (data-confirm), ventana de estado (data-aviso-inicial, data-cargando), toasts,
 * menú adaptable y control Mostrar/Ocultar de contraseña. Sin dependencias ni compilación.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Menú adaptable: sin JavaScript la navegación permanece visible.
    document.body.classList.add('menu-enhanced');
    const menu = document.querySelector('[data-menu-toggle]');
    const navegacion = document.getElementById('navegacion');
    if (menu && navegacion) {
        menu.addEventListener('click', () => {
            const abierto = navegacion.classList.toggle('is-open');
            menu.setAttribute('aria-expanded', String(abierto));
            menu.setAttribute('aria-label', abierto ? 'Cerrar menú' : 'Abrir menú');
        });
    }

    // Contraseña: cambia la visibilidad sin enviar el formulario y devuelve el foco al campo.
    document.querySelectorAll('[data-password-toggle]').forEach(boton => {
        const campo = document.getElementById(boton.getAttribute('aria-controls'));
        if (!campo) return;
        boton.addEventListener('click', () => {
            const visible = campo.type === 'password';
            campo.type = visible ? 'text' : 'password';
            boton.setAttribute('aria-pressed', String(visible));
            boton.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
            boton.textContent = visible ? 'Ocultar' : 'Mostrar';
            campo.focus();
        });
    });

    // Confirmación compartida (<x-ui.modal-confirmacion>).
    const modal = document.getElementById('modal-confirmacion');
    let pendiente = null;
    const cerrarModal = () => { if (modal?.open) modal.close(); };
    document.addEventListener('click', event => {
        const boton = event.target.closest?.('[data-confirm]');
        if (!boton || boton.disabled) return;
        if (!modal || typeof modal.showModal !== 'function') {
            if (!window.confirm(boton.dataset.confirm)) event.preventDefault();
            return;
        }
        event.preventDefault();
        pendiente = boton;
        const peligro = boton.dataset.confirmVariant === 'danger';
        const aceptar = modal.querySelector('[data-modal-aceptar]');
        modal.classList.toggle('is-danger', peligro);
        modal.querySelector('#modal-confirmacion-titulo').textContent = boton.dataset.confirmTitle || 'Confirmar acción';
        modal.querySelector('#modal-confirmacion-mensaje').textContent = boton.dataset.confirm;
        aceptar.className = peligro ? 'btn-danger' : 'btn-primary';
        // Cada disparador declara su aceptación específica (manual §19.10); «Aceptar» solo como respaldo.
        aceptar.textContent = boton.dataset.confirmAccept || 'Aceptar';
        modal.showModal();
        modal.querySelector('[data-modal-cancelar].btn-secondary').focus();
    });
    if (modal) {
        modal.querySelectorAll('[data-modal-cancelar]').forEach(control => control.addEventListener('click', cerrarModal));
        modal.addEventListener('click', event => { if (event.target === modal) cerrarModal(); });
        modal.addEventListener('close', () => { pendiente?.focus(); });
        modal.querySelector('[data-modal-aceptar]').addEventListener('click', () => {
            const boton = pendiente;
            pendiente = null;
            cerrarModal();
            if (boton?.form) boton.form.requestSubmit(boton);
            else if (boton?.href) window.location.assign(boton.href);
        });
    }

    // Ventana de estado: resultados e indicador de procesamiento. Los avisos se encolan.
    const estado = document.createElement('dialog');
    estado.className = 'status-dialog';
    estado.setAttribute('aria-labelledby', 'status-title');
    estado.setAttribute('aria-describedby', 'status-description');
    estado.innerHTML = '<div class="status-modal"><span class="status-symbol" aria-hidden="true"></span><h2 id="status-title"></h2><p id="status-description" role="status"></p><button type="button" class="btn-primary status-accept">Aceptar</button></div>';
    document.body.append(estado);
    let origenEstado = null;
    let tipoEstado = 'info';
    const colaAvisos = [];
    const presentarEstado = (titulo, mensaje, tipo = 'info', origen = null) => {
        origenEstado = origen;
        tipoEstado = tipo;
        estado.querySelector('.status-modal').className = 'status-modal ' + tipo;
        estado.querySelector('.status-symbol').textContent = tipo === 'success' ? '✓' : tipo === 'error' ? '!' : tipo === 'loading' ? '' : 'i';
        estado.querySelector('#status-title').textContent = titulo;
        estado.querySelector('#status-description').textContent = mensaje;
        estado.querySelector('button').hidden = tipo === 'loading';
        estado.setAttribute('aria-busy', String(tipo === 'loading'));
        if (!estado.open) estado.showModal();
        if (tipo !== 'loading') estado.querySelector('button').focus({preventScroll: true});
    };
    const mostrarAviso = (titulo, mensaje, origen = null, tipo = 'info') => {
        if (estado.open && tipoEstado !== 'loading') { colaAvisos.push([titulo, mensaje, tipo, origen]); return; }
        presentarEstado(titulo, mensaje, tipo, origen);
    };
    estado.querySelector('button').addEventListener('click', () => estado.close());
    estado.addEventListener('cancel', event => { if (tipoEstado === 'loading') event.preventDefault(); });
    estado.addEventListener('close', () => {
        (origenEstado ?? document.getElementById('contenido'))?.focus({preventScroll: true});
        if (colaAvisos.length) presentarEstado(...colaAvisos.shift());
    });
    document.querySelectorAll('[data-aviso-inicial]').forEach(aviso => {
        mostrarAviso(aviso.dataset.titulo, aviso.dataset.mensaje, null, aviso.dataset.tipo || 'info');
    });

    // Formularios con data-cargando: indicador que nombra la operación; evita envíos repetidos.
    document.addEventListener('submit', event => {
        const formulario = event.target;
        const mensaje = formulario.dataset?.cargando;
        if (!mensaje || event.defaultPrevented) return;
        if (formulario.dataset.enviando === 'true') { event.preventDefault(); return; }
        formulario.dataset.enviando = 'true';
        presentarEstado('Procesando', mensaje, 'loading');
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-enviando]').forEach(formulario => { delete formulario.dataset.enviando; });
        if (estado.open && tipoEstado === 'loading') estado.close();
    });

    // Filtros sin recargar la página; conserva el foco al escribir.
    document.querySelectorAll('[data-listado-dinamico]').forEach(listado => {
        const formulario = listado.querySelector('form[role="search"]');
        const resultados = listado.querySelector('[data-listado-resultados]');
        const total = listado.querySelector('[data-listado-total]');
        const aviso = listado.querySelector('[data-listado-aviso]');
        let temporizador;
        let solicitud;
        let revision = 0;

        const cancelar = () => {
            clearTimeout(temporizador);
            solicitud?.abort();
            revision++;
            resultados.removeAttribute('aria-busy');
            aviso.hidden = true;
        };
        const urlFiltros = () => {
            const url = new URL(formulario.action);
            for (const [nombre, valor] of new FormData(formulario)) {
                if (valor.trim()) url.searchParams.set(nombre, valor.trim());
            }
            return url;
        };
        const actualizar = async (url = urlFiltros()) => {
            cancelar();
            const desde = formulario.elements.namedItem('from');
            const hasta = formulario.elements.namedItem('to');
            hasta.setCustomValidity(desde.value && hasta.value && hasta.value < desde.value
                ? 'La fecha final debe ser igual o posterior a la fecha inicial.' : '');
            if (!formulario.reportValidity()) return;

            solicitud = new AbortController();
            const actual = revision;
            resultados.setAttribute('aria-busy', 'true');
            aviso.textContent = 'Actualizando expedientes…';
            aviso.hidden = false;
            try {
                const respuesta = await fetch(url, {
                    signal: solicitud.signal,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (actual !== revision) return;
                if (respuesta.redirected) {
                    window.location.assign(respuesta.url);
                    return;
                }
                if (!respuesta.ok) throw new Error('No se pudieron actualizar los expedientes. Intenta de nuevo.');
                const pagina = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
                if (actual !== revision) return;
                const nuevos = pagina.querySelector('[data-listado-resultados]');
                const nuevoTotal = pagina.querySelector('[data-listado-total]');
                if (!nuevos || !nuevoTotal) throw new Error('No se pudieron actualizar los expedientes. Intenta de nuevo.');
                resultados.replaceChildren(...nuevos.childNodes);
                total.textContent = nuevoTotal.textContent;
                window.history.replaceState(null, '', url);
                aviso.textContent = `${total.textContent.trim()}. Resultados actualizados.`;
                aviso.classList.add('sr-only');
            } catch (error) {
                if (actual !== revision || error.name === 'AbortError') return;
                aviso.classList.remove('sr-only');
                aviso.textContent = error.message;
            } finally {
                if (actual === revision) resultados.removeAttribute('aria-busy');
            }
        };
        formulario.addEventListener('input', event => {
            if (event.target.name !== 'q') return;
            cancelar();
            if (!event.isComposing) temporizador = setTimeout(() => actualizar(), 350);
        });
        formulario.addEventListener('compositionend', () => {
            cancelar();
            temporizador = setTimeout(() => actualizar(), 350);
        });
        formulario.addEventListener('change', event => {
            if (event.target.name !== 'q') actualizar();
        });
        formulario.addEventListener('submit', event => {
            event.preventDefault();
            actualizar();
        });
        listado.querySelector('[data-limpiar-filtros]').addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            for (const campo of formulario.elements) {
                if (campo.tagName === 'SELECT') campo.selectedIndex = 0;
                else if (campo.tagName === 'INPUT') campo.value = '';
            }
            actualizar();
        });
        resultados.addEventListener('click', event => {
            const enlace = event.target.closest('.table-pagination a');
            if (!enlace || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            actualizar(new URL(enlace.href));
        });
        window.addEventListener('pagehide', cancelar);
    });

    // Toasts con cierre y autocierre opcional.
    document.querySelectorAll('[data-toast]').forEach(toast => {
        const cerrar = () => { toast.classList.add('is-leaving'); setTimeout(() => toast.remove(), 200); };
        toast.querySelector('[data-toast-cerrar]')?.addEventListener('click', cerrar);
        if (toast.dataset.autocierre) setTimeout(cerrar, Number(toast.dataset.autocierre));
    });
});
