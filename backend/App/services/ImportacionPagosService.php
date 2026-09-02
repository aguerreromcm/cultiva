<?php

namespace App\services;

defined("APPPATH") or die("Access denied");

use App\repositories\ImportacionPagosRepository;
use Core\Database;
use Core\Model;

/**
 * Lógica de negocio: importación de layouts de corresponsales a PAGOSDIA.
 */
class ImportacionPagosService
{
    /**
     * Previsualiza el archivo sin insertar en base de datos.
     *
     * @return array Respuesta Model::Responde
     */
    public static function previsualizar(string $rutaTmp, string $nombreArchivo, string $corresponsal): array
    {
        $repo = new ImportacionPagosRepository();

        if ($repo->archivoYaImportado($nombreArchivo)) {
            return Model::Responde(false, 'Este archivo ya fue importado anteriormente: ' . $nombreArchivo, null, 'Archivo duplicado');
        }

        $parseo = ImportacionPagosParser::parsear($rutaTmp, $nombreArchivo, $corresponsal);
        if (empty($parseo['success'])) {
            return Model::Responde(false, $parseo['mensaje'] ?? 'Error al leer el archivo.', null, $parseo['error'] ?? null);
        }

        $filas = self::enriquecerFilas($parseo['datos'] ?? [], $corresponsal);
        $filas = self::marcarDuplicados($filas);

        $incidencias = 0;
        $duplicados = 0;
        foreach ($filas as $f) {
            if (!empty($f['DUPLICADO'])) {
                $duplicados++;
            }
            if (!empty($f['INCIDENCIA'])) {
                $incidencias++;
            }
        }

        if ($duplicados > 0 && $duplicados === count($filas)) {
            return Model::Responde(false,
                'Estos pagos ya fueron importados anteriormente (aunque el archivo tenga otro nombre). '
                . 'Revise el historial de importaciones.',
                [
                    'archivo' => $nombreArchivo,
                    'corresponsal' => strtoupper($corresponsal),
                    'total' => count($filas),
                    'incidencias' => $incidencias,
                    'duplicados' => $duplicados,
                    'filas' => $filas,
                ],
                'Pagos duplicados'
            );
        }

        return Model::Responde(true, 'Vista previa generada.', [
            'archivo' => $nombreArchivo,
            'corresponsal' => strtoupper($corresponsal),
            'total' => count($filas),
            'incidencias' => $incidencias,
            'duplicados' => $duplicados,
            'filas' => $filas,
        ]);
    }

    /**
     * Confirma e inserta los pagos en PAGOSDIA.
     *
     * @return array Respuesta Model::Responde
     */
    public static function confirmarImportacion(
        string $rutaTmp,
        string $nombreArchivo,
        string $corresponsal,
        string $usuario
    ): array {
        $repo = new ImportacionPagosRepository();

        if ($repo->archivoYaImportado($nombreArchivo)) {
            return Model::Responde(false, 'Este archivo ya fue importado anteriormente.', null, 'Archivo duplicado');
        }

        $parseo = ImportacionPagosParser::parsear($rutaTmp, $nombreArchivo, $corresponsal);
        if (empty($parseo['success'])) {
            return Model::Responde(false, $parseo['mensaje'] ?? 'Error al leer el archivo.', null, $parseo['error'] ?? null);
        }

        $filas = self::enriquecerFilas($parseo['datos'] ?? [], $corresponsal);
        if (empty($filas)) {
            return Model::Responde(false, 'No hay registros para importar.');
        }

        $filas = self::marcarDuplicados($filas);
        $duplicados = 0;
        foreach ($filas as $f) {
            if (!empty($f['DUPLICADO'])) {
                $duplicados++;
            }
        }
        if ($duplicados > 0) {
            return Model::Responde(false,
                "No se puede importar: {$duplicados} pago(s) ya existen en el sistema (misma fecha, referencia y monto). "
                . 'Puede que el archivo ya se haya cargado con otro nombre.',
                ['duplicados' => $duplicados, 'total' => count($filas)],
                'Pagos duplicados'
            );
        }

        $idImportacion = $repo->siguienteIdImportacion();
        $db = new Database();
        if ($db->db_activa === null) {
            return Model::Responde(false, 'No hay conexión a la base de datos.');
        }

        $insertados = 0;
        $incidencias = 0;

        try {
            $db->IniciaTransaccion();

            foreach ($filas as $fila) {
                $pago = [
                    'CDGNS' => $fila['CDGNS'],
                    'CICLO' => $fila['CICLO'],
                    'FECHA' => $fila['FECHA'],
                    'MONTO' => $fila['MONTO'],
                    'CDGPE' => $usuario,
                    'CDGOCPE' => $fila['CDGOCPE'] ?? ' ',
                    'REFERENCIA' => $fila['REFERENCIA'],
                    'ARCHIVO' => $nombreArchivo,
                    'ID_IMPORTACION' => $idImportacion,
                    'INCIDENCIA' => !empty($fila['INCIDENCIA']) ? 1 : 0,
                ];

                if (!$repo->insertarPago($pago, $db)) {
                    $db->CancelaTransaccion();
                    return Model::Responde(false,
                        'Error al insertar el pago de la línea ' . ($fila['LINEA'] ?? '?') . '. No se importó ningún registro.',
                        null,
                        'Error de inserción'
                    );
                }

                $insertados++;
                if (!empty($fila['INCIDENCIA'])) {
                    $incidencias++;
                }
            }

            $db->ConfirmaTransaccion();
        } catch (\Throwable $e) {
            $db->CancelaTransaccion();
            return Model::Responde(false, 'Error al importar pagos.', null, $e->getMessage());
        }

        $mensaje = "Se importaron {$insertados} pagos correctamente.";
        if ($incidencias > 0) {
            $mensaje .= " {$incidencias} registro(s) requieren revisión (referencia no identificada).";
        }

        return Model::Responde(true, $mensaje, [
            'insertados' => $insertados,
            'incidencias' => $incidencias,
            'id_importacion' => $idImportacion,
            'archivo' => $nombreArchivo,
        ]);
    }

    /**
     * @return array Respuesta Model::Responde
     */
    public static function listarIncidencias(?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $repo = new ImportacionPagosRepository();
        return Model::Responde(true, 'OK', $repo->listarIncidencias($fechaDesde, $fechaHasta));
    }

    /**
     * @return array Respuesta Model::Responde
     */
    public static function listarHistorial(): array
    {
        $repo = new ImportacionPagosRepository();
        return Model::Responde(true, 'OK', $repo->listarImportaciones());
    }

    /**
     * @return array Respuesta Model::Responde
     */
    public static function detalleImportacion(array $datos): array
    {
        $archivo = trim((string) ($datos['archivo'] ?? ''));
        $idImportacion = $datos['id_importacion'] ?? null;
        if ($archivo === '') {
            return Model::Responde(false, 'Archivo requerido.');
        }

        $repo = new ImportacionPagosRepository();
        $filas = $repo->listarDetalleImportacion($archivo, $idImportacion);
        return Model::Responde(true, 'OK', [
            'archivo' => $archivo,
            'id_importacion' => $idImportacion,
            'registros' => $filas,
            'total' => count($filas),
        ]);
    }

    /**
     * Corrige un pago con incidencia asignando crédito y ciclo validados.
     *
     * @return array Respuesta Model::Responde
     */
    public static function corregirIncidencia(array $datos): array
    {
        $fecha = trim((string) ($datos['fecha'] ?? ''));
        $secuencia = trim((string) ($datos['secuencia'] ?? ''));
        $credito = str_pad(preg_replace('/\D/', '', (string) ($datos['credito'] ?? '')), 6, '0', STR_PAD_LEFT);
        $ciclo = trim((string) ($datos['ciclo'] ?? ''));
        $ciclo = str_pad(preg_replace('/\D/', '', $ciclo), 2, '0', STR_PAD_LEFT);

        if ($fecha === '' || $secuencia === '' || $credito === '' || $ciclo === '') {
            return Model::Responde(false, 'Fecha, secuencia, crédito y ciclo son obligatorios.');
        }

        $repo = new ImportacionPagosRepository();
        $prn = $repo->validarCreditoCicloEntregado($credito, $ciclo);
        if ($prn === null) {
            return Model::Responde(false, 'No existe un crédito entregado con los datos proporcionados.');
        }

        $referencia = $repo->generarReferencia($credito, $ciclo);
        if ($referencia === null) {
            return Model::Responde(false, 'No se pudo generar la referencia de pago.');
        }

        $cdgocpe = trim((string) ($prn['CDGOCPE'] ?? ' '));
        if (!$repo->corregirIncidencia($fecha, $secuencia, $credito, $ciclo, $referencia, $cdgocpe)) {
            return Model::Responde(false, 'No se pudo actualizar el registro.');
        }

        return Model::Responde(true, 'Incidencia corregida correctamente.', [
            'credito' => $credito,
            'ciclo' => $ciclo,
            'referencia' => $referencia,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $filas
     * @return array<int, array<string, mixed>>
     */
    private static function enriquecerFilas(array $filas, string $corresponsal): array
    {
        $repo = new ImportacionPagosRepository();
        $corresponsal = strtoupper(trim($corresponsal));
        $esPaycash = in_array($corresponsal, [
            ImportacionPagosParser::CORRESPONSAL_PAYCASH,
            ImportacionPagosParser::CORRESPONSAL_BANCOPPEL,
        ], true);

        $resultado = [];
        foreach ($filas as $fila) {
            $refOriginal = (string) ($fila['REFERENCIA_ORIGINAL'] ?? '');
            $incidencia = false;
            $motivo = '';
            $credito = null;

            if ($esPaycash) {
                $refNorm = ImportacionPagosParser::normalizarReferenciaPaycash($refOriginal);
                if (!ImportacionPagosParser::referenciaPaycashValida($refNorm)) {
                    $credito = '000000';
                    $ciclo = '00';
                    $referencia = $refOriginal;
                    $incidencia = true;
                    $motivo = 'Referencia inválida (debe ser 10 caracteres e iniciar con P, C, 0 o 1).';
                } else {
                    $credito = ImportacionPagosParser::extraerCredito($corresponsal, $refNorm);
                    $referencia = $refNorm;
                }
            } else {
                $credito = ImportacionPagosParser::extraerCredito($corresponsal, $refOriginal);
                $referencia = preg_replace('/\s+.*$/', '', $refOriginal);
            }

            $cdgocpe = ' ';
            if ($credito !== null && $credito !== '000000') {
                $prn = $repo->obtenerCicloEntregado($credito);
                if ($prn === null) {
                    $credito = '000000';
                    $ciclo = '00';
                    $incidencia = true;
                    $motivo = $motivo !== '' ? $motivo : 'No se encontró un crédito entregado con esos datos.';
                } else {
                    $ciclo = str_pad(preg_replace('/\D/', '', (string) $prn['CICLO']), 2, '0', STR_PAD_LEFT);
                    $cdgocpe = trim((string) ($prn['CDGOCPE'] ?? ' '));
                }
            }

            if (!isset($ciclo)) {
                if ($credito === null || $credito === '000000') {
                    $credito = '000000';
                    $ciclo = '00';
                    $incidencia = true;
                    $motivo = $motivo !== '' ? $motivo : 'No se pudo extraer el número de crédito.';
                }
            }

            $resultado[] = array_merge($fila, [
                'CDGNS' => $credito ?? '000000',
                'CICLO' => $ciclo ?? '00',
                'CDGOCPE' => $cdgocpe,
                'REFERENCIA' => $referencia ?? $refOriginal,
                'INCIDENCIA' => $incidencia ? 1 : 0,
                'MOTIVO_INCIDENCIA' => $motivo,
                'FECHA_FMT' => isset($fila['FECHA']) ? date('d/m/Y', strtotime($fila['FECHA'])) : '',
            ]);
        }

        return $resultado;
    }

    /**
     * Marca filas que ya existen en PAGOSDIA (fecha + referencia + monto).
     *
     * @param array<int, array<string, mixed>> $filas
     * @return array<int, array<string, mixed>>
     */
    private static function marcarDuplicados(array $filas): array
    {
        if (empty($filas)) {
            return $filas;
        }

        $fechas = [];
        foreach ($filas as $f) {
            $fecha = trim((string) ($f['FECHA'] ?? ''));
            if ($fecha !== '') {
                $fechas[$fecha] = true;
            }
        }
        if (empty($fechas)) {
            return $filas;
        }

        $listaFechas = array_keys($fechas);
        sort($listaFechas);
        $repo = new ImportacionPagosRepository();
        $existentes = $repo->clavesPagosExistentes($listaFechas[0], $listaFechas[count($listaFechas) - 1]);

        foreach ($filas as &$fila) {
            $clave = ImportacionPagosRepository::clavePago(
                (string) ($fila['FECHA'] ?? ''),
                (string) ($fila['REFERENCIA'] ?? ''),
                $fila['MONTO'] ?? 0
            );
            $duplicado = $clave !== '' && isset($existentes[$clave]);
            $fila['DUPLICADO'] = $duplicado ? 1 : 0;
            if ($duplicado) {
                $archivoOrigen = trim((string) $existentes[$clave]);
                $motivoDup = 'Ya existe en el sistema (misma fecha, referencia y monto)';
                if ($archivoOrigen !== '') {
                    $motivoDup .= '. Archivo previo: ' . $archivoOrigen;
                }
                $fila['MOTIVO_INCIDENCIA'] = trim((string) ($fila['MOTIVO_INCIDENCIA'] ?? '')) !== ''
                    ? $fila['MOTIVO_INCIDENCIA']
                    : $motivoDup;
                // Conservamos motivo de incidencia si ya había uno; el estatus UI prioriza DUPLICADO.
                $fila['MOTIVO_DUPLICADO'] = $motivoDup;
            }
        }
        unset($fila);

        return $filas;
    }
}
