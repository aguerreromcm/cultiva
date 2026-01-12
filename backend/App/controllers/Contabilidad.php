<?php

namespace App\controllers;

defined("APPPATH") or die("Access denied");

use Core\View;
use Core\Controller;
use App\models\Contabilidad as ContabilidadDao;

class Contabilidad extends Controller
{

    private $_contenedor;

    public function __construct()
    {
        parent::__construct();
        $this->_contenedor = new Contenedor;
    }

    public function ConsultaGrupo()
    {
        $js = <<<HTML
            <script>
                {$this->mensajes}
                {$this->configuraTabla}
                {$this->actualizaDatosTabla}
                {$this->consultaServidor}
                {$this->respuestaError}
                {$this->respuestaSuccess}
                {$this->descargaExcel}

                const idTabla = "grupo"

                const getParametros = (post = true) => {
                    const p = {
                        grupo: $("#noGrupoBuscar").val(),
                        ciclo: $("#cicloBuscar").val()
                    }

                    if (post) return p
                    return Object.keys(p).map((key) => key + "=" + p[key]).join("&")
                }

                const buscarGrupo = () => {
                    consultaServidor("/Contabilidad/BuscaGrupo", getParametros(), (res) => {
                        if (!res.success) return respuestaError(idTabla, res.mensaje)
                        if (res.datos.length === 0) return respuestaError(idTabla, "No se encontraron registros para los parámetros solicitados.")
                        const datos = res.datos.map((item) => {
                            item.PRESTAMO = "$ " + parseFloat(item.PRESTAMO).toFixed(2)
                            item.SEGURO_FINANCIADO = "$ " + parseFloat(item.SEGURO_FINANCIADO).toFixed(2)
                            item.TOTAL_CREDITO = "$ " + parseFloat(item.TOTAL_CREDITO).toFixed(2)
                            item.GARANTIA = "$ " + parseFloat(item.GARANTIA).toFixed(2)
                            return item
                        })


                        respuestaSuccess(idTabla, res.datos)
                    })
                }

                const solonumeros = (e) => {
                    const key = e.which || e.keyCode;
                    if (key < 48 || key > 57) {
                        e.preventDefault();
                    }
                }

                $(document).ready(() => {
                    configuraTabla(idTabla)
                    $("#noGrupoBuscar").keypress(solonumeros)
                    $("#cicloBuscar").keypress(solonumeros)

                    $("#buscar").click(buscarGrupo)
                    $("#exportar").click(() => descargaExcel("/Contabilidad/ExportReporteGrupo/?" + getParametros(false)))
                })
            </script>
        HTML;

        View::set('header', $this->_contenedor->header(self::GetExtraHeader("Reporte por Grupo")));
        View::set('footer', $this->_contenedor->footer($js));
        View::render('contabilidad_consulta_grupo');
    }

    public function BuscaGrupo()
    {
        // Prueba cambio de repositorio
        echo json_encode(ContabilidadDao::BuscaGrupo($_POST));
    }

    public function ExportReporteGrupo()
    {
        $centrado = ['estilo' => \PHPSpreadsheet::GetEstilosExcel('centrado')];
        $moneda = ['estilo' => \PHPSpreadsheet::GetEstilosExcel('moneda'), 'total' => true];
        $fecha = ['estilo' => \PHPSpreadsheet::GetEstilosExcel('fecha')];

        $columnas = [
            \PHPSpreadsheet::ColumnaExcel('GRUPO', 'No. Grupo', $centrado),
            \PHPSpreadsheet::ColumnaExcel('NOMBRE_GRUPO', 'Nombre de Grupo'),
            \PHPSpreadsheet::ColumnaExcel('CLIENTE', 'No. Cliente', $centrado),
            \PHPSpreadsheet::ColumnaExcel('PRESTAMO', 'Préstamo', $moneda),
            \PHPSpreadsheet::ColumnaExcel('SEGURO_FINANCIADO', 'Seguro Financiado', $moneda),
            \PHPSpreadsheet::ColumnaExcel('TOTAL_CREDITO', 'Total del crédito', $moneda),
            \PHPSpreadsheet::ColumnaExcel('GARANTIA', 'Garantía', $moneda),
            \PHPSpreadsheet::ColumnaExcel('FECHA_INICIO', 'Fecha de inicio', $fecha)
        ];

        $filas = ContabilidadDao::BuscaGrupo($_GET);
        $filas = $filas['success'] ? $filas['datos'] : [];

        \PHPSpreadsheet::DescargaExcel('Reporte de grupo CULTIVA', 'Reporte', "Grupo {$_GET['grupo']}", $columnas, $filas);
    }
}
