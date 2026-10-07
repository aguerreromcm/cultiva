<?php

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mensajero
{
    static private $SMTP_SERVER = '';
    static private $SMTP_PORT = 0;
    static private $SMTP_USER = '';
    static private $SMTP_PASS = '';
    static private $SMTP_FROM = '';

    /**
     * Configura los parámetros del servidor SMTP para el envío de correos electrónicos.
     *
     * @param string|null $server Dirección del servidor SMTP. Si es null, se toma del archivo de configuración.
     * @param int|null $port Puerto del servidor SMTP. Si es null, se toma del archivo de configuración.
     * @param string|null $user Nombre de usuario para autenticarse en el servidor SMTP. Si es null, se toma del archivo de configuración.
     * @param string|null $pass Contraseña para autenticarse en el servidor SMTP. Si es null, se toma del archivo de configuración.
     * @param string|null $from Dirección de correo electrónico del remitente. Si es null, se toma del archivo de configuración.
     * @return void
     */
    public static function configura($server = null, $port = null, $user = null, $pass = null, $from = null)
    {
        $config = parse_ini_file(dirname(__DIR__, 2) . '/App/config/configuracion.ini');
        self::$SMTP_SERVER = $server ?? $config['SMTP_SERVER'];
        self::$SMTP_PORT = $port ?? $config['SMTP_PORT'];
        self::$SMTP_USER = $user ?? $config['SMTP_USER'];
        self::$SMTP_PASS = $pass ?? $config['SMTP_PASS'];
        self::$SMTP_FROM = $from ?? $config['SMTP_FROM'];
    }

    /**
     * Envía un correo electrónico utilizando PHPMailer.
     *
     * @param array|string $destinatarios Lista de destinatarios del correo. Puede ser un array o una cadena con un solo destinatario.
     * @param string $asunto Asunto del correo.
     * @param string $mensaje Cuerpo del mensaje del correo. Puede contener HTML.
     * @param array|string $adjuntos (Opcional) Lista de archivos adjuntos. Puede ser un array o una cadena con un solo archivo.
     * @param bool $enviarCopiaHistorico Si es false, no se envía copia al buzón SMTP_USER (uso típico: cierre en modo desarrollo).
     * @return bool Devuelve true si el correo se envió correctamente, false en caso contrario.
     */
    public static function EnviarCorreo($destinatarios, $asunto, $mensaje, $adjuntos = [], $enviarCopiaHistorico = true)
    {

        $mensajero = new PHPMailer(true);
        self::configura();

        try {
            $mensajero->setLanguage('es', __DIR__ . '/vendor/phpmailer/phpmailer/language/');
            $mensajero->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mensajero->isSMTP();
            $mensajero->Host = self::$SMTP_SERVER;
            $mensajero->Port = self::$SMTP_PORT;
            $mensajero->SMTPAuth = true;
            $mensajero->Username = self::$SMTP_USER;
            $mensajero->Password = self::$SMTP_PASS;
            $mensajero->isHTML(true);
            $mensajero->Subject = $asunto;
            $mensajero->Body = $mensaje;
            $mensajero->AltBody = strip_tags($mensaje);
            $mensajero->CharSet = 'UTF-8';
            $mensajero->setFrom(self::$SMTP_USER, self::$SMTP_FROM);
            $mensajero->addCustomHeader('Return-Path', self::$SMTP_USER);

            $adjuntos = is_array($adjuntos) ? $adjuntos : [$adjuntos];
            if (count($adjuntos) > 0) {
                foreach ($adjuntos as $adjunto) {
                    $mensajero->addAttachment($adjunto);
                }
            }

            $destinatarios = is_array($destinatarios) ? $destinatarios : [$destinatarios];
            foreach ($destinatarios as $destinatario) {
                $mensajero->clearAddresses();
                $mensajero->addAddress($destinatario);
                $mensajero->send();
            }

            if ($enviarCopiaHistorico) {
                // Se crea el JSON
                $destInfo = __DIR__ . '/destinatarios.json';
                file_put_contents($destInfo, json_encode($destinatarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                // Se envia una copia a la cuenta de SMTP para historico
                $mensajero->clearAddresses();
                $mensajero->addAddress(self::$SMTP_USER);
                $mensajero->addAttachment($destInfo);
                $mensajero->send();

                unlink($destInfo);
            }

            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo: {$e->getMessage()}");
            // mostrar la pila de errores
            error_log($e->getTraceAsString());
            return false;
        }
    }

    public static function Notificaciones($body)
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="es">
                <head>
                    <meta charset="UTF-8" />
                    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
                </head>
                <body style="margin: 0; padding: 0 10px; font-family: Arial, sans-serif; background-color: #f4f4f4">
                    <div
                        style="
                            max-width: 600px;
                            margin: 20px auto;
                            background-color: #ffffff;
                            border-radius: 10px;
                            overflow: hidden;
                            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
                        "
                    >
                        <!-- Encabezado -->
                        <div style="background-color: #494949; color: #fff; height: 60px">
                            <table style="width: 95%; height: 100%; border-spacing: 0; margin: auto">
                                <tr>
                                    <td style="padding: 0">
                                        <div style="height: 55px; display: block">
                                            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="55" height="55" viewBox="42 -104 120 120">
                                                <path fill="#6f7071" d="m 86.649827,15.130291 c -2.399,-0.517 -4.578,-2.003 -5.893,-4.28 l -36.789,-63.717 c -2.472,-4.28 -0.989,-9.802 3.288,-12.274 l 40.134,-23.172 -0.158,22.244 -17.029,9.833 c -2.487,1.434 -3.346,4.642 -1.912,7.129 l 2.247,3.89 25.865997,-14.935 0.055,19.035 -17.664997,10.198 8.085,14.006 z" />
                                                <path fill="#7a191e" d="m 156.74382,-22.650709 -61.092996,35.272 0.161,-22.371 c 0.335,-0.11 0.661,-0.256 0.977,-0.438 l 37.010996,-21.37 c 2.487,-1.434 3.346,-4.642 1.912,-7.129 l -21.37,-37.011 c -1.437,-2.487 -4.642,-3.346 -7.129,-1.912 l -10.961996,6.329 0.158,-22.244 14.562996,-8.408001 c 4.28,-2.472 9.802,-0.992 12.274,3.288001 l 36.785,63.717 c 2.473,4.283 0.993,9.805 -3.287,12.277" />
                                            </svg>
                                        </div>
                                    </td>
                                    <td style="padding: 0">
                                        <h1 style="margin: 0; text-align: right">Notificaciones</h1>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Cuerpo -->
                        <div style="padding: 15px; color: #333333">
                            {$body}
                        </div>

                        <!-- Pie de página -->
                        <div
                            style="
                                background-color: #f4f4f4;
                                height: 60px;
                                text-align: center;
                                font-size: 10px;
                                color: #555555;
                                border-top: 1px solid #ddd;
                            "
                        >
                            <p>Este correo ha sido generado automáticamente, no responda a este mensaje.</p>
                            <p>Si usted no es el destinatario previsto, favor de reenviarlo a soporte.</p>
                        </div>
                    </div>
                </body>
            </html>
        HTML;
    }
}
