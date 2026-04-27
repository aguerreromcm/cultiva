<?php

namespace App\models;

defined("APPPATH") or die("Access denied");

use Core\Database;
use Core\Model;

class Herramientas extends Model
{
    private static function normalizarFechaYmd($valor)
    {
        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }
        $valor = trim((string) $valor);
        $d = \DateTime::createFromFormat('Y-m-d', $valor);
        if ($d) {
            return $d->format('Y-m-d');
        }
        $d = \DateTime::createFromFormat('d/m/Y', $valor);
        if ($d) {
            return $d->format('Y-m-d');
        }
        $ts = strtotime(str_replace('/', '-', $valor));
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    private static function getAuditoriaDevengoLogPath(): string
    {
        return defined('APPPATH')
            ? APPPATH . '/../logs/auditoria_devengo_proceso.log'
            : __DIR__ . '/../../logs/auditoria_devengo_proceso.log';
    }

    private static function iniciarTransaccion(Database $db): void
    {
        if (method_exists($db, 'AutoCommitOff')) {
            $db->AutoCommitOff();
        }

        if (method_exists($db, 'IniciaTransaccion')) {
            $db->IniciaTransaccion();
            return;
        }

        $db->db_activa->beginTransaction();
    }

    private static function confirmarTransaccion(Database $db): void
    {
        if (method_exists($db, 'ConfirmaTransaccion')) {
            $db->ConfirmaTransaccion();
            return;
        }

        $db->db_activa->commit();
    }

    private static function cancelarTransaccion(Database $db): void
    {
        if (method_exists($db, 'CancelaTransaccion')) {
            $db->CancelaTransaccion();
            return;
        }

        if ($db->db_activa && $db->db_activa->inTransaction()) {
            $db->db_activa->rollBack();
        }
    }

    /**
     * Reporte de días de atraso (PRN situación L).
     * Opcional: filtrar desde el primer día de un mes/año hasta la fecha actual.
     *
     * @param array $datos Opcional: 'mes' (1-12), 'anio' (ej. 2025)
     * @return array { success, mensaje, datos }
     */
    public static function GetRepDiaAtraso($datos = [])
    {
        $qry = <<<SQL
        SELECT
            PRN.CDGNS AS COD_CTE,
            PRN.CICLO,
            NS.NOMBRE,
            TO_CHAR(PRN.INICIO, 'DD/MM/YYYY') AS INICIO,
            FNCALDIASATRASO(PRN.CDGEM, PRN.CDGNS, PRN.CICLO, 'G', SYSDATE) AS DIAS_ATRASO
        FROM
            PRN
            INNER JOIN NS ON PRN.CDGEM = NS.CDGEM
                         AND PRN.CDGNS = NS.CODIGO
        WHERE
            PRN.SITUACION = 'L'
        SQL;
        $prm = [];
        $mes = isset($datos['mes']) ? (int) $datos['mes'] : 0;
        $anio = isset($datos['anio']) ? (int) $datos['anio'] : 0;
        if ($mes >= 1 && $mes <= 12 && $anio >= 2000 && $anio <= 2100) {
            $qry .= ' AND PRN.INICIO >= TO_DATE(:fechaDesde, \'YYYY-MM-DD\')';
            $prm['fechaDesde'] = sprintf('%04d-%02d-01', $anio, $mes);
        }
        try {
            $db = new Database();
            $res = $db->queryAll($qry, $prm);
            return self::Responde(true, 'Consulta exitosa', $res);
        } catch (\Exception $e) {
            return self::Responde(false, 'Error al consultar el reporte', null, $e->getMessage());
        }
    }

    /**
     * Devengos faltantes en una sola consulta.
     *
     * @param array $datos ['credito','ciclo','fecha_corte']
     * @return array
     */
    public static function GetDevengosFaltantes($datos = [])
    {
        $credito = !empty(trim((string) ($datos['credito'] ?? ''))) ? trim((string) $datos['credito']) : null;
        $ciclo = !empty(trim((string) ($datos['ciclo'] ?? ''))) ? trim((string) $datos['ciclo']) : null;
        $fechaCorte = !empty(trim((string) ($datos['fecha_corte'] ?? ''))) ? trim((string) $datos['fecha_corte']) : date('Y-m-d');

        if ($credito === null && $ciclo === null) {
            return self::Responde(false, 'Captura al menos un filtro: crédito o ciclo.');
        }

        if ($credito !== null && !ctype_digit($credito)) {
            return self::Responde(false, 'El crédito debe contener solo números.');
        }

        if ($ciclo !== null && !ctype_digit($ciclo)) {
            return self::Responde(false, 'El ciclo debe contener solo números.');
        }

        $hoy = date('Y-m-d');
        if ($fechaCorte > $hoy) {
            $fechaCorte = $hoy;
        }

        $prm = [
            'fecha_corte' => $fechaCorte,
            'credito' => $credito,
            'ciclo' => $ciclo,
        ];

        $qry = <<<SQL
WITH
PARAMETROS AS (
    SELECT PRN.CDGNS AS CREDITO, PRN.CICLO, TO_DATE(:fecha_corte, 'YYYY-MM-DD') AS CORTE
    FROM PRN
    JOIN MP ON PRN.CDGEM = MP.CDGEM AND PRN.CDGNS = MP.CDGCLNS AND PRN.CICLO = MP.CICLO AND MP.TIPO = 'IN'
    JOIN CF ON PRN.CDGEM = CF.CDGEM AND PRN.CDGFDI = CF.CDGFDI
    WHERE PRN.CDGEM = 'EMPFIN'
      AND (:credito IS NULL OR PRN.CDGNS = :credito)
      AND (:ciclo IS NULL OR PRN.CICLO = :ciclo)
),
DATOS_CREDITO AS (
    SELECT
        P.CREDITO, P.CICLO, P.CORTE, PRN.INICIO, PRN.PLAZO,
        NVL(PRN.PERIODICIDAD, 'S') AS PERIODICIDAD,
        DECODE(NVL(PRN.PERIODICIDAD, 'S'), 'S', 7, 'C', 14, 'Q', 15, 'M', 30, 7) AS FACTOR_DIAS,
        ABS(APagarInteresPrN(
            PRN.CDGEM, PRN.CDGNS, PRN.CICLO,
            NVL(PRN.CANTENTRE, PRN.CANTAUTOR), PRN.TASA, PRN.PLAZO, PRN.PERIODICIDAD,
            PRN.CDGMCI, PRN.INICIO, PRN.DIAJUNTA, PRN.MULTPER, PRN.PERIGRCAP, PRN.PERIGRINT,
            PRN.DESFASEPAGO, PRN.CDGTI
        )) AS DEVENGO_TOTAL,
        NVL(CF.IVA / NULLIF(CF.PORCENTAJE, 0), 0.16) AS IVA
    FROM PRN
    JOIN MP ON PRN.CDGEM = MP.CDGEM AND PRN.CDGNS = MP.CDGCLNS AND PRN.CICLO = MP.CICLO AND MP.TIPO = 'IN'
    JOIN CF ON PRN.CDGEM = CF.CDGEM AND PRN.CDGFDI = CF.CDGFDI
    CROSS JOIN PARAMETROS P
    WHERE PRN.CDGNS = P.CREDITO AND PRN.CICLO = P.CICLO AND PRN.CDGEM = 'EMPFIN'
),
DATOS_CALCULO AS (
    SELECT DC.*,
        (DC.FACTOR_DIAS * DC.PLAZO) AS PLAZO_DIAS,
        TRUNC(DC.INICIO + (DC.FACTOR_DIAS * DC.PLAZO)) AS FIN,
        ROUND(DC.DEVENGO_TOTAL / NULLIF(DC.FACTOR_DIAS * DC.PLAZO, 0), 2) AS DEVENGO_DIARIO
    FROM DATOS_CREDITO DC
),
CORTE_LIQUIDA AS (
    SELECT
        CDGCLNS AS CREDITO,
        CICLO,
        MAX(TRUNC(FECHA_LIQUIDA)) AS FECHA_LIQUIDA
    FROM TBL_CIERRE_DIA
    WHERE FECHA_LIQUIDA IS NOT NULL
    GROUP BY CDGCLNS, CICLO
),
LIMITES AS (
    SELECT
        DC.*,
        LEAST(DC.FIN, NVL(CL.FECHA_LIQUIDA, DC.CORTE), TRUNC(SYSDATE)) AS FECHA_HASTA
    FROM DATOS_CALCULO DC
    LEFT JOIN CORTE_LIQUIDA CL ON CL.CREDITO = DC.CREDITO AND CL.CICLO = DC.CICLO
),
CALENDARIO (FECHA_CALC, CREDITO, CICLO, DEVENGO_DIARIO, FIN, FECHA_HASTA) AS (
    SELECT L.INICIO + 1, L.CREDITO, L.CICLO, L.DEVENGO_DIARIO, L.FIN, L.FECHA_HASTA
    FROM LIMITES L
    WHERE L.INICIO + 1 <= L.FECHA_HASTA
    UNION ALL
    SELECT C.FECHA_CALC + 1, C.CREDITO, C.CICLO, C.DEVENGO_DIARIO, C.FIN, C.FECHA_HASTA
    FROM CALENDARIO C
    WHERE C.FECHA_CALC + 1 <= C.FECHA_HASTA
),
PROYECCION AS (
    SELECT C.CREDITO, C.CICLO, L.INICIO, L.FIN, C.FECHA_CALC, L.PLAZO, L.PLAZO_DIAS, L.PERIODICIDAD, L.DEVENGO_TOTAL, L.IVA,
        CASE WHEN C.FECHA_CALC = L.FIN THEN 1 ELSE 0 END AS ES_ULTIMO_DIA,
        SUM(C.DEVENGO_DIARIO) OVER (PARTITION BY C.CREDITO, C.CICLO ORDER BY C.FECHA_CALC ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING) AS DEVENGO_ACUMULADO,
        C.DEVENGO_DIARIO
    FROM CALENDARIO C
    JOIN LIMITES L ON C.CREDITO = L.CREDITO AND C.CICLO = L.CICLO
),
RESULTADO AS (
    SELECT
        P.CREDITO, P.CICLO,
        TO_CHAR(P.FECHA_CALC, 'YYYY-MM-DD') AS FECHA_CALC_ISO,
        TO_CHAR(P.FECHA_CALC, 'DD/MM/YYYY') AS FECHA_FALTANTE,
        TO_CHAR(P.INICIO, 'YYYY-MM-DD') AS INICIO,
        CASE WHEN P.ES_ULTIMO_DIA = 1 THEN P.DEVENGO_TOTAL - NVL(P.DEVENGO_ACUMULADO, 0) ELSE P.DEVENGO_DIARIO END AS DEV_DIARIO,
        (P.FECHA_CALC - P.INICIO) AS DIAS_DEV,
        SUM(CASE WHEN P.ES_ULTIMO_DIA = 1 THEN P.DEVENGO_TOTAL - NVL(P.DEVENGO_ACUMULADO, 0) ELSE P.DEVENGO_DIARIO END)
            OVER (PARTITION BY P.CREDITO, P.CICLO ORDER BY P.FECHA_CALC) AS INT_DEV,
        ROUND((CASE WHEN P.ES_ULTIMO_DIA = 1 THEN P.DEVENGO_TOTAL - NVL(P.DEVENGO_ACUMULADO, 0) ELSE P.DEVENGO_DIARIO END) / (1 + P.IVA), 2) AS DEV_DIARIO_SIN_IVA,
        ROUND((CASE WHEN P.ES_ULTIMO_DIA = 1 THEN P.DEVENGO_TOTAL - NVL(P.DEVENGO_ACUMULADO, 0) ELSE P.DEVENGO_DIARIO END)
            - (CASE WHEN P.ES_ULTIMO_DIA = 1 THEN P.DEVENGO_TOTAL - NVL(P.DEVENGO_ACUMULADO, 0) ELSE P.DEVENGO_DIARIO END) / (1 + P.IVA), 2) AS IVA_INT,
        P.PLAZO, P.PERIODICIDAD, P.PLAZO_DIAS, TO_CHAR(P.FIN, 'YYYY-MM-DD') AS FIN_DEVENGO,
        'EMPFIN' AS CDGEM, 'RE' AS ESTATUS, 'G' AS CLNS
    FROM PROYECCION P
)
SELECT
    R.CREDITO,
    R.CICLO,
    R.FECHA_FALTANTE,
    R.FECHA_CALC_ISO AS FECHA_CALC,
    R.FECHA_CALC_ISO,
    COALESCE(
        (
            SELECT MAX(CONCATENA_NOMBRE(CL.NOMBRE1, CL.NOMBRE2, CL.PRIMAPE, CL.SEGAPE))
            FROM SC
            INNER JOIN CL ON CL.CODIGO = SC.CDGCL
            WHERE SC.CDGNS = R.CREDITO
              AND SC.CICLO = R.CICLO
        ),
        (
            SELECT MAX(NS2.NOMBRE)
            FROM NS NS2
            WHERE NS2.CODIGO = R.CREDITO
        )
    ) AS NOMBRE,
    R.INICIO, R.DEV_DIARIO, R.DIAS_DEV, R.INT_DEV, R.DEV_DIARIO_SIN_IVA, R.IVA_INT,
    R.PLAZO, R.PERIODICIDAD, R.PLAZO_DIAS, R.FIN_DEVENGO, R.CDGEM, R.ESTATUS, R.CLNS
FROM RESULTADO R
WHERE NOT EXISTS (
    SELECT 1 FROM ESIACOM.DEVENGO_DIARIO DD
    WHERE DD.CDGCLNS = R.CREDITO AND DD.CICLO = R.CICLO AND DD.CDGEM = R.CDGEM
      AND TRUNC(DD.FECHA_CALC) = TO_DATE(R.FECHA_CALC_ISO, 'YYYY-MM-DD')
)
ORDER BY R.CREDITO, R.CICLO, R.FECHA_CALC_ISO
SQL;

        try {
            $db = new Database();
            $stmt = $db->db_activa->prepare($qry);
            $stmt->execute($prm);
            $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return self::Responde(true, 'Consulta exitosa', is_array($res) ? $res : []);
        } catch (\PDOException $e) {
            return self::Responde(false, 'Error al consultar devengos faltantes: ' . $e->getMessage(), null, $e->getMessage());
        } catch (\Throwable $e) {
            return self::Responde(false, 'Error al consultar devengos faltantes', null, $e->getMessage());
        }
    }

    public static function ProcesarDevengoIndividual(
        array $fila,
        string $usuario,
        string $perfil,
        string $ip,
        string $tipoEjecucion = 'INDIVIDUAL'
    ): array {
        $credito = trim((string) ($fila['CREDITO'] ?? $fila['CDGCLNS'] ?? $fila['credito'] ?? ''));
        $ciclo = trim((string) ($fila['CICLO'] ?? $fila['ciclo'] ?? ''));
        $db = new Database();

        try {
            self::iniciarTransaccion($db);

            if ($credito === '' || $ciclo === '') {
                throw new \Exception('Crédito y ciclo son obligatorios.');
            }

            $res = self::ValidarCreditoExiste($db, $credito);
            if (!$res['success']) {
                throw new \Exception($res['mensaje']);
            }

            $res = self::ValidarCicloExiste($db, $credito, $ciclo);
            if (!$res['success']) {
                throw new \Exception($res['mensaje']);
            }

            $fechaProceso = trim((string) ($fila['FECHA_CALC_ISO'] ?? $fila['FECHA_CALC'] ?? date('Y-m-d')));
            $res = self::ValidarFechaLiquida($db, $credito, $ciclo, $fechaProceso);
            if (!$res['success']) {
                throw new \Exception($res['mensaje']);
            }

            self::ObtenerBloqueo($db, $credito, $ciclo);
            $insertados = self::InsertarFilasDevengo($db, [$fila], $usuario);
            self::InsertarBitacora($db, $credito, $ciclo, $fechaProceso, $tipoEjecucion, $usuario, $perfil, 'OK', null, $ip);

            self::confirmarTransaccion($db);

            return [
                'success' => true,
                'mensaje' => $insertados > 0 ? "$insertados devengos procesados correctamente" : "No había devengos pendientes",
                'insertados' => $insertados,
                'credito' => $credito,
                'ciclo' => $ciclo,
            ];
        } catch (\Throwable $e) {
            self::cancelarTransaccion($db);

            $mensaje = $e->getMessage();
            if (strpos($mensaje, 'ORA-00054') !== false) {
                @file_put_contents(self::getAuditoriaDevengoLogPath(), date('c') . " [INSERT] BLOQUEO ORA-00054: $mensaje\n", FILE_APPEND);
            }

            $fechaLog = trim((string) ($fila['FECHA_CALC_ISO'] ?? $fila['FECHA_CALC'] ?? date('Y-m-d')));
            try {
                self::InsertarBitacoraLog($credito, $ciclo, $fechaLog, $tipoEjecucion, $usuario, $perfil, 'ERROR', $mensaje, $ip);
            } catch (\Throwable $ignored) {
            }

            return [
                'success' => false,
                'mensaje' => $mensaje !== '' ? $mensaje : 'Error al procesar el crédito',
                'insertados' => 0,
                'credito' => $credito,
                'ciclo' => $ciclo,
            ];
        }
    }

    public static function ProcesarDevengoMasivo(array $registros, string $usuario, string $perfil, string $ip): array
    {
        $db = new Database();

        try {
            self::iniciarTransaccion($db);

            $paresValidados = [];
            foreach ($registros as $fila) {
                $credito = trim((string) ($fila['CREDITO'] ?? $fila['CDGCLNS'] ?? $fila['credito'] ?? ''));
                $ciclo = trim((string) ($fila['CICLO'] ?? $fila['ciclo'] ?? ''));
                $fechaCalc = trim((string) ($fila['FECHA_CALC_ISO'] ?? $fila['FECHA_CALC'] ?? date('Y-m-d')));

                if ($credito === '' || $ciclo === '') {
                    throw new \Exception('Registro inválido: crédito y ciclo obligatorios.');
                }

                $key = $credito . '|' . $ciclo;
                if (!isset($paresValidados[$key])) {
                    $res = self::ValidarCreditoExiste($db, $credito);
                    if (!$res['success']) {
                        throw new \Exception("Crédito $credito: " . $res['mensaje']);
                    }

                    $res = self::ValidarCicloExiste($db, $credito, $ciclo);
                    if (!$res['success']) {
                        throw new \Exception("Crédito $credito ciclo $ciclo: " . $res['mensaje']);
                    }

                    self::ObtenerBloqueo($db, $credito, $ciclo);
                    $paresValidados[$key] = true;
                }

                $res = self::ValidarFechaLiquida($db, $credito, $ciclo, $fechaCalc);
                if (!$res['success']) {
                    throw new \Exception("Crédito $credito ciclo $ciclo fecha $fechaCalc: " . $res['mensaje']);
                }
            }

            $insertados = self::InsertarFilasDevengo($db, $registros, $usuario);

            foreach (array_keys($paresValidados) as $key) {
                list($credito, $ciclo) = explode('|', $key, 2);
                self::InsertarBitacora($db, $credito, $ciclo, date('Y-m-d'), 'MASIVO', $usuario, $perfil, 'OK', null, $ip);
            }

            self::confirmarTransaccion($db);

            $creditosProcesados = [];
            foreach (array_keys($paresValidados) as $key) {
                list($credito, $ciclo) = explode('|', $key, 2);
                $creditosProcesados[] = ['credito' => $credito, 'ciclo' => $ciclo];
            }

            return [
                'success' => true,
                'mensaje' => $insertados > 0 ? "$insertados devengos procesados correctamente" : "No había devengos pendientes",
                'insertados' => $insertados,
                'creditosProcesados' => $creditosProcesados,
                'credito' => '',
                'ciclo' => '',
            ];
        } catch (\Throwable $e) {
            self::cancelarTransaccion($db);

            return [
                'success' => false,
                'mensaje' => $e->getMessage() !== '' ? $e->getMessage() : 'Error al procesar los créditos',
                'insertados' => 0,
                'credito' => '',
                'ciclo' => '',
            ];
        }
    }

    public static function ValidarCreditoExiste(Database $db, string $credito): array
    {
        $qry = "SELECT COUNT(*) AS CNT FROM PRN WHERE CDGNS = :credito AND CDGEM = 'EMPFIN'";
        $res = $db->queryOne($qry, ['credito' => $credito]);
        if ((int) ($res['CNT'] ?? 0) === 0) {
            return self::Responde(false, 'El crédito no existe.');
        }

        return self::Responde(true, 'OK');
    }

    public static function ValidarCicloExiste(Database $db, string $credito, string $ciclo): array
    {
        $qry = "SELECT COUNT(*) AS CNT FROM PRN WHERE CDGNS = :credito AND CICLO = :ciclo AND CDGEM = 'EMPFIN'";
        $res = $db->queryOne($qry, ['credito' => $credito, 'ciclo' => $ciclo]);
        if ((int) ($res['CNT'] ?? 0) === 0) {
            return self::Responde(false, 'El ciclo no existe para este crédito.');
        }

        return self::Responde(true, 'OK');
    }

    public static function ValidarFechaLiquida(Database $db, string $credito, string $ciclo, ?string $fechaCalc = null): array
    {
        $qry = <<<SQL
            SELECT TO_CHAR(MAX(FECHA_LIQUIDA), 'YYYY-MM-DD') AS FECHA_LIQUIDA
            FROM TBL_CIERRE_DIA
            WHERE CDGCLNS = :credito AND CICLO = :ciclo AND FECHA_LIQUIDA IS NOT NULL
SQL;
        $res = $db->queryOne($qry, ['credito' => $credito, 'ciclo' => $ciclo]);
        $fechaLiquida = trim((string) ($res['FECHA_LIQUIDA'] ?? ''));

        if ($fechaLiquida === '') {
            return self::Responde(true, 'OK');
        }

        $fechaCalc = trim((string) ($fechaCalc ?? ''));
        if ($fechaCalc === '') {
            return self::Responde(true, 'OK');
        }

        $tsFechaCalc = strtotime($fechaCalc);
        $tsFechaLiquida = strtotime($fechaLiquida);
        if ($tsFechaCalc === false || $tsFechaLiquida === false) {
            return self::Responde(false, 'No se pudo validar la fecha de proceso contra FECHA_LIQUIDA.');
        }

        if ($tsFechaCalc > $tsFechaLiquida) {
            return self::Responde(false, 'No se puede procesar una fecha posterior a FECHA_LIQUIDA (' . $fechaLiquida . ').');
        }

        return self::Responde(true, 'OK');
    }

    public static function ValidarTieneDevengosFaltantes(Database $db, string $credito, string $ciclo): array
    {
        $qry = <<<SQL
            SELECT COUNT(*) AS CNT
            FROM CREDITOS_ACTIVOS CA
            CROSS JOIN (
                SELECT LEVEL - 1 AS NUM
                FROM DUAL
                CONNECT BY LEVEL <= (
                    SELECT NVL(MAX(LEAST(TRUNC(SYSDATE) - 1, FIN) - (INICIO + 1)) + 1, 1)
                    FROM CREDITOS_ACTIVOS
                )
            ) N
            WHERE CA.CDGNS = :credito AND CA.CICLO = :ciclo
            AND (CA.INICIO + 1) + N.NUM <= LEAST(TRUNC(SYSDATE) - 1, CA.FIN)
            AND NOT EXISTS (
                SELECT 1 FROM ESIACOM.DEVENGO_DIARIO DD
                WHERE DD.CDGCLNS = CA.CDGNS AND DD.CICLO = CA.CICLO
                AND TRUNC(DD.FECHA_CALC) = (CA.INICIO + 1) + N.NUM
            )
SQL;
        $res = $db->queryOne($qry, ['credito' => $credito, 'ciclo' => $ciclo]);
        $cnt = (int) ($res['CNT'] ?? 0);
        if ($cnt === 0) {
            return self::Responde(false, 'No hay devengos faltantes para este crédito/ciclo.');
        }

        return self::Responde(true, 'OK');
    }

    public static function ObtenerBloqueo(Database $db, string $credito, string $ciclo): void
    {
        $qry = "SELECT CDGNS, CICLO FROM PRN WHERE CDGNS = :credito AND CICLO = :ciclo AND CDGEM = 'EMPFIN' FOR UPDATE";
        $db->queryOne($qry, ['credito' => $credito, 'ciclo' => $ciclo]);
    }

    public static function ObtenerDatosBaseDevengo(Database $db, string $credito, string $ciclo): ?array
    {
        $qry = <<<SQL
            SELECT
                TO_CHAR(TRUNC(PRN.INICIO), 'YYYY-MM-DD') AS INICIO,
                PRN.PLAZO,
                NVL(PRN.PERIODICIDAD, 'S') AS PERIODICIDAD,
                NVL(PRN.CDGEM, 'EMPFIN') AS CDGEM,
                NVL(PRN.CDGOCPE, 'AMGM') AS CDGPE,
                DECODE(NVL(PRN.PERIODICIDAD, 'S'), 'S', 7, 'C', 14, 'Q', 15, 'M', 30, 7) AS FACTOR_DIAS,
                (DECODE(NVL(PRN.PERIODICIDAD, 'S'), 'S', 7, 'C', 14, 'Q', 15, 'M', 30, 7) * PRN.PLAZO) AS PLAZO_DIAS,
                TO_CHAR(TRUNC(PRN.INICIO + (DECODE(NVL(PRN.PERIODICIDAD, 'S'), 'S', 7, 'C', 14, 'Q', 15, 'M', 30, 7) * PRN.PLAZO)), 'YYYY-MM-DD') AS FIN_TS,
                ABS(APagarInteresPrN(
                    PRN.CDGEM, PRN.CDGNS, PRN.CICLO,
                    NVL(PRN.CANTENTRE, PRN.CANTAUTOR), PRN.TASA, PRN.PLAZO, PRN.PERIODICIDAD,
                    PRN.CDGMCI, PRN.INICIO, PRN.DIAJUNTA, PRN.MULTPER, PRN.PERIGRCAP, PRN.PERIGRINT,
                    PRN.DESFASEPAGO, PRN.CDGTI
                )) AS DEVENGO_TOTAL,
                0.16 AS IVA
            FROM PRN
            INNER JOIN MP ON PRN.CDGEM = MP.CDGEM AND PRN.CDGNS = MP.CDGCLNS AND PRN.CICLO = MP.CICLO AND MP.TIPO = 'IN'
            LEFT JOIN CF ON PRN.CDGEM = CF.CDGEM AND PRN.CDGFDI = CF.CDGFDI
            WHERE PRN.CDGNS = :credito AND PRN.CICLO = :ciclo AND PRN.CDGEM = 'EMPFIN'
        SQL;

        $row = $db->queryOne($qry, ['credito' => $credito, 'ciclo' => $ciclo]);
        return !empty($row) ? $row : null;
    }

    public static function EnriquecerFilasConDatosInsertar(Database $db, array $filas): array
    {
        if (empty($filas)) {
            return $filas;
        }

        $filas = array_values($filas);
        $grupos = [];
        foreach ($filas as $idx => $fila) {
            $fila = array_change_key_case((array) $fila, CASE_UPPER);
            $filas[$idx] = $fila;

            $credito = trim((string) ($fila['CREDITO'] ?? ''));
            $ciclo = trim((string) ($fila['CICLO'] ?? ''));
            $key = $credito . '|' . $ciclo;

            if (!isset($grupos[$key])) {
                $grupos[$key] = ['credito' => $credito, 'ciclo' => $ciclo, 'indices' => []];
            }
            $grupos[$key]['indices'][] = $idx;
        }

        foreach ($grupos as $grupo) {
            $base = self::ObtenerDatosBaseDevengo($db, $grupo['credito'], $grupo['ciclo']);
            if (!$base) {
                continue;
            }

            $base = array_change_key_case($base, CASE_UPPER);
            $plazoDias = (int) ($base['PLAZO_DIAS'] ?? 0);
            $devengoTotal = (float) ($base['DEVENGO_TOTAL'] ?? 0);
            $iva = (float) ($base['IVA'] ?? 0.16);
            $inicioStr = trim((string) ($base['INICIO'] ?? ''));
            $finStr = trim((string) ($base['FIN_TS'] ?? ''));
            $cdgem = trim((string) ($base['CDGEM'] ?? 'EMPFIN'));
            $cdgpe = trim((string) ($base['CDGPE'] ?? 'AMGM'));
            $periodicidad = trim((string) ($base['PERIODICIDAD'] ?? 'S'));
            $plazo = (int) ($base['PLAZO'] ?? 0);
            $devengoDiarioFijo = $plazoDias > 0 ? round($devengoTotal / $plazoDias, 2) : 0.0;
            $acumulado = 0.0;

            $filasGrupo = [];
            foreach ($grupo['indices'] as $idx) {
                $filasGrupo[] = [
                    'idx' => $idx,
                    'fecha' => trim((string) ($filas[$idx]['FECHA_CALC_ISO'] ?? '')),
                ];
            }

            usort($filasGrupo, function ($a, $b) {
                return strcmp($a['fecha'], $b['fecha']);
            });

            foreach ($filasGrupo as $item) {
                $idx = $item['idx'];
                $fechaCalc = $item['fecha'];
                $diasDev = (int) round((strtotime($fechaCalc . ' 12:00:00') - strtotime($inicioStr . ' 12:00:00')) / 86400);
                $esUltimoDia = ($fechaCalc === $finStr);
                $devDiario = $esUltimoDia ? round($devengoTotal - $acumulado, 2) : $devengoDiarioFijo;
                $devDiario = round($devDiario, 2);
                $acumulado = round($acumulado + $devDiario, 2);
                $devDiarioSinIva = round($devDiario / (1 + $iva), 2);
                $ivaInt = round($devDiario - $devDiarioSinIva, 2);

                $filas[$idx]['FECHA_CALC_ISO'] = $fechaCalc;
                $filas[$idx]['CDGEM'] = $cdgem;
                $filas[$idx]['CDGCLNS'] = $grupo['credito'];
                $filas[$idx]['CICLO'] = $grupo['ciclo'];
                $filas[$idx]['INICIO'] = $inicioStr;
                $filas[$idx]['DEV_DIARIO'] = $devDiario;
                $filas[$idx]['DIAS_DEV'] = $diasDev;
                $filas[$idx]['INT_DEV'] = $acumulado;
                $filas[$idx]['CDGPE'] = $cdgpe;
                $filas[$idx]['DEV_DIARIO_SIN_IVA'] = $devDiarioSinIva;
                $filas[$idx]['IVA_INT'] = $ivaInt;
                $filas[$idx]['PLAZO'] = $plazo;
                $filas[$idx]['PERIODICIDAD'] = $periodicidad;
                $filas[$idx]['PLAZO_DIAS'] = $plazoDias;
                $filas[$idx]['FIN_DEVENGO'] = $finStr;
                $filas[$idx]['ESTATUS'] = 'RE';
                $filas[$idx]['CLNS'] = 'G';
            }
        }

        return $filas;
    }

    public static function EnriquecerUnaFilaParaInsertar(Database $db, string $credito, string $ciclo, string $fechaCalcIso): ?array
    {
        $fila = ['CREDITO' => $credito, 'CICLO' => $ciclo, 'FECHA_CALC_ISO' => $fechaCalcIso];
        $enriquecidas = self::EnriquecerFilasConDatosInsertar($db, [$fila]);

        return isset($enriquecidas[0]) && !empty(trim((string) ($enriquecidas[0]['INICIO'] ?? '')))
            ? $enriquecidas[0]
            : null;
    }

    public static function InsertarFilasDevengo(Database $db, array $filas, string $usuario): int
    {
        if (empty($filas)) {
            return 0;
        }

        $logPath = self::getAuditoriaDevengoLogPath();

        for ($i = 0; $i < count($filas); $i++) {
            $fila = array_change_key_case((array) $filas[$i], CASE_UPPER);
            if (trim((string) ($fila['INICIO'] ?? '')) !== '') {
                continue;
            }

            $fechaCalc = trim((string) ($fila['FECHA_CALC_ISO'] ?? $fila['FECHA_CALC'] ?? ''));
            $credito = trim((string) ($fila['CDGCLNS'] ?? $fila['CREDITO'] ?? ''));
            $ciclo = trim((string) ($fila['CICLO'] ?? ''));
            if ($fechaCalc === '' || $credito === '' || $ciclo === '') {
                continue;
            }

            try {
                $enriquecida = self::EnriquecerUnaFilaParaInsertar($db, $credito, $ciclo, $fechaCalc);
                if ($enriquecida !== null) {
                    $filas[$i] = $enriquecida;
                }
            } catch (\Throwable $e) {
                @file_put_contents($logPath, date('c') . " [InsertarFilasDevengo] Enriquecer $credito/$ciclo/$fechaCalc: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        try {
            $schemaStmt = $db->db_activa->prepare("SELECT SYS_CONTEXT('USERENV','CURRENT_SCHEMA') AS SCH FROM DUAL");
            $schemaStmt->execute();
            $row = $schemaStmt->fetch(\PDO::FETCH_ASSOC);
            $currentSchema = strtoupper((string) ($row['SCH'] ?? ''));
            if ($currentSchema !== 'ESIACOM') {
                $db->db_activa->exec("ALTER SESSION SET CURRENT_SCHEMA = ESIACOM");
            }
        } catch (\Throwable $e) {
            @file_put_contents($logPath, date('c') . " [InsertarFilasDevengo] Error esquema: " . $e->getMessage() . "\n", FILE_APPEND);
            throw new \Exception("Error al cambiar esquema a ESIACOM: " . $e->getMessage());
        }

        $sqlInsert = <<<SQL
            INSERT INTO ESIACOM.DEVENGO_DIARIO (
                FECHA_CALC, CDGEM, CDGCLNS, CICLO, INICIO, DEV_DIARIO, DIAS_DEV, INT_DEV,
                CDGPE, FREGISTRO, DEV_DIARIO_SIN_IVA, IVA_INT, PLAZO, PERIODICIDAD, PLAZO_DIAS, FIN_DEVENGO, ESTATUS, CLNS
            ) VALUES (
                TO_TIMESTAMP(:fecha_calc || ' 00:00:00', 'YYYY-MM-DD HH24:MI:SS'),
                :cdgem, :cdgclns, :ciclo, TO_DATE(:inicio, 'YYYY-MM-DD'), :dev_diario, :dias_dev, :int_dev,
                :cdgpe, SYSTIMESTAMP, :dev_diario_sin_iva, :iva_int, :plazo, :periodicidad, :plazo_dias,
                TO_DATE(:fin_devengo, 'YYYY-MM-DD'), 'RE', 'G'
            )
        SQL;

        $stmtInsert = $db->db_activa->prepare($sqlInsert);
        $stmtCheck = $db->db_activa->prepare(
            "SELECT 1 FROM ESIACOM.DEVENGO_DIARIO WHERE CDGCLNS = :credito AND CICLO = :ciclo AND TRUNC(FECHA_CALC) = TO_DATE(:fecha_calc, 'YYYY-MM-DD')"
        );

        $insertados = 0;
        foreach ($filas as $fila) {
            $fila = array_change_key_case((array) $fila, CASE_UPPER);
            $fechaCalc = trim((string) ($fila['FECHA_CALC_ISO'] ?? $fila['FECHA_CALC'] ?? ''));
            $credito = trim((string) ($fila['CDGCLNS'] ?? $fila['CREDITO'] ?? ''));
            $ciclo = trim((string) ($fila['CICLO'] ?? ''));
            $inicio = trim((string) ($fila['INICIO'] ?? ''));

            if ($fechaCalc === '' || $credito === '' || $ciclo === '' || $inicio === '') {
                continue;
            }

            try {
                $stmtCheck->execute([
                    'credito' => $credito,
                    'ciclo' => $ciclo,
                    'fecha_calc' => $fechaCalc,
                ]);
                if ($stmtCheck->fetch()) {
                    continue;
                }
            } catch (\Throwable $e) {
                @file_put_contents($logPath, date('c') . " [InsertarFilasDevengo] Check duplicado $credito/$ciclo/$fechaCalc: " . $e->getMessage() . "\n", FILE_APPEND);
                continue;
            }

            try {
                $stmtInsert->execute([
                    'fecha_calc' => $fechaCalc,
                    'cdgem' => $fila['CDGEM'] ?? 'EMPFIN',
                    'cdgclns' => $credito,
                    'ciclo' => $ciclo,
                    'inicio' => $inicio,
                    'dev_diario' => (float) ($fila['DEV_DIARIO'] ?? 0),
                    'dias_dev' => (int) ($fila['DIAS_DEV'] ?? 0),
                    'int_dev' => (float) ($fila['INT_DEV'] ?? 0),
                    'cdgpe' => $usuario !== '' ? $usuario : ($_SESSION['usuario'] ?? 'SYSTEM'),
                    'dev_diario_sin_iva' => (float) ($fila['DEV_DIARIO_SIN_IVA'] ?? 0),
                    'iva_int' => (float) ($fila['IVA_INT'] ?? 0),
                    'plazo' => (int) ($fila['PLAZO'] ?? 0),
                    'periodicidad' => $fila['PERIODICIDAD'] ?? 'S',
                    'plazo_dias' => (int) ($fila['PLAZO_DIAS'] ?? 0),
                    'fin_devengo' => trim((string) ($fila['FIN_DEVENGO'] ?? '')),
                ]);
                $insertados++;
            } catch (\Throwable $e) {
                @file_put_contents($logPath, date('c') . " [InsertarFilasDevengo] Fila $credito/$ciclo/$fechaCalc: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        return $insertados;
    }

    public static function ObtenerFechasFaltantesParaInsertar(
        Database $db,
        string $credito,
        string $ciclo,
        string $fechaCorte,
        $inicioTs,
        $finTs
    ): array {
        $finTsStr = $finTs instanceof \DateTimeInterface
            ? $finTs->format('Y-m-d')
            : date('Y-m-d', is_numeric($finTs) ? $finTs : strtotime((string) $finTs));
        $inicioStr = $inicioTs instanceof \DateTimeInterface
            ? $inicioTs->format('Y-m-d')
            : date('Y-m-d', is_numeric($inicioTs) ? $inicioTs : strtotime((string) $inicioTs));
        $corte = $fechaCorte;
        if (strtotime($finTsStr) < strtotime($corte)) {
            $corte = $finTsStr;
        }

        $qry = <<<SQL
            WITH n AS (SELECT LEVEL - 1 AS l FROM DUAL CONNECT BY LEVEL <= 3660)
            SELECT TO_CHAR(TO_DATE(:inicio, 'YYYY-MM-DD') + 1 + n.l, 'YYYY-MM-DD') AS FECHA_CALC
            FROM n
            WHERE TO_DATE(:inicio, 'YYYY-MM-DD') + 1 + n.l <= LEAST(TO_DATE(:corte, 'YYYY-MM-DD'), TO_DATE(:fin, 'YYYY-MM-DD'))
            AND NOT EXISTS (
                SELECT 1 FROM ESIACOM.DEVENGO_DIARIO DD
                WHERE DD.CDGCLNS = :credito AND DD.CICLO = :ciclo
                AND TRUNC(DD.FECHA_CALC) = TO_DATE(:inicio, 'YYYY-MM-DD') + 1 + n.l
            )
            ORDER BY n.l
        SQL;
        $params = [
            'credito' => $credito,
            'ciclo' => $ciclo,
            'inicio' => $inicioStr,
            'fin' => $finTsStr,
            'corte' => $corte,
        ];

        try {
            $rows = $db->queryAll($qry, $params);
            if (!is_array($rows)) {
                return [];
            }

            $fechas = [];
            foreach ($rows as $row) {
                if (!empty($row['FECHA_CALC'])) {
                    $fechas[] = $row['FECHA_CALC'];
                }
            }
            return $fechas;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function InsertarRegistrosDevengoDiario(
        Database $db,
        string $credito,
        string $ciclo,
        string $usuario,
        array $datosBase,
        array $fechasOrdenadas
    ): int {
        if (empty($fechasOrdenadas)) {
            return 0;
        }

        $plazoDias = (int) ($datosBase['PLAZO_DIAS'] ?? 0);
        $devengoTotal = (float) ($datosBase['DEVENGO_TOTAL'] ?? 0);
        $iva = (float) ($datosBase['IVA'] ?? 0);
        $inicioRaw = $datosBase['INICIO'] ?? '';
        $inicioStr = $inicioRaw instanceof \DateTimeInterface
            ? $inicioRaw->format('Y-m-d')
            : (is_numeric($inicioRaw) ? date('Y-m-d', (int) $inicioRaw) : date('Y-m-d', strtotime((string) $inicioRaw)));
        $finTs = $datosBase['FIN_TS'] ?? '';
        $finStr = $finTs instanceof \DateTimeInterface
            ? $finTs->format('Y-m-d')
            : (is_numeric($finTs) ? date('Y-m-d', (int) $finTs) : date('Y-m-d', strtotime((string) $finTs)));
        $cdgem = $datosBase['CDGEM'] ?? 'EMPFIN';
        $periodicidad = $datosBase['PERIODICIDAD'] ?? 'S';
        $plazo = (int) ($datosBase['PLAZO'] ?? 0);
        $devengoDiarioFijo = $plazoDias > 0 ? round($devengoTotal / $plazoDias, 2) : 0.0;
        $acumulado = 0.0;
        $insertados = 0;

        $stmtInsert = $db->db_activa->prepare(<<<SQL
            INSERT INTO ESIACOM.DEVENGO_DIARIO (
                FECHA_CALC, CDGEM, CDGCLNS, CICLO, INICIO, DEV_DIARIO, DIAS_DEV, INT_DEV,
                CDGPE, FREGISTRO, DEV_DIARIO_SIN_IVA, IVA_INT, PLAZO, PERIODICIDAD, PLAZO_DIAS, FIN_DEVENGO, ESTATUS, CLNS
            ) VALUES (
                TO_TIMESTAMP(:fecha_calc || ' 00:00:00', 'YYYY-MM-DD HH24:MI:SS'),
                :cdgem, :cdgclns, :ciclo, TO_DATE(:inicio, 'YYYY-MM-DD'), :dev_diario, :dias_dev, :int_dev,
                :cdgpe, SYSTIMESTAMP, :dev_diario_sin_iva, :iva_int, :plazo, :periodicidad, :plazo_dias,
                TO_DATE(:fin_devengo, 'YYYY-MM-DD'), 'RE', 'G'
            )
        SQL);

        foreach ($fechasOrdenadas as $fechaCalc) {
            $fechaCalc = trim((string) $fechaCalc);
            if ($fechaCalc === '') {
                continue;
            }
            $ts = strtotime($fechaCalc);
            if ($ts === false) {
                continue;
            }

            $diasDev = (int) round((strtotime($fechaCalc . ' 12:00:00') - strtotime($inicioStr . ' 12:00:00')) / 86400);
            if ($diasDev < 0) {
                continue;
            }

            $esUltimoDia = ($fechaCalc === $finStr);
            $devDiario = $esUltimoDia ? round($devengoTotal - $acumulado, 2) : $devengoDiarioFijo;
            $devDiario = round($devDiario, 2);
            $acumulado = round($acumulado + $devDiario, 2);
            $devDiarioSinIva = round($devDiario / (1 + $iva), 2);
            $ivaInt = round($devDiario - $devDiarioSinIva, 2);

            try {
                $stmtCheck = $db->db_activa->prepare(
                    "SELECT 1 FROM ESIACOM.DEVENGO_DIARIO WHERE CDGCLNS = :c AND CICLO = :cic AND TRUNC(FECHA_CALC) = TO_DATE(:f, 'YYYY-MM-DD')"
                );
                $stmtCheck->execute(['c' => $credito, 'cic' => $ciclo, 'f' => $fechaCalc]);
                if ($stmtCheck->fetch()) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }

            try {
                $stmtInsert->execute([
                    'fecha_calc' => $fechaCalc,
                    'cdgem' => $cdgem,
                    'cdgclns' => $credito,
                    'ciclo' => $ciclo,
                    'inicio' => $inicioStr,
                    'dev_diario' => $devDiario,
                    'dias_dev' => $diasDev,
                    'int_dev' => $acumulado,
                    'cdgpe' => $usuario !== '' ? $usuario : ($_SESSION['usuario'] ?? 'SYSTEM'),
                    'dev_diario_sin_iva' => $devDiarioSinIva,
                    'iva_int' => $ivaInt,
                    'plazo' => $plazo,
                    'periodicidad' => $periodicidad,
                    'plazo_dias' => $plazoDias,
                    'fin_devengo' => $finStr,
                ]);
                $insertados++;
            } catch (\Throwable $e) {
            }
        }

        return $insertados;
    }

    public static function InsertarDevengosFaltantes(
        Database $db,
        string $credito,
        string $ciclo,
        string $usuario,
        string $fechaCorte
    ): int {
        $logPath = self::getAuditoriaDevengoLogPath();

        $currentSchema = null;
        try {
            $schemaStmt = $db->db_activa->prepare("SELECT SYS_CONTEXT('USERENV','CURRENT_SCHEMA') AS SCH FROM DUAL");
            $schemaStmt->execute();
            $row = $schemaStmt->fetch(\PDO::FETCH_ASSOC);
            $currentSchema = $row['SCH'] ?? '?';
        } catch (\Throwable $e) {
            $currentSchema = 'error:' . $e->getMessage();
        }
        @file_put_contents($logPath, date('c') . " [INSERT] PRE credito=$credito | ciclo=$ciclo | usuario=$usuario | fecha_corte=$fechaCorte | schema=$currentSchema\n", FILE_APPEND);

        if ($currentSchema !== null && strtoupper((string) $currentSchema) !== 'ESIACOM') {
            try {
                $db->db_activa->exec("ALTER SESSION SET CURRENT_SCHEMA = ESIACOM");
                @file_put_contents($logPath, date('c') . " [INSERT] ALTER SESSION SET CURRENT_SCHEMA = ESIACOM\n", FILE_APPEND);
            } catch (\Throwable $e) {
                @file_put_contents($logPath, date('c') . " [INSERT] ALTER SESSION error: " . $e->getMessage() . "\n", FILE_APPEND);
                throw new \Exception("Error al cambiar esquema a ESIACOM: " . $e->getMessage());
            }
        }

        $datosBase = self::ObtenerDatosBaseDevengo($db, $credito, $ciclo);
        if (!$datosBase) {
            @file_put_contents($logPath, date('c') . " [INSERT] No se obtuvieron datos base para $credito / $ciclo\n", FILE_APPEND);
            return 0;
        }

        $inicioTs = $datosBase['INICIO'];
        $finTs = $datosBase['FIN_TS'];
        $fechas = self::ObtenerFechasFaltantesParaInsertar($db, $credito, $ciclo, $fechaCorte, $inicioTs, $finTs);
        if (empty($fechas)) {
            $existStmt = $db->db_activa->prepare(
                "SELECT COUNT(*) AS C FROM ESIACOM.DEVENGO_DIARIO WHERE CDGCLNS = :c AND CICLO = :cic"
            );
            $existStmt->execute(['c' => $credito, 'cic' => $ciclo]);
            $row = $existStmt->fetch(\PDO::FETCH_ASSOC);
            $totalExistentes = (int) ($row['C'] ?? 0);
            @file_put_contents($logPath, date('c') . " [INSERT] Cero fechas faltantes; total existentes=$totalExistentes\n", FILE_APPEND);
            return $totalExistentes > 0 ? -1 : 0;
        }

        $insertados = self::InsertarRegistrosDevengoDiario($db, $credito, $ciclo, $usuario, $datosBase, $fechas);
        @file_put_contents($logPath, date('c') . " [INSERT] Insertados=$insertados\n", FILE_APPEND);

        if ($insertados === 0) {
            $existStmt = $db->db_activa->prepare(
                "SELECT COUNT(*) AS C FROM ESIACOM.DEVENGO_DIARIO WHERE CDGCLNS = :c AND CICLO = :cic"
            );
            $existStmt->execute(['c' => $credito, 'cic' => $ciclo]);
            $row = $existStmt->fetch(\PDO::FETCH_ASSOC);
            $totalExistentes = (int) ($row['C'] ?? 0);
            if ($totalExistentes > 0) {
                return -1;
            }
        }

        return $insertados;
    }

    public static function InsertarBitacora(
        Database $db,
        string $credito,
        string $ciclo,
        string $fechaProcesada,
        string $tipoEjecucion,
        string $usuario,
        string $perfil,
        string $resultado,
        ?string $mensajeError,
        string $ip
    ): void {
        $qry = <<<SQL
            INSERT INTO BITACORA_AUDITORIA_DEVENGO (
                CREDITO, CICLO, FECHA_PROCESADA, TIPO_EJECUCION, USUARIO, PERFIL,
                FECHA_EJECUCION, RESULTADO, MENSAJE_ERROR, IP
            ) VALUES (
                :credito, :ciclo, TO_DATE(:fecha_procesada, 'YYYY-MM-DD'), :tipo_ejecucion,
                :usuario, :perfil, SYSDATE, :resultado, :mensaje_error, :ip
            )
SQL;

        $db->insertar($qry, [
            'credito' => $credito,
            'ciclo' => $ciclo,
            'fecha_procesada' => $fechaProcesada,
            'tipo_ejecucion' => $tipoEjecucion,
            'usuario' => $usuario,
            'perfil' => $perfil,
            'resultado' => $resultado,
            'mensaje_error' => $mensajeError ?? '',
            'ip' => $ip,
        ]);
    }

    public static function InsertarBitacoraLog(
        string $credito,
        string $ciclo,
        string $fechaProcesada,
        string $tipoEjecucion,
        string $usuario,
        string $perfil,
        string $resultado,
        string $mensajeError,
        string $ip
    ): void {
        $db = new Database();
        self::InsertarBitacora($db, $credito, $ciclo, $fechaProcesada, $tipoEjecucion, $usuario, $perfil, $resultado, $mensajeError, $ip);
    }

    /**
     * Monitoreo con los db links publicados `DB_CULTIVA` y `DB_MCM`. Siempre se
     * intenta primero: `v$...@{dblink}`. Si Oracle devuelve **ORA-02019** (el
     * dblink no está creado en el servidor) y la sesión es en realidad la base
     * de ese destino (p. ej. app conectada a Cultiva sin link `DB_CULTIVA` a
     * sí misma), se repite la misma consulta sobre la conexión actual, sin
     * cambiar el contrato de la pantalla (dos target cultiva / mcm).
     */
    public static function GetEstatusBD()
    {
        $db = new Database();
        if (empty($db->db_activa)) {
            return self::Responde(false, 'Base de datos no disponible.');
        }
        $ident = self::ObtenerIdentidadInstanciaEstatus($db);
        $datos = [
            'DB_CULTIVA'  => self::GetEstatusBaseDatosDblink($db, 'DB_CULTIVA', $ident),
            'DB_MCM'      => self::GetEstatusBaseDatosDblink($db, 'DB_MCM', $ident),
            'consultado_en' => date('Y-m-d H:i:s'),
        ];
        return self::Responde(true, 'Consulta exitosa', $datos);
    }

    /**
     * @return array{db_name: string, global_name: string, instance_name: string, haystack: string}
     */
    private static function ObtenerIdentidadInstanciaEstatus(Database $db): array
    {
        $qry = <<<'SQL'
SELECT
    (SELECT UPPER(TRIM(d.NAME)) FROM v$database d)            AS "DB_NAME",
    (SELECT UPPER(TRIM(g.GLOBAL_NAME)) FROM global_name g)  AS "GLOBAL_NAME",
    (SELECT UPPER(TRIM(i.INSTANCE_NAME)) FROM v$instance i) AS "INSTANCE_NAME"
FROM dual
SQL;
        $res = self::EstatusPrimeraFilaDblink($db, $qry);
        $row = (empty($res['error']) && \is_array($res['data'])) ? $res['data'] : [
            'DB_NAME' => '', 'GLOBAL_NAME' => '', 'INSTANCE_NAME' => '',
        ];
        $h = trim(
            (string) ($row['DB_NAME'] ?? '') . ' ' .
            (string) ($row['GLOBAL_NAME'] ?? '') . ' ' .
            (string) ($row['INSTANCE_NAME'] ?? '')
        );
        return [
            'db_name'       => (string) ($row['DB_NAME'] ?? ''),
            'global_name'   => (string) ($row['GLOBAL_NAME'] ?? ''),
            'instance_name' => (string) ($row['INSTANCE_NAME'] ?? ''),
            'haystack'      => $h,
        ];
    }

    private static function sesionEsInstalacionMcm(array $ident): bool
    {
        $h = ' ' . ($ident['haystack'] ?? '') . ' ';
        return (bool) preg_match('/\bMCM\b|MCM_|_MCM/iu', $h);
    }

    private static function sesionEsInstalacionCultiva(array $ident): bool
    {
        if (self::sesionEsInstalacionMcm($ident)) {
            return false;
        }
        $h = $ident['haystack'] ?? '';
        return (bool) preg_match('/CULTIVA|ESIACOM/iu', $h);
    }

    private static function puedeLecturaDirectaComoDblinkFaltante($dbLink, array $ident): bool
    {
        if ($dbLink === 'DB_CULTIVA') {
            return self::sesionEsInstalacionCultiva($ident);
        }
        if ($dbLink === 'DB_MCM') {
            return self::sesionEsInstalacionMcm($ident);
        }
        return false;
    }

    private static function GetEstatusBaseDatosDblink(Database $db, $dbLink, array $ident)
    {
        $qArchivoRemoto = "SELECT STATUS, ERROR FROM v\$archive_dest@{$dbLink} WHERE dest_id = 2";
        $qArchivoLocal  = 'SELECT STATUS, ERROR FROM v$archive_dest WHERE dest_id = 2';

        $qRecoveryRemoto = <<<SQL
SELECT
    NAME,
    ROUND(SPACE_LIMIT / 1024 / 1024 / 1024, 2)         AS LIMITE_GB,
    ROUND(SPACE_USED / 1024 / 1024 / 1024, 2)            AS USADO_GB,
    ROUND((SPACE_USED / SPACE_LIMIT) * 100, 2)           AS USADO_PCT,
    ROUND(SPACE_RECLAIMABLE / 1024 / 1024 / 1024, 2)   AS REUTILIZABLE_GB
FROM v\$recovery_file_dest@{$dbLink}
SQL;
        $qRecoveryLocal = <<<'SQL'
SELECT
    NAME,
    ROUND(SPACE_LIMIT / 1024 / 1024 / 1024, 2)         AS LIMITE_GB,
    ROUND(SPACE_USED / 1024 / 1024 / 1024, 2)            AS USADO_GB,
    ROUND((SPACE_USED / SPACE_LIMIT) * 100, 2)           AS USADO_PCT,
    ROUND(SPACE_RECLAIMABLE / 1024 / 1024 / 1024, 2)   AS REUTILIZABLE_GB
FROM v$recovery_file_dest
SQL;

        $a = self::EstatusDblinkConReintento02019(
            $db,
            $qArchivoRemoto,
            $qArchivoLocal,
            $ident,
            $dbLink
        );
        $r = self::EstatusDblinkConReintento02019(
            $db,
            $qRecoveryRemoto,
            $qRecoveryLocal,
            $ident,
            $dbLink
        );

        $origenA = $a['origen'] ?? 'dblink';
        $origenR = $r['origen'] ?? 'dblink';
        $origen = ($origenA === 'dblink' && $origenR === 'dblink') ? 'dblink' : 'sesion_02019';

        return [
            'db_link'            => $dbLink,
            'origen_consulta'    => $origen,
            'archive_dest'       => $a['data'],
            'archive_error'      => $a['error'],
            'recovery_file_dest' => $r['data'],
            'recovery_error'     => $r['error'],
        ];
    }

    /**
     * @return array{data: ?array, error: ?string, origen: string}
     */
    private static function EstatusDblinkConReintento02019(
        Database $db,
        $sqlDblink,
        $sqlMismaVistaEnSesion,
        array $ident,
        $dbLink
    ) {
        $res = self::EstatusPrimeraFilaDblink($db, $sqlDblink);
        if ($res['error'] === null) {
            $res['origen'] = 'dblink';
            return $res;
        }
        if (stripos($res['error'], 'ORA-02019') === false) {
            $res['origen'] = 'dblink';
            return $res;
        }
        if (!self::puedeLecturaDirectaComoDblinkFaltante($dbLink, $ident)) {
            $res['origen'] = 'dblink';
            return $res;
        }
        $alt = self::EstatusPrimeraFilaDblink($db, $sqlMismaVistaEnSesion);
        if ($alt['error'] === null) {
            $alt['origen'] = 'sesion_02019';
            return $alt;
        }
        $res['origen'] = 'dblink';
        return $res;
    }

    /**
     * @return array{data: ?array, error: ?string}
     */
    private static function EstatusPrimeraFilaDblink(Database $db, $sql)
    {
        try {
            $stmt = $db->db_activa->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return [
                'data'  => $row ?: null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'data'  => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
