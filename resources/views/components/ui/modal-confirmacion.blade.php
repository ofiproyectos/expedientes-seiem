<dialog class="modal" id="modal-confirmacion" aria-labelledby="modal-confirmacion-titulo" aria-describedby="modal-confirmacion-mensaje">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-icon"><x-ui.icono nombre="alerta"/></span>
            <div>
                <h2 id="modal-confirmacion-titulo">Confirmar acción</h2>
                <p id="modal-confirmacion-mensaje"></p>
            </div>
            <button type="button" class="icon-button" data-modal-cancelar aria-label="Cerrar"><x-ui.icono nombre="cerrar"/></button>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" data-modal-cancelar>Cancelar</button>
            <button type="button" class="btn-primary" data-modal-aceptar>Aceptar</button>
        </div>
    </div>
</dialog>
