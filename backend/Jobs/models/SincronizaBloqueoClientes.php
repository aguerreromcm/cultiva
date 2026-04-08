<?php

namespace Jobs\models;

include_once dirname(__DIR__) . "\..\Core\Model.php";
include_once dirname(__DIR__) . "\..\Core\Database.php";

use Core\Model;
use Core\Database;

class SincronizaBloqueoClientes extends Model
{
    public static function GetListaNegra($mcm = true)
    {
        $qry = <<<SQL
            SELECT
                PRC.CDGCL,
                CL.CURP 
            FROM
                PRN_LEGAL PRL
            JOIN
                PRC ON PRL.CDGCLNS = PRC.CDGNS AND PRL.CICLO = PRC.CICLO 
            JOIN 
                CL ON PRC.CDGCL = CL.CODIGO
            WHERE
                PRL.CDGEM = 'EMPFIN'
            UNION
            SELECT
                CM.CDGCL,
                CM.CURP
            FROM
                CL_MARCA CM
            WHERE
                CM.CDGEM = 'EMPFIN'
                AND CM.CAUSA != 9
                AND CM.CAUSA != 10
                AND CM.CAUSA != 11
        SQL;

        $servidor = $mcm ? 'SERVIDOR-MCM' : null;
        $db = new Database($servidor);
        if ($db->db_activa == null) return self::Responde(false, "Error al conectar a la base de datos.");

        $resultado =  $db->queryAll($qry);
        if ($resultado == null) return self::Responde(false, "Error al obtener la lista negra.");
        return self::Responde(true, "Lista Negra obtenida correctamente.", $resultado);
    }

    public static function ValidaListaNegra($datos, $mcm = true)
    {
        $tblTemp = "CREATE TABLE curps_lista_negra (CDGCL VARCHAR2(20), CURP VARCHAR2(20))";
        $in = "INSERT INTO curps_lista_negra (CDGCL, CURP) VALUES (:cdgcl, :curp)";

        $inserts = [];
        $valores = [];
        foreach ($datos as $dato) {
            array_push($inserts, $in);
            array_push($valores, ['cdgcl' => $dato['CDGCL'], 'curp' => $dato['CURP']]);
        }

        $qry = <<<SQL
            SELECT
                CLN.CDGCL,
                CLN.CURP
            FROM
                curps_lista_negra CLN
            WHERE
                NOT EXISTS (
                    SELECT
                        1
                    FROM
                        CL_MARCA CM
                    WHERE
                        CM.CURP = CLN.CURP
                )
        SQL;

        $dropTbl = "DROP TABLE curps_lista_negra";

        $servidor = $mcm ? 'SERVIDOR-MCM' : null;
        $db = new Database($servidor);
        if ($db->db_activa == null) return self::Responde(false, "Error al conectar a la base de datos.");

        $resultado = $db->insert($tblTemp);
        if ($resultado == false) return self::Responde(false, "Error al crear la tabla temporal.");

        $resultado = $db->insertaMultiple($inserts, $valores);
        if ($resultado) $resultado = $db->queryAll($qry);
        $db->insert($dropTbl);
        if (count($resultado) > 0) return self::Responde(true, "Lista Negra validada correctamente.", $resultado);
        if (count($resultado) == 0) return self::Responde(false, "No se encontraron registros para insertar.");
        return self::Responde(false, "Error al validar la lista negra.");
    }

    public static function InsertaListaNegra($datos, $mcm = true)
    {
        $qry = <<<SQL
            INSERT INTO CL_MARCA
                (CDGEM, SECUENCIA, CDGCL, CURP, TIPOMARCA, ESTATUS, MONTOMAX, ALTAPE, ALTA, BAJAPE, BAJA, FREGISTRO, CAUSA, CAUSABAJA, CDGCLNS, CICLO, CLNS)
            VALUES
                ('EMPFIN', (SELECT NVL(MAX(SECUENCIA),0) + 1 FROM CL_MARCA WHERE TO_CHAR(ALTA, 'YYYYMMDD') = TO_CHAR(SYSDATE, 'YYYYMMDD')), null, :curp, 'LN', 'A', NULL, 'SYSTEM', TRUNC(SYSDATE), NULL, NULL, SYSDATE, 9, NULL, NULL, NULL, NULL)
        SQL;

        $inserts = [];
        $valores = [];

        foreach ($datos as $dato) {
            array_push($inserts, $qry);
            array_push($valores, [
                'curp' => $dato['CURP']
            ]);
        }

        $servidor = $mcm ? 'SERVIDOR-MCM' : null;
        $db = new Database($servidor);
        if ($db->db_activa == null) return self::Responde(false, "Error al conectar a la base de datos.");

        $resultado = $db->insertaMultiple($inserts, $valores);
        if ($resultado) return self::Responde(true, "Lista Negra insertada correctamente.");
        return self::Responde(false, "Error al insertar la lista negra.");
    }

    public static function GetListaGris($mcm = true)
    {
        $qry = <<<SQL
            SELECT
                PRC.CDGCL,
                CL.CURP,
                TO_CHAR(PRN.INICIO, 'YYYYMMDD') AS FECHA
            FROM
                PRN
            JOIN
                PRC ON PRN.CDGNS = PRC.CDGNS AND PRN.CICLO = PRC.CICLO 
            JOIN 
                CL ON PRC.CDGCL = CL.CODIGO
            WHERE
                PRN.CDGEM = 'EMPFIN'
                AND PRN.SITUACION = 'E'
        SQL;

        $servidor = $mcm ? 'SERVIDOR-MCM' : null;
        $db = new Database($servidor);
        if ($db->db_activa == null) return self::Responde(false, "Error al conectar a la base de datos.");

        $resultado = $db->queryAll($qry);
        if ($resultado == null) return self::Responde(false, "Error al obtener la lista gris.");
        return self::Responde(true, "Lista Gris obtenida correctamente.", $resultado);
    }

    public static function ActualizaListaGris($datos, $mcm = true)
    {
        $qryElimina = <<<SQL
            DELETE FROM
                CL_MARCA
            WHERE
                CDGEM = 'EMPFIN'
                AND ALTAPE = 'SYSTEM'
                AND CAUSA = 10
        SQL;

        $qryInserta = <<<SQL
            INSERT INTO CL_MARCA
                (CDGEM, SECUENCIA, CDGCL, CURP, TIPOMARCA, ESTATUS, MONTOMAX, ALTAPE, ALTA, BAJAPE, BAJA, FREGISTRO, CAUSA, CAUSABAJA, CDGCLNS, CICLO, CLNS)
            VALUES
                ('EMPFIN', (SELECT NVL(MAX(SECUENCIA),0) + 1 FROM CL_MARCA WHERE TO_CHAR(ALTA, 'YYYYMMDD') = :inicio), null, :curp, 'LN', 'A', NULL, 'SYSTEM', TO_DATE(:inicio, 'YYYYMMDD'), NULL, NULL, SYSDATE, 10, NULL, NULL, NULL, NULL)
        SQL;

        $servidor = $mcm ? 'SERVIDOR-MCM' : null;
        $db = new Database($servidor);
        if ($db->db_activa == null) return self::Responde(false, "Error al conectar a la base de datos.");

        $rElimina = $db->eliminar($qryElimina);
        if ($rElimina == false) return self::Responde(false, "Error al eliminar la lista gris.");

        $valores = [];

        foreach ($datos as $dato) {
            $v = [
                'curp' => $dato['CURP'],
                'inicio' => $dato['FECHA']
            ];

            $rInserta = $db->insert($qryInserta, $v);
            if ($rInserta) array_push($valores, $v);
        }

        return self::Responde(true, "Lista Gris actualizada correctamente.", $valores);
    }
}
