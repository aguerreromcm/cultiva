<?php

namespace App\models;

defined("APPPATH") or die("Access denied");

use \Core\Database;

class Login
{
    public static function getById($usuario)
    {
        $query1 = <<<sql
        SELECT
            CONCATENA_NOMBRE(PE.NOMBRE1, PE.NOMBRE2, PE.PRIMAPE, PE.SEGAPE) NOMBRE
            , UT.CDGTUS PERFIL
            , PE.PUESTO
            , PE.CDGCO, PE.CODIGO 
        FROM
            PE
            , UT
        WHERE
            PE.CODIGO = UT.CDGPE
            AND PE.CDGEM = UT.CDGEM
            AND PE.CDGEM = 'EMPFIN'
            AND PE.ACTIVO = 'S'
            AND (PE.BLOQUEO = 'N' OR PE.BLOQUEO IS NULL)
            AND PE.CODIGO = :usuario
            AND PE.CLAVE LIKE (SELECT CODIFICA(:password) as pass FROM DUAL)
            AND UT.CDGTUS IN (
            	'ADMIN' ------ USUARIO ADMIN
                , 'OFCLD' ------- USUARIO CAJA (EXTRA)
                , 'PLDCO' ----- USUARIO OCOF
                , 'REPOR' 
                , 'CONS' 
            )
        sql;

        $params1 = array(
            ':usuario' => $usuario->_usuario,
            ':password' => $usuario->_password
        );


        $db = new Database;
        return [$db->queryOne($query1, $params1)];
    }

    public static function getUser($usuario)
    {
        $query = <<<sql
        SELECT
            CONCATENA_NOMBRE(PE.NOMBRE1, PE.NOMBRE2, PE.PRIMAPE, PE.SEGAPE) NOMBRE,
            UT.CDGTUS PERFIL, PE.PUESTO , PE.CDGCO, PE.CODIGO
        FROM
            PE,
            UT
        WHERE
            PE.CODIGO = UT.CDGPE
            AND PE.CDGEM = UT.CDGEM
            AND PE.CDGEM = 'EMPFIN'
            AND PE.ACTIVO = 'S'
            AND (PE.BLOQUEO = 'N' OR PE.BLOQUEO IS NULL)
            AND PE.CODIGO = '$usuario'
        sql;

        $db = new Database;
        return $db->queryAll($query);
    }

    public static function ValidaPassword($usuario, $password)
    {
        if (trim((string) $usuario) === '' || trim((string) $password) === '') {
            return false;
        }
        $query = <<<SQL
            SELECT COUNT(*) AS OK
            FROM PE
            WHERE PE.CDGEM = 'EMPFIN'
            AND PE.ACTIVO = 'S'
            AND (PE.BLOQUEO = 'N' OR PE.BLOQUEO IS NULL)
            AND PE.CODIGO = :usuario
            AND PE.CLAVE = CODIFICA(:password)
        SQL;
        try {
            $db = new Database();
            $r = $db->queryOne($query, [':usuario' => $usuario, ':password' => $password]);
            return $r && isset($r['OK']) && (int) $r['OK'] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
