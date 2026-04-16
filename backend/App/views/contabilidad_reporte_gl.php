<?= $header; ?>

<style>
    #reporteGL {
        width: 100% !important;
        table-layout: fixed;
        font-size: 11px;
        word-wrap: break-word;
        word-break: break-word;
    }
    #reporteGL th,
    #reporteGL td {
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: break-word;
        padding: 4px 6px;
        vertical-align: middle;
        text-align: center;
    }
    #reporteGL th {
        font-size: 10.5px;
        line-height: 1.15;
    }
    #reporteGL_wrapper {
        width: 100%;
        overflow-x: hidden;
    }
    .tabla-reporte-gl {
        width: 100%;
        overflow: hidden;
    }
</style>

<div class="right_col">
    <div class="panel">
        <div class="panel-header" style="padding: 10px;">
            <div class="x_title">
                <label style="font-size: large;">Reporte GL</label>
                <div class="clearfix"></div>
            </div>
            <div class="card">
                <div class="card-header" style="margin: 20px 0;">
                    <span class="card-title">Seleccione el rango de fechas para generar el reporte de garantías.</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="fechaInicial">Fecha Inicial</label>
                                <input type="date" class="form-control" id="fechaInicial" value="<?= htmlspecialchars($fechaInicial, ENT_QUOTES, 'UTF-8'); ?>" max="<?= htmlspecialchars($fechaMaxima, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="fechaFinal">Fecha Final</label>
                                <input type="date" class="form-control" id="fechaFinal" value="<?= htmlspecialchars($fechaFinal, ENT_QUOTES, 'UTF-8'); ?>" max="<?= htmlspecialchars($fechaMaxima, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="visibility: hidden;">Acciones</label>
                                <div>
                                    <button type="button" class="btn btn-primary" id="buscar"><i class="fa fa-search">&nbsp;</i>Buscar</button>
                                    <button type="button" class="btn btn-success" id="exportar"><i class="fa fa-file-excel-o">&nbsp;</i>Exportar a Excel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <div class="panel-body resultado">
            <div class="row tabla-reporte-gl">
                <table class="table table-striped table-bordered table-hover" id="reporteGL">
                    <thead>
                        <tr>
                            <th>Región</th>
                            <th>Asesor</th>
                            <th>Nombre Asesor</th>
                            <th>Crédito</th>
                            <th>Grupo</th>
                            <th>Situación</th>
                            <th>Saldo Inicial</th>
                            <th>Depósito Banco</th>
                            <th>Depósito Excedente</th>
                            <th>Pago GL</th>
                            <th>Saldo Final</th>
                            <th>Total</th>
                            <th>Pago Comisión</th>
                            <th>Dev. Cancelación de Cheque</th>
                            <th>Conciliación Comisión</th>
                            <th>Pago Adelantado</th>
                            <th>Canc. Aplic. a Pago de Crédito</th>
                            <th>Canc. Traspaso Gar. a Ciclo Sig.</th>
                            <th>Canc. Pago Comisión</th>
                            <th>Canc. Conciliación Comisión</th>
                            <th>Canc. Pago Adelantado</th>
                            <th>Movimiento Cancelado</th>
                            <th>Traspaso de Garantía a Pago</th>
                            <th>Dev. por Depósito Excedente</th>
                            <th>Canc. Cheque Dev. Garantía</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $footer; ?>
