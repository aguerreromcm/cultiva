<?= $header; ?>

<div class="right_col">
    <div class="panel panel-default pagos-import-page">
        <div class="panel-body">
            <div class="x_title">
                <h3>Importar pagos de corresponsales</h3>
                <div class="clearfix"></div>
            </div>

            <p class="text-muted ci-descripcion">
                Cargue los layouts recibidos de <strong>OXXO</strong>, <strong>PAYCASH</strong> o <strong>BanCoppel</strong> para importar los pagos.
            </p>

            <div class="panel-card ft-toolbar ci-toolbar">
                <div class="ft-toolbar-inner">
                    <div class="ci-field ci-field-corresponsal">
                        <label class="ft-tb-lbl" for="corresponsal">
                            <i class="fa fa-building-o"></i> Corresponsal
                        </label>
                        <select id="corresponsal" class="form-control ci-control">
                            <option value="">— Seleccione —</option>
                            <option value="OXXO">OXXO (.dat)</option>
                            <option value="PAYCASH">PAYCASH (.csv)</option>
                            <option value="BANCOPPEL">BanCoppel (.xls)</option>
                        </select>
                    </div>

                    <div class="ft-toolbar-sep" aria-hidden="true"></div>

                    <div class="ci-field ci-field-archivo">
                        <label class="ft-tb-lbl" for="archivo_layout">
                            <i class="fa fa-file-text-o"></i> Archivo de layout
                        </label>
                        <input type="file" id="archivo_layout" class="form-control ci-control">
                        <small id="hint-extension" class="ci-hint"></small>
                    </div>

                    <div class="ft-toolbar-sep" aria-hidden="true"></div>

                    <div class="ci-field ci-field-acciones">
                        <span class="ft-tb-lbl ci-lbl-spacer" aria-hidden="true">&nbsp;</span>
                        <div class="ci-acciones-line">
                            <button type="button" id="btn_previsualizar" class="btn btn-primary">
                                <i class="fa fa-eye"></i> Previsualizar
                            </button>
                            <button type="button" id="btn_confirmar" class="btn btn-success" disabled>
                                <i class="fa fa-upload"></i> Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="panel-preview" class="panel-card" style="display: none;">
                <div class="head">
                    <h4><i class="fa fa-eye"></i> Vista previa del archivo</h4>
                    <span class="ft-conteo" id="resumen-preview"></span>
                </div>
                <div class="body">
                    <div class="ft-encabezado-tabla">
                        <h4 class="titulo"><i class="fa fa-list"></i> Registros detectados</h4>
                    </div>
                    <table class="table table-striped table-bordered table-hover" id="tabla-preview-importacion">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Referencia</th>
                                <th>Crédito</th>
                                <th>Ciclo</th>
                                <th>Monto</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="panel-card ci-tabs-wrap">
                <div class="head" style="padding-bottom: 0;">
                    <ul class="nav nav-tabs ci-tabs" role="tablist">
                        <li role="presentation" class="active">
                            <a href="#tab-historial" aria-controls="tab-historial" role="tab" data-toggle="tab">
                                <i class="fa fa-history"></i> Historial
                            </a>
                        </li>
                        <li role="presentation">
                            <a href="#tab-incidencias" aria-controls="tab-incidencias" role="tab" data-toggle="tab">
                                <i class="fa fa-exclamation-triangle"></i> Incidencias
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="body tab-content">
                    <div role="tabpanel" class="tab-pane active" id="tab-historial">
                        <p class="ci-tab-intro">
                            Archivos ya importados en el sistema. Si un archivo aparece aquí, no podrá cargarse de nuevo.
                        </p>
                        <table class="table table-striped table-bordered table-hover" id="tabla-historial-importacion">
                            <thead>
                                <tr>
                                    <th>Archivo</th>
                                    <th>Fecha pago</th>
                                    <th>Registros</th>
                                    <th>Monto total</th>
                                    <th>Incidencias</th>
                                    <th>Fecha de importación</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div role="tabpanel" class="tab-pane" id="tab-incidencias">
                        <p class="ci-tab-intro">
                            Pagos con crédito <strong>000000</strong> y ciclo <strong>00</strong> por referencia inválida o crédito no encontrado.
                            Capture el crédito y ciclo correctos de un préstamo en situación <strong>Entregado</strong>.
                        </p>
                        <table class="table table-striped table-bordered table-hover" id="tabla-incidencias-importacion">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Referencia</th>
                                    <th>Crédito</th>
                                    <th>Ciclo</th>
                                    <th>Monto</th>
                                    <th>Archivo</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalleImportacion" tabindex="-1" role="dialog" aria-labelledby="modalDetalleImportacionTitle">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
                <h4 class="modal-title" id="modalDetalleImportacionTitle">Registros importados</h4>
            </div>
            <div class="modal-body">
                <p class="ci-tab-intro" style="margin-top: 0;">
                    Archivo: <strong id="detalle_archivo_nombre"></strong>
                    <span id="detalle_resumen" class="text-muted"></span>
                </p>
                <table class="table table-striped table-bordered table-hover" id="tabla-detalle-importacion">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th>Crédito</th>
                            <th>Ciclo</th>
                            <th>Monto</th>
                            <th>Estatus</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCorregirIncidencia" tabindex="-1" role="dialog" aria-labelledby="modalCorregirIncidenciaTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
                <h4 class="modal-title" id="modalCorregirIncidenciaTitle">Corregir incidencia</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="corr_fecha">
                <input type="hidden" id="corr_secuencia">
                <div class="ft-credito-modal">
                    <strong>Referencia original:</strong> <span id="corr_referencia"></span><br>
                    <strong>Monto:</strong> <span id="corr_monto"></span>
                </div>
                <div class="form-group">
                    <label for="corr_credito">Número de crédito *</label>
                    <input type="text" id="corr_credito" class="form-control" maxlength="6" placeholder="000000" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="corr_ciclo">Ciclo *</label>
                    <input type="text" id="corr_ciclo" class="form-control" maxlength="3" placeholder="00" autocomplete="off">
                </div>
                <p class="text-muted small" style="margin-bottom: 0;">
                    Se validará que el crédito y ciclo correspondan a un préstamo en situación Entregado.
                    La referencia se generará automáticamente con el dígito verificador.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn_guardar_correccion" class="btn btn-primary">
                    <i class="fa fa-save"></i> Guardar corrección
                </button>
            </div>
        </div>
    </div>
</div>

<?= $footer; ?>
