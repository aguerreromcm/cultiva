<?php

namespace App\services;

defined("APPPATH") or die("Access denied");

/**
 * Parsea layouts de corresponsales (OXXO, PAYCASH, BanCoppel) a registros normalizados.
 */
class ImportacionPagosParser
{
    public const CORRESPONSAL_OXXO = 'OXXO';
    public const CORRESPONSAL_PAYCASH = 'PAYCASH';
    public const CORRESPONSAL_BANCOPPEL = 'BANCOPPEL';

    /**
     * @return array{success: bool, mensaje: string, datos?: array, error?: string}
     */
    public static function parsear(string $rutaArchivo, string $nombreArchivo, string $corresponsal): array
    {
        $corresponsal = strtoupper(trim($corresponsal));
        $ext = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        if ($ext === 'xsl') {
            $ext = 'xls';
        }

        switch ($corresponsal) {
            case self::CORRESPONSAL_OXXO:
                if ($ext !== 'dat') {
                    return self::error('OXXO requiere archivo con extensión .dat');
                }
                return self::parsearOxxo($rutaArchivo, $nombreArchivo);
            case self::CORRESPONSAL_PAYCASH:
                if ($ext !== 'csv') {
                    return self::error('PAYCASH requiere archivo con extensión .csv');
                }
                return self::parsearPaycash($rutaArchivo, $nombreArchivo);
            case self::CORRESPONSAL_BANCOPPEL:
                if (!in_array($ext, ['xls', 'xlsx', 'csv'], true)) {
                    return self::error('BanCoppel requiere archivo .xls, .xlsx o .csv');
                }
                return self::parsearBanCoppel($rutaArchivo, $nombreArchivo, $ext);
            default:
                return self::error('Corresponsal no reconocido.');
        }
    }

    /**
     * @return array{success: bool, mensaje: string, datos?: array, error?: string}
     */
    private static function parsearOxxo(string $ruta, string $archivo): array
    {
        $lineas = @file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lineas === false) {
            return self::error('No se pudo leer el archivo OXXO.');
        }

        $registros = [];
        $numLinea = 0;
        foreach ($lineas as $linea) {
            $numLinea++;
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }

            $partes = str_getcsv($linea);
            if (count($partes) < 7) {
                continue;
            }

            $fechaRaw = trim($partes[2]);
            $referencia = trim($partes[4]);
            $montoRaw = trim($partes[6]);

            $fecha = self::parsearFechaYmd($fechaRaw);
            $monto = self::parsearMontoDecimal($montoRaw);
            if ($fecha === null || $monto === null || $referencia === '') {
                continue;
            }

            $registros[] = self::filaBase($archivo, self::CORRESPONSAL_OXXO, $fecha, $referencia, $monto, $numLinea);
        }

        if (empty($registros)) {
            return self::error('El archivo OXXO no contiene registros válidos.');
        }

        return self::ok('Archivo OXXO leído correctamente.', $registros);
    }

    /**
     * @return array{success: bool, mensaje: string, datos?: array, error?: string}
     */
    private static function parsearPaycash(string $ruta, string $archivo): array
    {
        $fh = @fopen($ruta, 'rb');
        if ($fh === false) {
            return self::error('No se pudo abrir el archivo PAYCASH.');
        }

        $encabezado = fgetcsv($fh);
        if ($encabezado === false) {
            fclose($fh);
            return self::error('El archivo PAYCASH está vacío.');
        }

        $mapa = self::mapearEncabezados($encabezado);
        if (!isset($mapa['REF EMISOR']) && !isset($mapa['REF_EMISOR'])) {
            fclose($fh);
            return self::error('No se encontró la columna REF EMISOR en el archivo PAYCASH.');
        }
        $idxRef = $mapa['REF EMISOR'] ?? $mapa['REF_EMISOR'];
        $idxFecha = $mapa['FECHA'] ?? null;
        $idxMonto = $mapa['MONTO'] ?? null;

        $registros = [];
        $numLinea = 1;
        while (($fila = fgetcsv($fh)) !== false) {
            $numLinea++;
            if (!is_array($fila) || count($fila) === 0) {
                continue;
            }

            $referencia = trim((string) ($fila[$idxRef] ?? ''));
            if ($referencia === '') {
                continue;
            }

            $fecha = null;
            if ($idxFecha !== null) {
                $fecha = self::parsearFechaFlexible(trim((string) ($fila[$idxFecha] ?? '')));
            }

            $monto = null;
            if ($idxMonto !== null) {
                $monto = self::parsearMontoPaycash(trim((string) ($fila[$idxMonto] ?? '')));
            }

            if ($fecha === null || $monto === null) {
                continue;
            }

            $registros[] = self::filaBase($archivo, self::CORRESPONSAL_PAYCASH, $fecha, $referencia, $monto, $numLinea);
        }
        fclose($fh);

        if (empty($registros)) {
            return self::error('El archivo PAYCASH no contiene registros válidos.');
        }

        return self::ok('Archivo PAYCASH leído correctamente.', $registros);
    }

    /**
     * @return array{success: bool, mensaje: string, datos?: array, error?: string}
     */
    private static function parsearBanCoppel(string $ruta, string $archivo, string $ext): array
    {
        $filas = [];
        if ($ext === 'csv') {
            $filas = self::leerFilasCsv($ruta);
        } else {
            $filas = self::leerFilasExcel($ruta);
        }

        if (empty($filas)) {
            return self::error('No se pudieron leer filas del archivo BanCoppel. Verifique que sea .xls/.xlsx legible (no protegido).');
        }

        $registros = self::extraerRegistrosBanCoppel($filas, $archivo);

        if (empty($registros)) {
            $muestra = self::muestraFilasBanCoppel($filas);
            return self::error(
                'El archivo BanCoppel no contiene registros válidos. Revise que existan columnas FECHA, REFERENCIA y MONTO.'
                . ($muestra !== '' ? ' Muestra leída: ' . $muestra : '')
            );
        }

        return self::ok('Archivo BanCoppel leído correctamente.', $registros);
    }

    /**
     * Extrae pagos BanCoppel. Incluye referencias inválidas (ej. "21011436649 mariposas")
     * para que el flujo las marque como incidencia 000000/00.
     *
     * @param list<array<int, mixed>> $filas
     * @return list<array<string, mixed>>
     */
    private static function extraerRegistrosBanCoppel(array $filas, string $archivo): array
    {
        $idxEncabezado = self::buscarEncabezadoBanCoppel($filas);
        $idxFecha = 0;
        $idxRef = 1;
        $idxMonto = 2;
        $inicio = 0;

        if ($idxEncabezado !== null) {
            $mapa = self::mapearEncabezados($filas[$idxEncabezado]);
            $idxFecha = $mapa['FECHA'] ?? self::indiceColumnaPorNombre($filas[$idxEncabezado], 'FECHA') ?? 0;
            $idxRef = $mapa['REFERENCIA'] ?? self::indiceColumnaPorNombre($filas[$idxEncabezado], 'REFERENCIA') ?? 1;
            $idxMonto = $mapa['MONTO'] ?? self::indiceColumnaPorNombre($filas[$idxEncabezado], 'MONTO') ?? 2;
            $inicio = $idxEncabezado + 1;
        }

        $registros = [];
        for ($i = $inicio; $i < count($filas); $i++) {
            $fila = $filas[$i];
            if (!is_array($fila)) {
                continue;
            }

            $extraido = self::extraerCamposFilaBanCoppel($fila, $idxFecha, $idxRef, $idxMonto);
            if ($extraido === null) {
                continue;
            }

            $registros[] = self::filaBase(
                $archivo,
                self::CORRESPONSAL_BANCOPPEL,
                $extraido['fecha'],
                $extraido['referencia'],
                $extraido['monto'],
                $i + 1
            );
        }

        return $registros;
    }

    /**
     * @param array<int, mixed> $fila
     * @return array{fecha: string, referencia: string, monto: float}|null
     */
    private static function extraerCamposFilaBanCoppel(array $fila, int $idxFecha, int $idxRef, int $idxMonto): ?array
    {
        $referencia = trim((string) ($fila[$idxRef] ?? ''));
        $fechaRaw = trim((string) ($fila[$idxFecha] ?? ''));
        $montoRaw = trim((string) ($fila[$idxMonto] ?? ''));

        $fecha = self::parsearFechaFlexible($fechaRaw);
        $monto = self::parsearMontoFlexible($montoRaw);

        // Si las columnas fijas fallan (xls desalinea celdas), busca por tipo de valor.
        if ($referencia === '' || $fecha === null || $monto === null || $monto <= 0) {
            $heuristica = self::extraerCamposHeuristicaBanCoppel($fila);
            if ($heuristica === null) {
                return null;
            }
            $referencia = $heuristica['referencia'];
            $fecha = $heuristica['fecha'];
            $monto = $heuristica['monto'];
        }

        if ($referencia === '' || self::esFilaResumenBanCoppel($referencia)) {
            return null;
        }
        if (stripos($referencia, 'PAGOS') !== false && stripos($referencia, 'BANCOPPEL') !== false) {
            return null;
        }
        if (strcasecmp($referencia, 'REFERENCIA') === 0 || strcasecmp($referencia, 'FECHA') === 0) {
            return null;
        }
        if ($fecha === null || $monto === null || $monto <= 0) {
            return null;
        }

        return ['fecha' => $fecha, 'referencia' => $referencia, 'monto' => $monto];
    }

    /**
     * Recorre la fila y detecta fecha / referencia / monto sin depender del índice.
     *
     * @param array<int, mixed> $fila
     * @return array{fecha: string, referencia: string, monto: float}|null
     */
    private static function extraerCamposHeuristicaBanCoppel(array $fila): ?array
    {
        $fecha = null;
        $referencia = null;
        $monto = null;

        foreach ($fila as $celda) {
            $texto = trim((string) $celda);
            if ($texto === '') {
                continue;
            }
            if ($fecha === null) {
                $f = self::parsearFechaFlexible($texto);
                if ($f !== null) {
                    $fecha = $f;
                    continue;
                }
            }
            if ($monto === null) {
                // Evita tomar el serial de fecha como monto si aún no hay fecha parseada.
                $m = self::parsearMontoFlexible($texto);
                if ($m !== null && $m > 0 && !preg_match('/^\d{5}(\.\d+)?$/', $texto)) {
                    // Referencias tipo P00… no son montos; montos suelen ser numéricos puros o con separadores.
                    if (preg_match('/^[\d.,\s$]+$/', $texto)) {
                        $monto = $m;
                        continue;
                    }
                }
            }
            if ($referencia === null) {
                $upper = strtoupper($texto);
                if (in_array($upper, ['FECHA', 'REFERENCIA', 'MONTO', 'MONED', 'MONEDA', 'MN'], true)) {
                    continue;
                }
                if (self::esFilaResumenBanCoppel($texto)) {
                    return null;
                }
                // Referencia válida P/C/0/1… o el caso práctico inválido (dígitos + texto).
                $token = preg_split('/\s+/', $texto)[0] ?? '';
                if (self::referenciaPaycashValida(strtoupper($token))
                    || preg_match('/^\d{6,}/', $token)
                    || (isset($texto[0]) && in_array(strtoupper($texto[0]), ['P', 'C'], true))
                ) {
                    $referencia = $texto;
                }
            }
        }

        if ($fecha === null || $referencia === null || $monto === null || $monto <= 0) {
            return null;
        }

        return ['fecha' => $fecha, 'referencia' => $referencia, 'monto' => $monto];
    }

    /**
     * @param array<int, mixed> $encabezado
     */
    private static function indiceColumnaPorNombre(array $encabezado, string $buscar): ?int
    {
        $buscar = strtoupper($buscar);
        foreach ($encabezado as $i => $col) {
            $nombre = strtoupper(trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $col)));
            $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
            if ($nombre === $buscar || strpos($nombre, $buscar) !== false) {
                return (int) $i;
            }
        }
        return null;
    }

    /**
     * @param list<array<int, mixed>> $filas
     */
    private static function muestraFilasBanCoppel(array $filas): string
    {
        $partes = [];
        $limite = min(count($filas), 4);
        for ($i = 0; $i < $limite; $i++) {
            $fila = $filas[$i];
            if (!is_array($fila)) {
                continue;
            }
            $celdas = [];
            foreach (array_slice($fila, 0, 4) as $c) {
                $celdas[] = trim((string) $c);
            }
            $partes[] = 'F' . ($i + 1) . '=[' . implode(' | ', $celdas) . ']';
        }
        return implode('; ', $partes);
    }

    /**
     * @param list<array<int, mixed>> $filas
     */
    private static function buscarEncabezadoBanCoppel(array $filas): ?int
    {
        $limite = min(count($filas), 30);
        for ($i = 0; $i < $limite; $i++) {
            if (self::esEncabezadoBanCoppel($filas[$i] ?? [])) {
                return $i;
            }
        }
        return null;
    }

    /**
     * @return list<array<int, string>>
     */
    private static function leerFilasCsv(string $ruta): array
    {
        $fh = @fopen($ruta, 'rb');
        if ($fh === false) {
            return [];
        }
        $filas = [];
        while (($fila = fgetcsv($fh)) !== false) {
            $filas[] = $fila;
        }
        fclose($fh);
        return $filas;
    }

    /**
     * @return list<array<int, string>>
     */
    private static function leerFilasExcel(string $ruta): array
    {
        // Muchos bancos exportan "Excel" que en realidad es HTML con extensión .xls
        $html = self::leerFilasHtmlComoExcel($ruta);
        if (!empty($html)) {
            return $html;
        }

        $matriz = XlsxSinZipReader::matrizPrimeraHoja($ruta);
        if ($matriz !== null && !empty($matriz)) {
            $maxFila = max(array_keys($matriz));
            $maxCol = 0;
            foreach ($matriz as $cols) {
                if (!empty($cols)) {
                    $maxCol = max($maxCol, max(array_keys($cols)));
                }
            }
            $filas = [];
            for ($f = 1; $f <= $maxFila; $f++) {
                $fila = [];
                for ($c = 1; $c <= $maxCol; $c++) {
                    $fila[] = trim((string) ($matriz[$f][$c] ?? ''));
                }
                $filas[] = $fila;
            }
            return $filas;
        }

        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            require_once dirname(__DIR__) . '/../libs/PhpSpreadsheet/vendor/autoload.php';
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta);
            $mejor = [];
            foreach ($spreadsheet->getAllSheets() as $hoja) {
                $raw = $hoja->toArray(null, true, true, false);
                if (!is_array($raw) || empty($raw)) {
                    continue;
                }
                $filas = self::normalizarFilasExcel($raw);
                // Prefiere la hoja que trae el encabezado BanCoppel.
                if (self::buscarEncabezadoBanCoppel($filas) !== null) {
                    return $filas;
                }
                if (count($filas) > count($mejor)) {
                    $mejor = $filas;
                }
            }
            return $mejor;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<array<int, mixed>> $raw
     * @return list<array<int, string>>
     */
    private static function normalizarFilasExcel(array $raw): array
    {
        $filas = [];
        foreach ($raw as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $normalizada = [];
            foreach ($fila as $celda) {
                if ($celda instanceof \DateTimeInterface) {
                    $normalizada[] = $celda->format('Y-m-d');
                } elseif (is_float($celda) || is_int($celda)) {
                    if (is_float($celda) && $celda > 20000 && $celda < 80000 && abs($celda - round($celda)) < 0.00001) {
                        try {
                            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($celda);
                            $normalizada[] = $dt->format('Y-m-d');
                        } catch (\Throwable $e) {
                            $normalizada[] = (string) $celda;
                        }
                    } else {
                        $normalizada[] = (string) $celda;
                    }
                } else {
                    $texto = trim((string) $celda);
                    // Quita BOM / NBSP que rompen el encabezado FECHA.
                    $texto = preg_replace('/^\xEF\xBB\xBF/u', '', $texto);
                    $texto = str_replace("\xC2\xA0", ' ', $texto);
                    $normalizada[] = trim((string) $texto);
                }
            }
            $filas[] = $normalizada;
        }
        return $filas;
    }

    /**
     * Lee .xls exportados como tabla HTML (frecuente en layouts bancarios).
     *
     * @return list<array<int, string>>
     */
    private static function leerFilasHtmlComoExcel(string $ruta): array
    {
        $bin = @file_get_contents($ruta);
        if ($bin === false || $bin === '') {
            return [];
        }
        // BIFF real (.xls) empieza con D0 CF 11 E0; ZIP/xlsx con PK.
        $sig = substr($bin, 0, 8);
        if (strncmp($sig, "\xD0\xCF\x11\xE0", 4) === 0 || strncmp($sig, 'PK', 2) === 0) {
            return [];
        }
        if (stripos($bin, '<table') === false && stripos($bin, '<html') === false) {
            return [];
        }

        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $ok = @$dom->loadHTML('<?xml encoding="UTF-8">' . $bin);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($ok === false) {
            return [];
        }

        $tablas = $dom->getElementsByTagName('table');
        if ($tablas->length === 0) {
            return [];
        }

        $filas = [];
        $tabla = $tablas->item(0);
        if (!$tabla instanceof \DOMElement) {
            return [];
        }
        foreach ($tabla->getElementsByTagName('tr') as $tr) {
            $fila = [];
            foreach ($tr->childNodes as $celda) {
                if (!$celda instanceof \DOMElement) {
                    continue;
                }
                $tag = strtolower($celda->tagName);
                if ($tag !== 'td' && $tag !== 'th') {
                    continue;
                }
                $fila[] = trim(html_entity_decode($celda->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            if (!empty($fila)) {
                $filas[] = $fila;
            }
        }
        return $filas;
    }

    private static function esEncabezadoBanCoppel(array $fila): bool
    {
        $texto = strtoupper(implode('|', array_map('strval', $fila)));
        return strpos($texto, 'FECHA') !== false && strpos($texto, 'REFERENCIA') !== false;
    }

    private static function esFilaResumenBanCoppel(string $referencia): bool
    {
        $ref = strtoupper($referencia);
        if (stripos($ref, 'TRANSF') !== false) {
            return true;
        }
        if (stripos($ref, 'FINANCIERA') !== false) {
            return true;
        }
        return false;
    }

    /**
     * @param array<int, string> $encabezado
     * @return array<string, int>
     */
    private static function mapearEncabezados(array $encabezado): array
    {
        $mapa = [];
        foreach ($encabezado as $i => $col) {
            $nombre = (string) $col;
            $nombre = preg_replace('/^\xEF\xBB\xBF/u', '', $nombre);
            $nombre = str_replace("\xC2\xA0", ' ', $nombre);
            $nombre = strtoupper(trim(preg_replace('/\s+/', ' ', $nombre)));
            if ($nombre !== '') {
                $mapa[$nombre] = (int) $i;
            }
        }
        return $mapa;
    }

    private static function filaBase(
        string $archivo,
        string $corresponsal,
        string $fecha,
        string $referencia,
        float $monto,
        int $linea
    ): array {
        return [
            'ARCHIVO' => $archivo,
            'CORRESPONSAL' => $corresponsal,
            'FECHA' => $fecha,
            'REFERENCIA_ORIGINAL' => $referencia,
            'MONTO' => round($monto, 2),
            'LINEA' => $linea,
        ];
    }

    public static function extraerCredito(string $corresponsal, string $referencia): ?string
    {
        $corresponsal = strtoupper(trim($corresponsal));
        $referencia = trim($referencia);

        if ($corresponsal === self::CORRESPONSAL_OXXO) {
            $soloDigitos = preg_replace('/\D/', '', $referencia);
            if ($soloDigitos === null || strlen($soloDigitos) < 19) {
                return null;
            }
            return substr($soloDigitos, 13, 6);
        }

        $token = preg_split('/\s+/', strtoupper($referencia))[0] ?? '';
        if (!self::referenciaPaycashValida($token)) {
            return null;
        }
        return substr($token, 1, 6);
    }

    public static function referenciaPaycashValida(string $referencia): bool
    {
        $referencia = trim($referencia);
        if (strlen($referencia) !== 10) {
            return false;
        }
        return in_array($referencia[0], ['P', 'C', '0', '1'], true);
    }

    public static function normalizarReferenciaPaycash(string $referencia): string
    {
        return preg_split('/\s+/', trim(strtoupper($referencia)))[0] ?? '';
    }

    private static function parsearFechaYmd(string $valor): ?string
    {
        $valor = preg_replace('/\D/', '', $valor);
        if ($valor === null || strlen($valor) !== 8) {
            return null;
        }
        $y = (int) substr($valor, 0, 4);
        $m = (int) substr($valor, 4, 2);
        $d = (int) substr($valor, 6, 2);
        if (!checkdate($m, $d, $y)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    private static function parsearFechaFlexible(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        // Excel a veces deja hora: "24/08/2026 0:00" o "24.08.2026 0:00"
        if (preg_match('#^(\d{1,2}[./-]\d{1,2}[./-]\d{2,4})\b#', $valor, $soloFecha)) {
            $valor = $soloFecha[1];
        }
        if (preg_match('/^\d{8}$/', $valor)) {
            return self::parsearFechaYmd($valor);
        }
        // ISO YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $valor, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            if (!checkdate($mo, $d, $y)) {
                return null;
            }
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        // DD/MM/YYYY, MM/DD/YYYY, DD.MM.YYYY (también año a 2 dígitos)
        if (preg_match('#^(\d{1,2})[./-](\d{1,2})[./-](\d{2,4})$#', $valor, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $y = (int) $m[3];
            if ($y < 100) {
                $y += ($y >= 70) ? 1900 : 2000;
            }
            // PhpSpreadsheet (locale US) formatea 24/08/2026 como "8/24/2026".
            // Si el segundo número es > 12, es mes/día (MDY); si el primero es > 12, es día/mes (DMY).
            // Ambiguo (ambos ≤ 12): se asume DMY (México / BanCoppel).
            if ($b > 12 && $a >= 1 && $a <= 12) {
                $mo = $a;
                $d = $b;
            } else {
                $d = $a;
                $mo = $b;
            }
            if (!checkdate($mo, $d, $y)) {
                return null;
            }
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        // Serial de Excel como texto ("45928")
        if (preg_match('/^\d{5}(\.\d+)?$/', $valor)) {
            $serial = (float) $valor;
            if ($serial > 20000 && $serial < 80000) {
                try {
                    if (!class_exists('\PhpOffice\PhpSpreadsheet\Shared\Date')) {
                        require_once dirname(__DIR__) . '/../libs/PhpSpreadsheet/vendor/autoload.php';
                    }
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($serial);
                    return $dt->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }
        return null;
    }

    private static function parsearMontoDecimal(string $valor): ?float
    {
        $valor = trim(str_replace(['$', ' '], '', $valor));
        if ($valor === '') {
            return null;
        }
        $valor = str_replace(',', '', $valor);
        if (!is_numeric($valor)) {
            return null;
        }
        return (float) $valor;
    }

    /** PAYCASH entrega montos en centavos. */
    private static function parsearMontoPaycash(string $valor): ?float
    {
        $valor = trim(str_replace(['$', ' ', ','], '', $valor));
        if ($valor === '' || !is_numeric($valor)) {
            return null;
        }
        return ((float) $valor) / 100;
    }

    private static function parsearMontoFlexible(string $valor): ?float
    {
        $valor = trim(str_replace(['$', ' '], '', $valor));
        if ($valor === '') {
            return null;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d{2}$/', $valor)) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } else {
            $valor = str_replace(',', '', $valor);
        }
        if (!is_numeric($valor)) {
            return null;
        }
        return (float) $valor;
    }

    /**
     * @return array{success: bool, mensaje: string, datos?: array, error?: string}
     */
    private static function ok(string $mensaje, array $datos): array
    {
        return ['success' => true, 'mensaje' => $mensaje, 'datos' => $datos];
    }

    /**
     * @return array{success: bool, mensaje: string, error?: string}
     */
    private static function error(string $mensaje): array
    {
        return ['success' => false, 'mensaje' => $mensaje, 'error' => $mensaje];
    }
}
