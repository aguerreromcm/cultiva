<?php

namespace Jobs\controllers;

include_once dirname(__DIR__) . '/../Core/Job.php';
include_once dirname(__DIR__) . '/models/CierreDia.php';
include_once dirname(__DIR__) . '/../libs/PHPMailer/Mensajero.php';
include_once dirname(__DIR__) . '/../App/services/CierreDiaJobLock.php';

use Core\Job;
use Jobs\models\CierreDia as JobsDao;
use Mensajero;
use App\services\CierreDiaJobLock;

class CierreDia extends Job
{
    public function __construct()
    {
        $logs = dirname(__DIR__) . '/Logs';
        if (!is_dir($logs)) {
            @mkdir($logs, 0777, true);
        }
        parent::__construct('CierreDia');
    }

    public function CierreDia($fecha, $usuario, $regenerar = 0)
    {
        set_time_limit(0);
        self::SaveLog('Inicio');
        if (empty($fecha) || empty($usuario)) {
            self::SaveLog('Finalizado con error: Fecha y usuario son requeridos');
            return;
        }

        if (!CierreDiaJobLock::adquirirJob()) {
            $motivo = CierreDiaJobLock::ultimoError();
            self::SaveLog('Finalizado sin ejecutar el SP: ' . ($motivo !== '' ? $motivo : 'ya hay un Job de cierre de día en ejecución'));
            return;
        }

        $mensajeRes = null;
        $exito = 0;
        $datos = [
            'fecha' => $fecha,
            'usuario' => $usuario,
            'regenerar' => $regenerar ? 1 : 0,
        ];

        try {
            $resultado = JobsDao::CierreDia($datos);

            if (isset($resultado['datos']) && isset($resultado['datos']['MENSAJE'])) {
                $texto = trim($resultado['datos']['MENSAJE']);
                $lineas = preg_split('/\r\n|\r|\n/', $texto);
                $mensajeRes = end($lineas);
                $resultado['datos']['MENSAJE'] = $mensajeRes;
            }

            if ($resultado['success']) {
                $exito = 1;
                $mensaje = "Cierre de día concluido: fecha $fecha - usuario $usuario";
            } else {
                $error = isset($resultado['error']) ? $resultado['error'] : $mensajeRes;
                $mensaje = "Finalizado con error: $error";
            }

            $envio = $this->destinosCorreoCierreDia($exito === 1);
            $dest = $envio['destinatarios'];
            $copiaHistorico = $envio['copiaHistorico'];

            if (!empty($dest) && !empty($resultado['datos'])) {
                try {
                    $fechaFmt = new \DateTime($fecha);
                    $fechaFmt = $fechaFmt->format('d/m/Y');
                    $enviado = Mensajero::EnviarCorreo(
                        $dest,
                        "Cierre del día $fechaFmt",
                        Mensajero::Notificaciones(self::PLantilla_mail_Cierre_Dia($resultado['datos'])),
                        [],
                        $copiaHistorico
                    );
                    if ($enviado) {
                        $resCorreo = JobsDao::CorreoCierreDia($resultado['datos']);
                        self::SaveLog('Estatus del correo: ' . $resCorreo['mensaje'] . ' -> ' . ($resCorreo['error'] ?? ''));
                    } else {
                        self::SaveLog('El correo de resumen no se pudo enviar (SMTP).');
                    }
                } catch (\Throwable $e) {
                    self::SaveLog('Error al enviar correo: ' . $e->getMessage());
                }
            } elseif (empty($dest)) {
                self::SaveLog('Sin destinatarios: no se envió correo de cierre.');
            }

            self::SaveLog($mensaje);
        } catch (\Throwable $e) {
            self::SaveLog('Finalizado con error: ' . $e->getMessage());
        } finally {
            CierreDiaJobLock::liberarJob();
        }
    }

    /**
     * Destinatarios del correo de cierre.
     * Con CIERRE_DIA_SOLO_FLUJO: solo CORREOS_DESARROLLO, sin copia a SMTP_USER.
     * En producción: grupos de aplicación 5 (éxito) / 6 (error) y copia histórica.
     *
     * @param bool $exito
     * @return array{destinatarios: string[], copiaHistorico: bool}
     */
    private function destinosCorreoCierreDia($exito)
    {
        $ini = @parse_ini_file(dirname(__DIR__) . '/../App/config/configuracion.ini', true);
        $cfg = isset($ini['cierre_dia']) && is_array($ini['cierre_dia']) ? $ini['cierre_dia'] : [];
        $val = isset($cfg['CIERRE_DIA_SOLO_FLUJO']) ? trim((string) $cfg['CIERRE_DIA_SOLO_FLUJO']) : '';
        $soloFlujo = $val !== '' && (filter_var($val, FILTER_VALIDATE_BOOLEAN) || strtolower($val) === 'true' || $val === '1');

        if ($soloFlujo) {
            $raw = isset($cfg['CORREOS_DESARROLLO']) ? trim((string) $cfg['CORREOS_DESARROLLO']) : '';
            $dest = array_values(array_filter(array_unique(array_map('trim', explode(',', $raw)))));
            if (empty($dest)) {
                self::SaveLog('CIERRE_DIA_SOLO_FLUJO activo y CORREOS_DESARROLLO vacío: no se envía correo.');
            } else {
                self::SaveLog('Correo solo desarrollo: ' . implode(', ', $dest));
            }
            return ['destinatarios' => $dest, 'copiaHistorico' => false];
        }

        $appId = $exito ? 5 : 6;
        $dest = $this->GetDestinatarios(JobsDao::GetDestinatarios_Aplicacion($appId));
        return ['destinatarios' => $dest, 'copiaHistorico' => true];
    }

    private function GetDestinatarios($respuesta)
    {
        if (empty($respuesta['success']) || empty($respuesta['datos'])) {
            return [];
        }
        $destinatarios = array_map(function ($d) {
            return $d['CORREO'];
        }, $respuesta['datos']);
        sort($destinatarios);
        return array_values(array_unique($destinatarios));
    }

    public function PLantilla_mail_Cierre_Dia($datos)
    {
        $fmtMoneda = function ($n) {
            $n = (float) $n;
            if (class_exists(\NumberFormatter::class)) {
                $fmt = new \NumberFormatter('es_MX', \NumberFormatter::CURRENCY);
                return $fmt->formatCurrency($n, 'MXN') ?? '$0.00';
            }
            return '$ ' . number_format($n, 2);
        };
        $fmtFecha = function ($dmy) {
            $dt = \DateTime::createFromFormat('d/m/Y', (string) $dmy);
            if (!$dt) {
                return $dmy ?: 'N/A';
            }
            if (class_exists(\IntlDateFormatter::class)) {
                $fecha = new \IntlDateFormatter(
                    'es_ES',
                    \IntlDateFormatter::LONG,
                    \IntlDateFormatter::NONE,
                    'America/Mexico_City',
                    \IntlDateFormatter::GREGORIAN,
                    "d 'de' MMMM 'de' y"
                );
                $out = $fecha->format($dt);
                return $out !== false ? $out : $dt->format('d/m/Y');
            }
            return $dt->format('d/m/Y');
        };
        $devengoFecha = isset($datos['DEVENGO_FECHA']) && trim((string) $datos['DEVENGO_FECHA']) !== ''
            ? $datos['DEVENGO_FECHA']
            : 'N/A';
        $resumen = JobsDao::GetResumenCierreDia($datos);

        $fecha_calculo = isset($datos['FECHA_CALCULO']) ? $fmtFecha($datos['FECHA_CALCULO']) : 'N/A';

        $devengo_registros = isset($datos['DEVENGO_REGISTROS']) ? $datos['DEVENGO_REGISTROS'] : '0';
        $devengo_monto = isset($datos['DEVENGO_MONTO']) ? $fmtMoneda($datos['DEVENGO_MONTO'] ?? 0) : '$ 0.00';

        if ($resumen['success']) {
            $pagos = $resumen['datos']['pagos'] ?? [];
            $detalle = $resumen['datos']['detalle'] ?? [];
            $mp = $resumen['datos']['mp'] ?? [];
            $garantias = $resumen['datos']['garantias'] ?? [];

            $pagos_total_registros = $pagos['TOTAL_REGISTROS'] ?? 0;
            $pagos_total_monto = $fmtMoneda($pagos['TOTAL_MONTO'] ?? 0);
            $pagos_pendiente_registros = $pagos['PENDIENTES_REGISTROS'] ?? 0;
            $pagos_pendiente_monto = $fmtMoneda($pagos['PENDIENTES_MONTO'] ?? 0);
            $pagos_aplicados_registros = $pagos['APLICADOS_REGISTROS'] ?? 0;
            $pagos_aplicados_monto = $fmtMoneda($pagos['APLICADOS_MONTO'] ?? 0);
            $pagos_registros = $detalle['PAGOS_REGISTROS'] ?? 0;
            $pagos_monto = $fmtMoneda($detalle['PAGOS_MONTO'] ?? 0);
            $garantias_registros = $detalle['GARANTIAS_REGISTROS'] ?? 0;
            $garantias_monto = $fmtMoneda($detalle['GARANTIAS_MONTO'] ?? 0);
            $incidencias_registros = $detalle['INCIDENCIAS_REGISTROS'] ?? 0;
            $incidencias_monto = $fmtMoneda($detalle['INCIDENCIAS_MONTO'] ?? 0);
            $mp_total_registros = $mp['TOTAL_REGISTROS'] ?? 0;
            $mp_total_monto = $fmtMoneda($mp['TOTAL_MONTO'] ?? 0);
            $mp_pendiente_registros = $mp['PENDIENTES_REGISTROS'] ?? 0;
            $mp_pendiente_monto = $fmtMoneda($mp['PENDIENTES_MONTO'] ?? 0);
            $mp_conciliados_registros = $mp['CONCILIADOS_REGISTROS'] ?? 0;
            $mp_conciliados_monto = $fmtMoneda($mp['CONCILIADOS_MONTO'] ?? 0);
            $garantias_por_saldo = $garantias['POR_SALDO'] ?? 0;
            $garantias_por_saldo_monto = $fmtMoneda($garantias['POR_SALDO_MONTO'] ?? 0);
            $garantias_por_fecha_fin = $garantias['POR_FECHA'] ?? 0;
            $garantias_por_fecha_fin_monto = $fmtMoneda($garantias['POR_FECHA_MONTO'] ?? 0);
        }

        return <<<HTML
            <table
                role="presentation"
                width="100%"
                cellspacing="0"
                cellpadding="0"
                style="border-spacing: 0; border-collapse: separate"
            >
                <tr>
                    <td colspan="3">
                        <div
                            style="
                                background: linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 16px;
                                margin-bottom: 18px;
                            "
                        >
                            Resumen de cierre del día $fecha_calculo
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <div
                            style="
                                font-size: 13px;
                                font-weight: 700;
                                color: #475569;
                                text-transform: uppercase;
                                letter-spacing: 0.06em;
                                margin-bottom: 10px;
                            "
                        >
                            Proceso
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 33.33%; padding: 0 6px 12px 0">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 12px;
                                padding: 12px 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 4px;
                                    font-weight: 600;
                                "
                            >
                                Usuario
                            </div>
                            <div style="font-size: 14px; color: #0f172a; font-weight: 700">
                                {$datos['USUARIO']}
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 3px 12px 3px">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 12px;
                                padding: 12px 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 4px;
                                    font-weight: 600;
                                "
                            >
                                Inicio
                            </div>
                            <div style="font-size: 14px; color: #0f172a; font-weight: 700">
                                {$datos['INICIO']}
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 0 12px 6px">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 12px;
                                padding: 12px 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 4px;
                                    font-weight: 600;
                                "
                            >
                                Fin
                            </div>
                            <div style="font-size: 14px; color: #0f172a; font-weight: 700">
                                {$datos['FIN']}
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 33.33%; padding: 0 6px 12px 0">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 12px;
                                padding: 12px 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 4px;
                                    font-weight: 600;
                                "
                            >
                                Registros
                            </div>
                            <div style="font-size: 14px; color: #0f172a; font-weight: 700">
                                {$datos['CIERRE_REGISTROS']}
                            </div>
                        </div>
                    </td>
                    <td colspan="2" style="width: 33.33%; padding: 0 3px 12px 3px">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 12px;
                                padding: 12px 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 4px;
                                    font-weight: 600;
                                "
                            >
                                Estatus
                            </div>
                            <div style="font-size: 14px; color: #0f172a; font-weight: 700">
                                {$datos['MENSAJE']}
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" style="padding: 6px 0 18px">
                        <div style="height: 1px; background: #dbe3ef"></div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <div
                            style="
                                font-size: 13px;
                                font-weight: 700;
                                color: #475569;
                                text-transform: uppercase;
                                letter-spacing: 0.06em;
                                margin-bottom: 10px;
                            "
                        >
                            Pagos del día
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 33.33%; padding: 0 6px 12px 0">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                Total
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $pagos_total_registros
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $pagos_total_monto
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 3px 12px 3px">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                Pendientes
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $pagos_pendiente_registros
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $pagos_pendiente_monto
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 0 12px 6px">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                Aplicados
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $pagos_aplicados_registros
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $pagos_aplicados_monto
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <div
                            style="
                                font-size: 13px;
                                font-weight: 700;
                                color: #475569;
                                text-transform: uppercase;
                                letter-spacing: 0.06em;
                                margin-bottom: 10px;
                            "
                        >
                            Identificados
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 33.33%; padding: 0 6px 12px 0">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                Pagos
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $pagos_registros
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $pagos_monto
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 3px 12px 3px">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                Garantías
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $garantias_registros
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $garantias_monto
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 0 12px 6px">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                Incidencias
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $incidencias_registros
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $incidencias_monto
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" style="padding: 6px 0 18px">
                        <div style="height: 1px; background: #dbe3ef"></div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <div
                            style="
                                font-size: 13px;
                                font-weight: 700;
                                color: #475569;
                                text-transform: uppercase;
                                letter-spacing: 0.06em;
                                margin-bottom: 10px;
                            "
                        >
                            Garantías aplicadas
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 33.33%; padding: 0 6px 12px 0">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                por saldo
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $garantias_por_saldo
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $garantias_por_saldo_monto
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 6px 12px 0">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                por fecha fin
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $garantias_por_fecha_fin
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $garantias_por_fecha_fin_monto
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 0 12px 6px"></td>
                </tr>
                <tr>
                    <td colspan="3" style="padding: 6px 0 18px">
                        <div style="height: 1px; background: #dbe3ef"></div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <div
                            style="
                                font-size: 13px;
                                font-weight: 700;
                                color: #475569;
                                text-transform: uppercase;
                                letter-spacing: 0.06em;
                                margin-bottom: 10px;
                            "
                        >
                            Conciliación
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 33.33%; padding: 0 6px 12px 0">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                Total
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $mp_total_registros
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $mp_total_monto
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 6px 12px 0">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                Pendientes
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $mp_pendiente_registros
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $mp_pendiente_monto
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 3px 12px 3px">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                Conciliados
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $mp_conciliados_registros
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $mp_conciliados_monto
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 0 12px 6px"></td>
                </tr>
                <tr>
                    <td colspan="3" style="padding: 6px 0 18px">
                        <div style="height: 1px; background: #dbe3ef"></div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <div
                            style="
                                font-size: 13px;
                                font-weight: 700;
                                color: #475569;
                                text-transform: uppercase;
                                letter-spacing: 0.06em;
                                margin-bottom: 10px;
                            "
                        >
                            Devengo para el día {$devengoFecha}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 33.33%; padding: 0 6px 12px 0">
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #dbe3ef;
                                border-radius: 14px;
                                padding: 14px;
                            "
                        >
                            <div
                                style="
                                    font-size: 12px;
                                    color: #64748b;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                "
                            >
                                Créditos
                            </div>
                            <div
                                style="
                                    font-size: 28px;
                                    text-align: end;
                                    line-height: 1;
                                    color: #0f172a;
                                    font-weight: 800;
                                    letter-spacing: -0.02em;
                                "
                            >
                                $devengo_registros
                            </div>
                            <div
                                style="
                                    font-size: 12px;
                                    text-align: end;
                                    color: #334155;
                                    margin-top: 8px;
                                    font-weight: 600;
                                "
                            >
                                $devengo_monto
                            </div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 0 3px 12px 3px"></td>
                    <td style="width: 33.33%; padding: 0 0 12px 6px"></td>
                </tr>
            </table>
        HTML;
    }
}

if (isset($argv[1])) {
    $jobs = new CierreDia();

    switch ($argv[1]) {
        case 'CierreDia':
            $jobs->CierreDia($argv[2] ?? null, $argv[3] ?? null, isset($argv[4]) ? (int) $argv[4] : 0);
            break;
        case 'help':
            echo "CierreDia [fecha YYYY-MM-DD] [usuario] [regenerar 0|1]: Ejecuta el cierre del día y envía el correo de resultado\n";
            break;
        default:
            echo "No se encontró el job especificado.\n";
            break;
    }
} else echo "Debe especificar el job a ejecutar.\nEjecute 'php CierreDia.php help' para ver los jobs disponibles.\n";