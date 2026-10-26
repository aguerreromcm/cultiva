<?php

namespace App\controllers;

defined("APPPATH") or die("Access denied");

use Core\View;
use Core\Controller;
use Core\MasterDom;
use App\models\Creditos as CreditosDao;
use App\services\ReasignacionService;

class Creditos extends Controller
{
    private $_contenedor;

    function __construct()
    {
        parent::__construct();
        $this->_contenedor = new Contenedor;
    }

    public function ReporteReferencias()
    {
        $regSuc = CreditosDao::GetRegionSucursal();
        $regSuc = $regSuc['success'] ? json_encode($regSuc['datos']) : [];
        $suc = $_SESSION['cdgco'] ?? '';

        $js = <<<HTML
            <script>
                {$this->mensajes}
                {$this->configuraTabla}
                {$this->actualizaDatosTabla}
                {$this->consultaServidor}
                {$this->respuestaError}
                {$this->descargaExcel}

                const idTabla = "tablaPrincipal"
                const regSuc = JSON.parse('$regSuc')

                const getParametros = () => {
                    const p = {}

                    if ($("#credito").val()) p.credito = $("#credito").val()
                    else {
                        if ($("#situacion").val()) p.situacion = $("#situacion").val()
                        if ($("#region").val()) p.region = $("#region").val()
                        if ($("#sucursal").val()) p.sucursal = $("#sucursal").val()
                    }

                    return p
                }

                const buscarReferencias = () => {
                    consultaServidor("/Creditos/GetReporteReferencias", getParametros(), (res) => {
                        if (!res.success) return respuestaError(idTabla, res.mensaje)
                        if (res.datos.length === 0) return respuestaError(idTabla, "No se encontraron registros para los parámetros solicitados.")

                        const tipo = getElementoRef()
                        const datos = res.datos.map((item) => {
                            const paycash = getElementoRef(item.REF_PAGO_PAYCASH, item.REF_COMISION_PAYCASH)
                            const bancopel = getElementoRef(item.REF_PAGO_BANCOPPEL, item.REF_COMISION_BANCOPPEL)
                            const oxxo = getElementoRef(item.REF_PAGO_OXXO, item.REF_COMISION_OXXO)

                            return [
                                item.GRUPO,
                                item.CREDITO,
                                item.ULTIMO_CICLO,
                                item.SITUACION,
                                item.SUCURSAL,
                                item.REGION,
                                tipo.outerHTML,
                                paycash.outerHTML,
                                bancopel.outerHTML,
                                oxxo.outerHTML
                            ]
                        })

                        actualizaDatosTabla(idTabla, datos)
                        $(".resultado").toggleClass("conDatos", true)
                    })
                }

                const getElementoRef = (pago = "Pago", comision = "Comisión") => {
                    const tipo = document.createElement("div")
                    const pagos = document.createElement("div")
                    const hr = document.createElement("hr")
                    const comisiones = document.createElement("div")
                    const nd = "<b>No Disponible</b>"

                    pagos.innerHTML = "<span>" + (pago ?? nd) + "</span>"
                    comisiones.innerHTML = "<span>" + (comision ?? nd) + "</span>"

                    tipo.appendChild(pagos)
                    tipo.appendChild(hr)
                    tipo.appendChild(comisiones)
                    return tipo
                }

                const exportarExcel = () => {
                    const parametros = getParametros()
                    const qry = Object.keys(parametros).map((key) => key + "=" + parametros[key]).join("&")

                    descargaExcel("/Creditos/ExportReporteReferencias/?" + qry)
                }

                const actualizaSucursales = () => {
                    const region = $("#region").val()
                    $("#sucursal").empty()
                    $("#sucursal").append(new Option("Todas", ""))
                    
                    regSuc.filter((reg) => reg.REGION === region || region === "")
                        .sort((a, b) => a.NOMBRE_SUCURSAL.localeCompare(b.NOMBRE_SUCURSAL))
                        .forEach((suc) => {
                            if (suc.REGION === region || region === "") $("#sucursal").append(new Option(suc.NOMBRE_SUCURSAL, suc.SUCURSAL))
                        })
                }

                $(document).ready(() => {
                    configuraTabla(idTabla)
                    $("#buscar").click(buscarReferencias)
                    $("#descargarExcel").click(exportarExcel)

                    $("#region").append(new Option("Todas", ""))
                    regSuc.forEach((reg) => {
                        if ($("#region option[value='" + reg.REGION + "']").length === 0)
                            $("#region").append(new Option(reg.NOMBRE_REGION, reg.REGION))

                        if (reg.SUCURSAL === "$suc") $("#region").val(reg.REGION)
                    })
                    actualizaSucursales()

                    $("#region").change(actualizaSucursales)
                })
            </script>
        HTML;

        View::set('header', $this->_contenedor->header(self::GetExtraHeader("Reporte de Referencias")));
        View::set('footer', $this->_contenedor->footer($js));
        View::render('reporte_referencias');
    }

    public function GetReporteReferencias()
    {
        echo json_encode(CreditosDao::GetReporteReferencias($_POST));
    }

    public function ExportReporteReferencias()
    {
        $texto = ['estilo' => \PHPSpreadsheet::GetEstilosExcel('texto_centrado')];

        $columnas = [
            \PHPSpreadsheet::ColumnaExcel('GRUPO', 'Grupo'),
            \PHPSpreadsheet::ColumnaExcel('CREDITO', 'Crédito', $texto),
            \PHPSpreadsheet::ColumnaExcel('ULTIMO_CICLO', 'Ultimo Ciclo', $texto),
            \PHPSpreadsheet::ColumnaExcel('SITUACION', 'Situación'),
            \PHPSpreadsheet::ColumnaExcel('REGION', 'Región'),
            \PHPSpreadsheet::ColumnaExcel('SUCURSAL', 'Sucursal'),
            \PHPSpreadsheet::ColumnaExcel('Pago', [
                \PHPSpreadsheet::ColumnaExcel('REF_PAGO_PAYCASH', 'Paycash', $texto),
                \PHPSpreadsheet::ColumnaExcel('REF_PAGO_BANCOPPEL', 'Bancoppel', $texto),
                \PHPSpreadsheet::ColumnaExcel('REF_PAGO_OXXO', 'Oxxo', $texto),
            ]),
            \PHPSpreadsheet::ColumnaExcel('Comisión', [
                \PHPSpreadsheet::ColumnaExcel('REF_COMISION_PAYCASH', 'Paycash', $texto),
                \PHPSpreadsheet::ColumnaExcel('REF_COMISION_BANCOPPEL', 'Bancoppel', $texto),
                \PHPSpreadsheet::ColumnaExcel('REF_COMISION_OXXO', 'Oxxo', $texto),
            ]),
        ];

        $datos = CreditosDao::GetReporteReferencias($_GET);
        $filas = $datos['success'] ? $datos['datos'] : [];

        \PHPSpreadsheet::DescargaExcel('Referencias Cultiva', 'Reporte', 'Referencias', $columnas, $filas);
    }

    public function Reasignacion()
    {
        $credito = trim((string) ($_GET['Credito'] ?? ''));
        $registros = [];
        $mensajeError = (string) ($_SESSION['reasignacion_error'] ?? '');
        unset($_SESSION['reasignacion_error']);

        $cambios = isset($_SESSION['reasignacion_cambios']) && is_array($_SESSION['reasignacion_cambios'])
            ? $_SESSION['reasignacion_cambios']
            : [];
        unset($_SESSION['reasignacion_cambios']);

        $resultadoMasivo = isset($_SESSION['reasignacion_resultado']) && is_array($_SESSION['reasignacion_resultado'])
            ? $_SESSION['reasignacion_resultado']
            : null;
        unset($_SESSION['reasignacion_resultado']);

        if ($credito !== '') {
            $consulta = CreditosDao::SelectCreditoReasignacion($credito);
            if (is_array($consulta)) {
                foreach ($consulta as $fila) {
                    if (!is_array($fila) || trim((string) ($fila['GRUPO'] ?? '')) === '') {
                        continue;
                    }
                    $registros[] = $this->marcarReasignacionAplicada($fila, $cambios);
                }
            }
            if ($registros === []) {
                $mensajeError = 'No se encontró información para el crédito solicitado.';
            }
        }

        $extraHeader = '<title>Reasignación</title><link rel="shortcut icon" href="/img/logo.svg" type="image/x-icon">';
        View::set('header', $this->_contenedor->header($extraHeader));
        View::set('footer', $this->_contenedor->footer());
        View::set('credito', $credito);
        View::set('registros', $registros);
        View::set('asesores', CreditosDao::ListaAsesoresReasignacion());
        View::set('sucursales', CreditosDao::ListaSucursalesReasignacion());
        View::set('mensaje_error', $mensajeError);
        View::set('resultado_masivo', $resultadoMasivo);
        View::render('reasignacion');
    }

    public function UpdateReasignacion()
    {
        header('Content-Type: application/json; charset=utf-8');
        $credito = trim((string) MasterDom::getDataAll('credito'));
        $ciclo = trim((string) MasterDom::getDataAll('ciclo'));
        $asesor = trim((string) MasterDom::getDataAll('asesor'));
        $sucursal = trim((string) MasterDom::getDataAll('sucursal'));

        if ($credito === '' || $ciclo === '') {
            echo json_encode(['estatus' => 'ERROR', 'resultado' => 'Crédito y ciclo son obligatorios.']);
            return;
        }
        if ($asesor === '' && $sucursal === '') {
            echo json_encode(['estatus' => 'ERROR', 'resultado' => 'Debe seleccionar un asesor, una sucursal o ambos.']);
            return;
        }

        $antes = CreditosDao::SelectCreditoReasignacion($credito, $ciclo);
        $resultado = CreditosDao::EjecutarReasignacion(
            $credito,
            $ciclo,
            $asesor,
            $sucursal,
            (string) $this->__usuario
        );

        $estatus = strtoupper(trim((string) ($resultado['ESTATUS'] ?? 'ERROR')));
        if ($estatus === 'OK') {
            if (!isset($_SESSION['reasignacion_cambios']) || !is_array($_SESSION['reasignacion_cambios'])) {
                $_SESSION['reasignacion_cambios'] = [];
            }
            $_SESSION['reasignacion_cambios'][$this->claveReasignacion($credito, $ciclo)] = [
                'asesor' => is_array($antes) ? trim((string) ($antes['EJECUTIVO'] ?? '')) : '',
                'sucursal' => is_array($antes) ? trim((string) ($antes['SUCURSAL'] ?? '')) : '',
            ];
        }

        echo json_encode([
            'estatus' => $estatus,
            'resultado' => trim((string) ($resultado['RESULTADO'] ?? 'No fue posible realizar la reasignación.')),
            'redirect' => '/Creditos/Reasignacion/?Credito=' . rawurlencode($credito),
        ]);
    }

    private function claveReasignacion(string $credito, string $ciclo): string
    {
        return trim($credito) . '|' . trim($ciclo);
    }

    /**
     * Las columnas "nuevo" se derivan del estado actual del crédito: la sesión
     * solo guarda el valor previo para poder contrastarlo tras la reasignación.
     */
    private function marcarReasignacionAplicada(array $fila, array $cambios): array
    {
        $asesorActual = trim((string) ($fila['EJECUTIVO'] ?? ''));
        $sucursalActual = trim((string) ($fila['SUCURSAL'] ?? ''));

        $fila['ASESOR_ANTERIOR'] = $asesorActual;
        $fila['ASESOR_NUEVO'] = '';
        $fila['SUCURSAL_ANTERIOR'] = $sucursalActual;
        $fila['SUCURSAL_NUEVA'] = '';

        $clave = $this->claveReasignacion(
            (string) ($fila['NO_CREDITO'] ?? ''),
            (string) ($fila['CICLO'] ?? '')
        );
        if (!isset($cambios[$clave]) || !is_array($cambios[$clave])) {
            return $fila;
        }

        $asesorPrevio = trim((string) ($cambios[$clave]['asesor'] ?? ''));
        $sucursalPrevia = trim((string) ($cambios[$clave]['sucursal'] ?? ''));

        if ($asesorPrevio !== '' && $asesorPrevio !== $asesorActual) {
            $fila['ASESOR_ANTERIOR'] = $asesorPrevio;
            $fila['ASESOR_NUEVO'] = $asesorActual;
        }
        if ($sucursalPrevia !== '' && $sucursalPrevia !== $sucursalActual) {
            $fila['SUCURSAL_ANTERIOR'] = $sucursalPrevia;
            $fila['SUCURSAL_NUEVA'] = $sucursalActual;
        }

        return $fila;
    }

    public function ReasignacionLayout()
    {
        $estilos = \PHPSpreadsheet::GetEstilosExcel();
        $texto = ['estilo' => $estilos['texto_centrado']];
        $columnas = [
            \PHPSpreadsheet::ColumnaExcel('GRUPO', '[GRUPO]', $texto),
            \PHPSpreadsheet::ColumnaExcel('CICLO', 'CICLO', $texto),
            \PHPSpreadsheet::ColumnaExcel('ASESOR', '[ASESOR]', $texto),
            \PHPSpreadsheet::ColumnaExcel('EMPRESA', '[EMPRESA]', $texto),
            \PHPSpreadsheet::ColumnaExcel('NOM_SUCURSAL', '[NOM_SUCURSAL]'),
        ];
        \PHPSpreadsheet::DescargaExcel(
            'layout_reasignacion',
            'Reasignacion',
            'Reasignación',
            $columnas,
            []
        );
    }

    public function ReasignacionCargaMasiva()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: /Creditos/Reasignacion/');
            exit;
        }
        if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
            $this->redirigirReasignacionConError('No se recibió el archivo Excel.');
        }
        if ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $this->redirigirReasignacionConError(
                'Error al subir el archivo (código ' . (int) $_FILES['archivo']['error'] . ').'
            );
        }

        $extension = strtolower((string) pathinfo((string) $_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            $this->redirigirReasignacionConError('El archivo debe ser Excel (.xls o .xlsx).');
        }

        $directorio = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($directorio)) {
            @mkdir($directorio, 0755, true);
        }
        $destino = $directorio . DIRECTORY_SEPARATOR . 'reasignacion_' . uniqid('', true) . '.' . $extension;
        $guardado = @move_uploaded_file($_FILES['archivo']['tmp_name'], $destino);
        if (!$guardado) {
            $guardado = @copy($_FILES['archivo']['tmp_name'], $destino);
        }
        if (!$guardado || !is_readable($destino)) {
            $destinoAlterno = sys_get_temp_dir()
                . DIRECTORY_SEPARATOR
                . 'reasignacion_' . uniqid('', true) . '.' . $extension;
            $guardado = @copy($_FILES['archivo']['tmp_name'], $destinoAlterno);
            if ($guardado && is_readable($destinoAlterno)) {
                $destino = $destinoAlterno;
            } else {
                @unlink($destino);
                @unlink($destinoAlterno);
                $this->redirigirReasignacionConError(
                    'No se pudo guardar el archivo recibido para procesarlo.'
                );
            }
        }

        try {
            $resultado = ReasignacionService::cargaMasivaDesdeArchivo(
                $destino,
                (string) $this->__usuario
            );
        } catch (\Throwable $e) {
            $resultado = [
                'success' => false,
                'mensaje' => 'No fue posible procesar el archivo.',
                'error' => $e->getMessage(),
            ];
        } finally {
            @unlink($destino);
        }

        $datos = $resultado['datos'] ?? [];
        $_SESSION['reasignacion_resultado'] = [
            'resumen' => $resultado['mensaje'] ?? '',
            'detalle_error' => $resultado['error'] ?? '',
            'actualizados' => $datos['actualizados'] ?? [],
            'errores' => $datos['errores'] ?? [],
            'exito' => !empty($resultado['success']),
        ];
        header('Location: /Creditos/Reasignacion/');
        exit;
    }

    private function redirigirReasignacionConError(string $mensaje): void
    {
        $_SESSION['reasignacion_error'] = $mensaje;
        header('Location: /Creditos/Reasignacion/');
        exit;
    }

    public function ReportePrestamos()
    {
        $regSuc = CreditosDao::GetSucursales();
        $regSuc = $regSuc['success'] ? $regSuc['datos'] : [];
        $sucursales = json_encode($regSuc);

        $extraFooter = <<<HTML
            <script>
                {$this->mensajes}
                {$this->configuraTabla}
                {$this->actualizaDatosTabla}
                {$this->consultaServidor}
                {$this->respuestaError}
                {$this->respuestaSuccess}
                {$this->descargaExcel}

                const idTabla = "prestamos"
                const regSuc = JSON.parse('$sucursales')

                const getParametros = (post = true) => {
                    const p = {
                        fechaI: $("#fechaI").val(),
                        fechaF: $("#fechaF").val(),
                        region: $("#region").val(),
                        sucursal: $("#sucursal").val(),
                        sitSolicitud: $("#sitSolicitud").val(),
                        sitPrestamo: $("#sitPrestamo").val(),
                    }

                    if (post) return p
                    return Object.keys(p).map((key) => key + "=" + p[key]).join("&")
                }

                const getReporte = () => {
                    consultaServidor("/Creditos/GetReportePrestamos", getParametros(), (res) => {
                        if (!res.success) return respuestaError(idTabla, res.mensaje)
                        if (res.datos.length === 0) return respuestaError(idTabla, "No se encontraron registros para los parámetros solicitados.")

                        if (res.datos.length > 2000) return respuestaError(idTabla, "El reporte es demasiado grande para ser mostrado, reduzca el rango de fechas o descargue el reporte en Excel.")
                        
                        const datos = res.datos.map((item) => {
                            return [
                                item.CREDITO,
                                item.CICLO,
                                item.NOMBRE,
                                item.MUJERES,
                                item.HOMBRES,
                                item.REGION,
                                item.SUCURSAL,
                                item.SOLICITUD,
                                item.AUTORIZACION,
                                item.AUTORIZO,
                                item.DURACION,
                                item.TASA + " %",
                                item.INICIO,
                                item.FIN_CICLO,
                                item.SITUACION_SOLICITUD,
                                item.SITUACION_PRESTAMO,
                                formatoMoneda(item.PARCIALIDAD),
                                formatoMoneda(item.CANTSOLIC),
                                item.DIAS_MORA,
                                formatoMoneda(item.CANTENTRE),
                                formatoMoneda(item.INT_GEN),
                                item.NO_PUEDO_PAGAR,
                                formatoMoneda(item.CAPITAL_PAGADO),
                                formatoMoneda(item.INT_PAGADO),
                                formatoMoneda(item.TOTAL_PAGADO),
                                formatoMoneda(item.SALDO_CAP),
                                formatoMoneda(item.SALDO_INT),
                                formatoMoneda(item.SALDO_TOT),
                                formatoMoneda(item.MORA_TOT),
                                formatoMoneda(item.SALDO_GL),
                                item.ASESOR,
                                item.NOM_GERENTE,
                                item.TIPO_CARTERA
                            ]
                        })

                        respuestaSuccess(idTabla, datos)
                    })
                }

                const formatoMoneda = (valor) => {
                    if (valor === null || valor === undefined) return "$ 0.00"
                    return "$ " + parseFloat(valor).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                }

                const actualizaSucursales = () => {
                    const region = $("#region").val()
                    $("#sucursal").empty()
                    $("#sucursal").append(new Option("Todas", ""))

                    regSuc.filter((reg) => reg.ID_REGION === region || region === "")
                        .sort((a, b) => a.SUCURSAL.localeCompare(b.SUCURSAL))
                        .forEach((suc) => {
                            if (suc.ID_REGION === region || region === "") $("#sucursal").append(new Option(suc.SUCURSAL, suc.ID_SUCURSAL))
                        })
                }

                $(document).ready(() => {
                    $("#region").change(actualizaSucursales)
                    $("#generar").click(getReporte)
                    $("#exportar").click(() => descargaExcel("/Creditos/ExportReportePrestamos/?" + getParametros(false)))
                    
                    configuraTabla(idTabla)
                    actualizaSucursales()
                })
            </script>
        HTML;

        $regiones = '<option value="">Todas</option>';

        foreach ($regSuc as $key => $val) {
            $reg = "<option value='{$val['ID_REGION']}'>{$val['REGION']}</option>";

            if (strpos($regiones, $reg) === false) $regiones .= $reg;
        }

        View::set('header', $this->_contenedor->header(self::GetExtraHeader("Reporte de Prestamos")));
        View::set('footer', $this->_contenedor->footer($extraFooter));
        View::set('regiones', $regiones);
        View::render("creditos_reporte_prestamos");
    }

    public function GetReportePrestamos()
    {
        echo json_encode(CreditosDao::GetReportePrestamos($_POST));
    }

    public function ExportReportePrestamos()
    {
        $texto = ['estilo' => \PHPSpreadsheet::GetEstilosExcel('texto_centrado')];
        $centrado = ['estilo' => \PHPSpreadsheet::GetEstilosExcel('centrado')];
        $moneda = ['estilo' => \PHPSpreadsheet::GetEstilosExcel('moneda'), 'total' => true];
        $fecha = ['estilo' => \PHPSpreadsheet::GetEstilosExcel('fecha')];
        $porcentaje = ['estilo' => \PHPSpreadsheet::GetEstilosExcel('porcentaje_entero')];

        $columnas = [
            \PHPSpreadsheet::ColumnaExcel('CREDITO', 'Crédito', $texto),
            \PHPSpreadsheet::ColumnaExcel('CICLO', 'Ciclo', $texto),
            \PHPSpreadsheet::ColumnaExcel('NOMBRE', 'Nombre grupo'),
            \PHPSpreadsheet::ColumnaExcel('MUJERES', 'Mujeres', $centrado),
            \PHPSpreadsheet::ColumnaExcel('HOMBRES', 'Hombres', $centrado),
            \PHPSpreadsheet::ColumnaExcel('REGION', 'Región'),
            \PHPSpreadsheet::ColumnaExcel('SUCURSAL', 'Sucursal'),
            \PHPSpreadsheet::ColumnaExcel('SOLICITUD', 'Fecha de solicitud', $fecha),
            \PHPSpreadsheet::ColumnaExcel('AUTORIZACION', 'Fecha de autorización', $fecha),
            \PHPSpreadsheet::ColumnaExcel('AUTORIZO', 'Autorizo'),
            \PHPSpreadsheet::ColumnaExcel('DURACION', 'Duración'),
            \PHPSpreadsheet::ColumnaExcel('TASA', 'Tasa', $porcentaje),
            \PHPSpreadsheet::ColumnaExcel('INICIO', 'Fecha de inicio', $fecha),
            \PHPSpreadsheet::ColumnaExcel('FIN_CICLO', 'Fecha de fin', $fecha),
            \PHPSpreadsheet::ColumnaExcel('SITUACION_SOLICITUD', 'Situación de la solicitud'),
            \PHPSpreadsheet::ColumnaExcel('SITUACION_PRESTAMO', 'Situación del préstamo'),
            \PHPSpreadsheet::ColumnaExcel('PARCIALIDAD', 'Parcialidad', $moneda),
            \PHPSpreadsheet::ColumnaExcel('CANTSOLIC', 'Cantidad solicitada', $moneda),
            \PHPSpreadsheet::ColumnaExcel('DIAS_MORA', 'Días de mora'),
            \PHPSpreadsheet::ColumnaExcel('CANTENTRE', 'Cantidad entregada', $moneda),
            \PHPSpreadsheet::ColumnaExcel('INT_GEN', 'Interés generado', $moneda),
            \PHPSpreadsheet::ColumnaExcel('NO_PUEDO_PAGAR', 'NO PUEDO PAGAR', $moneda),
            \PHPSpreadsheet::ColumnaExcel('CAPITAL_PAGADO', 'Capital pagado', $moneda),
            \PHPSpreadsheet::ColumnaExcel('INT_PAGADO', 'Interés pagado', $moneda),
            \PHPSpreadsheet::ColumnaExcel('TOTAL_PAG', 'Total pagado', $moneda),
            \PHPSpreadsheet::ColumnaExcel('SALDO_CAP', 'Saldo capital', $moneda),
            \PHPSpreadsheet::ColumnaExcel('SALDO_INT', 'Saldo interés', $moneda),
            \PHPSpreadsheet::ColumnaExcel('SALDO_TOT', 'Saldo total', $moneda),
            \PHPSpreadsheet::ColumnaExcel('MORA_TOT', 'Mora total', $moneda),
            \PHPSpreadsheet::ColumnaExcel('SALDO_GL', 'Garantía', $moneda),
            \PHPSpreadsheet::ColumnaExcel('ASESOR', 'Asesor'),
            \PHPSpreadsheet::ColumnaExcel('NOM_GERENTE', 'Gerente'),
            \PHPSpreadsheet::ColumnaExcel('TIPO_CARTERA', 'Tipo de cartera'),
        ];

        $datos = CreditosDao::GetReportePrestamos($_GET);
        $filas = $datos['success'] ? $datos['datos'] : [];

        \PHPSpreadsheet::DescargaExcel('Prestamos Cultiva', 'Reporte de Prestamos', 'Prestamos Cultiva', $columnas, $filas);
    }

    public function AdminCorreos()
    {
        $extraFooter = <<<HTML
            <script>
                {$this->mensajes}
                {$this->consultaServidor}
                {$this->configuraTabla}
                {$this->actualizaDatosTabla}

                $(document).on("ready", () => {
                    $("#addCorreo").on("click", () => $("#modalCorreo").modal("show"))
                    $("#addGrupo").on("click", () => $("#modalGrupo").modal("show"))

                    $("#areaFiltro").on("change", getCorreos)
                    $("#sucursalFiltro").on("change", getCorreos)
                    $("#btnAgregar").on("click", addCorreoGrupo)
                    $("#btnQuitar").on("click", eliminarCorreoGrupo)
                    $("#nombre").on("change", validaCampos)
                    $("#correo").on("keyup", sugerenciasCorreo)
                    $("#correo").on("blur", () => {
                        const correo = $("#correo").val()
                        $("#sugerenciasCorreo").remove()
                        $("#correo").attr("list", "")
                        if (!correo) return showError("Debe ingresar un correo electrónico.")
                        if (!validaCorreo(correo)) return showError("El correo electrónico ingresado no es válido.")
                    })
                    $("#area").on("change", validaCampos)
                    $("#sucursal").on("change", validaCampos)
                    $("#guardarDireccion").on("click", addCorreo)
                    $("#guardarGrupo").on("click", addGrupo)
                    $("#buscarGrupo").on("keyup", buscarGrupos)

                    configuraTabla("tblCorreos", {noRegXvista: false})
                    configuraTabla("tblGrupo", {noRegXvista: false})
                    getCorreos()
                    getCorreoGrupo()

                    $(".dataTables_filter").css("width", "100%")
                })

                const getCorreos = () => {
                    const parametros = {}

                    if ($("#areaFiltro").val() !== "*") parametros.area = $("#areaFiltro").val()
                    if ($("#sucursalFiltro").val() !== "*") parametros.sucursal = $("#sucursalFiltro").val()

                    consultaServidor("/Creditos/GetCorreos", parametros, (respuesta) => {
                        if (!respuesta.success) return showError("Ocurrio un error al buscar los correos.")
                        if (respuesta.datos.length === 0) return showError("No se encontraron correos registrados.").then(() => actualizaDatosTabla("tblCorreos", []))

                        const correos = respuesta.datos.map((correo) => {
                            const checador = "<input type='checkbox' name='correo' value='" + correo.ID + "' onchange='compruebaCorreoGrupo(event)'>"

                            return [
                                checador,
                                correo.NOMBRE,
                                correo.CORREO,
                                correo.AREA,
                                correo.SUCURSAL
                            ]
                        })

                        actualizaDatosTabla("tblCorreos", correos)
                    })
                }

                let idsGrupo = new Set()

                const getCorreoGrupo = () => {
                    const grupo = $("#idGrupoSeleccionado").val()

                    consultaServidor("/Creditos/GetCorreosGrupo", { grupo }, (respuesta) => {
                        if (!respuesta.success) return showError("Ocurrio un error al buscar los grupos.")

                        idsGrupo = new Set(respuesta.datos.map((grupo) => String(grupo.ID_CORREO)))
                        const grupos = respuesta.datos.map((grupo) => {
                            return [
                                grupo.EDITABLE == 1 ? "<input type='checkbox' name='grupo' value='" + grupo.ID_CORREO + "'>" : "",
                                grupo.CORREO
                            ]
                        })

                        actualizaDatosTabla("tblGrupo", grupos)
                    })
                }

                const correoEnGrupo = (id) => idsGrupo.has(String(id))

                const addCorreoGrupo = () => {
                    const correosNuevos = []
                    $("#tblCorreos").DataTable().$("input[type='checkbox']:checked").each((index, element) => {
                        if (!correoEnGrupo($(element).val())) correosNuevos.push($(element).val())
                        else element.checked = false
                    })

                    if (correosNuevos.length === 0) return showError("Seleccione al menos un correo que no esté en el grupo para agregarlo.")

                    const grupo = $("#idGrupoSeleccionado").val()
                    if (!grupo) return showError("Selecciones un grupo para agregar los correos.")

                    consultaServidor("/Creditos/AgregaCorreoGrupo", { grupo, correos: correosNuevos }, (respuesta) => {
                        if (!respuesta.success) return showError("Ocurrio un error al agregar los correos al grupo.")

                        $("#tblCorreos").DataTable().$("input[type='checkbox']").prop("checked", false)
                        actualizaListaGrupos($("#grupoSeleccionado").text())
                        showSuccess("Correos agregados al grupo correctamente.")
                    })
                }

                const eliminarCorreoGrupo = () => {
                    const correos = []
                    $("#tblGrupo").DataTable().$("input[type='checkbox']:checked").each((index, element) => {
                        correos.push($(element).val())
                    })

                    if (correos.length === 0) return showError("Seleccione al menos un correo para quitar del grupo.")

                    const grupo = $("#idGrupoSeleccionado").val()
                    if (!grupo) return showError("Selecciones un grupo para quitar los correos.")

                    consultaServidor("/Creditos/EliminaCorreoGrupo", { grupo, correos }, (respuesta) => {
                        if (!respuesta.success) return showError("Ocurrio un error al quitar los correos del grupo.")

                        actualizaListaGrupos($("#grupoSeleccionado").text())
                        showSuccess("Correos quitados del grupo correctamente.")
                    })
                }

                const compruebaCorreoGrupo = (e) => {
                    if (!e.target.checked) return
                    e.target.checked = false

                    if ($("#idGrupoSeleccionado").val() === "") return showError("Debe seleccionar un grupo para agregar correos.")
                    if (correoEnGrupo(e.target.value))
                        return showError("El correo seleccionado ya está agregado al grupo " + $("#grupoSeleccionado").text() + ".")

                    e.target.checked = true
                }

                const validaCampos = (e) => {
                    $("#guardarDireccion").prop("disabled", (!$("#nombre").val() || !$("#correo").val() || !$("#area").val() || !$("#sucursal").val()))
                }

                const addCorreo = () => {
                    if (!$("#nombre").val()) return showError("Ingrese el nombre del usuario.")
                    if (!$("#correo").val()) return showError("Ingrese el correo electrónico.")
                    if (!validaCorreo($("#correo").val())) return showError("El correo electrónico ingresado no es válido.")
                    if (!$("#area").val()) return showError("Seleccione un área.")
                    if (!$("#sucursal").val()) return showError("Seleccione una sucursal.")

                    const registro = {
                        nombre: $("#nombre").val(),
                        correo: $("#correo").val(),
                        area: $("#area").val(),
                        sucursal: $("#sucursal").val()
                    }

                    consultaServidor("/Creditos/AgregaCorreo", registro, (respuesta) => {
                        if (!respuesta.success) return showError("Ocurrio un error al registrar el correo.")

                        showSuccess("Correo registrado correctamente.")
                        getCorreos()
                    })

                    $("#nombre").val("")
                    $("#correo").val("")
                    $("#area").val("")
                    $("#sucursal").val("")
                    $("#guardarDireccion").prop("disabled", true)

                    $("#modalCorreo").modal("hide")
                }

                const validaCorreo = (correo) => {
                    const regexCorreo = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/
                    return (!correo || !regexCorreo.test(correo)) ? false : true
                }

                const addGrupo = () => {
                    const grupo = $("#nombreGrupo").val().trim()
                    if (!grupo) return showError("Ingrese un nombre para el nuevo grupo.")

                    const coincidencias = $("#grupoFiltro li .nombreGrupo").filter((index, element) => element.innerText.trim().toLowerCase() === grupo.toLowerCase())
                    if (coincidencias.length > 0) return showError("El grupo " + grupo + " ya existe.")

                    consultaServidor("/Creditos/AgregaGrupo", { grupo }, (respuesta) => {
                        if (!respuesta.success) return showError("Ocurrio un error al registrar el grupo.")

                        showSuccess("Grupo registrado correctamente.")
                        actualizaListaGrupos(grupo)
                    })

                    $("#nombreGrupo").val("")
                    $("#modalGrupo").modal("hide")
                }

                const actualizaListaGrupos = (grupo = null) => {
                    consultaServidor("/Creditos/GetParametros", null, (respuesta) => {
                        if (!respuesta.success) return showError("Ocurrio un error al buscar los grupos.")

                        $("#grupoFiltro").empty()
                        $("#grupoFiltro").append(respuesta.datos.grupo)
                        const encontrado = grupo ? $("#grupoFiltro li .nombreGrupo").filter((index, element) => element.innerText === grupo) : $()
                        if (encontrado.length > 0) encontrado.click()
                        else {
                            $("#grupoSeleccionado").text("Seleccionar grupo")
                            $("#idGrupoSeleccionado").val("")
                            getCorreoGrupo()
                        }
                    })
                }

                const sugerenciasCorreo = () => {
                    if ($("#correo").val().indexOf("@") !== -1) {
                        const empresas = ["masconmenos.com.mx", "financieracultiva.com"]

                        const correo = $("#correo").val().split("@")[0]
                        const lista = empresas.map((empresa) => {
                            return "<option value='" + correo + "@" + empresa + "'>" + correo + "@" + empresa + "</option>"
                        })

                        $("#sugerenciasCorreo").remove()
                        const datalist = $("<datalist id='sugerenciasCorreo'>" + lista.join("") + "</datalist>")
                        $("#correo").after(datalist).attr("list", "sugerenciasCorreo")
                    } else {
                        $("#sugerenciasCorreo").remove()
                        $("#correo").attr("list", "")
                    }
                    validaCampos()
                }

                const seleccionGrupo = (id, grupo) => {
                    $("#idGrupoSeleccionado").val(id)
                    $("#grupoSeleccionado").text(grupo)
                    getCorreoGrupo()
                }

                const eliminarGrupo = (id, grupo) => {
                    confirmarMovimiento("Administración de correos", "¿Esta seguro de eliminar el grupo " + grupo + "?")
                    .then((continuar) => {
                        if (!continuar) return
                        consultaServidor("/Creditos/EliminaGrupo", { grupo: id }, (respuesta) => {
                            if (!respuesta.success) return showError(respuesta.mensaje || "Ocurrio un error al eliminar el grupo.")

                            showSuccess("Grupo eliminado correctamente.")
                            actualizaListaGrupos($("#idGrupoSeleccionado").val() === id ? null : $("#grupoSeleccionado").text())
                        })
                    })
                }

                const buscarGrupos = () => {
                    $("#sinResultados").hide()
                    const buscar = $("#buscarGrupo").val().toLowerCase()

                    const encontrados = $("#grupoFiltro li").filter((index, element) => {
                        const elemento = $(element).find(".nombreGrupo")
                        const textoOriginal = elemento.text()
                        const textoMinuscula = textoOriginal.toLowerCase()
                        const indexMatch = textoMinuscula.indexOf(buscar)

                        if (indexMatch !== -1) {
                            const parteAntes = textoOriginal.substring(0, indexMatch)
                            const parteCoincidente = textoOriginal.substring(indexMatch, indexMatch + buscar.length)
                            const parteDespues = textoOriginal.substring(indexMatch + buscar.length)

                            if (buscar === "") elemento.text(textoOriginal)
                            else elemento.html(parteAntes + "<mark>" + parteCoincidente + "</mark>" + parteDespues)

                            $(element).show()
                            return true
                        } else {
                            elemento.text(textoOriginal)
                            $(element).hide()
                            return false
                        }
                    })

                    if (encontrados.length === 0) $("#sinResultados").show()
                }
            </script>
        HTML;

        $prm = $this->GetParametros(true);
        $prm = $prm['datos'];

        View::set('header', $this->_contenedor->header(self::GetExtraHeader("Administración de correos")));
        View::set('footer', $this->_contenedor->footer($extraFooter));
        View::set('opcArea', $prm['area']);
        View::set('opcSucursal', $prm['sucursal']);
        View::set('opcGrupo', $prm['grupo']);
        View::set('opcSucursales', $prm['sucursales']);
        View::set('opcAreas', $prm['areas']);
        View::render('creditos_adminCorreos');
    }

    public function GetParametros($ret = false)
    {
        $parametros = CreditosDao::GetParametrosCorreos();

        $opcArea = "<option value='*'>Todas</option>";
        $opcSucursal = "<option value='*'>Todas</option>";
        $opcGrupo = "";
        $opcSucursales = "<option value=''>Seleccione una sucursal</option>";
        $opcAreas = "<option value=''>Selecciona una opción</option>";

        if ($parametros['success']) {
            foreach ($parametros['datos'] as $parametro) {
                if ($parametro['TIPO'] === 'AREA') {
                    $opcArea .= "<option value='{$parametro['VALOR']}'>{$parametro['MOSTRAR']}</option>";
                    $opcAreas .= "<option value='{$parametro['VALOR']}'>{$parametro['MOSTRAR']}</option>";
                }
                if ($parametro['TIPO'] === 'SUCURSAL') $opcSucursal .= "<option value='{$parametro['VALOR']}'>{$parametro['MOSTRAR']}</option>";

                if ($parametro['TIPO'] === 'GRUPO') {
                    $boton = '';

                    if ($parametro['USUARIOS'] == 0) $boton = "<button type='button' class='btn btn-danger btn-sm' onclick='eliminarGrupo(\"{$parametro['VALOR']}\", \"{$parametro['MOSTRAR']}\")' style='grid-column: 2;'>
                        <span class='glyphicon glyphicon-trash'></span>
                    </button>";

                    $opcGrupo .= "<li class='dropdown-item d-flex justify-content-between align-items-center'>
                        <div style='display: grid; grid-template-columns: 1fr auto .3fr; width: 100%; gap: 20px; align-items: center; padding: 5px;'>
                            <span style='grid-column: 1; cursor: pointer;' class='nombreGrupo' onclick='seleccionGrupo(\"{$parametro['VALOR']}\", \"{$parametro['MOSTRAR']}\")'>{$parametro['MOSTRAR']}</span> 
                            $boton
                            <span style='grid-column: 3; text-align: right;'>{$parametro['USUARIOS']}&nbsp;<span class='glyphicon glyphicon-user'></span></span>
                        </div>
                    </li>";
                }
                if ($parametro['TIPO'] === 'SUCURSALES') $opcSucursales .= "<option value='{$parametro['VALOR']}'>{$parametro['MOSTRAR']}</option>";
            }
        }

        $res = [
            'success' => $parametros['success'],
            'mensaje' => $parametros['mensaje'],
            'datos' => [
                'area' => $opcArea,
                'sucursal' => $opcSucursal,
                'grupo' => $opcGrupo,
                'sucursales' => $opcSucursales,
                'areas' => $opcAreas
            ]
        ];

        if (!$ret) echo json_encode($res);
        else return $res;
    }

    public function GetCorreos()
    {
        echo json_encode(CreditosDao::GetCorreos($_POST));
    }

    public function GetCorreosGrupo()
    {
        if (isset($_POST['grupo']) && $_POST['grupo'] !== '')
            echo json_encode(CreditosDao::GetCorreosGrupo($_POST));
        else echo json_encode(["success" => true, "datos" => []]);
    }

    public function AgregaCorreoGrupo()
    {
        $_POST['usuario'] = $this->__usuario;
        echo json_encode(CreditosDao::AgregaCorreoGrupo($_POST));
    }

    public function EliminaCorreoGrupo()
    {
        echo json_encode(CreditosDao::EliminaCorreoGrupo($_POST));
    }

    public function AgregaCorreo()
    {
        $_POST['usuario'] = $this->__usuario;
        echo json_encode(CreditosDao::AgregaCorreo($_POST));
    }

    public function AgregaGrupo()
    {
        $_POST['usuario'] = $this->__usuario;
        echo json_encode(CreditosDao::AgregaGrupo($_POST));
    }

    public function EliminaGrupo()
    {
        echo json_encode(CreditosDao::EliminaGrupo($_POST));
    }
}
