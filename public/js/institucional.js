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

    // Toasts con cierre y autocierre opcional.
    document.querySelectorAll('[data-toast]').forEach(toast => {
        const cerrar = () => { toast.classList.add('is-leaving'); setTimeout(() => toast.remove(), 200); };
        toast.querySelector('[data-toast-cerrar]')?.addEventListener('click', cerrar);
        if (toast.dataset.autocierre) setTimeout(cerrar, Number(toast.dataset.autocierre));
    });
});
