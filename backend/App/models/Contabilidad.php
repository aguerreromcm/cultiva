<?php

namespace App\models;

defined("APPPATH") or die("Access denied");

use Core\Database;
use Core\Model;

class Contabilidad extends Model
{
    public static function BuscaGrupo($datos)
    {
        $qry = <<<SQL
            SELECT
                PRC.CDGNS AS GRUPO,
                NS.NOMBRE AS NOMBRE_GRUPO,
                PRC.CDGCL AS CLIENTE,
                (PRC.CANTENTRE - NVL(SC.COMISION, 0)) AS PRESTAMO,
                NVL(SC.COMISION, 0) AS SEGURO_FINANCIADO,
                PRC.CANTENTRE AS TOTAL_CREDITO,
                (PRC.CANTENTRE - NVL(SC.COMISION, 0)) * .1 AS GARANTIA,
                TO_CHAR(PRC.SOLICITUD, 'DD/MM/YYYY') AS FECHA_INICIO
            FROM
                PRC
                INNER JOIN SC ON SC.CDGCL = PRC.CDGCL AND SC.CDGNS = PRC.CDGNS AND SC.CICLO = PRC.CICLO AND SC.SITUACION = 'A'
                INNER JOIN NS ON NS.CODIGO = PRC.CDGNS
            WHERE
                PRC.CDGNS = :grupo
                AND PRC.CICLO = :ciclo
            ORDER BY
                PRC.SOLICITUD ASC
        SQL;

        $prm = [
            'grupo' => $datos['grupo'],
            'ciclo' => $datos['ciclo']
        ];

        try {
            $db = new Database();
            $res = $db->queryAll($qry, $prm);
            return self::Responde(true, "Consulta exitosa", $res);
        } catch (\Exception $e) {
            return self::Responde(false, 'Error al ejecutar la consulta', null, $e->getMessage());
        }
    }

    /**
     * Reporte de análisis de garantías (Reporte GL).
     *
     * Consulta EXACTAMENTE igual a garantias.sql. Las únicas modificaciones
     * respecto al original son la sustitución de las fechas literales
     * ('2026-01-01' y '2026-04-15') por bind parameters para permitir un
     * rango de fechas configurable desde la UI. Se usan nombres únicos
     * (:ffin1..:ffin8) porque PDO/OCI no permite repetir placeholders.
     *
     * @param array $datos ['fechaInicial' => 'YYYY-MM-DD', 'fechaFinal' => 'YYYY-MM-DD']
     */
    public static function GetReporteGL($datos)
    {
        $qry = <<<SQL
            WITH SALDO_INICIAL AS (
                SELECT
                    CDGCLNS AS CREDITO
                    ,SUM(CANTIDAD) AS SDO_INI
                FROM PAG_GAR_SIM
                WHERE TRUNC(FPAGO) < TO_DATE(:fechaInicial, 'YYYY-MM-DD')
                GROUP BY CDGEM, CDGCLNS, CLNS
            )
            , GARANTIAS AS (
                SELECT
                    CDGCLNS AS CREDITO
                    ,SUM(DECODE(ESTATUS, 'RE', CANTIDAD, 0)) AS RE
                    ,SUM(DECODE(ESTATUS, 'DC', CANTIDAD, 0)) AS DC
                    ,SUM(DECODE(ESTATUS, 'CO', CANTIDAD, 0)) AS CO
                    ,SUM(DECODE(ESTATUS, 'DA', CANTIDAD, 0)) AS DA
                    ,SUM(DECODE(ESTATUS, 'CP', CANTIDAD, 0)) AS CP
                    ,SUM(DECODE(ESTATUS, 'CG', CANTIDAD, 0)) AS CG
                    ,SUM(DECODE(ESTATUS, 'CR', CANTIDAD, 0)) AS CR
                    ,SUM(DECODE(ESTATUS, 'CC', CANTIDAD, 0)) AS CC
                    ,SUM(DECODE(ESTATUS, 'CI', CANTIDAD, 0)) AS CI
                    ,SUM(DECODE(ESTATUS, 'CA', CANTIDAD, 0)) AS CA
                    ,SUM(DECODE(ESTATUS, 'GP', CANTIDAD, 0)) AS GP
                    ,SUM(DECODE(ESTATUS, 'DE', CANTIDAD, 0)) AS DE
                    ,SUM(DECODE(ESTATUS, 'CD', CANTIDAD, 0)) AS CD
                    ,SUM(CANTIDAD) AS TOTAL
                    ,COUNT(*) AS MOVIMIENTOS
                FROM PAG_GAR_SIM
                WHERE TRUNC(FPAGO) BETWEEN TO_DATE(:fechaInicial, 'YYYY-MM-DD') AND TO_DATE(:fechaFinal, 'YYYY-MM-DD')
                GROUP BY CDGEM, CDGCLNS, CLNS
            )
            , CREDITOS AS (
                SELECT 
                    SN.CDGNS AS CREDITO
                    ,DECODE(NVL(PRN.SITUACION, SN.SITUACION), 'R', 'RECHAZADO', 'A', 'AUTORIZADO POR CARTERA', 'S', 'SOLICITADO', 'E', 'ENTREGADO', 'D', 'DEVUELTO', 'T', 'AUTORIZADO POR TESORERIA', 'L', 'LIQUIDADO', 'NO APLICA') AS SITUACION
                    ,ROW_NUMBER() OVER (PARTITION BY SN.CDGEM, SN.CDGNS ORDER BY SN.INICIO DESC, SN.SOLICITUD DESC) AS RN
                FROM SN
                    LEFT JOIN PRN ON SN.CDGEM = PRN.CDGEM AND SN.CDGNS = PRN.CDGNS AND SN.CICLO = PRN.CICLO AND SN.INICIO = PRN.INICIO
                ORDER BY SN.INICIO DESC
            )
            , GRUPOS AS (
                SELECT
                    NS.CODIGO AS CREDITO
                    ,NS.NOMBRE AS GRUPO
                    ,RG.NOMBRE AS REGION
                    ,CO.NOMBRE AS SUCURSAL
                    ,NS.CDGACPE AS ASESOR
                    ,GET_NOMBRE_EMPLEADO(NS.CDGACPE) AS ASESOR_NOMBRE
                FROM NS
                    LEFT JOIN CO ON CO.CODIGO = NS.CDGCO
                    LEFT JOIN RG ON RG.CODIGO = CO.CDGRG
            )
            SELECT
                GRP.REGION
                ,GRP.ASESOR
                ,GRP.ASESOR_NOMBRE
                ,C.CREDITO
                ,GRP.GRUPO
                ,C.SITUACION
                ,NVL(SI.SDO_INI, 0) AS SDO_INI
                ,NVL(SI.SDO_INI, 0) + NVL(G.TOTAL, 0) AS SDO_FIN
                ,NVL(G.RE, 0) AS "PAGO COMISION"
                ,NVL(G.DC, 0) AS "DEVOLUCION POR CANCELACION DE CHEQUE"
                ,NVL(G.CO, 0) AS "CONCILIACION COMISION"
                ,NVL(G.DA, 0) AS "PAGO ADELANTADO"
                ,NVL(G.CP, 0) AS "CANCELACION POR APLICACION A PAGO DE CREDITO"
                ,NVL(G.CG, 0) AS "CANCELACION POR TRASPASO DE GARANTIA A CICLO SIGUIENTE"
                ,NVL(G.CR, 0) AS "CANCELACION PAGO COMISION"
                ,NVL(G.CC, 0) AS "CANCELACION DE CONCILIACION COMISION"
                ,NVL(G.CI, 0) AS "CANCELACION DE PAGO ADELANTADO"
                ,NVL(G.CA, 0) AS "MOVIMIENTO CANCELADO"
                ,NVL(G.GP, 0) AS "TRASPASO DE GARANTIA A PAGO"
                ,NVL(G.DE, 0) AS "DEVOLUCION POR DEPOSITO EXCEDENTE"
                ,NVL(G.CD, 0) AS "CANCELACION DE CHEQUE DE DEVOLUCION DE GARANTIA"
            FROM
                CREDITOS C
                LEFT JOIN GRUPOS GRP ON GRP.CREDITO = C.CREDITO AND C.RN = 1
                LEFT JOIN SALDO_INICIAL SI ON SI.CREDITO = C.CREDITO
                LEFT JOIN GARANTIAS G ON G.CREDITO = C.CREDITO
            WHERE
                NOT GRP.CREDITO  IS NULL
                AND (NVL(SI.SDO_INI, 0) <> 0
                OR G.MOVIMIENTOS <> 0)
        SQL;

        $prm = [
            'fechaInicial'  => $datos['fechaInicial'],
            'fechaFinal' => $datos['fechaFinal']
        ];

        try {
            $db = new Database();
            $res = $db->queryAll($qry, $prm);
            return self::Responde(true, "Consulta exitosa", $res);
        } catch (\Exception $e) {
            return self::Responde(false, 'Error al ejecutar la consulta', null, $e->getMessage());
        }
    }
}
