<?php

namespace Jobs\models;

include_once dirname(__DIR__) . "/../Core/Model.php";
include_once dirname(__DIR__) . "/../Core/Database.php";

use Core\Model;
use Core\Database;

class CierreDia extends Model
{
    public static function ResultadoCierreDia(string $fecha, string $proceso): ?array
    {
        $qry = <<<SQL
            SELECT
                TO_CHAR(b.FECHA_CALCULO, 'DD/MM/YYYY') AS FECHA_CALCULO,
                b.USUARIO,
                TO_CHAR(b.INICIO, 'DD/MM/YYYY HH24:MI') AS INICIO,
                TO_CHAR(b.FIN, 'DD/MM/YYYY HH24:MI') AS FIN,
                NVL(b.EXITO, 0) AS EXITO,
                b.ID_IMPORTACION,
                b.ID_PROCESO,
                b.MENSAJE,
                b.CIERRE_REGISTROS,
                b.DEVENGO_REGISTROS,
                b.DEVENGO_MONTO,
                TO_CHAR(b.FECHA_CALCULO + 1, 'DD/MM/YYYY') AS DEVENGO_FECHA
            FROM BITACORA_CIERRE_DIARIO b
            WHERE TRUNC(b.FECHA_CALCULO) = TO_DATE(:fecha, 'YYYY-MM-DD')
              AND b.FIN IS NOT NULL
              AND b.ID_PROCESO = :proceso
            ORDER BY b.INICIO DESC NULLS LAST
            FETCH FIRST 1 ROW ONLY
        SQL;

        try {
            $r = (new Database())->queryOne($qry, ['fecha' => $fecha, 'proceso' => $proceso]);
            return is_array($r) ?
                self::Responde(true, "Datos de cierre obtenidos", $r) :
                self::Responde(false, "Datos de cierre no encontrados");
        } catch (\Throwable $e) {
            return self::Responde(false, "Datos de cierre no encontrados", null, $e->getMessage());
        }
    }

    public static function CierreDia($datos)
    {
        try {
            $db = new Database();
            $abierto = $db->queryOne('SELECT COUNT(*) AS TOTAL FROM BITACORA_CIERRE_DIARIO WHERE FIN IS NULL');
            if ($abierto && (int) ($abierto['TOTAL'] ?? 0) > 0)
                return self::Responde(false, 'Ya hay un cierre en ejecución.', null, 'Concurrencia SP');

            $fecha = isset($datos['fecha']) ? trim((string) $datos['fecha']) : '';
            $usuario = isset($datos['usuario']) ? trim((string) $datos['usuario']) : '';

            $sp = "CALL SP_PAGOS_CIERRE_DEVENGO(TO_DATE(:fecha, 'YYYY-MM-DD'), :usuario, :output)";

            $id_proceso = $db->EjecutaSP($sp, ['fecha' => $fecha, 'usuario' => $usuario]);
            return self::ResultadoCierreDia($fecha, $id_proceso);
        } catch (\Throwable $e) {
            return self::Responde(false, 'Error al ejecutar el SP de cierre de día.', null, $e->getMessage());
        }
    }

    public static function GetResumenCierreDia($datos)
    {
        $pagos = <<<SQL
            SELECT COUNT(*) AS TOTAL_REGISTROS,
                SUM(MONTO) AS TOTAL_MONTO,
                SUM(CASE WHEN ID_IMPORTACION IS NULL THEN 1 ELSE 0 END) AS PENDIENTES_REGISTROS,
                SUM(CASE WHEN ID_IMPORTACION IS NULL THEN MONTO ELSE 0 END) AS PENDIENTES_MONTO,
                SUM(CASE WHEN ID_IMPORTACION IS NOT NULL THEN 1 ELSE 0 END) AS APLICADOS_REGISTROS,
                SUM(CASE WHEN ID_IMPORTACION IS NOT NULL THEN MONTO ELSE 0 END) AS APLICADOS_MONTO
            FROM PAGOSDIA
            WHERE
                ESTATUS = 'A'
                AND TIPO IN ('P', 'G')
                AND MONTO != 0
                AND TRUNC(FECHA) = TO_DATE(:fecha, 'DD/MM/YYYY')
        SQL;

        $det = <<<SQL
            SELECT SUM(DECODE(VALIDACION, 0, 1, 0)) AS PAGOS_REGISTROS
                , SUM(DECODE(VALIDACION, 0, MONTO, 0)) AS PAGOS_MONTO
                , SUM(DECODE(VALIDACION, 1, 1, 0)) AS GARANTIAS_REGISTROS
                , SUM(DECODE(VALIDACION, 1, MONTO, 0)) AS GARANTIAS_MONTO
                , SUM(DECODE(VALIDACION, 2, 1, 0)) AS INCIDENCIAS_REGISTROS
                , SUM(DECODE(VALIDACION, 2, MONTO, 0)) AS INCIDENCIAS_MONTO
            FROM RES_IMPOR
            WHERE IDENTIFICADOR = :id_proceso
            AND CTABANCARIA <> '12'
        SQL;

        $mp = <<<SQL
            SELECT COUNT(*) AS TOTAL_REGISTROS,
                SUM(CANTIDAD) AS TOTAL_MONTO,
                SUM(CASE WHEN CONCILIADO = 'C' THEN 1 ELSE 0 END) AS PENDIENTES_REGISTROS,
                SUM(CASE WHEN CONCILIADO = 'C' THEN CANTIDAD ELSE 0 END) AS PENDIENTES_MONTO,
                SUM(CASE WHEN CONCILIADO = 'D' THEN 1 ELSE 0 END) AS CONCILIADOS_REGISTROS,
                SUM(CASE WHEN CONCILIADO = 'D' THEN CANTIDAD ELSE 0 END) AS CONCILIADOS_MONTO
            FROM MP
            WHERE TRUNC(FDEPOSITO) = TO_DATE(:fecha, 'DD/MM/YYYY')
            AND TIPO = 'PD'
            AND MODO = 'I'
        SQL;

        $garantias = <<<SQL
            SELECT SUM(DECODE(RENEXCEL, 1, 1, 0)) AS POR_SALDO
                , SUM(DECODE(RENEXCEL, 1, MONTO, 0)) AS POR_SALDO_MONTO
                , SUM(DECODE(RENEXCEL, 2, 1, 0)) AS POR_FECHA
                , SUM(DECODE(RENEXCEL, 2, MONTO, 0)) AS POR_FECHA_MONTO
            FROM RES_IMPOR
            WHERE IDENTIFICADOR = :id_proceso
            AND CTABANCARIA = '12'
            AND VALIDACION = 0
        SQL;

        try {
            $db = new Database();
            $res_pagos = $db->queryOne($pagos, ['fecha' => $datos['FECHA_CALCULO']]);
            $res_det = $db->queryOne($det, ['id_proceso' => $datos['ID_PROCESO']]);
            $res_mp = $db->queryOne($mp, ['fecha' => $datos['FECHA_CALCULO']]);
            $res_garantias = $db->queryOne($garantias, ['id_proceso' => $datos['ID_PROCESO']]);

            return self::Responde(true, 'Consulta exitosa', [
                'pagos' => $res_pagos,
                'detalle' => $res_det,
                'mp' => $res_mp,
                'garantias' => $res_garantias
            ]);
        } catch (\Exception $e) {
            return self::Responde(false, 'Error al consultar el resultado del cierre', null, $e->getMessage());
        }
    }

    public static function CorreoCierreDia($datos)
    {
        $qry = <<<SQL
            UPDATE BITACORA_CIERRE_DIARIO
            SET CORREO = 1
            WHERE TRUNC(FECHA_CALCULO) = TO_DATE(:fecha, 'DD/MM/YYYY')
                AND ID_PROCESO = :id_proceso
        SQL;

        $params = [
            'fecha' => $datos['FECHA_CALCULO'],
            'id_proceso' => $datos['ID_PROCESO']
        ];

        try {
            $db = new Database();
            $db->insertar($qry, $params);
            return self::Responde(true, 'Estatus del correo actualizado');
        } catch (\Exception $e) {
            return self::Responde(false, 'Error al actualizar el estatus del correo', null, $e->getMessage());
        }
    }
}
