<?php

namespace Jobs\models;

include_once dirname(__DIR__) . "/../Core/Model.php";
include_once dirname(__DIR__) . "/../Core/Database.php";

use Core\Model;
use Core\Database;

class CierreDia extends Model
{
    public static function CierreDia($datos)
    {
        $fecha = isset($datos['fecha']) ? trim((string) $datos['fecha']) : '';
        $usuario = isset($datos['usuario']) ? trim((string) $datos['usuario']) : '';
        $regenerar = !empty($datos['regenerar']);

        $sp = "CALL SP_PAGOS_CIERRE_DEVENGO(TO_DATE(:fecha, 'YYYY-MM-DD'), :usuario, :output)";

        // ID_IMPORTACION se reutiliza entre intentos. ORDER BY la columna DATE (b.INICIO),
        // nunca el alias TO_CHAR DD/MM (ordenaría 24/07 encima de 11/08).
        $qry = <<<SQL
            SELECT
                TO_CHAR(b.FECHA_CALCULO, 'DD/MM/YYYY') AS FECHA_CALCULO
                ,b.USUARIO
                ,TO_CHAR(b.INICIO, 'DD/MM/YYYY HH24:MI') AS INICIO
                ,TO_CHAR(b.FIN, 'DD/MM/YYYY HH24:MI') AS FIN
                ,b.EXITO
                ,b.ID_IMPORTACION
                ,b.ID_PROCESO
                ,b.MENSAJE
                ,b.CIERRE_REGISTROS
                ,b.DEVENGO_REGISTROS
                ,b.DEVENGO_MONTO
                ,TO_CHAR(b.FECHA_CALCULO + 1, 'DD/MM/YYYY') AS DEVENGO_FECHA
            FROM
                BITACORA_CIERRE_DIARIO b
            WHERE TRUNC(b.FECHA_CALCULO) = TO_DATE(:fecha, 'YYYY-MM-DD')
              AND b.ID_IMPORTACION IS NOT NULL
            ORDER BY b.INICIO DESC NULLS LAST
            FETCH FIRST 1 ROW ONLY
        SQL;

        $parametros = [
            'fecha' => $fecha,
            'usuario' => $usuario
        ];

        try {
            $db = new Database();
            $abierto = $db->queryOne("
                SELECT COUNT(*) AS TOTAL
                FROM BITACORA_CIERRE_DIARIO
                WHERE FIN IS NULL
                  AND ID_IMPORTACION IS NOT NULL
            ");
            if ($abierto && (int) ($abierto['TOTAL'] ?? 0) > 0) {
                return self::Responde(false, 'Ya hay un cierre en ejecución.', null, 'Concurrencia SP');
            }

            if ($regenerar) {
                // Devengo del cierre X se guarda en FECHA_CALC = X+1. TBL_CIERRE_DIA usa la fecha de cierre.
                $fechaDevengo = date('Y-m-d', strtotime($fecha . ' +1 day'));
                $sqlRegen = <<<PLSQL
                    BEGIN
                    DELETE FROM DEVENGO_DIARIO d
                    WHERE TRUNC(d.FECHA_CALC) = TO_DATE(:f1, 'YYYY-MM-DD');
                    DELETE FROM TBL_CIERRE_DIA t
                    WHERE TRUNC(t.FECHA_CALC) = TO_DATE(:f2, 'YYYY-MM-DD');
                    END;
                PLSQL;
                $db->db_activa->prepare($sqlRegen)->execute(['f1' => $fechaDevengo, 'f2' => $fecha]);
            }

            $db->EjecutaSP($sp, $parametros);
            $res = $db->queryOne($qry, ['fecha' => $fecha]);
            if (!$res) {
                return self::Responde(false, 'Error en el proceso', null, 'No se encontró bitácora del SP para la fecha.');
            }

            $finTxt = trim((string) ($res['FIN'] ?? ''));
            if ($finTxt === '' && (int) ($res['EXITO'] ?? 0) === 1) {
                $db->db_activa->prepare(<<<SQL
                    UPDATE BITACORA_CIERRE_DIARIO b
                    SET b.FIN = SYSDATE
                    WHERE TRUNC(b.FECHA_CALCULO) = TO_DATE(:fecha, 'YYYY-MM-DD')
                      AND b.ID_IMPORTACION IS NOT NULL
                      AND b.FIN IS NULL
                SQL)->execute(['fecha' => $fecha]);
                $res = $db->queryOne($qry, ['fecha' => $fecha]);
            }

            return ((int) ($res['EXITO'] ?? 0) === 1) ?
                self::Responde(true, 'Proceso exitoso', $res) :
                self::Responde(false, 'Error en el proceso', $res);
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
                AND TIPO IN ('P', 'G', 'X')
                AND MONTO != 0
                AND TRUNC(FECHA) = TO_DATE(:fecha, 'DD/MM/YYYY')
        SQL;

        $det = <<<SQL
            SELECT SUM(CASE WHEN ESTATUS = 1 THEN NO_REGISTROS ELSE 0 END) AS PAGOS_REGISTROS,
                 SUM(CASE WHEN ESTATUS = 1 THEN MONTO ELSE 0 END) AS PAGOS_MONTO,
                 SUM(CASE WHEN ESTATUS = 2 THEN NO_REGISTROS ELSE 0 END) AS GARANTIAS_REGISTROS,
                 SUM(CASE WHEN ESTATUS = 2 THEN MONTO ELSE 0 END) AS GARANTIAS_MONTO,
                 SUM(CASE WHEN ESTATUS = 3 THEN NO_REGISTROS ELSE 0 END) AS INCIDENCIAS_REGISTROS,
                 SUM(CASE WHEN ESTATUS = 3 THEN MONTO ELSE 0 END) AS INCIDENCIAS_MONTO
            FROM IMPORTACIONPAGDET
            WHERE ID_IMPORTACION = :id_importacion
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
            $res_det = $db->queryOne($det, ['id_importacion' => $datos['ID_IMPORTACION']]);
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
                AND ID_IMPORTACION = :id_importacion
                AND ID_PROCESO = :id_proceso
        SQL;

        $params = [
            'fecha' => $datos['FECHA_CALCULO'],
            'id_importacion' => $datos['ID_IMPORTACION'],
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
