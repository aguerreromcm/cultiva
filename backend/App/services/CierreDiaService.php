<?php

namespace App\services;

defined("APPPATH") or die("Access denied");

use App\repositories\CierreDiaRepository;
use Core\Model;

/**
 * Cierre de día: validación, lanzamiento del Job en segundo plano y consulta de estado.
 * El SP no confirma la bitácora hasta terminar, por eso "en ejecución" se determina con el candado del Job.
 */
class CierreDiaService
{
    const MSG_EN_EJECUCION = 'Hay un cierre de día en proceso. Espere a que termine para realizar cualquier acción.';

    public static function enEjecucion(): bool
    {
        return CierreDiaJobLock::jobActivo();
    }

    /**
     * Respuesta de error para las acciones que deben bloquearse durante el cierre, o null si no hay cierre en proceso.
     */
    public static function respuestaSiEnEjecucion(): ?array
    {
        return self::enEjecucion() ? Model::Responde(false, self::MSG_EN_EJECUCION) : null;
    }

    public static function estado(): array
    {
        $ejecutando = self::enEjecucion();
        $info = $ejecutando ? CierreDiaJobLock::leerInfo() : [];

        $repo = new CierreDiaRepository();
        $ultimo = $repo->ultimoCierreExitoso();
        $sugerida = $ultimo ? date('Y-m-d', strtotime($ultimo . ' +1 day')) : date('Y-m-d', strtotime('-1 day'));
        if ($sugerida > date('Y-m-d')) {
            $sugerida = date('Y-m-d');
        }

        return Model::Responde(true, 'Estado del cierre', [
            'ejecutando' => $ejecutando,
            'fecha' => $info['fecha'] ?? null,
            'usuario' => $info['usuario'] ?? null,
            'inicio' => $info['inicio'] ?? null,
            'segundos' => isset($info['inicio_ts']) ? max(0, time() - (int) $info['inicio_ts']) : 0,
            'tiempo_estimado' => $repo->tiempoEstimado(),
            'ultimo_cierre' => $ultimo,
            'fecha_sugerida' => $sugerida,
        ]);
    }

    /**
     * Si el cierre ya fue ejecutado responde success con yaEjecutado; solo ADMIN puede regenerar.
     */
    public static function validacionPrevia(string $fecha, string $perfil = ''): array
    {
        $fecha = trim($fecha);
        $dt = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$dt || $dt->format('Y-m-d') !== $fecha) {
            return Model::Responde(false, 'Indique una fecha de cierre válida.');
        }
        if ($fecha > date('Y-m-d')) {
            return Model::Responde(false, 'No es posible procesar el cierre de una fecha futura.');
        }
        if (self::enEjecucion()) {
            return Model::Responde(false, 'Ya hay un proceso de cierre de día en ejecución, no es posible iniciar otro.');
        }

        $repo = new CierreDiaRepository();
        $enEjecucion = $repo->validaCierreEnEjecucion();
        if (!empty($enEjecucion)) {
            return Model::Responde(
                false,
                "Ya hay un proceso de cierre de día en ejecución, no es posible iniciar otro (cierre del {$enEjecucion['FECHA_CIERRE']} iniciado por {$enEjecucion['USUARIO']} el {$enEjecucion['INICIO']}).",
                $enEjecucion
            );
        }

        $yaEjecutado = $repo->cierreYaEjecutado($fecha);
        $puedeRegenerar = $yaEjecutado && stripos($perfil, 'ADMIN') !== false;

        return Model::Responde(true, $yaEjecutado ? 'El cierre de ese día ya fue ejecutado.' : 'Validación correcta.', array_merge(
            [
                'fecha' => $fecha,
                'fecha_fmt' => $dt->format('d/m/Y'),
                'yaEjecutado' => $yaEjecutado,
                'puedeRegenerar' => $puedeRegenerar,
            ],
            $repo->pagosRegistrados($fecha)
        ));
    }

    public static function procesar(string $fecha, string $usuario, string $perfil = '', bool $regenerar = false): array
    {
        $usuario = trim($usuario);
        if ($usuario === '') {
            return Model::Responde(false, 'No se identificó al usuario que procesa el cierre.');
        }

        $jobScript = realpath(dirname(__DIR__, 2) . '/Jobs/controllers/CierreDia.php');
        if ($jobScript === false) {
            return Model::Responde(false, 'No se encontró el Job de cierre de día. No se inició el proceso.');
        }

        $phpBin = self::resolverBinarioPhp();
        if ($phpBin === '') {
            return Model::Responde(false, 'No se encontró el intérprete PHP para lanzar el Job. No se inició el proceso.');
        }

        $lanzamiento = CierreDiaJobLock::adquirirLanzamiento();
        if ($lanzamiento === null) {
            return Model::Responde(false, 'Ya hay un proceso de cierre de día en ejecución, no es posible iniciar otro.');
        }

        try {
            $previa = self::validacionPrevia($fecha, $perfil);
            if (empty($previa['success'])) {
                return $previa;
            }
            if (!empty($previa['datos']['yaEjecutado'])) {
                if (!$regenerar) {
                    return Model::Responde(false, 'El cierre de ese día ya fue ejecutado. No es posible iniciarlo de nuevo.');
                }
                if (empty($previa['datos']['puedeRegenerar'])) {
                    return Model::Responde(false, 'No tiene permisos para regenerar el cierre de ese día.');
                }
            } else {
                $regenerar = false;
            }

            ignore_user_abort(true);
            CierreDiaJobLock::guardarInfo([
                'fecha' => $fecha,
                'usuario' => $usuario,
                'inicio' => date('d/m/Y H:i:s'),
                'inicio_ts' => time(),
            ]);
            if (!self::lanzarJob($phpBin, $jobScript, $fecha, $usuario, $regenerar)) {
                return Model::Responde(false, 'No fue posible iniciar el Job de cierre de día. Intente de nuevo.');
            }

            // El Job toma el candado antes de invocar el SP: si no lo hace, murió al arrancar.
            if (!CierreDiaJobLock::esperarHastaActivo(15)) {
                return Model::Responde(
                    false,
                    'El proceso de cierre no arrancó y el procedimiento no se ejecutó. Intente de nuevo; si persiste, avise a sistemas.',
                    null,
                    self::salidaArranqueJob() ?: null
                );
            }

            return Model::Responde(true, 'El cierre de día se ha iniciado. Al finalizar se enviará el correo con el resultado.', [
                'fecha' => $fecha,
                'usuario' => $usuario,
                'regenerar' => $regenerar ? 1 : 0,
            ]);
        } finally {
            CierreDiaJobLock::liberarLanzamiento($lanzamiento);
        }
    }

    /**
     * Resultado del último intento del cierre para la fecha (se consulta al terminar el Job).
     */
    public static function resultado(string $fecha): array
    {
        if (self::enEjecucion()) {
            return Model::Responde(true, 'En proceso', ['en_proceso' => true]);
        }

        $r = (new CierreDiaRepository())->estatusPorFecha(trim($fecha));
        if ($r === null) {
            return Model::Responde(false, 'No se encontró registro del cierre en bitácora.', null, self::salidaArranqueJob() ?: null);
        }

        $lineas = preg_split('/\r\n|\r|\n/', trim((string) ($r['MENSAJE'] ?? '')));
        return Model::Responde(true, 'Resultado del cierre', [
            'en_proceso' => false,
            'exito' => (int) $r['EXITO'] === 1,
            'usuario' => $r['USUARIO'],
            'inicio' => $r['INICIO'],
            'fin' => $r['FIN'],
            'mensaje' => preg_replace('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} - /', '', (string) end($lineas)),
        ]);
    }

    /**
     * Solo el ejecutable CLI: PHP_BINARY con mod_php apunta a httpd.exe y el Job no correría.
     */
    private static function resolverBinarioPhp(): string
    {
        $nombre = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
        $candidatos = [];

        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
            $candidatos[] = PHP_BINARY;
        }

        $pistas = [];
        $iniCargado = php_ini_loaded_file();
        if ($iniCargado) {
            $pistas[] = dirname($iniCargado);
        }
        if (PHP_BINARY !== '') {
            $pistas[] = dirname(PHP_BINARY);
        }
        if (PHP_BINDIR !== '') {
            $pistas[] = PHP_BINDIR;
        }
        foreach ($pistas as $dir) {
            $candidatos[] = $dir . DIRECTORY_SEPARATOR . $nombre;
            // XAMPP: el CLI vive en <raíz>\php y el servidor en <raíz>\apache\bin
            $candidatos[] = dirname($dir) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . $nombre;
            $candidatos[] = dirname($dir, 2) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . $nombre;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidatos[] = 'C:\\xampp\\php\\php.exe';
        } else {
            $candidatos[] = '/usr/bin/php';
            $candidatos[] = '/usr/local/bin/php';
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            $dir = trim($dir);
            if ($dir !== '') {
                $candidatos[] = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . $nombre;
            }
        }

        foreach ($candidatos as $bin) {
            if (is_file($bin) && preg_replace('/\.exe$/i', '', strtolower(basename($bin))) === 'php') {
                return $bin;
            }
        }

        return '';
    }

    private static function lanzarJob(string $phpBin, string $jobScript, string $fecha, string $usuario, bool $regenerar): bool
    {
        $salida = CierreDiaJobLock::archivoSalidaJob();
        @unlink($salida);

        $argumentos = escapeshellarg($phpBin) . ' '
            . escapeshellarg($jobScript) . ' '
            . escapeshellarg('CierreDia') . ' '
            . escapeshellarg($fecha) . ' '
            . escapeshellarg($usuario) . ' '
            . escapeshellarg($regenerar ? '1' : '0');

        if (PHP_OS_FAMILY === 'Windows') {
            $handle = @popen('start /B "" ' . $argumentos . ' > ' . escapeshellarg($salida) . ' 2>&1', 'r');
            if (!is_resource($handle)) {
                return false;
            }
            pclose($handle);
            return true;
        }

        $out = [];
        $code = 1;
        @exec('nohup ' . $argumentos . ' > ' . escapeshellarg($salida) . ' 2>&1 & echo $!', $out, $code);
        $pid = isset($out[0]) ? trim((string) $out[0]) : '';
        return $code === 0 && ctype_digit($pid);
    }

    private static function salidaArranqueJob(): string
    {
        $archivo = CierreDiaJobLock::archivoSalidaJob();
        if (!is_file($archivo)) {
            return '';
        }
        $contenido = @file_get_contents($archivo, false, null, max(0, filesize($archivo) - 2048));
        return is_string($contenido) ? trim($contenido) : '';
    }
}
