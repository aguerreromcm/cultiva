<?php

namespace Core;

defined("APPPATH") or die("Access denied");

class Controller
{
    public $mensajes = <<<JAVASCRIPT
        const tipoMensaje = (mensaje, icono, config = null) => {
            let configMensaje = (typeof mensaje === "object") ? { content: mensaje } : { text: mensaje }
            configMensaje.icon = icono
            if (config) Object.assign(configMensaje, config)
            return swal(configMensaje)
        }

        const showError = (mensaje) =>  tipoMensaje(mensaje, "error")
        const showSuccess = (mensaje) => tipoMensaje(mensaje, "success")
        const showInfo = (mensaje) => tipoMensaje(mensaje, "info")
        const showWarning = (mensaje) => tipoMensaje(mensaje, "warning")
        const showWait = (mensaje) => {
            const config = {
                button: false,
                closeOnClickOutside: false,
                closeOnEsc: false
            }
            return tipoMensaje(mensaje, "/img/wait.gif", config)
        }
        const confirmarMovimiento = async (titulo, mensaje, html = null) => {
            return await swal({ title: titulo, content: html, text: mensaje, icon: "warning", buttons: ["No", "Si, continuar"], dangerMode: true })
        }
    JAVASCRIPT;
    public $consultaServidor = <<<JAVASCRIPT
        const consultaServidor = (url, datos, fncOK, metodo = "POST", tipo = "JSON", tipoContenido = null, procesar = null) => {
            swal({ text: "Procesando la solicitud, espere un momento...", icon: "/img/wait.gif", button: false, closeOnClickOutside: false, closeOnEsc: false })
            const configuracion = {
                type: metodo,
                url: url,
                data: datos,
                success: (res) => {
                    if (tipo === "JSON") {
                        try {
                            res = JSON.parse(res)
                        } catch (error) {
                            console.error(error)
                            res =  {
                                success: false,
                                mensaje: "Ocurrió un error al procesar la respuesta del servidor."
                            }
                        }
                    }
                    if (tipo === "blob") res = new Blob([res], { type: "application/pdf" })

                    swal.close()
                    fncOK(res)
                },
                error: (error) => {
                    console.error(error)
                    showError("Ocurrió un error al procesar la solicitud.")
                }
            }

            if (tipoContenido != null) configuracion.contentType = tipoContenido 
            if (procesar != null) configuracion.processData = procesar

            $.ajax(configuracion)
        }
    JAVASCRIPT;
    public $configuraTabla = <<<JAVASCRIPT
        const configuraTabla = (id, {noRegXvista = true} = {}) => {
            const configuracion = {
                lengthMenu: [
                    [10, 40, -1],
                    [10, 40, "Todos"]
                ],
                order: [],
                autoWidth: false,
                language: {
                    emptyTable: "No hay datos disponibles",
                    paginate: {
                        previous: "Anterior",
                        next: "Siguiente",
                    },
                    info: "Mostrando de _START_ a _END_ de _TOTAL_ registros",
                    infoEmpty: "Sin registros para mostrar",
                    zeroRecords: "No se encontraron registros",
                    lengthMenu: "Mostrar _MENU_ registros por página",
                    search: "Buscar:",
                },
                createdRow: (row) => {
                    $(row).find('td').css('vertical-align', 'middle');
                }
            }

            configuracion.lengthChange = noRegXvista

            $("#" + id).DataTable(configuracion)
        }
    JAVASCRIPT;
    public $actualizaDatosTabla = <<<JAVASCRIPT
        const actualizaDatosTabla = (id, datos) => {
            const tabla = $("#" + id).DataTable()
            tabla.clear()
            datos.forEach((item) => {
                if (Array.isArray(item)) tabla.row.add(item).draw(false)
                else tabla.row.add(Object.values(item)).draw(false)
            })
            tabla.draw()
        }
    JAVASCRIPT;
    public $respuestaError = <<<JAVASCRIPT
        const respuestaError = (tabla, mensaje) => {
            $(".resultado").toggleClass("conDatos", false)
            showError(mensaje).then(() => actualizaDatosTabla(tabla, []))
        }
    JAVASCRIPT;
    public $respuestaSuccess = <<<JAVASCRIPT
        const respuestaSuccess = (tabla, datos) => {
            actualizaDatosTabla(tabla, datos)
            $(".resultado").toggleClass("conDatos", true)
        }
    JAVASCRIPT;
    public $descargaExcel = <<<JAVASCRIPT
        const descargaExcel = (url) => {
            swal({ text: "Generando archivo, espere un momento...", icon: "/img/wait.gif", closeOnClickOutside: false, closeOnEsc: false })
            const ventana = window.open(url, "_blank")
            const intervalo = setInterval(() => {
                if (ventana.closed) {
                    clearInterval(intervalo)
                    swal.close()
                }
            }, 1000)

            window.focus()
        }
    JAVASCRIPT;
    public $muestraPDF = <<<JAVASCRIPT
        const muestraPDF = (titulo, ruta) => {
            const host = window.location.origin

            let plantilla = '<!DOCTYPE html>'
                plantilla += '<html lang="es">'
                plantilla += '<head>'
                plantilla += '<meta charset="UTF-8">'
                plantilla += '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
                plantilla += '<link rel="shortcut icon" href="' + host + '/img/logo_ico.svg">'
                plantilla += '<title>' + titulo + '</title>'
                plantilla += '</head>'
                plantilla += '<body style="margin: 0; padding: 0; background-color: #333333;">'
                plantilla += '<iframe src="' + ruta + '" style="width: 100%; height: 99vh; border: none; margin: 0; padding: 0;"></iframe>'
                plantilla += '</body>'
                plantilla += '</html>'
            
                const blob = new Blob([plantilla], { type: 'text/html' })
                const url = URL.createObjectURL(blob)
                window.open(url, '_blank')
        }
    JAVASCRIPT;

    public $__usuario = '';
    public $__nombre = '';
    public $__puesto = '';
    public $__cdgco = '';
    public $__perfil = '';

    public function __construct()
    {
        $modo = $_POST['modo'] ?? $_GET['modo'] ?? null;
        if ($modo === 'API') return;

        session_start();
        if ($_SESSION['usuario'] == '' || empty($_SESSION['usuario'])) {
            unset($_SESSION);
            session_unset();
            session_destroy();
            header("Location: /Login/");
            exit();
        } else {
            $this->__usuario = $_SESSION['usuario'];
            $this->__nombre = $_SESSION['nombre'];
            $this->__puesto = $_SESSION['puesto'];
            $this->__cdgco = $_SESSION['cdgco'];
            $this->__perfil = $_SESSION['perfil'];
        }
    }

    public function GetRespuesta($success, $mensaje, $datos = null, $error = null)
    {
        $respuesta = [
            "success" => $success,
            "mensaje" => $mensaje
        ];

        if ($datos !== null) $respuesta['datos'] = $datos;
        if ($error !== null) $respuesta['error'] = $error;

        return $respuesta;
    }

    public function RespondeJSON($respuesta)
    {
        echo json_encode($respuesta);
    }

    public function Responde($success, $mensaje, $datos = null, $error = null)
    {
        self::RespondeJSON(self::GetRespuesta($success, $mensaje, $datos, $error));
    }

    public function ErrorPDF($mensaje)
    {
        return <<<HTML
            <html>
                <head>
                    <title>Error</title>
                </head>
                <body style="background-color: #333333; color: #ffffff; font-weight: bold; font-family: sans-serif; text-align: center;">
                    <div style="height: 100%; width: 100%; display: flex; justify-content: center; align-items: center;">
                        <h1>
                            $mensaje
                        </h1>
                    <div>
                </body>
            </html>
        HTML;
    }

    public function GetExtraHeader($titulo, $elementos = [])
    {
        $html = <<<HTML
        <title>$titulo</title>
        HTML;

        if (!empty($elementos)) {
            foreach ($elementos as $elemento) {
                $html .= "\n" . $elemento;
            }
        }

        return $html;
    }
}
