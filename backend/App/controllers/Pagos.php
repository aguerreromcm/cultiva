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
                        estatus = "<span class='badge-duplicado'>" + (motivoDup || "Duplicado") + "</span>";
                    } else if (f.INCIDENCIA) {
                        estatus = "<span class='badge-revisar'>" + (motivo || "Incidencia") + "</span>";
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

            const renderDesglosePreview = (filas, selector) => {
                const box = $(selector || "#desglose-preview-fechas");
                if (!box.length) return;

                const mapa = {};
                (filas || []).forEach((f) => {
                    const clave = f.FECHA || "";
                    const etiqueta = f.FECHA_FMT || f.FECHA || "-";
                    if (!mapa[clave]) {
                        mapa[clave] = { etiqueta: etiqueta, registros: 0, monto: 0, incidencias: 0 };
                    }
                    mapa[clave].registros++;
                    mapa[clave].monto += parseFloat(f.MONTO) || 0;
                    if (Number(f.INCIDENCIA) === 1) {
                        mapa[clave].incidencias++;
                    }
                });
                const dias = Object.keys(mapa).sort();
                if (dias.length <= 1) {
                    box.hide().empty();
                    return;
                }

                const head = $("<div>").addClass("ci-desglose-head")
                    .append($("<i>").addClass("fa fa-calendar"))
                    .append(document.createTextNode(" Desglose por día"));

                const tabla = $("<table>").addClass("ci-desglose-tabla");
                tabla.append(
                    $("<thead>").append(
                        $("<tr>")
                            .append($("<th>").text("Fecha"))
                            .append($("<th>").addClass("num").text("Registros"))
                            .append($("<th>").addClass("num").text("Monto"))
                            .append($("<th>").addClass("num").text("Incidencias"))
                    )
                );
                const tbody = $("<tbody>");
                let totReg = 0;
                let totMonto = 0;
                let totInc = 0;
                dias.forEach((d) => {
                    const row = mapa[d];
                    totReg += row.registros;
                    totMonto += row.monto;
                    totInc += row.incidencias;
                    const tr = $("<tr>");
                    if (row.incidencias > 0) tr.addClass("has-incidencias");
                    tr.append($("<td>").text(row.etiqueta));
                    tr.append($("<td>").addClass("num").text(row.registros));
                    tr.append($("<td>").addClass("num").text(formateaMoneda(row.monto)));
                    tr.append($("<td>").addClass("num").text(row.incidencias));
                    tbody.append(tr);
                });

                const trTot = $("<tr>").addClass("ci-desglose-total");
                if (totInc > 0) trTot.addClass("has-incidencias");
                trTot.append($("<td>").text("Total"));
                trTot.append($("<td>").addClass("num").text(totReg));
                trTot.append($("<td>").addClass("num").text(formateaMoneda(totMonto)));
                trTot.append($("<td>").addClass("num").text(totInc));
                tbody.append(trTot);
                tabla.append(tbody);

                box.empty()
                    .append(head)
                    .append($("<div>").addClass("ci-desglose-wrap").append(tabla))
                    .show();
            };

            const renderCeldaMulti = (desglose, campo, fallbackHtml, formatear) => {
                const dias = Array.isArray(desglose) ? desglose : [];
                if (dias.length <= 1) {
                    return fallbackHtml;
                }
                const lineas = dias.map((d) => {
                    let valor = d[campo];
                    if (typeof formatear === "function") {
                        valor = formatear(valor);
                    }
                    return '<div class="ci-multi-line">' + (valor == null || valor === "" ? "-" : valor) + "</div>";
                }).join("");
                return '<div class="ci-multi-stack">' + lineas + "</div>";
            };

            const renderReferenciaDetalle = (f) => {
                const actual = (f.REFERENCIA || f.referencia || "-").toString();
                const original = (f.REFERENCIA_ORIGINAL || f.referencia_original || "").toString().trim();
                const quien = (f.CDGPE_CORRIGE || f.cdgpe_corrige || "").toString().trim();
                const cuando = (f.F_CORRIGE_REF_FMT || f.f_corrige_ref_fmt || "").toString().trim();
                const huboCorreccion = quien !== "" || cuando !== "" || (original !== "" && original !== actual);

                let html = '<div class="ci-ref-cell">';
                html += '<div class="ci-ref-nueva">' + actual + "</div>";
                if (huboCorreccion && original !== "" && original !== actual) {
                    html += '<div class="ci-ref-ant">Original: ' + original + "</div>";
                }
                if (quien !== "" || cuando !== "") {
                    html += '<div class="ci-ref-meta">Corregido'
                        + (quien ? " por " + quien : "")
                        + (cuando ? " · " + cuando : "")
                        + "</div>";
                }
                html += "</div>";
                return html;
            };

            const cargarHistorial = () => {
                $.getJSON("/Pagos/ListarHistorial/", (res) => {
                    if (!res || !res.success) return;
                    const datos = (res.datos || []).map((f) => {
                        const archivo = f.ARCHIVO || "";
                        const idLote = f.ID_LOTE_IMPORTACION != null ? f.ID_LOTE_IMPORTACION : (f.ID_IMPORTACION != null ? f.ID_IMPORTACION : "");
                        const puedeEliminar = parseInt(f.PUEDE_ELIMINAR, 10) === 1;
                        const desglose = f.DESGLOSE || [];
                        const multi = desglose.length > 1 || parseInt(f.NUM_FECHAS, 10) > 1;
                        const btnVer = "<button type='button' class='btn btn-info btn-xs btn-ver-importacion' " +
                            "data-archivo='" + String(archivo).replace(/'/g, "&#39;") + "' " +
                            "data-id='" + idLote + "' title='Ver registros'>" +
                            "<i class='fa fa-list'></i> Ver</button>";
                        const btnEliminar = puedeEliminar
                            ? " <button type='button' class='btn btn-danger btn-xs btn-eliminar-importacion' " +
                              "data-archivo='" + String(archivo).replace(/'/g, "&#39;") + "' " +
                              "data-id='" + idLote + "' title='Eliminar archivo'>" +
                              "<i class='fa fa-trash'></i> Eliminar</button>"
                            : "";
                        const fechaHtml = multi
                            ? renderCeldaMulti(desglose, "FECHA_FMT", '<span class="ci-multi-label">Múltiples fechas</span>')
                            : (f.FECHA_PAGO || "-");
                        const registrosHtml = multi
                            ? renderCeldaMulti(desglose, "REGISTROS", f.REGISTROS || 0)
                            : (f.REGISTROS || 0);
                        const montoHtml = multi
                            ? renderCeldaMulti(desglose, "MONTO", formateaMoneda(f.MONTO_TOTAL), formateaMoneda)
                            : formateaMoneda(f.MONTO_TOTAL);
                        const incidenciasHtml = multi
                            ? renderCeldaMulti(desglose, "INCIDENCIAS", f.INCIDENCIAS || 0)
                            : (f.INCIDENCIAS || 0);
                        return [
                            archivo || "-",
                            fechaHtml,
                            registrosHtml,
                            montoHtml,
                            incidenciasHtml,
                            f.FECHA_CARGA || f.F_IMPORTACION || "-",
                            btnVer + btnEliminar
                        ];
                    });
                    actualizaDatosTabla(idTablaHistorial, datos);
                });
            };

            const verDetalleImportacion = (archivo, idLote) => {
                if (!archivo) return showWarning("No se pudo identificar el archivo.");
                swal({ text: "Cargando registros...", icon: "/img/wait.gif", button: false, closeOnClickOutside: false, closeOnEsc: false });
                $.getJSON("/Pagos/DetalleImportacion/", { archivo: archivo, id_lote_importacion: idLote || "" }, (res) => {
                    swal.close();
                    if (!res.success) return showError(res.mensaje || "No se pudieron cargar los registros.");
                    const data = res.datos || {};
                    const filas = data.registros || [];
                    let montoTotal = 0;
                    let incidencias = 0;
                    filas.forEach((f) => {
                        montoTotal += parseFloat(f.MONTO) || 0;
                        if (parseInt(f.INCIDENCIA, 10) === 1) incidencias++;
                    });
                    $("#detalle_archivo_nombre").text(data.archivo || archivo);
                    let resumen = " · Monto total: " + formateaMoneda(montoTotal);
                    if (incidencias > 0) {
                        resumen += " · " + incidencias + " incidencia" + (incidencias === 1 ? "" : "s");
                    }
                    $("#detalle_resumen").text(resumen);
                    const datos = filas.map((f) => {
                        const estatus = parseInt(f.INCIDENCIA, 10) === 1
                            ? "<span class='badge-revisar'>Incidencia</span>"
                            : "<span class='badge-ok'>OK</span>";
                        return [
                            f.FECHA_FMT || f.FECHA || "-",
                            renderReferenciaDetalle(f),
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

            const eliminarImportacion = async (archivo, idLote) => {
                if (!archivo) return showWarning("No se pudo identificar el archivo.");
                const ok = await confirmarMovimiento(
                    "Eliminar importación",
                    "Se eliminarán todos los pagos del archivo \"" + archivo + "\". Solo es posible si ninguno fue procesado en el cierre. ¿Desea continuar?"
                );
                if (!ok) return;

                consultaServidor("/Pagos/EliminarImportacion/", {
                    archivo: archivo,
                    id_lote_importacion: idLote || ""
                }, (res) => {
                    if (!res.success) return showError(res.mensaje || "No se pudo eliminar.");
                    showSuccess(res.mensaje).then(() => {
                        cargarHistorial();
                        cargarIncidencias();
                    });
                });
            };

            const cargarIncidencias = () => {
                $.getJSON("/Pagos/ListarIncidencias/", (res) => {
                    if (!res.success) return;
                    const datos = (res.datos || []).map((f) => {
                        const btn = "<button type='button' class='btn btn-warning btn-xs btn-corregir' " +
                            "data-fecha='" + (f.FECHA || "") + "' data-secuencia='" + (f.SECUENCIA || "") + "' " +
                            "data-referencia='" + String(f.REFERENCIA || "").replace(/'/g, "&#39;") + "' " +
                            "data-monto='" + (f.MONTO || "") + "' " +
                            "data-archivo='" + String(f.ARCHIVO || "").replace(/'/g, "&#39;") + "' " +
                            "data-id='" + (f.ID_LOTE_IMPORTACION != null ? f.ID_LOTE_IMPORTACION : "") + "'>" +
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
                            let montoTotal = 0;
                            (data.filas || []).forEach((f) => { montoTotal += parseFloat(f.MONTO) || 0; });
                            let resumen = "Monto total: " + formateaMoneda(montoTotal);
                            if (inc) resumen += " · " + inc + " incidencia" + (inc === 1 ? "" : "s");
                            if (dup) resumen += " · " + dup + " duplicado" + (dup === 1 ? "" : "s");
                            $("#resumen-preview").text(resumen);
                            renderPreview(data.filas || []);
                            renderDesglosePreview(data.filas || []);
                            $("#btn_confirmar").prop("disabled", !res.success || dup > 0);
                            $("#modalPreviewImportacion").modal("show");
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
                            $("#modalPreviewImportacion").modal("hide");
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

                $(document).on("click", ".btn-eliminar-importacion", function () {
                    eliminarImportacion($(this).data("archivo"), $(this).data("id"));
                });

                $("#modalPreviewImportacion").on("shown.bs.modal", function () {
                    const dt = $("#" + idTablaPreview).DataTable();
                    if (dt) dt.columns.adjust();
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
                    const archivo = $(this).data("archivo") || "";
                    const idLote = $(this).data("id") || "";
                    $("#corr_fecha").val(fecha);
                    $("#corr_secuencia").val(secuencia);
                    $("#corr_referencia").text(referencia);
                    $("#corr_monto").text(formateaMoneda(monto));
                    $("#corr_credito").val("");
                    $("#corr_ciclo").val("");
                    $("#modalCorregirIncidencia")
                        .data("archivo", archivo)
                        .data("id-lote", idLote)
                        .modal("show");
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
            'id_lote_importacion' => $_GET['id_lote_importacion'] ?? ($_POST['id_lote_importacion'] ?? ($_GET['id_importacion'] ?? ($_POST['id_importacion'] ?? null))),
        ]));
    }

    public function EliminarImportacion()
    {
        echo json_encode(ImportacionPagosService::eliminarImportacion([
            'archivo' => $_POST['archivo'] ?? ($_GET['archivo'] ?? ''),
            'id_lote_importacion' => $_POST['id_lote_importacion'] ?? ($_GET['id_lote_importacion'] ?? null),
        ]));
    }

    public function CorregirIncidencia()
    {
        $datos = $_POST;
        $datos['usuario'] = $this->__usuario;
        echo json_encode(ImportacionPagosService::corregirIncidencia($datos));
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
