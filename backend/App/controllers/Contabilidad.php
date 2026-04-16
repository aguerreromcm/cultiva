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

    /**
     * Vista del Reporte GL (análisis de garantías) con filtro por rango de fechas.
     */
    public function ReporteGL()
    {
        $hoy = date('Y-m-d');
        $primerDiaAnio = date('Y-01-01');

        $js = <<<HTML
            <script>
                {$this->mensajes}
                {$this->configuraTabla}
                {$this->actualizaDatosTabla}
                {$this->consultaServidor}
                {$this->respuestaError}
                {$this->respuestaSuccess}
                {$this->descargaExcel}

                const idTabla = "reporteGL"

                const columnasMoneda = [
                    "SDO_INI", "SDO_FIN",
                    "PAGO COMISION",
                    "DEVOLUCION POR CANCELACION DE CHEQUE",
                    "CONCILIACION COMISION",
                    "PAGO ADELANTADO",
                    "CANCELACION POR APLICACION A PAGO DE CREDITO",
                    "CANCELACION POR TRASPASO DE GARANTIA A CICLO SIGUIENTE",
                    "CANCELACION PAGO COMISION",
                    "CANCELACION DE CONCILIACION COMISION",
                    "CANCELACION DE PAGO ADELANTADO",
                    "MOVIMIENTO CANCELADO",
                    "TRASPASO DE GARANTIA A PAGO",
                    "DEVOLUCION POR DEPOSITO EXCEDENTE",
                    "CANCELACION DE CHEQUE DE DEVOLUCION DE GARANTIA"
                ]

                const formatoMoneda = (valor) => {
                    const num = parseFloat(valor)
                    const v = isNaN(num) ? 0 : num
                    return "$ " + v.toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                }

                const getParametros = (post = true) => {
                    const p = {
                        fechaInicial: $("#fechaInicial").val(),
                        fechaFinal: $("#fechaFinal").val()
                    }

                    if (post) return p
                    return Object.keys(p).map((key) => key + "=" + encodeURIComponent(p[key])).join("&")
                }

                const validaFechas = () => {
                    const fi = $("#fechaInicial").val()
                    const ff = $("#fechaFinal").val()
                    if (!fi || !ff) {
                        showError("Debe capturar la fecha inicial y la fecha final.")
                        return false
                    }
                    if (fi > ff) {
                        showError("La fecha inicial no puede ser mayor que la fecha final.")
                        return false
                    }
                    return true
                }

                const buscarReporteGL = () => {
                    if (!validaFechas()) return
                    consultaServidor("/Contabilidad/BuscaReporteGL", getParametros(), (res) => {
                        if (!res.success) return respuestaError(idTabla, res.mensaje)
                        if (!res.datos || res.datos.length === 0) return respuestaError(idTabla, "No se encontraron registros para el rango de fechas solicitado.")

                        const datos = res.datos.map((item) => {
                            const r = Object.assign({}, item)
                            columnasMoneda.forEach((c) => { r[c] = formatoMoneda(r[c]) })
                            return r
                        })

                        respuestaSuccess(idTabla, datos)
                    })
                }

                $(document).ready(() => {
                    configuraTabla(idTabla)
                    $("#buscar").click(buscarReporteGL)
                    $("#exportar").click(() => {
                        if (!validaFechas()) return
                        descargaExcel("/Contabilidad/ExportReporteGL/?" + getParametros(false))
                    })
                })
            </script>
        HTML;

        View::set('fechaInicial', $primerDiaAnio);
        View::set('fechaFinal', $hoy);
        View::set('fechaMaxima', $hoy);
        View::set('header', $this->_contenedor->header(self::GetExtraHeader("Reporte GL")));
        View::set('footer', $this->_contenedor->footer($js));
        View::render('contabilidad_reporte_gl');
    }

    /**
     * Devuelve los datos del Reporte GL en formato JSON.
     */
    public function BuscaReporteGL()
    {
        set_time_limit(600);
        $datos = [
            'fechaInicial' => isset($_POST['fechaInicial']) ? trim((string) $_POST['fechaInicial']) : null,
            'fechaFinal'   => isset($_POST['fechaFinal']) ? trim((string) $_POST['fechaFinal']) : null,
        ];

        if (!self::_validaFechaYMD($datos['fechaInicial']) || !self::_validaFechaYMD($datos['fechaFinal'])) {
            $this->Responde(false, 'Las fechas enviadas no son válidas (formato esperado AAAA-MM-DD).');
            return;
        }
        if ($datos['fechaInicial'] > $datos['fechaFinal']) {
            $this->Responde(false, 'La fecha inicial no puede ser mayor que la fecha final.');
            return;
        }

        echo json_encode(ContabilidadDao::GetReporteGL($datos));
    }

    /**
     * Descarga en Excel del Reporte GL según el rango de fechas.
     */
    public function ExportReporteGL()
    {
        set_time_limit(600);
        $datos = [
            'fechaInicial' => isset($_GET['fechaInicial']) ? trim((string) $_GET['fechaInicial']) : null,
            'fechaFinal'   => isset($_GET['fechaFinal']) ? trim((string) $_GET['fechaFinal']) : null,
        ];

        if (!self::_validaFechaYMD($datos['fechaInicial']) || !self::_validaFechaYMD($datos['fechaFinal']) || $datos['fechaInicial'] > $datos['fechaFinal']) {
            http_response_code(400);
            echo 'Rango de fechas inválido. Capture un rango correcto en formato AAAA-MM-DD.';
            return;
        }

        $estilos = \PHPSpreadsheet::GetEstilosExcel();
        $centrado = ['estilo' => $estilos['centrado']];
        $moneda = ['estilo' => $estilos['moneda'], 'total' => true];

        $columnas = [
            \PHPSpreadsheet::ColumnaExcel('REGION', 'Región'),
            \PHPSpreadsheet::ColumnaExcel('ASESOR', 'Asesor', $centrado),
            \PHPSpreadsheet::ColumnaExcel('ASESOR_NOMBRE', 'Nombre Asesor'),
            \PHPSpreadsheet::ColumnaExcel('CREDITO', 'Crédito', $centrado),
            \PHPSpreadsheet::ColumnaExcel('GRUPO', 'Grupo'),
            \PHPSpreadsheet::ColumnaExcel('SITUACION', 'Situación'),
            \PHPSpreadsheet::ColumnaExcel('SDO_INI', 'Saldo Inicial', $moneda),
            \PHPSpreadsheet::ColumnaExcel('SDO_FIN', 'Saldo Final', $moneda),
            \PHPSpreadsheet::ColumnaExcel('PAGO COMISION', 'Pago Comisión', $moneda),
            \PHPSpreadsheet::ColumnaExcel('DEVOLUCION POR CANCELACION DE CHEQUE', 'Devolución por Cancelación de Cheque', $moneda),
            \PHPSpreadsheet::ColumnaExcel('CONCILIACION COMISION', 'Conciliación Comisión', $moneda),
            \PHPSpreadsheet::ColumnaExcel('PAGO ADELANTADO', 'Pago Adelantado', $moneda),
            \PHPSpreadsheet::ColumnaExcel('CANCELACION POR APLICACION A PAGO DE CREDITO', 'Cancelación por Aplicación a Pago de Crédito', $moneda),
            \PHPSpreadsheet::ColumnaExcel('CANCELACION POR TRASPASO DE GARANTIA A CICLO SIGUIENTE', 'Cancelación por Traspaso de Garantía a Ciclo Siguiente', $moneda),
            \PHPSpreadsheet::ColumnaExcel('CANCELACION PAGO COMISION', 'Cancelación Pago Comisión', $moneda),
            \PHPSpreadsheet::ColumnaExcel('CANCELACION DE CONCILIACION COMISION', 'Cancelación de Conciliación Comisión', $moneda),
            \PHPSpreadsheet::ColumnaExcel('CANCELACION DE PAGO ADELANTADO', 'Cancelación de Pago Adelantado', $moneda),
            \PHPSpreadsheet::ColumnaExcel('MOVIMIENTO CANCELADO', 'Movimiento Cancelado', $moneda),
            \PHPSpreadsheet::ColumnaExcel('TRASPASO DE GARANTIA A PAGO', 'Traspaso de Garantía a Pago', $moneda),
            \PHPSpreadsheet::ColumnaExcel('DEVOLUCION POR DEPOSITO EXCEDENTE', 'Devolución por Depósito Excedente', $moneda),
            \PHPSpreadsheet::ColumnaExcel('CANCELACION DE CHEQUE DE DEVOLUCION DE GARANTIA', 'Cancelación de Cheque de Devolución de Garantía', $moneda),
        ];

        $resultado = ContabilidadDao::GetReporteGL($datos);
        $filas = (!empty($resultado['success']) && isset($resultado['datos'])) ? $resultado['datos'] : [];

        \PHPSpreadsheet::DescargaExcel(
            'Reporte GL CULTIVA',
            'Reporte GL',
            "Reporte GL del {$datos['fechaInicial']} al {$datos['fechaFinal']}",
            $columnas,
            $filas
        );
    }

    /**
     * Valida que una cadena tenga el formato AAAA-MM-DD y sea una fecha real.
     */
    private static function _validaFechaYMD($fecha)
    {
        if (empty($fecha)) return false;
        $d = \DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }
}
