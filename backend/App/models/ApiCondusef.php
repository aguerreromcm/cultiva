<?php

namespace App\models;

defined("APPPATH") or die("Access denied");

use Core\Database;
use Core\Model;

class ApiCondusef extends Model
{
    public static function GetProductos()
    {
        $qry = <<<SQL
            SELECT
                RP.CODIGO_PRODUCTO
                ,RP.NOMBRE_CORTO
                ,RC.CODIGO_CAUSA
                ,RC.DESCRIPCION
            FROM
                REUNE_CAUSAS RC
                LEFT JOIN REUNE_PRODUCTOS RP ON RP.ID = RC.PRODUCTO_ID
            WHERE
                RP.ACTIVO = 1
                AND RC.ACTIVO = 1
                AND RC.RECLAMACION = 1
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
                    'texto' => $row['DESCRIPCION']
                ];
            }

            return self::Responde(true, "Consulta exitosa", ['productos' => $productos, 'causas' => $causas]);
        } catch (\Exception $e) {
            return self::Responde(false, "Error al obtener las causas de reclamación: ", null, $e->getMessage());
        }
    }
}
