<?php echo $header; ?>
<div class="right_col">
    <div class="col-md-12 col-sm-12 col-xs-12 col-lg-12">
        <div class="panel panel-body">
            <div class="x_title">
                <h3>Reporte de Días de Atraso</h3>
                <div class="clearfix"></div>
            </div>

            <div class="card col-md-12">
                <hr style="border-top: 1px solid #e5e5e5; margin-top: 5px;">
                <form name="all" id="all" method="POST">
                    <button id="export_excel_consulta" type="button" class="btn btn-success btn-circle"><i class="fa fa-file-excel-o"> </i> <b>Exportar a Excel</b></button>
                    <button id="export_csv_consulta" type="button" class="btn btn-info btn-circle"><i class="fa fa-file-text-o"> </i> <b>Exportar a CSV</b></button>
                    <hr style="border-top: 1px solid #787878; margin-top: 5px;">

                <div class="dataTable_wrapper">
                    <table class="table table-striped table-bordered table-hover" id="muestra-cupones">
                        <thead>
                        <tr>
                            <th>Código Cliente</th>
                            <th>Ciclo</th>
                            <th>Nombre</th>
                            <th>Inicio</th>
                            <th>Días de Atraso</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?= $tabla; ?>
                        </tbody>
                    </table>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php echo $footer; ?>
