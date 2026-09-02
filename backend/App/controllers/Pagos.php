<?php

namespace App\controllers;

defined("APPPATH") or die("Access denied");

use App\services\ImportacionPagosService;
use Core\Controller;
use Core\View;

class Pagos extends Controller
{
    private $_contenedor;

    public function __construct()
    {
        parent::__construct();
        $this->_contenedor = new Contenedor;
        View::set('header', $this->_contenedor->header());
        View::set('footer', $this->_contenedor->footer());
    }

    /**
     * Vista principal: importación de layouts de corresponsales.
     */
    public function ImportarPagos()
    {
        $extraFooter = <<<HTML
        <script>
            {$this->mensajes}
            {$this->consultaServidor}
            {$this->configuraTabla}
            {$this->actualizaDatosTabla}

            const idTablaPreview = "tabla-preview-importacion";
            const idTablaHistorial = "tabla-historial-importacion";
            const idTablaIncidencias = "tabla-incidencias-importacion";
            const idTablaDetalle = "tabla-detalle-importacion";

            let previewActual = null;

            const formateaMoneda = (v) => {
                const n = parseFloat(v);
                if (isNaN(n)) return "-";
                return n.toLocaleString("es-MX", { style: "currency", currency: "MXN" });
            };

            const renderPreview = (filas) => {
                const datos = (filas || []).map((f) => {
                    const motivoDup = (f.MOTIVO_DUPLICADO || f.MOTIVO_INCIDENCIA || "").trim();
                    const motivo = (f.MOTIVO_INCIDENCIA || "").trim();
                    let estatus;
                    if (f.DUPLICADO) {
                        estatus = "<span class='badge-duplicado' title='" + motivoDup.replace(/'/g, "&#39;") + "'>Duplicado</span>"
                            + (motivoDup ? "<span class='celda-secundaria ci-motivo'>" + motivoDup + "</span>" : "");
                    } else if (f.INCIDENCIA) {
                        estatus = "<span class='badge-revisar' title='" + motivo.replace(/'/g, "&#39;") + "'>Revisar</span>"
                            + (motivo ? "<span class='celda-secundaria ci-motivo'>" + motivo + "</span>" : "");
                    } else {
                        estatus = "<span class='badge-ok'>OK</span>";
                    }
                    return [
                        f.FECHA_FMT || f.FECHA || "-",
                        '<span class="celda-principal">' + (f.REFERENCIA || f.REFERENCIA_ORIGINAL || "-") + "</span>",
                        '<span class="celda-principal">' + (f.CDGNS || "-") + "</span>",
                        f.CICLO || "-",
                        formateaMoneda(f.MONTO),
                        estatus
                    ];
                });
                actualizaDatosTabla(idTablaPreview, datos);
            };

            const cargarHistorial = () => {
                $.getJSON("/Pagos/ListarHistorial/", (res) => {
                    if (!res.success) return;
                    const datos = (res.datos || []).map((f) => {
                        const archivo = f.ARCHIVO || "";
                        const idImp = f.ID_IMPORTACION != null ? f.ID_IMPORTACION : "";
                        const btn = "<button type='button' class='btn btn-info btn-xs btn-ver-importacion' " +
                            "data-archivo='" + String(archivo).replace(/'/g, "&#39;") + "' " +
                            "data-id='" + idImp + "' title='Ver registros'>" +
                            "<i class='fa fa-list'></i> Ver</button>";
                        return [
                            archivo || "-",
                            f.FECHA_PAGO || "-",
                            f.REGISTROS || 0,
                            formateaMoneda(f.MONTO_TOTAL),
                            f.INCIDENCIAS || 0,
                            f.F_IMPORTACION || "-",
                            btn
                        ];
                    });
                    actualizaDatosTabla(idTablaHistorial, datos);
                });
            };

            const verDetalleImportacion = (archivo, idImportacion) => {
                if (!archivo) return showWarning("No se pudo identificar el archivo.");
                swal({ text: "Cargando registros...", icon: "/img/wait.gif", button: false, closeOnClickOutside: false, closeOnEsc: false });
                $.getJSON("/Pagos/DetalleImportacion/", { archivo: archivo, id_importacion: idImportacion || "" }, (res) => {
                    swal.close();
                    if (!res.success) return showError(res.mensaje || "No se pudieron cargar los registros.");
                    const data = res.datos || {};
                    const filas = data.registros || [];
                    $("#detalle_archivo_nombre").text(data.archivo || archivo);
                    $("#detalle_resumen").text(" · " + (data.total || filas.length) + " registro(s)");
                    const datos = filas.map((f) => {
                        const estatus = parseInt(f.INCIDENCIA, 10) === 1
                            ? "<span class='badge-revisar'>Revisar</span>"
                            : "<span class='badge-ok'>OK</span>";
                        return [
                            f.FECHA_FMT || f.FECHA || "-",
                            '<span class="celda-principal">' + (f.REFERENCIA || "-") + "</span>",
                            '<span class="celda-principal">' + (f.CDGNS || "-") + "</span>",
                            f.CICLO || "-",
                            formateaMoneda(f.MONTO),
                            estatus
                        ];
                    });
                    actualizaDatosTabla(idTablaDetalle, datos);
                    $("#modalDetalleImportacion").modal("show");
                }).fail(() => { swal.close(); showError("Error al consultar el detalle."); });
            };

            const cargarIncidencias = () => {
                $.getJSON("/Pagos/ListarIncidencias/", (res) => {
                    if (!res.success) return;
                    const datos = (res.datos || []).map((f) => {
                        const btn = "<button type='button' class='btn btn-warning btn-xs btn-corregir' " +
                            "data-fecha='" + (f.FECHA || "") + "' data-secuencia='" + (f.SECUENCIA || "") + "' " +
                            "data-referencia='" + (f.REFERENCIA || "") + "' data-monto='" + (f.MONTO || "") + "'>" +
                            "<i class='fa fa-edit'></i> Corregir</button>";
                        return [
                            f.FECHA_FMT || f.FECHA || "-",
                            '<span class="celda-principal">' + (f.REFERENCIA || "-") + "</span>",
                            '<span class="celda-principal">' + (f.CDGNS || "-") + "</span>",
                            f.CICLO || "-",
                            formateaMoneda(f.MONTO),
                            '<span class="celda-secundaria">' + (f.ARCHIVO || "-") + "</span>",
                            btn
                        ];
                    });
                    actualizaDatosTabla(idTablaIncidencias, datos);
                });
            };

            const previsualizar = () => {
                const archivo = $("#archivo_layout")[0].files[0];
                const corresponsal = $("#corresponsal").val();
                if (!archivo) return showWarning("Seleccione un archivo.");
                if (!corresponsal) return showWarning("Seleccione el corresponsal.");

                const fd = new FormData();
                fd.append("archivo", archivo);
                fd.append("corresponsal", corresponsal);

                swal({ text: "Leyendo archivo...", icon: "/img/wait.gif", button: false, closeOnClickOutside: false, closeOnEsc: false });
                $.ajax({
                    url: "/Pagos/PrevisualizarImportacion/",
                    type: "POST",
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: (res) => {
                        swal.close();
                        try { res = typeof res === "string" ? JSON.parse(res) : res; } catch (e) { return showError("Error al procesar respuesta."); }
                        const data = res.datos || null;
                        if (data && data.filas) {
                            previewActual = data;
                            const inc = data.incidencias || 0;
                            const dup = data.duplicados || 0;
                            const total = data.total || 0;
                            let resumen = total + " registro" + (total === 1 ? "" : "s");
                            if (inc) resumen += " · " + inc + " incidencia" + (inc === 1 ? "" : "s");
                            if (dup) resumen += " · " + dup + " duplicado" + (dup === 1 ? "" : "s");
                            $("#resumen-preview").text(resumen);
                            renderPreview(data.filas || []);
                            $("#panel-preview").show();
                            $("#btn_confirmar").prop("disabled", !res.success || dup > 0);
                        }
                        if (!res.success) {
                            return showError(res.mensaje || "No se pudo leer el archivo.");
                        }
                        if ((data.duplicados || 0) > 0) {
                            showWarning("Hay " + data.duplicados + " pago(s) que ya existen en el sistema. No se puede confirmar la importación.");
                        } else if ((data.incidencias || 0) > 0) {
                            showWarning("Hay " + data.incidencias + " registro(s) con referencia no identificada. Se importarán con crédito 000000 para revisión posterior.");
                        }
                    },
                    error: () => { swal.close(); showError("Error al previsualizar el archivo."); }
                });
            };

            const confirmar = async () => {
                if (!previewActual) return showWarning("Primero previsualice el archivo.");
                if ((previewActual.duplicados || 0) > 0) {
                    return showError("No se puede importar: hay pagos que ya existen en el sistema.");
                }
                const ok = await confirmarMovimiento(
                    "Confirmar importación",
                    "Se registrarán " + (previewActual.total || 0) + " pagos para el proceso de cierre. ¿Desea continuar?"
                );
                if (!ok) return;

                const archivo = $("#archivo_layout")[0].files[0];
                const corresponsal = $("#corresponsal").val();
                const fd = new FormData();
                fd.append("archivo", archivo);
                fd.append("corresponsal", corresponsal);

                swal({ text: "Importando pagos...", icon: "/img/wait.gif", button: false, closeOnClickOutside: false, closeOnEsc: false });
                $.ajax({
                    url: "/Pagos/ConfirmarImportacion/",
                    type: "POST",
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: (res) => {
                        swal.close();
                        try { res = typeof res === "string" ? JSON.parse(res) : res; } catch (e) { return showError("Error al procesar respuesta."); }
                        if (!res.success) return showError(res.mensaje || "No se pudo importar.");
                        showSuccess(res.mensaje).then(() => {
                            $("#archivo_layout").val("");
                            $("#panel-preview").hide();
                            previewActual = null;
                            $("#btn_confirmar").prop("disabled", true);
                            cargarHistorial();
                            cargarIncidencias();
                        });
                    },
                    error: () => { swal.close(); showError("Error al importar pagos."); }
                });
            };

            const opcionesTabla = () => ({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
                order: [],
                autoWidth: false,
                language: {
                    emptyTable: "No hay datos disponibles",
                    paginate: { previous: "Anterior", next: "Siguiente" },
                    info: "Mostrando de _START_ a _END_ de _TOTAL_ registros",
                    infoEmpty: "Sin registros para mostrar",
                    zeroRecords: "No se encontraron registros",
                    lengthMenu: "Mostrar _MENU_ registros por página",
                    search: "Buscar:"
                }
            });

            $(document).ready(function () {
                $("#" + idTablaPreview).DataTable(opcionesTabla());
                $("#" + idTablaHistorial).DataTable({
                    ...opcionesTabla(),
                    order: [[5, "desc"]],
                    columnDefs: [{ orderable: false, targets: 6 }]
                });
                $("#" + idTablaIncidencias).DataTable({ ...opcionesTabla(), order: [[0, "desc"]] });
                $("#" + idTablaDetalle).DataTable(opcionesTabla());

                $("#btn_previsualizar").click(previsualizar);
                $("#btn_confirmar").click(confirmar);

                $(document).on("click", ".btn-ver-importacion", function () {
                    verDetalleImportacion($(this).data("archivo"), $(this).data("id"));
                });

                $("#modalDetalleImportacion").on("shown.bs.modal", function () {
                    const dt = $("#" + idTablaDetalle).DataTable();
                    if (dt) dt.columns.adjust();
                });
                $("#corresponsal").change(() => {
                    const c = $("#corresponsal").val();
                    let ext = "";
                    if (c === "OXXO") ext = ".dat";
                    else if (c === "PAYCASH") ext = ".csv";
                    else if (c === "BANCOPPEL") ext = ".xls,.xlsx,.xsl,.csv";
                    $("#archivo_layout").attr("accept", ext);
                    $("#hint-extension").text(ext ? "Extensiones: " + ext : "");
                });

                $(document).on("click", ".btn-corregir", function () {
                    const fecha = $(this).data("fecha");
                    const secuencia = $(this).data("secuencia");
                    const referencia = $(this).data("referencia");
                    const monto = $(this).data("monto");
                    $("#corr_fecha").val(fecha);
                    $("#corr_secuencia").val(secuencia);
                    $("#corr_referencia").text(referencia);
                    $("#corr_monto").text(formateaMoneda(monto));
                    $("#corr_credito").val("");
                    $("#corr_ciclo").val("");
                    $("#modalCorregirIncidencia").modal("show");
                });

                $("#btn_guardar_correccion").click(async () => {
                    const credito = ($("#corr_credito").val() || "").trim();
                    const ciclo = ($("#corr_ciclo").val() || "").trim();
                    if (!credito || !ciclo) {
                        return showWarning("Capture el crédito y el ciclo.");
                    }
                    const ok = await confirmarMovimiento(
                        "Confirmar corrección",
                        "Se actualizará el pago con crédito " + credito + " y ciclo " + ciclo + ". ¿Desea continuar?"
                    );
                    if (!ok) return;

                    const datos = {
                        fecha: $("#corr_fecha").val(),
                        secuencia: $("#corr_secuencia").val(),
                        credito: credito,
                        ciclo: ciclo
                    };
                    consultaServidor("/Pagos/CorregirIncidencia/", datos, (res) => {
                        if (!res.success) return showError(res.mensaje);
                        showSuccess(res.mensaje).then(() => {
                            $("#modalCorregirIncidencia").modal("hide");
                            cargarIncidencias();
                            cargarHistorial();
                        });
                    });
                });

                cargarHistorial();
                cargarIncidencias();
            });
        </script>
        HTML;

        $extraHeader = $this->GetExtraHeader('Importar Pagos')
            . '<link href="/css/pagos-importar.css" rel="stylesheet">';
        View::set('header', $this->_contenedor->header($extraHeader));
        View::set('footer', $this->_contenedor->footer($extraFooter));
        View::render('pagos_importar');
    }

    public function PrevisualizarImportacion()
    {
        $this->procesarArchivo(function ($ruta, $nombre, $corresponsal) {
            return ImportacionPagosService::previsualizar($ruta, $nombre, $corresponsal);
        });
    }

    public function ConfirmarImportacion()
    {
        $this->procesarArchivo(function ($ruta, $nombre, $corresponsal) {
            return ImportacionPagosService::confirmarImportacion($ruta, $nombre, $corresponsal, $this->__usuario);
        });
    }

    public function ListarIncidencias()
    {
        echo json_encode(ImportacionPagosService::listarIncidencias(
            $_GET['fecha_desde'] ?? null,
            $_GET['fecha_hasta'] ?? null
        ));
    }

    public function ListarHistorial()
    {
        echo json_encode(ImportacionPagosService::listarHistorial());
    }

    public function DetalleImportacion()
    {
        echo json_encode(ImportacionPagosService::detalleImportacion([
            'archivo' => $_GET['archivo'] ?? ($_POST['archivo'] ?? ''),
            'id_importacion' => $_GET['id_importacion'] ?? ($_POST['id_importacion'] ?? null),
        ]));
    }

    public function CorregirIncidencia()
    {
        echo json_encode(ImportacionPagosService::corregirIncidencia($_POST));
    }

    /**
     * @param callable $callback fn(string $ruta, string $nombre, string $corresponsal): array
     */
    private function procesarArchivo(callable $callback): void
    {
        if (empty($_FILES['archivo']['tmp_name']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
            echo json_encode(['success' => false, 'mensaje' => 'No se recibió el archivo.']);
            return;
        }

        $corresponsal = trim((string) ($_POST['corresponsal'] ?? ''));
        if ($corresponsal === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Seleccione el corresponsal.']);
            return;
        }

        $nombre = basename((string) $_FILES['archivo']['name']);
        $ruta = $_FILES['archivo']['tmp_name'];

        echo json_encode($callback($ruta, $nombre, $corresponsal));
    }
}
