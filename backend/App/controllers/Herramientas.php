<?php

namespace App\controllers;

defined("APPPATH") or die("Access denied");

use \Core\View;
use \Core\Controller;
use \App\models\Herramientas as HerramientasDao;

class Herramientas extends Controller
{
    private $_contenedor;

    function __construct()
    {
        parent::__construct();
        $this->_contenedor = new Contenedor;
        View::set('header', $this->_contenedor->header());
        View::set('footer', $this->_contenedor->footer());
    }

    public function RepDiaAtraso()
    {
        $extraFooter = <<<HTML
        <script>
            $(document).ready(function () {
                $("#muestra-cupones").tablesorter();
                var oTable = $("#muestra-cupones").DataTable({
                    lengthMenu: [[13, 50, -1], [13, 50, "Todos"]],
                    columnDefs: [{ orderable: false, targets: 0 }],
                    order: false
                });

                $("#export_excel_consulta").click(function () {
                    descargaExcel("/Herramientas/generarExcelRepDiaAtraso/");
                });

                $("#export_csv_consulta").click(function () {
                    descargaExcel("/Herramientas/generarCsvRepDiaAtraso/");
                });
            });

            var descargaExcel = function (url) {
                swal({ text: "Generando archivo, espere un momento...", icon: "/img/wait.gif", closeOnClickOutside: false, closeOnEsc: false });
                var ventana = window.open(url, "_blank");
                var intervalo = setInterval(function () {
                    if (ventana.closed) {
                        clearInterval(intervalo);
                        swal.close();
                    }
                }, 1000);
                window.focus();
            };
        </script>
        HTML;

        $Consulta = HerramientasDao::ConsultarRepDiaAtraso();
        $tabla = "";

        if (empty($Consulta) || (isset($Consulta[0]) && $Consulta[0] == '')) {
            View::set('mensaje', 'No se encontraron registros para el reporte de días de atraso.');
            $vista = "herramienta_rep_dia_atraso_message";
        } else {
            foreach ($Consulta as $key => $value) {
                $codCte = $value['COD_CTE'] ?? '';
                $ciclo = $value['CICLO'] ?? '';
                $nombre = htmlspecialchars($value['NOMBRE'] ?? '');
                $inicio = isset($value['INICIO']) ? (is_object($value['INICIO']) ? $value['INICIO']->format('Y-m-d') : $value['INICIO']) : '';
                $diasAtraso = $value['DIAS_ATRASO'] ?? '0';
                $tabla .= <<<HTML
                <tr style="padding: 0px !important;">
                    <td style="padding: 0px !important;">{$codCte}</td>
                    <td style="padding: 0px !important;">{$ciclo}</td>
                    <td style="padding: 0px !important;">{$nombre}</td>
                    <td style="padding: 0px !important;">{$inicio}</td>
                    <td style="padding: 0px !important;">{$diasAtraso}</td>
                </tr>
                HTML;
            }

            View::set('tabla', $tabla);
            $vista = "herramienta_rep_dia_atraso_busqueda";
        }

        View::set('header', $this->_contenedor->header(self::GetExtraHeader("Rep Dia de Atraso")));
        View::set('footer', $this->_contenedor->footer($extraFooter));
        View::render($vista);
    }

    public function generarExcelRepDiaAtraso()
    {
        require_once dirname(__DIR__) . '/../libs/PhpSpreadsheet/PhpSpreadsheet.php';

        $estilos = \PHPSpreadsheet::GetEstilosExcel();
        $soloCentrado = ['estilo' => $estilos['centrado']];

        $columnas = [
            \PHPSpreadsheet::ColumnaExcel('COD_CTE', 'Código Cliente', $soloCentrado),
            \PHPSpreadsheet::ColumnaExcel('CICLO', 'Ciclo', $soloCentrado),
            \PHPSpreadsheet::ColumnaExcel('NOMBRE', 'Nombre'),
            \PHPSpreadsheet::ColumnaExcel('INICIO', 'Inicio', ['estilo' => $estilos['fecha']]),
            \PHPSpreadsheet::ColumnaExcel('DIAS_ATRASO', 'Días de Atraso', $soloCentrado)
        ];

        $filas = HerramientasDao::ConsultarRepDiaAtraso();

        \PHPSpreadsheet::DescargaExcel('Rep_Dia_de_Atraso', 'Reporte', 'Rep Dia de Atraso', $columnas, $filas);
    }

    public function generarCsvRepDiaAtraso()
    {
        $filas = HerramientasDao::ConsultarRepDiaAtraso();

        $columnas = ['COD_CTE', 'CICLO', 'NOMBRE', 'INICIO', 'DIAS_ATRASO'];
        $titulos = ['Código Cliente', 'Ciclo', 'Nombre', 'Inicio', 'Días de Atraso'];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment;filename="Rep_Dia_de_Atraso.csv"');
        header('Cache-Control: max-age=0');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Pragma: public');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, $titulos, ',', '"');

        foreach ($filas as $fila) {
            if (is_array($fila)) {
                $linea = [];
                foreach ($columnas as $col) {
                    $valor = $fila[$col] ?? '';
                    if (is_object($valor) && method_exists($valor, 'format')) {
                        $valor = $valor->format('Y-m-d');
                    }
                    $linea[] = $valor;
                }
                fputcsv($output, $linea, ',', '"');
            }
        }

        fclose($output);
    }
}
