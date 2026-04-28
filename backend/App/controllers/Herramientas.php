<?php

namespace App\controllers;

defined("APPPATH") or die("Access denied");

use \Core\View;
use \Core\Controller;
use \Core\App;
use \App\models\Herramientas as HerramientasDao;
use \App\services\AuditoriaDevengoService;

class Herramientas extends Controller
{
    private $_contenedor;

    function __construct()
    {
        parent::__construct();
        if (!App::herramientasHabilitado()) {
            header('Location: /Principal/');
            exit;
        }
        $this->_contenedor = new Contenedor;
        View::set('header', $this->_contenedor->header());
        View::set('footer', $this->_contenedor->footer());
    }

    /**
     * Vista del reporte Día de Atraso (tabla + botones Excel y CSV).
     * Los datos se cargan por AJAX. Opcional: filtrar desde mes y año.
     */
    public function RepDiaAtraso()
    {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $anio_actual = (int) date('Y');
        $options_mes = '<option value="">Todos</option>';
        foreach ($meses as $n => $nombre) {
            $options_mes .= '<option value="' . $n . '">' . $nombre . '</option>';
        }
        $options_anio = '<option value="">Todos</option>';
        for ($a = $anio_actual; $a >= $anio_actual - 15; $a--) {
            $options_anio .= '<option value="' . $a . '">' . $a . '</option>';
        }
        $extraFooter = <<<HTML
        <script>
            {$this->mensajes}
            {$this->configuraTabla}
            {$this->actualizaDatosTabla}
            const idTablaRepDia = "muestra-rep-dia-atraso";
            function consultarRepDiaAtraso() {
                var mes = $("#filtro_mes").val();
                var anio = $("#filtro_anio").val();
                var params = [];
                if (mes) params.push("mes=" + mes);
                if (anio) params.push("anio=" + anio);
                var url = "/Herramientas/GetRepDiaAtraso/" + (params.length ? "?" + params.join("&") : "");
                swal({ text: "Procesando la solicitud, espere un momento...", icon: "/img/wait.gif", button: false, closeOnClickOutside: false, closeOnEsc: false });
                $.ajax({
                    type: "GET",
                    url: url,
                    timeout: 600000,
                    success: function(res) {
                        swal.close();
                        try { res = typeof res === "string" ? JSON.parse(res) : res; } catch (e) { showError("Error al procesar la respuesta"); actualizaDatosTabla(idTablaRepDia, []); return; }
                        if (!res.success) {
                            showError(res.mensaje || "Error al cargar el reporte");
                            actualizaDatosTabla(idTablaRepDia, []);
                            return;
                        }
                        actualizaDatosTabla(idTablaRepDia, res.datos || []);
                    },
                    error: function() {
                        swal.close();
                        showError("La consulta tardó demasiado o hubo un error. Tiempo máximo: 10 minutos.");
                        actualizaDatosTabla(idTablaRepDia, []);
                    }
                });
            }
            $(document).ready(function(){
                $("#" + idTablaRepDia).DataTable({
                    lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "Todos"]],
                    order: [[4, "desc"]],
                    language: {
                        emptyTable: "Seleccione filtros y pulse Consultar para cargar el reporte.",
                        paginate: { previous: "Anterior", next: "Siguiente" },
                        info: "Mostrando de _START_ a _END_ de _TOTAL_ registros",
                        infoEmpty: "Sin registros",
                        zeroRecords: "No se encontraron registros",
                        lengthMenu: "Mostrar _MENU_ registros",
                        search: "Buscar:"
                    }
                });
                $("#btn_consultar").click(consultarRepDiaAtraso);
                $("#btn_excel").click(function(){
                    var mes = $("#filtro_mes").val();
                    var anio = $("#filtro_anio").val();
                    var qs = (mes || anio) ? "?" + [mes ? "mes=" + mes : "", anio ? "anio=" + anio : ""].filter(Boolean).join("&") : "";
                    window.open("/Herramientas/RepDiaAtraso_excel/" + qs, "_blank");
                });
                $("#btn_csv").click(function(){
                    var mes = $("#filtro_mes").val();
                    var anio = $("#filtro_anio").val();
                    var qs = (mes || anio) ? "?" + [mes ? "mes=" + mes : "", anio ? "anio=" + anio : ""].filter(Boolean).join("&") : "";
                    window.location.href = "/Herramientas/RepDiaAtraso_csv/" + qs;
                });
            });
        </script>
        HTML;
        View::set('header', $this->_contenedor->header($this->GetExtraHeader("Rep Dia de Atraso")));
        View::set('footer', $this->_contenedor->footer($extraFooter));
        View::set('tabla', '');
        View::set('options_mes', $options_mes);
        View::set('options_anio', $options_anio);
        View::render('herramientas_rep_dia_atraso');
    }

    /**
     * Devuelve el reporte en JSON para la carga por AJAX.
     * Parámetros opcionales GET: mes (1-12), anio (ej. 2025).
     * Tiempo máximo de ejecución: 10 minutos (para consultas pesadas).
     */
    public function GetRepDiaAtraso()
    {
        set_time_limit(600);
        $datos = array_filter([
            'mes'  => isset($_GET['mes']) ? $_GET['mes'] : null,
            'anio' => isset($_GET['anio']) ? $_GET['anio'] : null,
        ]);
        echo json_encode(HerramientasDao::GetRepDiaAtraso($datos));
    }

    /**
     * Descarga del reporte en Excel.
     */
    public function RepDiaAtraso_excel()
    {
        require_once dirname(__DIR__) . '/../libs/PhpSpreadsheet/PhpSpreadsheet.php';
        $estilos = \PHPSpreadsheet::GetEstilosExcel();
        $centrado = ['estilo' => $estilos['centrado']];
        $columnas = [
            \PHPSpreadsheet::ColumnaExcel('COD_CTE', 'Código Cliente', $centrado),
            \PHPSpreadsheet::ColumnaExcel('CICLO', 'Ciclo', $centrado),
            \PHPSpreadsheet::ColumnaExcel('NOMBRE', 'Nombre'),
            \PHPSpreadsheet::ColumnaExcel('INICIO', 'Inicio', ['estilo' => $estilos['fecha']]),
            \PHPSpreadsheet::ColumnaExcel('DIAS_ATRASO', 'Días Atraso', $centrado),
        ];
        $datos = array_filter([
            'mes'  => isset($_GET['mes']) ? $_GET['mes'] : null,
            'anio' => isset($_GET['anio']) ? $_GET['anio'] : null,
        ]);
        $resultado = HerramientasDao::GetRepDiaAtraso($datos);
        $filas = ($resultado['success'] && isset($resultado['datos'])) ? $resultado['datos'] : [];
        \PHPSpreadsheet::DescargaExcel('Rep Dia de Atraso', 'Reporte', 'Días de atraso', $columnas, $filas);
    }

    /**
     * Descarga del reporte en CSV.
     * Parámetros opcionales GET: mes (1-12), anio (ej. 2025).
     */
    public function RepDiaAtraso_csv()
    {
        $datos = array_filter([
            'mes'  => isset($_GET['mes']) ? $_GET['mes'] : null,
            'anio' => isset($_GET['anio']) ? $_GET['anio'] : null,
        ]);
        $resultado = HerramientasDao::GetRepDiaAtraso($datos);
        $filas = ($resultado['success'] && isset($resultado['datos'])) ? $resultado['datos'] : [];
        $nombre = 'rep_dia_atraso_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
        fputcsv($out, ['COD_CTE', 'CICLO', 'NOMBRE', 'INICIO', 'DIAS_ATRASO'], ',');
        foreach ($filas as $fila) {
            fputcsv($out, [
                $fila['COD_CTE'] ?? '',
                $fila['CICLO'] ?? '',
                $fila['NOMBRE'] ?? '',
                $fila['INICIO'] ?? '',
                $fila['DIAS_ATRASO'] ?? '',
            ], ',');
        }
        fclose($out);
        exit;
    }

    /**
     * Vista Auditoría Devengo.
     */
    public function AuditoriaDevengo()
    {
        View::set('header', $this->_contenedor->header($this->GetExtraHeader("Auditoría Devengo")));
        View::set('footer', $this->_contenedor->footer());
        View::set('tabla', '');
        View::render('herramientas_auditoria_devengo');
    }

    /**
     * Devuelve devengos faltantes en JSON.
     */
    public function GetDevengosFaltantes()
    {
        try {
            set_time_limit(120);
            header('Content-Type: application/json; charset=UTF-8');

            $fechaCorte = isset($_GET['fecha_corte']) ? trim((string) $_GET['fecha_corte']) : null;
            if ($fechaCorte !== null && $fechaCorte !== '') {
                $fecha = \DateTime::createFromFormat('Y-m-d', $fechaCorte);
                if (!$fecha) {
                    $fecha = \DateTime::createFromFormat('d/m/Y', $fechaCorte);
                    $fechaCorte = $fecha ? $fecha->format('Y-m-d') : null;
                }
            }
            if (empty($fechaCorte)) {
                $fechaCorte = date('Y-m-d');
            }
            $hoy = date('Y-m-d');
            if ($fechaCorte > $hoy) {
                $fechaCorte = $hoy;
            }

            $credito = isset($_GET['credito']) ? trim((string) $_GET['credito']) : null;
            $ciclo = isset($_GET['ciclo']) ? trim((string) $_GET['ciclo']) : null;

            if (($credito === null || $credito === '') && ($ciclo === null || $ciclo === '')) {
                echo json_encode(\Core\Model::Responde(false, 'Captura al menos un filtro: crédito o ciclo.'));
                return;
            }

            if ($credito !== null && $credito !== '' && !ctype_digit($credito)) {
                echo json_encode(\Core\Model::Responde(false, 'El crédito debe contener solo números.'));
                return;
            }

            if ($ciclo !== null && $ciclo !== '' && !ctype_digit($ciclo)) {
                echo json_encode(\Core\Model::Responde(false, 'El ciclo debe contener solo números.'));
                return;
            }

            $datos = [
                'credito' => $credito,
                'ciclo' => $ciclo,
                'fecha_corte' => $fechaCorte,
            ];

            echo json_encode(AuditoriaDevengoService::GetDevengosFaltantes($datos));
        } catch (\Throwable $e) {
            @file_put_contents(APPPATH . '/../logs/auditoria_devengo_error.log', date('c') . " GetDevengosFaltantes Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            echo json_encode(\Core\Model::Responde(false, 'Ocurrió un error al obtener los devengos faltantes.', null, $e->getMessage()));
        }
    }

    /**
     * Procesa un devengo individual.
     */
    public function ProcesarIndividual()
    {
        $log = APPPATH . '/../logs/auditoria_devengo_proceso.log';
        try {
            header('Content-Type: application/json; charset=UTF-8');
            $raw = file_get_contents('php://input');
            $datos = json_decode($raw, true) ?: [];
            $usuario = $this->__usuario ?? '';
            $perfil = $this->__perfil ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            @file_put_contents($log, date('c') . " [CTRL] ProcesarIndividual ENTRADA raw=" . substr($raw, 0, 200) . " | datos=" . json_encode($datos) . " | usuario=$usuario | perfil=$perfil\n", FILE_APPEND);

            $respuesta = AuditoriaDevengoService::ProcesarIndividual($datos, $usuario, $perfil, $ip);
            @file_put_contents($log, date('c') . " [CTRL] ProcesarIndividual SALIDA success=" . (!empty($respuesta['success']) ? 'true' : 'false') . " | mensaje=" . ($respuesta['mensaje'] ?? '') . "\n", FILE_APPEND);

            echo json_encode($respuesta);
        } catch (\Throwable $e) {
            @file_put_contents($log, date('c') . " [CTRL] ProcesarIndividual EXCEPCION: " . $e->getMessage() . "\n", FILE_APPEND);
            @file_put_contents(APPPATH . '/../logs/auditoria_devengo_error.log', date('c') . " ProcesarIndividual Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            echo json_encode(\Core\Model::Responde(false, 'Ocurrió un error al procesar el devengo individual.', null, $e->getMessage()));
        }
    }

    /**
     * Vista del monitor de Estatus de Base de Datos.
     * Al cargar dispara la consulta inicial vía AJAX y permite refrescar con un botón.
     */
    public function EstatusBD()
    {
        $extraFooter = <<<HTML
        <script>
            {$this->mensajes}
            (function () {
                const PCT_VERDE   = 85;  // &lt; 85 % verde
                const PCT_AMAR_90 = 90;  // 85&ndash;90 % amarillo; &gt; 90 rojo
                const URL_ESTATUS = "/Herramientas/GetEstatusBD/";

                function hEsc(s) {
                    return \$("<div>").text(s === null || s === undefined ? "" : String(s)).html();
                }
                function gridMetricasFra(lim, u, re) {
                    const cell = function (lbl, v) {
                        return "<div class=\"estatus-metrica\">"
                            + "<span class=\"estatus-metrica-lbl\">" + lbl + "</span>"
                            + "<span class=\"estatus-metrica-val\">" + hEsc(v) + "</span></div>";
                    };
                    return "<div class=\"estatus-metricas-grid\" role=\"group\" aria-label=\"FRA\">"
                        + cell("L" + "\u00ed" + "mite", lim) + cell("Usado", u) + cell("Reutilizable", re)
                        + "</div>";
                }

                function classByPct(pct) {
                    if (pct === null || pct === undefined || pct === "") return "estatus-warn";
                    const p = Number(pct);
                    if (!isFinite(p)) return "estatus-warn";
                    if (p < PCT_VERDE) return "estatus-ok";
                    if (p <= PCT_AMAR_90) return "estatus-warn";
                    return "estatus-error";
                }

                function kpiClassFromArchiveStatus(status) {
                    const u = String(status || "").toUpperCase();
                    if (u === "VALID") return "estatus-ok";
                    if (u === "SIN DATO") return "estatus-warn";
                    return "estatus-error";
                }

                function pintaArchive(prefix, info) {
                    const \$kpi    = $("#" + prefix + "-kpi-wrap");
                    const \$status = $("#" + prefix + "-archive-status");
                    const \$ico     = $("#" + prefix + "-archive-ico");
                    const \$err     = $("#" + prefix + "-archive-error");

                    \$kpi.removeClass("estatus-ok estatus-warn estatus-error");
                    \$err.removeClass("estatus-alert--sql").hide().empty();

                    if (info.archive_error) {
                        \$kpi.addClass("estatus-error");
                        \$ico.html("\u274C");
                        \$status.text("Error de consulta");
                        \$err.addClass("estatus-alert--sql");
                        \$err.append(\$("<span class=\"estatus-alert-cuerpo\">").text((info.archive_error || "").trim()));
                        \$err.show();
                        return false;
                    }
                    const archive  = info.archive_dest || {};
                    const status   = (archive.STATUS || archive.status || "SIN DATO").toString();
                    const errorMsg = (archive.ERROR || archive.error || "").toString();
                    const stUp  = String(status).toUpperCase();
                    const ok    = stUp === "VALID";
                    const kCls  = kpiClassFromArchiveStatus(status);
                    \$kpi.addClass(kCls);
                    if (kCls === "estatus-ok") {
                        \$ico.html("\u2714");
                    } else if (kCls === "estatus-warn") {
                        \$ico.html("\u26A0\ufe0f");
                    } else {
                        \$ico.html("\u2715");
                    }
                    \$status.text(stUp === "VALID" ? "Sincronizado" : stUp);
                    if (errorMsg) {
                        \$err.append(\$("<span class=\"estatus-alert-cuerpo\">").text(errorMsg));
                        \$err.show();
                    }
                    return ok;
                }

                function pintaRecovery(prefix, info) {
                    const \$metric = $("#" + prefix + "-rec-metricas");
                    const \$pct    = $("#" + prefix + "-rec-pct");
                    const \$bar     = $("#" + prefix + "-rec-bar");
                    const \$err    = $("#" + prefix + "-rec-error");

                    \$err.removeClass("estatus-alert--sql").hide().empty();

                    if (info.recovery_error) {
                        const nd = "\u2013";
                        \$metric.html(
                            "<div class=\"estatus-metricas-grid estatus-metricas-grid--error\" role=\"group\">"
                            + "<div class=\"estatus-metrica\"><span class=\"estatus-metrica-lbl\">" + "L" + "\u00ed" + "mite" + "</span>"
                            + "<span class=\"estatus-metrica-val estatus-metrica-val--muted\">" + nd + "</span></div>"
                            + "<div class=\"estatus-metrica\"><span class=\"estatus-metrica-lbl\">Usado</span>"
                            + "<span class=\"estatus-metrica-val estatus-metrica-val--muted\">" + nd + "</span></div>"
                            + "<div class=\"estatus-metrica\"><span class=\"estatus-metrica-lbl\">Reutilizable</span>"
                            + "<span class=\"estatus-metrica-val estatus-metrica-val--muted\">" + nd + "</span></div></div>"
                        );
                        \$pct.text("\u2013");
                        \$bar.css("width", "0%").removeClass().addClass("estatus-bar estatus-error");
                        \$err.addClass("estatus-alert--sql");
                        \$err.append(\$("<span class=\"estatus-alert-cuerpo\">").text((info.recovery_error || "").trim()));
                        \$err.show();
                        return null;
                    }
                    const r = info.recovery_file_dest || {};
                    const limite   = r.LIMITE_GB ?? r.limite_gb ?? null;
                    const usado    = r.USADO_GB ?? r.usado_gb ?? null;
                    const reusable = r.REUTILIZABLE_GB ?? r.reutilizable_gb ?? null;
                    const pct      = r.USADO_PCT ?? r.usado_pct ?? null;

                    const lim = limite === null || limite === "" ? "\u2013" : (String(limite) + " GB");
                    const u   = usado === null || usado === "" ? "\u2013" : (String(usado) + " GB");
                    const re  = reusable === null || reusable === "" ? "\u2013" : (String(reusable) + " GB");
                    \$metric.html(gridMetricasFra(lim, u, re));
                    const pctText = pct === null || pct === "" ? "\u2013" : (Number(pct) + " %");
                    \$pct.text(pctText);
                    const cls   = classByPct(pct);
                    const ancho = pct === null || pct === "" ? 0 : Math.min(100, Math.max(0, Number(pct)));
                    \$bar.css("width", ancho + "%").removeClass().addClass("estatus-bar " + cls);
                    return cls;
                }

                function pintaBase(prefix, info) {
                    const archiveOk = pintaArchive(prefix, info);
                    const recCls    = pintaRecovery(prefix, info);
                    const \$card     = $("#" + prefix + "-card");

                    let borde = "border-ok";
                    if (!archiveOk || info.archive_error || info.recovery_error || recCls === "estatus-error") {
                        borde = "border-error";
                    } else if (recCls === "estatus-warn") {
                        borde = "border-warn";
                    }
                    \$card.removeClass("border-ok border-warn border-error").addClass(borde);
                }

                function consultar() {
                    $("#btn-actualizar").prop("disabled", true);
                    $("#estatus-cargando").show();
                    $("#estatus-actualizado").text("").removeClass("is-live");

                    $.ajax({
                        type: "GET",
                        url: URL_ESTATUS,
                        timeout: 120000,
                        cache: false,
                        success: function (res) {
                            try { res = typeof res === "string" ? JSON.parse(res) : res; } catch (e) { res = null; }
                            if (!res || !res.success) {
                                showError((res && res.mensaje) || "No se pudo obtener el estatus de las bases.");
                                return;
                            }
                            const datos = res.datos || {};
                            if (datos.DB_CULTIVA) pintaBase("cultiva", datos.DB_CULTIVA);
                            if (datos.DB_MCM)     pintaBase("mcm", datos.DB_MCM);
                            const ts = (datos.consultado_en || "").toString();
                            $("#estatus-actualizado").text(ts ? "Actualizado: " + ts : "").addClass("is-live");
                        },
                        error: function () {
                            showError("Error al consultar el estatus de las bases. Intente de nuevo.");
                        },
                        complete: function () {
                            $("#btn-actualizar").prop("disabled", false);
                            $("#estatus-cargando").hide();
                        }
                    });
                }

                $(document).ready(function () {
                    $("#btn-actualizar").on("click", consultar);
                    consultar();
                });
            })();
        </script>
        HTML;
        View::set('header', $this->_contenedor->header($this->GetExtraHeader("Estatus BD")));
        View::set('footer', $this->_contenedor->footer($extraFooter));
        View::render('herramientas_estatus_bd');
    }

    /**
     * Devuelve el estatus actual de las bases (DB_CULTIVA y DB_MCM) en JSON.
     */
    public function GetEstatusBD()
    {
        set_time_limit(120);
        header('Content-Type: application/json; charset=UTF-8');
        try {
            echo json_encode(HerramientasDao::GetEstatusBD());
        } catch (\Throwable $e) {
            echo json_encode(\Core\Model::Responde(false, 'Ocurrió un error al consultar el estatus de las bases.', null, $e->getMessage()));
        }
    }

    /**
     * Procesa devengos en forma masiva.
     */
    public function ProcesarMasivo()
    {
        try {
            set_time_limit(600);
            header('Content-Type: application/json; charset=UTF-8');
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true) ?: [];
            $registros = $body['registros'] ?? [];
            $usuario = $this->__usuario ?? '';
            $perfil = $this->__perfil ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            echo json_encode(AuditoriaDevengoService::ProcesarMasivo($registros, $usuario, $perfil, $ip));
        } catch (\Throwable $e) {
            @file_put_contents(APPPATH . '/../logs/auditoria_devengo_error.log', date('c') . " ProcesarMasivo Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            echo json_encode(\Core\Model::Responde(false, 'Ocurrió un error al procesar devengos masivos.', null, $e->getMessage()));
        }
    }
}
