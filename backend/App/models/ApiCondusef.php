<?php

namespace App\models;

defined("APPPATH") or die("Access denied");

use Core\Database;

class ApiCondusef
{
    public static function GetProductos()
    {
        $query = <<<sql
            SELECT
                CODIGO,
                SUBPRODUCTO as producto
            FROM
                CAT_PROD_SERV_RED
        sql;

        $db = new Database();
        if ($db->db_activa == null) return [];
        $resultado =  $db->queryAll($query);
        if ($resultado == null) return [];
        return $resultado;
    }

    public static function GetCausas()
    {
        $query = <<<sql
            SELECT
                CODIGO,
                DESCRIPCION
            FROM
                CAT_CAUSA_QUEJA_RED
        sql;

        $db = new Database();
        if ($db->db_activa == null) return [];
        $resultado =  $db->queryAll($query);
        if ($resultado == null) return [];
        return $resultado;
    }

    public static function GetReuneProductos()
    {
        $query = <<<sql
            SELECT
                ID,
                NOMBRE_CORTO,
                CODIGO_PRODUCTO
            FROM
                REUNE_PRODUCTOS
            WHERE
                ACTIVO = 1
            ORDER BY
                NOMBRE_CORTO
        sql;

        $db = new Database();
        if ($db->db_activa == null) {
            return [];
        }

        $resultado = $db->queryAll($query);
        if ($resultado == null) {
            return [];
        }

        return array_map(static function ($row) {
            return [
                'id' => (int) $row['ID'],
                'nombre_corto' => $row['NOMBRE_CORTO'],
                'codigo_producto' => $row['CODIGO_PRODUCTO'],
            ];
        }, $resultado);
    }

    public static function GetReuneCausas($productoId)
    {
        $productoId = (int) $productoId;
        if ($productoId <= 0) {
            return [];
        }

        $query = <<<sql
            SELECT
                ID,
                DESCRIPCION,
                CODIGO_CAUSA,
                CONSULTA,
                RECLAMACION,
                ACLARACION
            FROM
                REUNE_CAUSAS
            WHERE
                PRODUCTO_ID = :producto_id
                AND ACTIVO = 1
            ORDER BY
                DESCRIPCION
        sql;

        $db = new Database();
        if ($db->db_activa == null) {
            return [];
        }

        $resultado = $db->queryAll($query, ['producto_id' => $productoId]);
        if ($resultado == null) {
            return [];
        }

        return array_map(static function ($row) {
            return [
                'id' => (int) $row['ID'],
                'descripcion' => $row['DESCRIPCION'],
                'codigo_causa' => $row['CODIGO_CAUSA'],
                'consulta' => (int) $row['CONSULTA'],
                'reclamacion' => (int) $row['RECLAMACION'],
                'aclaracion' => (int) $row['ACLARACION'],
            ];
        }, $resultado);
    }
}
