<?php

namespace App\services;

defined("APPPATH") or die("Access denied");

use App\models\Herramientas as HerramientasDao;
use Core\Model;

class AuditoriaDevengoService
{
    private const PERFILES_AUTORIZADOS = ['ADMIN', 'PLMV', 'PHEE'];

    /**
     * Permite coincidencia exacta o cualquier perfil que contenga ADMIN.
     */
    public static function validaPerfil(string $perfil): bool
    {
        if (in_array($perfil, self::PERFILES_AUTORIZADOS, true)) {
            return true;
        }

        return stripos($perfil, 'ADMIN') !== false;
    }

    public static function GetDevengosFaltantes(array $datos = []): array
    {
        return HerramientasDao::GetDevengosFaltantes($datos);
    }

    public static function ProcesarIndividual(array $datos, string $usuario, string $perfil, string $ip): array
    {
        $log = defined('APPPATH') ? APPPATH . '/../logs/auditoria_devengo_proceso.log' : __DIR__ . '/../../logs/auditoria_devengo_proceso.log';
        $fila = isset($datos['fila']) ? $datos['fila'] : $datos;
        $credito = trim((string) ($fila['CREDITO'] ?? $fila['CDGCLNS'] ?? $fila['credito'] ?? ''));
        $ciclo = trim((string) ($fila['CICLO'] ?? $fila['ciclo'] ?? ''));

        @file_put_contents($log, date('c') . " [SVC] ProcesarIndividual credito=$credito | ciclo=$ciclo | usuario=$usuario | perfil=$perfil\n", FILE_APPEND);

        if ($credito === '' || $ciclo === '') {
            @file_put_contents($log, date('c') . " [SVC] BLOQUEO: credito o ciclo vacíos\n", FILE_APPEND);
            return Model::Responde(false, 'Crédito y ciclo son obligatorios.', null, 'Parámetros incompletos');
        }

        if (!self::validaPerfil($perfil)) {
            @file_put_contents($log, date('c') . " [SVC] BLOQUEO: perfil '$perfil' no autorizado\n", FILE_APPEND);
            return Model::Responde(false, 'Perfil no autorizado para esta operación.', null, 'Acceso denegado');
        }

        try {
            return HerramientasDao::ProcesarDevengoIndividual($fila, $usuario, $perfil, $ip, 'INDIVIDUAL');
        } catch (\Throwable $e) {
            return Model::Responde(false, 'Error al procesar devengo.', null, $e->getMessage());
        }
    }

    public static function ProcesarMasivo(array $registros, string $usuario, string $perfil, string $ip): array
    {
        if (empty($registros) || !is_array($registros)) {
            return Model::Responde(false, 'No se recibieron registros para procesar.', null, 'Lista vacía');
        }

        if (!self::validaPerfil($perfil)) {
            return Model::Responde(false, 'Perfil no autorizado para esta operación.', null, 'Acceso denegado');
        }

        try {
            return HerramientasDao::ProcesarDevengoMasivo($registros, $usuario, $perfil, $ip);
        } catch (\Throwable $e) {
            return Model::Responde(false, 'Error en procesamiento masivo.', null, $e->getMessage());
        }
    }
}
