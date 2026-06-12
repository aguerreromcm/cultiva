<?php

namespace App\models;

defined("APPPATH") or die("Access denied");

use Core\Database;
use Core\Model;

class ApiCondusef extends Model
{
    public static function GetProductosREDECO()
    {
        $query = <<<sql
            SELECT
                CODIGO,
                SUBPRODUCTO as producto
            FROM
                CAT_PROD_SERV_RED
        sql;

        try {
            $db = new Database();
            $resultado =  $db->queryAll($query);
            return self::Responde(true, "Consulta exitosa", $resultado);
        } catch (\Exception $e) {
            return self::Responde(false, "Error al obtener las causas de reclamación: ", null, $e->getMessage());
        }
    }

    public static function GetCausasREDECO()
    {
        $query = <<<sql
            SELECT
                CODIGO,
                DESCRIPCION
            FROM
                CAT_CAUSA_QUEJA_RED
        sql;

        try {
            $db = new Database();
            $resultado =  $db->queryAll($query);
            return self::Responde(true, "Consulta exitosa", $resultado);
        } catch (\Exception $e) {
            return self::Responde(false, "Error al obtener las causas de reclamación: ", null, $e->getMessage());
        }
    }

    public static function GetProductosREUNE()
    {
        $qry = <<<SQL
            SELECT
                RP.CODIGO_PRODUCTO
                ,RP.NOMBRE_CORTO
                ,RC.CODIGO_CAUSA
                ,RC.DESCRIPCION
                ,RC.CONSULTA
                ,RC.RECLAMACION
                ,RC.ACLARACION 
            FROM
                REUNE_CAUSAS RC
                LEFT JOIN REUNE_PRODUCTOS RP ON RP.ID = RC.PRODUCTO_ID
            WHERE
                RP.ACTIVO = 1
                AND RC.ACTIVO = 1
            ORDER BY
                RP.NOMBRE_CORTO,
                RC.DESCRIPCION
        SQL;

        try {
            $db = new Database();
            $res = $db->queryAll($qry);

            $productos = [];
            $causas    = [];

            foreach ($res as $row) {
                $codProd = $row['CODIGO_PRODUCTO'];

                if (!isset($productos[$codProd])) {
                    $productos[$codProd] = $row['NOMBRE_CORTO'];
                }

                $causas[$codProd][] = [
                    'valor' => $row['CODIGO_CAUSA'],
                    'texto' => $row['DESCRIPCION'],
                    'consulta' => $row['CONSULTA'],
                    'reclamacion' => $row['RECLAMACION'],
                    'aclaracion' => $row['ACLARACION']
                ];
            }

            return self::Responde(true, "Consulta exitosa", ['productos' => $productos, 'causas' => $causas]);
        } catch (\Exception $e) {
            return self::Responde(false, "Error al obtener las causas de reclamación: ", null, $e->getMessage());
        }
    }
}
