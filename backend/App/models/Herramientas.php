<?php

namespace App\models;

defined("APPPATH") or die("Access denied");

use Core\Database;
use Core\Model;

class Herramientas extends Model
{
    public static function ConsultarRepDiaAtraso()
    {
        $query = <<<SQL
            SELECT 
                PRN.CDGNS AS COD_CTE,
                PRN.CICLO,
                NS.NOMBRE,
                PRN.INICIO,
                FNCALDIASATRASO(PRN.CDGEM, PRN.CDGNS, PRN.CICLO, 'G', SYSDATE) AS DIAS_ATRASO
            FROM 
                PRN
                INNER JOIN NS ON PRN.CDGEM = NS.CDGEM 
                             AND PRN.CDGNS = NS.CODIGO
            WHERE 
                PRN.SITUACION = 'L'
        SQL;

        try {
            $db = new Database();
            if ($db->db_activa == null) return "";
            return $db->queryAll($query);
        } catch (\Exception $e) {
            return "";
        }
    }
}
