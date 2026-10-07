<?php

namespace App\repositories;

defined("APPPATH") or die("Access denied");

use Core\Database;

/**
 * Consultas del cierre de día (BITACORA_CIERRE_DIARIO / IMPORTACIONPAG / PAGOSDIA).
 */
class CierreDiaRepository
{
    /**
     * Renglón del SP sin FIN: cierre en ejecución (o un intento que no terminó).
     */
    public function validaCierreEnEjecucion(): array
    {
        $db = new Database();
        if ($db->db_activa === null) {
            return [];
        }

        $qry = <<<SQL
            SELECT
                TO_CHAR(FECHA_CALCULO, 'DD/MM/YYYY') AS FECHA_CIERRE,
                TO_CHAR(INICIO, 'DD/MM/YYYY HH24:MI') AS INICIO,
                USUARIO
            FROM BITACORA_CIERRE_DIARIO
            WHERE FIN IS NULL
              AND INICIO IS NOT NULL
              AND ID_IMPORTACION IS NOT NULL
            ORDER BY INICIO DESC
            FETCH FIRST 1 ROW ONLY
        SQL;

        try {
            $r = $db->queryOne($qry);
            return is_array($r) && !empty($r) ? $r : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Hay un cierre exitoso para la fecha. Con intentos fallidos y ninguno exitoso se puede reintentar;
     * sin intentos en bitácora se toma IMPORTACIONPAG.
     */
    public function cierreYaEjecutado(string $fecha): bool
    {
        $qryExito = <<<SQL
            SELECT COUNT(*) AS TOTAL
            FROM BITACORA_CIERRE_DIARIO
            WHERE TRUNC(FECHA_CALCULO) = TO_DATE(:fecha, 'YYYY-MM-DD')
              AND FIN IS NOT NULL
              AND NVL(EXITO, 0) = 1
        SQL;

        $qryCualquiera = <<<SQL
            SELECT COUNT(*) AS TOTAL
            FROM BITACORA_CIERRE_DIARIO
            WHERE TRUNC(FECHA_CALCULO) = TO_DATE(:fecha, 'YYYY-MM-DD')
              AND FIN IS NOT NULL
        SQL;

        $qryImportacion = <<<SQL
            SELECT COUNT(*) AS TOTAL
            FROM IMPORTACIONPAG
            WHERE TRUNC(FEC_PAGO) = TO_DATE(:fecha, 'YYYY-MM-DD')
        SQL;

        $db = new Database();
        if ($db->db_activa === null) {
            return false;
        }

        try {
            $rOk = $db->queryOne($qryExito, ['fecha' => $fecha]);
            if ((int) ($rOk['TOTAL'] ?? 0) > 0) {
                return true;
            }
            $rAny = $db->queryOne($qryCualquiera, ['fecha' => $fecha]);
            if ((int) ($rAny['TOTAL'] ?? 0) > 0) {
                return false;
            }
            $r = $db->queryOne($qryImportacion, ['fecha' => $fecha]);
            return (int) ($r['TOTAL'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Pagos registrados para la fecha (mismo filtro del SP); PENDIENTES son los que aún no procesa un cierre.
     *
     * @return array{REGISTROS: int, MONTO: float, PENDIENTES: int}
     */
    public function pagosRegistrados(string $fecha): array
    {
        $vacio = ['REGISTROS' => 0, 'MONTO' => 0, 'PENDIENTES' => 0];
        $db = new Database();
        if ($db->db_activa === null) {
            return $vacio;
        }

        $qry = <<<SQL
            SELECT
                COUNT(*) AS REGISTROS,
                NVL(SUM(MONTO), 0) AS MONTO,
                SUM(CASE WHEN ID_IMPORTACION IS NULL THEN 1 ELSE 0 END) AS PENDIENTES
            FROM PAGOSDIA
            WHERE ESTATUS = 'A'
              AND TIPO IN ('P', 'G', 'X')
              AND MONTO != 0
              AND TRUNC(FECHA) = TO_DATE(:fecha, 'YYYY-MM-DD')
        SQL;

        try {
            $r = $db->queryOne($qry, ['fecha' => $fecha]);
            return [
                'REGISTROS' => (int) ($r['REGISTROS'] ?? 0),
                'MONTO' => (float) ($r['MONTO'] ?? 0),
                'PENDIENTES' => (int) ($r['PENDIENTES'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return $vacio;
        }
    }

    /**
     * Último intento del SP para la fecha.
     */
    public function estatusPorFecha(string $fecha): ?array
    {
        $db = new Database();
        if ($db->db_activa === null) {
            return null;
        }

        $qry = <<<SQL
            SELECT
                b.USUARIO,
                TO_CHAR(b.INICIO, 'DD/MM/YYYY HH24:MI') AS INICIO,
                TO_CHAR(b.FIN, 'DD/MM/YYYY HH24:MI') AS FIN,
                NVL(b.EXITO, 0) AS EXITO,
                b.MENSAJE
            FROM BITACORA_CIERRE_DIARIO b
            WHERE TRUNC(b.FECHA_CALCULO) = TO_DATE(:fecha, 'YYYY-MM-DD')
              AND b.ID_IMPORTACION IS NOT NULL
            ORDER BY b.INICIO DESC NULLS LAST
            FETCH FIRST 1 ROW ONLY
        SQL;

        try {
            $r = $db->queryOne($qry, ['fecha' => $fecha]);
            return is_array($r) && !empty($r) ? $r : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Tiempo estimado de cierre en minutos (promedio de los últimos 7 cierres exitosos).
     */
    public function tiempoEstimado(): int
    {
        $db = new Database();
        if ($db->db_activa === null) {
            return 0;
        }

        $qry = <<<SQL
            SELECT ROUND(AVG((CAST(FIN AS DATE) - CAST(INICIO AS DATE)) * 24 * 60), 0) AS ESTIMADO
            FROM (
                SELECT INICIO, FIN
                FROM BITACORA_CIERRE_DIARIO
                WHERE FIN IS NOT NULL AND INICIO IS NOT NULL AND EXITO = 1
                ORDER BY FIN DESC
                FETCH FIRST 7 ROWS ONLY
            )
        SQL;

        try {
            $r = $db->queryOne($qry);
            return (int) ($r['ESTIMADO'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Fecha (Y-m-d) del último cierre exitoso en bitácora.
     */
    public function ultimoCierreExitoso(): ?string
    {
        $db = new Database();
        if ($db->db_activa === null) {
            return null;
        }

        $qry = <<<SQL
            SELECT TO_CHAR(MAX(FECHA_CALCULO), 'YYYY-MM-DD') AS FECHA
            FROM BITACORA_CIERRE_DIARIO
            WHERE NVL(EXITO, 0) = 1
              AND FIN IS NOT NULL
        SQL;

        try {
            $r = $db->queryOne($qry);
            $f = trim((string) ($r['FECHA'] ?? ''));
            return $f !== '' ? $f : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
