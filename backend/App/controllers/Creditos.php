<?php

namespace App\controllers;

defined("APPPATH") or die("Access denied");

use Core\View;
use Core\Controller;
use App\models\Creditos as CreditosDao;

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

    public function CambioSucursal()
    {
        $extraFooter = <<<HTML
            <script>
                function getParameterByName(name) {
                    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]")
                    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
                        results = regex.exec(location.search)
                    return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "))
                }

                function enviar_add(ciclo_p) {
                    credito = getParameterByName("Credito")
                    sucursal = document.getElementById("sucursal").value
                    ciclo = ciclo_p

                    $.ajax({
                        type: "POST",
                        url: "/Creditos/UpdateSucursal/",
                        data: { credito, sucursal, ciclo },
                        success: function (respuesta) {
                            respuesta = JSON.parse(respuesta)
                            if (respuesta.success) {
                                location.reload()
                            } else {
                                swal(respuesta.mensaje, {
                                    icon: "error"
                                })
                            }
                        }
                    })
                }
                
                function EditarSucursal(id_suc) {
                    credito = getParameterByName("Credito");
                    id_sucursal = id_suc;

                    $("#modal_cambio_sucursal").modal("show");
                }
            </script>
        HTML;

        $credito = $_GET['Credito'];
        $vista = "cambio_sucursal_all";

        if ($credito != '') {
            $credito_cambio = CreditosDao::SelectSucursalAllCreditoCambioSuc(['credito' => $credito]);

            if (!$credito_cambio['success'] || !isset($credito_cambio['datos'])) {
                $vista = "cambio_sucursal_busqueda_message";
            } else {
                $datos = $credito_cambio['datos'];
                $sucursales = CreditosDao::ListaSucursales();
                $ComboSucursal = '';
                foreach ($sucursales['datos'] as $key => $val2) {
                    $selected = $val2['ID_SUCURSAL'] == $datos['ID_SUCURSAL'] ? 'selected' : '';
                    $ComboSucursal .= "<option $selected value='{$val2['ID_SUCURSAL']}'>{$val2['SUCURSAL']}</option>";
                }

                View::set('Administracion', $datos);
                View::set('sucursal', $ComboSucursal);
                $vista = "cambio_sucursal_busqueda";
            }
        }

        View::set('header', $this->_contenedor->header(self::GetExtraHeader("Cambio de Sucursal")));
        View::set('footer', $this->_contenedor->footer($extraFooter));
        View::set('credito', $credito);
        View::render($vista);
    }

    public function UpdateSucursal()
    {
        echo json_encode(CreditosDao::UpdateSucursal($_POST));
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
