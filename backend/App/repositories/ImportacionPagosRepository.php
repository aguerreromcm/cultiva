<?php

namespace App\repositories;

defined("APPPATH") or die("Access denied");

use Core\Database;

/**
 * Acceso a datos para importación de pagos en PAGOSDIA.
 */
class ImportacionPagosRepository
{
    public function archivoYaImportado(string $nombreArchivo): bool
    {
        $db = new Database();
        if ($db->db_activa === null) {
            return false;
        }

        $sql = <<<SQL
            SELECT COUNT(*) AS TOTAL
            FROM PAGOSDIA
            WHERE ARCHIVO = :archivo
              AND ROWNUM = 1
        SQL;

        try {
            $row = $db->queryOne($sql, ['archivo' => $nombreArchivo]);
            return isset($row['TOTAL']) && (int) $row['TOTAL'] > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Claves de pagos activos en el rango de fechas: FECHA|REFERENCIA|MONTO.
     *
     * @return array<string, string> clave => archivo origen (si existe)
     */
    public function clavesPagosExistentes(string $fechaDesde, string $fechaHasta): array
    {
        $db = new Database();
        if ($db->db_activa === null || $fechaDesde === '' || $fechaHasta === '') {
            return [];
        }

        $sql = <<<SQL
            SELECT
                TO_CHAR(FECHA, 'YYYY-MM-DD') AS FECHA,
                REFERENCIA,
                MONTO,
                ARCHIVO
            FROM PAGOSDIA
            WHERE CDGEM = 'EMPFIN'
              AND ESTATUS = 'A'
              AND TRUNC(FECHA) BETWEEN TO_DATE(:fd, 'YYYY-MM-DD') AND TO_DATE(:fh, 'YYYY-MM-DD')
              AND REFERENCIA IS NOT NULL
        SQL;

        try {
            $filas = $db->queryAll($sql, ['fd' => $fechaDesde, 'fh' => $fechaHasta]);
            if (!is_array($filas)) {
                return [];
            }

            $mapa = [];
            foreach ($filas as $f) {
                $clave = self::clavePago(
                    (string) ($f['FECHA'] ?? ''),
                    (string) ($f['REFERENCIA'] ?? ''),
                    $f['MONTO'] ?? 0
                );
                if ($clave !== '') {
                    $mapa[$clave] = (string) ($f['ARCHIVO'] ?? '');
                }
            }
            return $mapa;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function clavePago(string $fecha, string $referencia, $monto): string
    {
        $fecha = trim($fecha);
        $referencia = strtoupper(trim($referencia));
        if ($fecha === '' || $referencia === '') {
            return '';
        }
        $montoNum = round((float) $monto, 2);
        return $fecha . '|' . $referencia . '|' . number_format($montoNum, 2, '.', '');
    }

    public function siguienteIdImportacion(): int
    {
        $db = new Database();
        if ($db->db_activa === null) {
            return (int) time();
        }

        $sql = 'SELECT NVL(MAX(ID_IMPORTACION), 0) + 1 AS SIG FROM PAGOSDIA';
        try {
            $row = $db->queryOne($sql);
            $sig = (int) ($row['SIG'] ?? 0);
            return $sig > 0 ? $sig : (int) time();
        } catch (\Throwable $e) {
            return (int) time();
        }
    }

    /**
     * Obtiene ciclo entregado (SITUACION = E); si hay varios, el menor.
     *
     * @return array{CDGNS: string, CICLO: string, CDGOCPE: string|null, CDGTPC: string|null}|null
     */
    public function obtenerCicloEntregado(string $credito): ?array
    {
        $credito = trim($credito);
        if ($credito === '' || $credito === '000000') {
            return null;
        }

        $db = new Database();
        if ($db->db_activa === null) {
            return null;
        }

        $sql = <<<SQL
            SELECT CDGNS, CICLO, CDGOCPE, CDGTPC
            FROM PRN
            WHERE CDGEM = 'EMPFIN'
              AND CDGNS = :credito
              AND SITUACION = 'E'
            ORDER BY TO_NUMBER(CICLO) ASC
        SQL;

        try {
            $row = $db->queryOne($sql, ['credito' => $credito]);
            if (!is_array($row) || empty($row)) {
                return null;
            }
            $row['CICLO'] = str_pad(preg_replace('/\D/', '', (string) ($row['CICLO'] ?? '')), 2, '0', STR_PAD_LEFT);
            return $row;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Valida crédito y ciclo con situación Entregado.
     */
    public function validarCreditoCicloEntregado(string $credito, string $ciclo): ?array
    {
        $db = new Database();
        if ($db->db_activa === null) {
            return null;
        }

        $sql = <<<SQL
            SELECT CDGNS, CICLO, CDGOCPE, CDGTPC, SITUACION
            FROM PRN
            WHERE CDGEM = 'EMPFIN'
              AND CDGNS = :credito
              AND TO_NUMBER(CICLO) = TO_NUMBER(:ciclo)
              AND SITUACION = 'E'
        SQL;

        try {
            $row = $db->queryOne($sql, ['credito' => $credito, 'ciclo' => $ciclo]);
            return is_array($row) && !empty($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function generarReferencia(string $credito, string $ciclo): ?string
    {
        $db = new Database();
        if ($db->db_activa === null) {
            return null;
        }

        $credito = str_pad(preg_replace('/\D/', '', $credito), 6, '0', STR_PAD_LEFT);
        $ciclo = str_pad(preg_replace('/\D/', '', $ciclo), 2, '0', STR_PAD_LEFT);
        $base = 'P' . $credito . $ciclo;
        $sql = "SELECT 'P' || :credito || :ciclo || FN_DV(:base) AS REFERENCIA FROM DUAL";

        try {
            $row = $db->queryOne($sql, [
                'credito' => $credito,
                'ciclo' => $ciclo,
                'base' => $base,
            ]);
            $ref = isset($row['REFERENCIA']) ? trim((string) $row['REFERENCIA']) : '';
            return $ref !== '' ? $ref : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $pago
     */
    public function insertarPago(array $pago, Database $db = null): bool
    {
        if ($db === null) {
            $db = new Database();
        }
        if ($db->db_activa === null) {
            return false;
        }

        $sql = <<<SQL
            INSERT INTO PAGOSDIA (
                CDGEM, CDGNS, CICLO, SECUENCIA, FECHA, MONTO, TIPO, ESTATUS,
                FREGISTRO, CDGPE, NOMBRE, CDGOCPE, EJECUTIVO,
                REFERENCIA, ARCHIVO, ID_IMPORTACION, INCIDENCIA
            ) VALUES (
                'EMPFIN',
                :cdgns,
                :ciclo,
                (SELECT NVL(MAX(SECUENCIA), 0) + 1 FROM PAGOSDIA WHERE TRUNC(FECHA) = TO_DATE(:fecha_seq, 'YYYY-MM-DD')),
                TO_DATE(:fecha, 'YYYY-MM-DD'),
                :monto,
                'P',
                'A',
                SYSDATE,
                :cdgpe,
                NVL((SELECT NOMBRE FROM NS WHERE CDGEM = 'EMPFIN' AND CODIGO = :cdgns_nombre), 'SIN IDENTIFICAR'),
                :cdgocpe,
                NVL((SELECT GET_NOMBRE_EMPLEADO(:cdgocpe_nom) FROM DUAL), ' '),
                :referencia,
                :archivo,
                :id_importacion,
                :incidencia
            )
        SQL;

        return $db->insert($sql, [
            'cdgns' => $pago['CDGNS'],
            'ciclo' => $pago['CICLO'],
            'fecha_seq' => $pago['FECHA'],
            'fecha' => $pago['FECHA'],
            'monto' => $pago['MONTO'],
            'cdgpe' => $pago['CDGPE'],
            'cdgns_nombre' => $pago['CDGNS'],
            'cdgocpe' => $pago['CDGOCPE'] ?? ' ',
            'cdgocpe_nom' => $pago['CDGOCPE'] ?? ' ',
            'referencia' => $pago['REFERENCIA'],
            'archivo' => $pago['ARCHIVO'],
            'id_importacion' => $pago['ID_IMPORTACION'],
            'incidencia' => $pago['INCIDENCIA'] ?? 0,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarIncidencias(?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $db = new Database();
        if ($db->db_activa === null) {
            return [];
        }

        $cond = "PD.CDGEM = 'EMPFIN' AND PD.ESTATUS = 'A' AND (PD.INCIDENCIA = 1 OR PD.CDGNS = '000000')";
        $params = [];

        if ($fechaDesde !== null && $fechaDesde !== '') {
            $cond .= ' AND TRUNC(PD.FECHA) >= TO_DATE(:fd, \'YYYY-MM-DD\')';
            $params['fd'] = $fechaDesde;
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $cond .= ' AND TRUNC(PD.FECHA) <= TO_DATE(:fh, \'YYYY-MM-DD\')';
            $params['fh'] = $fechaHasta;
        }

        $sql = <<<SQL
            SELECT
                PD.SECUENCIA,
                TO_CHAR(PD.FECHA, 'YYYY-MM-DD') AS FECHA,
                TO_CHAR(PD.FECHA, 'DD/MM/YYYY') AS FECHA_FMT,
                PD.CDGNS,
                PD.CICLO,
                PD.MONTO,
                PD.REFERENCIA,
                PD.ARCHIVO,
                PD.ID_IMPORTACION
            FROM PAGOSDIA PD
            WHERE {$cond}
            ORDER BY PD.FECHA DESC, PD.SECUENCIA DESC
        SQL;

        try {
            $filas = $db->queryAll($sql, $params);
            return is_array($filas) ? $filas : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarDetalleImportacion(?string $archivo, $idImportacion = null): array
    {
        $archivo = trim((string) $archivo);
        if ($archivo === '') {
            return [];
        }

        $db = new Database();
        if ($db->db_activa === null) {
            return [];
        }

        $params = ['archivo' => $archivo];
        $condId = '';
        if ($idImportacion !== null && $idImportacion !== '') {
            $condId = ' AND ID_IMPORTACION = :id_imp';
            $params['id_imp'] = (int) $idImportacion;
        }

        $sql = <<<SQL
            SELECT
                SECUENCIA,
                TO_CHAR(FECHA, 'YYYY-MM-DD') AS FECHA,
                TO_CHAR(FECHA, 'DD/MM/YYYY') AS FECHA_FMT,
                CDGNS,
                CICLO,
                MONTO,
                REFERENCIA,
                NVL(INCIDENCIA, 0) AS INCIDENCIA,
                ARCHIVO,
                ID_IMPORTACION
            FROM PAGOSDIA
            WHERE CDGEM = 'EMPFIN'
              AND ESTATUS = 'A'
              AND ARCHIVO = :archivo
              {$condId}
            ORDER BY SECUENCIA
        SQL;

        try {
            $filas = $db->queryAll($sql, $params);
            return is_array($filas) ? $filas : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarImportaciones(): array
    {
        $db = new Database();
        if ($db->db_activa === null) {
            return [];
        }

        $sql = <<<SQL
            SELECT
                ARCHIVO,
                ID_IMPORTACION,
                MIN(TO_CHAR(FECHA, 'DD/MM/YYYY')) AS FECHA_PAGO,
                COUNT(*) AS REGISTROS,
                SUM(MONTO) AS MONTO_TOTAL,
                SUM(CASE WHEN INCIDENCIA = 1 THEN 1 ELSE 0 END) AS INCIDENCIAS,
                MIN(TO_CHAR(FREGISTRO, 'DD/MM/YYYY HH24:MI:SS')) AS F_IMPORTACION
            FROM PAGOSDIA
            WHERE ARCHIVO IS NOT NULL
            GROUP BY ARCHIVO, ID_IMPORTACION
            ORDER BY MAX(FREGISTRO) DESC
        SQL;

        try {
            $filas = $db->queryAll($sql);
            return is_array($filas) ? $filas : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function corregirIncidencia(
        string $fecha,
        string $secuencia,
        string $credito,
        string $ciclo,
        string $referencia,
        string $cdgocpe
    ): bool {
        $db = new Database();
        if ($db->db_activa === null) {
            return false;
        }

        $sql = <<<SQL
            UPDATE PAGOSDIA
            SET CDGNS = :credito,
                CICLO = :ciclo,
                REFERENCIA = :referencia,
                CDGOCPE = :cdgocpe,
                NOMBRE = NVL((SELECT NOMBRE FROM NS WHERE CDGEM = 'EMPFIN' AND CODIGO = :credito_ns), 'SIN IDENTIFICAR'),
                EJECUTIVO = NVL((SELECT GET_NOMBRE_EMPLEADO(:cdgocpe_nom) FROM DUAL), ' '),
                INCIDENCIA = 0,
                FACTUALIZA = SYSDATE
            WHERE TRUNC(FECHA) = TO_DATE(:fecha, 'YYYY-MM-DD')
              AND SECUENCIA = :secuencia
              AND INCIDENCIA = 1
        SQL;

        return $db->insert($sql, [
            'credito' => $credito,
            'ciclo' => $ciclo,
            'referencia' => $referencia,
            'cdgocpe' => $cdgocpe,
            'credito_ns' => $credito,
            'cdgocpe_nom' => $cdgocpe,
            'fecha' => substr($fecha, 0, 10),
            'secuencia' => $secuencia,
        ]);
    }
}
