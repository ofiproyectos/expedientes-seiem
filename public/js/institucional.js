/**
 * Comportamiento compartido del sistema visual SEIEM (manual unificado §7.8–§7.10, §6.3).
 * Confirmación declarativa (data-confirm), ventana de estado (data-aviso-inicial, data-cargando),
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

    const encabezado = document.querySelector('.app-header');
    if (encabezado && window.ResizeObserver) new ResizeObserver(() => {
        document.documentElement.style.setProperty('--header-height', `${encabezado.getBoundingClientRect().height}px`);
    }).observe(encabezado);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && navegacion?.classList.contains('is-open')) {
            navegacion.classList.remove('is-open');
            menu.setAttribute('aria-expanded', 'false');
            menu.setAttribute('aria-label', 'Abrir men\u00fa');
            menu.focus();
        }
    });

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

    // Índice del expediente (manual §8.3.1): marca con aria-current="location" la sección visible.
    // La sección activa es la última cuyo encabezado ya pasó bajo la barra superior y el índice horizontal.
    document.querySelectorAll('[data-section-nav]').forEach(indice => {
        const enlaces = [...indice.querySelectorAll('a[href^="#"]')];
        const secciones = enlaces.map(enlace => document.getElementById(enlace.hash.slice(1))?.closest('section')).filter(Boolean);
        if (secciones.length !== enlaces.length) return;
        let actual = -1;
        const marcar = posicion => {
            if (posicion === actual) return;
            actual = posicion;
            enlaces.forEach((enlace, i) => {
                if (i === posicion) enlace.setAttribute('aria-current', 'location');
                else enlace.removeAttribute('aria-current');
            });
            // En el índice horizontal, mantiene visible el enlace activo sin desplazar la página.
            const activo = enlaces[posicion];
            if (activo && indice.scrollWidth > indice.clientWidth) {
                indice.scrollTo({ left: activo.offsetLeft - indice.offsetLeft - 16, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            }
        };
        let pendiente = false;
        const calcular = () => {
            pendiente = false;
            const margen = parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0;
            const alFinal = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
            let posicion = 0;
            secciones.forEach((seccion, i) => { if (seccion.getBoundingClientRect().top <= margen + 8) posicion = i; });
            marcar(alFinal ? secciones.length - 1 : posicion);
        };
        const programar = () => { if (!pendiente) { pendiente = true; requestAnimationFrame(calcular); } };
        window.addEventListener('scroll', programar, { passive: true });
        window.addEventListener('resize', programar);
        enlaces.forEach((enlace, i) => enlace.addEventListener('click', () => marcar(i)));
        calcular();
    });

    // Menú «Más acciones» (manual §7.13): popover nativo con cierre por Escape o clic fuera; aquí solo se coloca
    // junto a su botón y dentro de la ventana. Al desplazar se recoloca; si el botón sale de la vista, se cierra.
    const menuAbierto = menu => { try { return menu.matches(':popover-open'); } catch { return false; } };
    const menus = document.querySelectorAll('[data-menu-acciones]');
    const colocarMenu = menu => {
        const caja = document.querySelector(`[popovertarget="${menu.id}"]`).getBoundingClientRect();
        if (caja.bottom < 0 || caja.top > window.innerHeight) { menu.hidePopover(); return; }
        const abajo = caja.bottom + 4;
        menu.style.left = `${Math.max(8, Math.min(caja.right - menu.offsetWidth, window.innerWidth - menu.offsetWidth - 8))}px`;
        menu.style.top = `${abajo + menu.offsetHeight > window.innerHeight - 8 ? Math.max(8, caja.top - menu.offsetHeight - 4) : abajo}px`;
    };
    menus.forEach(menu => {
        menu.addEventListener('keydown', event => {
            const opciones = [...menu.querySelectorAll('a, button:not(:disabled)')];
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                event.preventDefault();
                const actual = opciones.indexOf(document.activeElement);
                const siguiente = event.key === 'Home' ? 0 : event.key === 'End' ? opciones.length - 1 : (actual + (event.key === 'ArrowDown' ? 1 : -1) + opciones.length) % opciones.length;
                opciones[siguiente]?.focus();
            }
        });
        menu.addEventListener('toggle', event => {
            if (event.newState !== 'open') return;
            colocarMenu(menu);
            menu.querySelector('a, button')?.focus({ preventScroll: true });
        });
    });
    if (menus.length) {
        const recolocar = () => menus.forEach(menu => { if (menuAbierto(menu)) colocarMenu(menu); });
        document.addEventListener('scroll', recolocar, { capture: true, passive: true });
        window.addEventListener('resize', recolocar);
    }

    // Diez filas por pagina; filtrar nunca pierde la seleccion entre paginas.
    document.querySelectorAll('table[data-local-pagination]').forEach(tabla => {
        const filas = [...tabla.querySelectorAll('[data-pagination-row]')];
        const pie = document.querySelector(`[data-pagination-for="${tabla.id}"]`);
        if (!pie) return;
        const filtro = document.querySelector(`[data-filtro-documentos="${tabla.id}"]`);
        const busqueda = filtro?.elements.namedItem('q');
        const tipo = filtro?.elements.namedItem('tipo');
        const normalizar = valor => valor.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        let pagina = 1;
        const pintar = () => {
            const palabras = normalizar(busqueda?.value || '').split(/\s+/).filter(Boolean);
            const coincidencias = filas.filter(fila => palabras.every(palabra => (fila.dataset.texto || '').includes(palabra)) && (!tipo?.value || fila.dataset.tipo === tipo.value));
            const paginas = Math.max(1, Math.ceil(coincidencias.length / 10));
            pagina = Math.min(pagina, paginas);
            filas.forEach(fila => { fila.hidden = true; });
            coincidencias.slice((pagina - 1) * 10, pagina * 10).forEach(fila => { fila.hidden = false; });
            const vacio = tabla.querySelector('[data-sin-coincidencias]');
            if (vacio) vacio.hidden = coincidencias.length > 0;
            pie.querySelector('[data-page-range]').textContent = coincidencias.length ? `${(pagina - 1) * 10 + 1}\u2013${Math.min(pagina * 10, coincidencias.length)} de ${coincidencias.length}` : '0 registros';
            pie.querySelector('[data-page-controls]').hidden = paginas === 1;
            pie.querySelector('[data-page-label]').textContent = `P\u00e1gina ${pagina} de ${paginas}`;
            pie.querySelector('[data-page-prev]').disabled = pagina === 1;
            pie.querySelector('[data-page-next]').disabled = pagina === paginas;
        };
        const cambiarPagina = paso => { pagina += paso; pintar(); tabla.closest('[role="region"]')?.focus({ preventScroll: true }); };
        pie.querySelector('[data-page-prev]').addEventListener('click', () => cambiarPagina(-1));
        pie.querySelector('[data-page-next]').addEventListener('click', () => cambiarPagina(1));
        if (filtro) {
            const aplicar = () => { pagina = 1; pintar(); };
            filtro.addEventListener('input', aplicar);
            filtro.addEventListener('change', aplicar);
            filtro.addEventListener('submit', event => event.preventDefault());
            filtro.querySelector('[data-limpiar-documentos]').addEventListener('click', () => { filtro.reset(); aplicar(); busqueda.focus(); });
            filtro.hidden = false;
        }
        pintar();
    });

    // Cancelar, X o Escape y devuelven el foco al control de origen (o al botón del menú del que salieron).
    document.addEventListener('click', event => {
        const abrir = event.target.closest?.('[data-dialogo-abrir]');
        if (abrir) {
            const dialogo = document.getElementById(abrir.dataset.dialogoAbrir);
            if (!dialogo || typeof dialogo.showModal !== 'function') return;
            dialogo.origen = abrir;
            const origenMenu = abrir.closest('[popover]');
            if (origenMenu && menuAbierto(origenMenu)) origenMenu.hidePopover();
            dialogo.showModal();
            dialogo.querySelector('.modal-content :is(input:not([type="hidden"]), select, textarea)')?.focus();
            return;
        }
        event.target.closest?.('[data-dialogo-cerrar]')?.closest('dialog')?.close();
    });
    document.querySelectorAll('[data-dialogo]').forEach(dialogo => {
        dialogo.addEventListener('close', () => {
            const menu = dialogo.origen?.closest('[popover]');
            const destino = menu && !menuAbierto(menu) ? document.querySelector(`[popovertarget="${menu.id}"]`) : dialogo.origen;
            destino?.focus({ preventScroll: true });
        });
        if (dialogo.hasAttribute('data-abrir-al-cargar')) {
            dialogo.showModal();
            (dialogo.querySelector('[aria-invalid="true"]') || dialogo.querySelector('[data-dialog-error-summary]'))?.focus();
        }
    });

    // Confirmación compartida (<x-ui.modal-confirmacion>).
    const modal = document.getElementById('modal-confirmacion');
    let pendiente = null;
    let padreConfirmacion = null;
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
        padreConfirmacion = boton.closest('dialog[open]');
        if (padreConfirmacion) padreConfirmacion.close();
        const origenMenu = boton.closest('[popover]');
        if (origenMenu && menuAbierto(origenMenu)) origenMenu.hidePopover();
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
        modal.addEventListener('close', () => {
            if (pendiente && padreConfirmacion) padreConfirmacion.showModal();
            const origenMenu = pendiente?.closest('[popover]');
            const destino = origenMenu ? document.querySelector(`[popovertarget="${origenMenu.id}"]`) : pendiente;
            destino?.focus();
            padreConfirmacion = null;
        });
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
        document.querySelectorAll('dialog[open]').forEach(dialogo => { if (dialogo !== estado) dialogo.close(); });
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
        formulario.setAttribute('aria-busy', 'true');
        presentarEstado('Procesando', mensaje, 'loading');
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-enviando]').forEach(formulario => { delete formulario.dataset.enviando; formulario.removeAttribute('aria-busy'); });
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
        let espera;

        const cancelar = () => {
            clearTimeout(temporizador);
            clearTimeout(espera);
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
            if (desde && hasta) {
                hasta.setCustomValidity(desde.value && hasta.value && hasta.value < desde.value
                    ? 'La fecha final debe ser igual o posterior a la fecha inicial.' : '');
            }
            if (!formulario.reportValidity()) return;

            solicitud = new AbortController();
            const actual = revision;
            resultados.setAttribute('aria-busy', 'true');
            espera = setTimeout(() => { aviso.textContent = 'Actualizando resultados\u2026'; aviso.classList.remove('sr-only'); aviso.hidden = false; }, 300);
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
                if (!respuesta.ok) {
                    const mensajes = {
                        401: 'Tu sesión terminó. Recarga la página e inicia sesión.',
                        403: 'Tu cuenta ya no tiene acceso a estos resultados.',
                        419: 'Tu sesión terminó. Recarga la página e inicia sesión.',
                        422: 'Revisa los filtros e inténtalo de nuevo.',
                        429: 'Demasiadas solicitudes. Espera un momento antes de buscar de nuevo.',
                    };
                    throw new Error(mensajes[respuesta.status] || 'No se pudieron actualizar los resultados. Vuelve a buscar o usa Limpiar filtros.');
                }
                const pagina = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
                if (actual !== revision) return;
                const nuevos = pagina.querySelector('[data-listado-resultados]');
                const nuevoTotal = pagina.querySelector('[data-listado-total]');
                if (!nuevos || !nuevoTotal) throw new Error('No se pudieron actualizar los resultados. Intenta de nuevo.');
                resultados.replaceChildren(...nuevos.childNodes);
                total.textContent = nuevoTotal.textContent;
                window.history.replaceState(null, '', url);
                aviso.textContent = `${total.textContent.trim()}. Resultados actualizados.`;
                aviso.classList.add('sr-only');
                aviso.hidden = false;
                if (url.searchParams.has('page')) { resultados.tabIndex = -1; resultados.focus({ preventScroll: true }); }
            } catch (error) {
                if (actual !== revision || error.name === 'AbortError') return;
                aviso.classList.remove('sr-only');
                aviso.textContent = error instanceof TypeError ? 'No se pudo conectar. Conservamos los resultados anteriores; vuelve a buscar o usa Limpiar filtros.' : error.message;
                aviso.hidden = false;
            } finally {
                if (actual === revision) { clearTimeout(espera); resultados.removeAttribute('aria-busy'); }
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
            formulario.elements.namedItem('q')?.focus();
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

    if (!document.querySelector('[data-abrir-al-cargar]')) document.querySelector('[data-error-summary]')?.focus();

    // La comparación queda en memoria: no guarda contraseñas ni datos de formularios en el navegador.
    const formulariosEditables = [...document.querySelectorAll('form[method="POST" i]')].filter(form => form.querySelector('input:not([type="hidden"]):not([type="search"]), select, textarea'));
    const firma = form => JSON.stringify([...form.elements].filter(campo => campo.name && !['hidden', 'submit', 'button', 'search'].includes(campo.type)).map(campo => [campo.name, campo.type === 'file' ? [...campo.files].map(archivo => [archivo.name, archivo.size, archivo.lastModified]) : ['checkbox', 'radio'].includes(campo.type) ? campo.checked : campo.value]));
    const originales = new Map(formulariosEditables.map(form => [form, firma(form)]));
    let saliendo = false;
    document.addEventListener('submit', event => { if (!event.defaultPrevented) saliendo = true; });
    window.addEventListener('pageshow', () => { saliendo = false; });
    window.addEventListener('beforeunload', event => {
        if (!saliendo && formulariosEditables.some(form => firma(form) !== originales.get(form))) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
});
