document.addEventListener('DOMContentLoaded', () => {
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    document.querySelectorAll('[data-seleccion-tabla]').forEach(table => {
        const rows = [...table.querySelectorAll('[data-selection-row]')];
        const search = table.querySelector('[data-selection-search]');
        const only = table.querySelector('[data-selection-only]');
        const pageBox = table.querySelector('[data-selection-page]');
        let page = 1;
        const checked = row => row.querySelector('[data-selection-item]').checked;
        const matches = () => rows.filter(row => normalize(row.dataset.search).includes(normalize(search.value.trim())) && (!only.checked || checked(row)));
        table.selection = () => rows.filter(checked).map(row => Number(row.querySelector('[data-selection-item]').value));
        const render = () => {
            const filtered = matches();
            const pages = Math.max(1, Math.ceil(filtered.length / 10));
            page = Math.max(1, Math.min(page, pages));
            const visible = filtered.slice((page - 1) * 10, page * 10);
            rows.forEach(row => { row.hidden = !visible.includes(row); });
            table.querySelector('[data-selection-empty]').hidden = filtered.length > 0;
            table.querySelector('[data-selection-range]').textContent = filtered.length ? `${(page - 1) * 10 + 1}–${Math.min(page * 10, filtered.length)} de ${filtered.length}` : '0 registros';
            table.querySelector('[data-selection-page-label]').textContent = `Página ${page} de ${pages}`;
            table.querySelector('[data-selection-prev]').disabled = page === 1;
            table.querySelector('[data-selection-next]').disabled = page === pages;
            table.querySelector('[data-selection-pagination]').hidden = pages <= 1;
            table.querySelector('.selection-toolbar').hidden = rows.length === 0;
            const selected = table.selection();
            const allButton = table.querySelector('[data-selection-all]');
            // Conserva el icono del menú; cambia únicamente su etiqueta.
            allButton.lastChild.textContent = `Seleccionar todos los resultados (${filtered.length})`;
            allButton.disabled = filtered.length === 0;
            table.querySelector('[data-selection-clear]').disabled = selected.length === 0;
            table.querySelector('[data-selection-count]').textContent = `${selected.length} seleccionados${selected.length && search.value ? ' (incluye otras búsquedas)' : ''}`;
            pageBox.checked = visible.length > 0 && visible.every(checked);
            pageBox.indeterminate = visible.some(checked) && !pageBox.checked;
            pageBox.disabled = visible.length === 0;
            const output = table.querySelector('[data-selection-output]');
            if (output) output.value = JSON.stringify(selected);
            const form = table.closest('[data-selection-required]');
            if (form) {
                form.querySelector('[data-selection-submit]').disabled = selected.length === 0;
                form.querySelector('[data-selection-actions]').hidden = selected.length === 0;
            }
        };
        const changed = () => { render(); table.dispatchEvent(new CustomEvent('selectionchange', { bubbles: true })); };
        search.addEventListener('input', () => { page = 1; render(); });
        only.addEventListener('change', () => { page = 1; render(); });
        table.querySelector('[data-selection-clear-filter]').addEventListener('click', () => { search.value = ''; only.checked = false; page = 1; render(); search.focus(); });
        table.querySelector('[data-selection-clear]').addEventListener('click', () => { rows.forEach(row => { row.querySelector('[data-selection-item]').checked = false; }); changed(); });
        table.querySelector('[data-selection-all]').addEventListener('click', () => { matches().forEach(row => { row.querySelector('[data-selection-item]').checked = true; }); changed(); });
        pageBox.addEventListener('change', () => { matches().slice((page - 1) * 10, page * 10).forEach(row => { row.querySelector('[data-selection-item]').checked = pageBox.checked; }); changed(); });
        rows.forEach(row => row.querySelector('[data-selection-item]').addEventListener('change', changed));
        table.querySelector('[data-selection-prev]').addEventListener('click', () => { page--; render(); });
        table.querySelector('[data-selection-next]').addEventListener('click', () => { page++; render(); });
        table.querySelectorAll('[data-selection-controls]').forEach(control => { control.hidden = false; });
        render();
    });

    const wizard = document.querySelector('[data-permission-wizard]');
    if (wizard) {
        const docs = wizard.querySelector('#batch-documents');
        const readers = wizard.querySelector('#batch-readers');
        const steps = [...wizard.querySelectorAll('[data-batch-step]')];
        const token = wizard.querySelector('[data-review-token]');
        const save = wizard.querySelector('[data-batch-save]');
        const error = wizard.querySelector('[data-batch-error]');
        const summary = wizard.querySelector('[data-review-summary]');
        let step = 0;
        let revision = 0;
        let request;
        let reviewTimer;
        const payload = () => ({
            scope: wizard.elements.scope.value,
            document_ids: wizard.elements.scope.value === 'documents' ? docs.selection() : [],
            user_ids: readers.selection(), starts_at: wizard.elements.starts_at.value || null,
            expires_at: wizard.elements.expires_at.value || null,
        });
        const persist = () => { wizard.querySelector('[data-batch-selection]').value = JSON.stringify(payload()); };
        const invalidate = () => {
            revision++;
            request?.abort();
            clearTimeout(reviewTimer);
            token.value = '';
            save.disabled = true;
            error.hidden = true;
            summary.textContent = 'La selección se comprobará automáticamente antes de asignar.';
            wizard.querySelector('[data-document-picker]').hidden = wizard.elements.scope.value === 'expediente';
            persist();
        };
        const show = position => {
            step = position;
            steps.forEach((panel, i) => { panel.hidden = i !== step; });
            wizard.querySelectorAll('[data-step-label]').forEach((label, i) => {
                if (i === step) label.setAttribute('aria-current', 'step'); else label.removeAttribute('aria-current');
            });
            wizard.querySelector('[data-batch-back]').hidden = step === 0;
            wizard.querySelector('[data-batch-next]').hidden = step === 2;
            save.hidden = step !== 2;
            steps[step].querySelector('h2').focus();
        };
        const showError = message => { error.textContent = message; error.hidden = false; };
        const review = async () => {
            if (!wizard.reportValidity()) return;
            invalidate();
            request = new AbortController();
            const current = revision;
            summary.textContent = 'Revisando permisos existentes…';
            try {
                const response = await fetch(wizard.dataset.previewUrl, {
                    method: 'POST', signal: request.signal,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': wizard.elements._token.value },
                    body: JSON.stringify(payload()),
                });
                const result = await response.json();
                if (current !== revision) return;
                if (!response.ok) throw new Error(result.errors ? Object.values(result.errors).flat().join(' ') : 'No se pudo revisar. Actualiza la página si tu sesión terminó.');
                token.value = result.token;
                save.disabled = result.created === 0 && result.consolidated === 0;
                const data = result.data;
                const scopeText = data.scope === 'expediente' ? 'el expediente completo (incluye documentos futuros)' : `${result.document_count} documentos específicos (no incluye documentos futuros)`;
                summary.replaceChildren();
                const lines = [
                    `${result.reader_count} lectores · ${scopeText}.`,
                    `Consulta y descarga · Desde ${data.starts_at} · ${data.expires_at ? 'Hasta ' + data.expires_at : 'Sin expiración'}.`,
                    `${result.created} permisos nuevos; ${result.skipped} ya cubiertos y omitidos.`,
                    `${result.consolidated} permisos redundantes se consolidarán sin reducir su vigencia. Los accesos adicionales se conservan.`,
                ];
                if (!result.created && !result.consolidated) lines.push('No hay cambios pendientes. Todos los permisos están cubiertos.');
                lines.forEach(text => { const paragraph = document.createElement('p'); paragraph.textContent = text; summary.append(paragraph); });
            } catch (exception) {
                if (current === revision && exception.name !== 'AbortError') { summary.textContent = ''; showError(exception.message); }
            }
        };
        const changed = () => {
            invalidate();
            if (step === 2) reviewTimer = setTimeout(review, 350);
        };
        wizard.addEventListener('selectionchange', changed);
        wizard.querySelectorAll('[name="scope"], [name="starts_at"], [name="expires_at"]').forEach(field => field.addEventListener('input', changed));
        wizard.querySelector('[data-batch-next]').addEventListener('click', () => {
            error.hidden = true;
            if (step === 0 && wizard.elements.scope.value === 'documents' && docs.selection().length === 0) return showError('Selecciona al menos un documento.');
            if (step === 1 && readers.selection().length === 0) return showError('Selecciona al menos un lector.');
            show(step + 1);
            if (step === 2) review();
        });
        wizard.querySelector('[data-batch-back]').addEventListener('click', () => show(step - 1));
        wizard.addEventListener('submit', event => {
            if (!token.value || save.disabled || step !== 2) { event.preventDefault(); showError('Completa y revisa la selección antes de asignar.'); }
            persist();
        });
        invalidate();
    }

    document.querySelectorAll('[data-share-documents]').forEach(form => {
        const boxes = [...form.querySelectorAll('[data-share-document]')];
        const button = form.querySelector('[data-share-selected]');
        boxes.forEach(box => box.addEventListener('change', () => {
            const count = boxes.filter(item => item.checked).length;
            button.disabled = count === 0;
            form.querySelector('[data-share-actions]').hidden = count === 0;
            form.querySelector('[data-share-count]').textContent = `${count} documentos seleccionados`;
        }));
    });
});
