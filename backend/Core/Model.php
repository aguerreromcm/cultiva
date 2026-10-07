<?php

namespace Core;

class Model
{
    public static function Responde($respuesta, $mensaje, $datos = null, $error = null)
    {
        $res = [
            "success" => $respuesta,
            "mensaje" => $mensaje
        ];

        if ($datos !== null) $res['datos'] = $datos;
        if ($error !== null) $res['error'] = $error;

        return $res;
    }

    public static function GetSucursales()
    {
        $qry = <<<SQL
            SELECT DISTINCT 
                RG.CODIGO ID_REGION,
                RG.NOMBRE REGION,
                CO.CODIGO ID_SUCURSAL,
                CO.NOMBRE SUCURSAL
            FROM
                PCO, CO, RG
            WHERE
                PCO.CDGCO = CO.CODIGO
                AND CO.CDGRG = RG.CODIGO 
                AND PCO.CDGEM = 'EMPFIN'
            ORDER BY
                    SUCURSAL ASC
        SQL;

        try {
            $db = new Database();
            $res = $db->queryAll($qry);
            return self::Responde(true, 'Sucursales obtenidas', $res);
        } catch (\Exception $e) {
            return self::Responde(false, 'Error al obtener sucursales', null, $e->getMessage());
        }
    }

    public static function GetDestinatarios_Aplicacion($aplicacion)
    {
        $qry = <<<SQL
            SELECT DISTINCT
                CD.CORREO
            FROM
                CORREO_APLICACION_GRUPO CAG
                JOIN CORREO_DIRECTORIO_GRUPO CDG ON CAG.ID_GRUPO = CDG.ID_GRUPO
                JOIN CORREO_DIRECTORIO CD ON CD.ID = CDG.ID_CORREO
            WHERE
                CAG.ID_APLICACION = :aplicacion
        SQL;

        try {
            $db = new Database();
            $res = $db->queryAll($qry, ['aplicacion' => $aplicacion]);
            return self::Responde(true, 'Destinatarios obtenidos', $res);
        } catch (\Exception $e) {
            return self::Responde(false, 'Error al obtener destinatarios', null, $e->getMessage());
        }
    }
}
