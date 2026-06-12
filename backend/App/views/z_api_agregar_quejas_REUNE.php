<?= $header; ?>

<div class="right_col">
    <div class="panel panel-body">
        <div class="modal-header">
            <h3>Registrar reclamación REUNE</h3>
        </div>
        <div class="col-md-12">
            <h5 class="card-title">Ingrese los datos solicitados</h5>
        </div>
        <div class="col-md-12" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <div class="form-group">
                <label for="NumConsultas">Número de reclamaciones</label>
                <input class="form-control" id="NumConsultas" value="1" disabled />
                <span class="text-danger" id="spnMensaje">Este valor es fijo, no se puede modificar</span>
            </div>
            <div class="form-group">
                <label for="InstitucionClave">Institución</label>
                <input class="form-control" id="InstitucionClave" value="Financiera Cultiva, S.A.P.I. de C.V., SOFOM, E.N.R." disabled />
                <span class="text-danger" id="spnMensaje">Este valor es fijo, no se puede modificar</span>
            </div>
            <div class="form-group">
                <label for="Sector">Sector</label>
                <input class="form-control" id="Sector" value="Sociedades Financieras de Objeto Múltiple E.N.R." disabled />
                <span class="text-danger" id="spnMensaje">Este valor es fijo, no se puede modificar</span>
            </div>
            <div class="form-group">
                <label for="ConsultasTrim">Trimestre a informar *</label>
                <select class="form-control" id="ConsultasTrim" onchange=validaRequeridos()>
                    <option value="" disabled selected>Seleccionar</option>
                    <option value="1">Enero - Marzo</option>
                    <option value="2">Abril - Junio</option>
                    <option value="3">Julio - Septiembre</option>
                    <option value="4">Octubre - Diciembre</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ConsultasFolio">Número de folio *</label>
                <input class="form-control" id="ConsultasFolio" oninput=validaRequeridos() />
            </div>
            <div class="form-group">
                <label for="ConsultasEstatusCon">Estatus *</label>
                <select class="form-control" id="ConsultasEstatusCon" onchange=cambioEstatus()>
                    <option value="" disabled selected>Seleccionar</option>
                    <option value="1">PENDIENTE</option>
                    <option value="2">CONCLUIDO</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ConsultasFecRecepcion">Fecha de la reclamación *</label>
                <input type="date" class="form-control" id="ConsultasFecRecepcion" oninput=validaRequeridos() />
            </div>
            <div class="form-group">
                <label for="ConsultasFecAten">Fecha de atención</label>
                <input type="date" class="form-control" id="ConsultasFecAten" disabled oninput=validaRequeridos() />
                <span class="text-danger" id="spnMensaje">Aplica solo con estatus CONCLUIDO</span>
            </div>
            <div class="form-group">
                <label for="MediosId">Medio de recepción *</label>
                <select class="form-control" id="MediosId" onchange=cambioMedio()>
                    <option value="" disabled="" selected="">Seleccionar</option>
                    <option value="1">Correo electrónico</option>
                    <option value="2">Página de internet</option>
                    <option value="3">Sucursales</option>
                    <option value="4">Teléfono</option>
                    <option value="5">UNE</option>
                    <option value="6">CONDUSEF-SIGE gestión electrónica</option>
                    <option value="7">CONDUSEF-Gestión ordinaria</option>
                    <option value="8">Mensajeria</option>
                    <option value="9">Fax</option>
                    <option value="17">Oficinas de atención</option>
                    <option value="18">Centro de atención telefónica</option>
                    <option value="20">Aplicación movil</option>
                    <option value="21">Interfaces</option>
                    <option value="22">Api's</option>
                    <option value="23">Bots</option>
                </select>
            </div>
            <div class="form-group">
                <label for="Producto">Producto o servicio *</label>
                <select class="form-control" id="Producto" onchange="cambioProducto()">
                    <?= $productos; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="tipoRegistro">Tipo de registro *</label>
                <select class="form-control" id="tipoRegistro" onchange=cambioTipoRegistro() disabled>
                    <option value="" disabled selected>Seleccione un producto</option>
                    <option value="1">Consulta</option>
                    <option value="2">Reclamación</option>
                    <option value="3">Aclaración</option>
                </select>
            </div>
            <div class="form-group">
                <label for="CausaId">Causa de la reclamación *</label>
                <select class="form-control" id="CausaId" onchange=validaRequeridos() disabled>
                    <option value="" disabled selected>Seleccione un tipo de registro</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ConsultasCP">CP</label>

                <div class="input-group">
                    <input class="form-control"
                        id="ConsultasCP"
                        maxlength="5"
                        onkeypress="validaEntradaCP(event)"
                        disabled />

                    <span class="input-group-btn">
                        <button class="btn btn-primary"
                            type="button"
                            onclick="validaCP()"
                            id="btnCP"
                            disabled>
                            <i class="fa fa-search"></i>
                        </button>
                    </span>
                </div>
                <span class="text-danger" id="spnMensaje">Solo si el medio de recepción es UNE, Sucursal u Oficina</span>
            </div>
            <div class="form-group">
                <label for="EstadosId">Estado</label>
                <select class="form-control" id="EstadosId" onchange=validaRequeridos() disabled>
                    <option value="9" selected>Ciudad de México</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ConsultasMpioId">Municipio *</label>
                <select class="form-control" id="ConsultasMpioId" onchange=validaRequeridos() disabled>
                    <option value="14" selected>Benito Juárez</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ConsultascatnivelatenId">Nivel de atención o contacto</label>
                <select class="form-control" id="ConsultascatnivelatenId" disabled>
                    <option value="" disabled selected>Seleccionar</option>
                    <option value="1">UNE</option>
                    <option value="2">Sucursal</option>
                    <option value="3">Centro de atención telefónica</option>
                    <option value="4">Oficinas de atención</option>
                </select>
                <span class="text-danger" id="spnMensaje">Aplica solo con estatus CONCLUIDO</span>
            </div>
            <div class="form-group">
                <label for="ConsultasPori">PORI *</label>
                <select class="form-control" id="ConsultasPori" onchange=validaRequeridos()>
                    <option value="SI">SI</option>
                    <option value="NO">NO</option>
                </select>
            </div>
        </div>
        <div class="col-md-12 text-right">
            <button id="btnAgregar" class="btn btn-primary" onclick=registrarQueja(event) disabled>
                <span class="glyphicon glyphicon-floppy-disk"></span> Registrar
            </button>
        </div>
    </div>
</div>

<script>
    const causas = <?= json_encode($causas) ?>;
</script>

<?= $footer; ?>