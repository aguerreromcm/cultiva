<?php

namespace App\controllers;

defined("APPPATH") or die("Access denied");

use \Core\View;
use \Core\Controller;
use \App\models\ApiCondusef as ApiCondusefDao;

class ApiCondusef extends Controller
{
    private $_contenedor;

    function __construct()
    {
        parent::__construct();
        $this->_contenedor = new Contenedor;
        View::set('header', $this->_contenedor->header());
        View::set('footer', $this->_contenedor->footer());
    }

    public function AddRedeco()
    {
        $fecha = date('Y-m-d');

        $extraFooter = <<<HTML
            <script>                                                  
                const showError = (mensaje) => swal(mensaje, { icon: "error" })
                const showAviso = (mensaje) => swal(mensaje, { icon: "warning" })
                const showSuccess = (mensaje) => swal(mensaje, { icon: "success" , showConfirmButton: true,}).then((result) => {location.reload();} )
            
                const consumeAPI = (url, callback, datos = null, tipoDatos = 'json', tipo = "get", token = null, msgError = "", fncERR = null) => {
                    parametros = {
                        type: tipo,
                        url: url,
                        dataType: tipoDatos,
                        contentType: "application/json",
                        success: callback,
                        error: (resError) => {
                            console.log(resError.responseJSON)
                            if (fncERR) fncERR(resError.responseJSON)
                            else showError(msgError)
                        },
                        headers: { "Authorization": token }
                    }

                    if (datos) parametros.data = JSON.stringify(datos)

                    $.ajax(parametros)
                }
                 
                const limpiaCampos = (mensaje = "") => {
                    if (mensaje !== "") showError(mensaje)
                    document.querySelector("#QuejasEstados").innerHTML = ""
                    document.querySelector("#QuejasEstados").disabled = true
                    document.querySelector("#QuejasMunId").innerHTML = ""
                    document.querySelector("#QuejasMunId").disabled = true
                    document.querySelector("#QuejasLocId").innerHTML = ""
                    document.querySelector("#QuejasLocId").disabled = true
                    document.querySelector("#QuejasColId").innerHTML = ""
                    document.querySelector("#QuejasColId").disabled = true
                }
                 
                const soloNumeros = (e) => {
                    if (e.keyCode < 48 || e.keyCode > 57) return e.preventDefault()
                }
                 
                const validaLargo = (e, largo = 1) => {
                    if (e.target.value.length > largo) return e.preventDefault()
                }
                 
                const validaEntradaCP = (e) =>{
                    if (e.keyCode === 13) {
                        validaCP()
                        return e.preventDefault()
                    }
                    if (e.keyCode < 48 || e.keyCode > 57) return e.preventDefault()
                }
         
                const validaEdad = (e) => {
                    if (e.target.value < 18) {
                        return showAviso("La edad mínima para registrar una queja es de 18 años.")
                        .then(() => e.target.focus())
                    }
                }
                 
                const formatoFecha = (fecha) => {
                    const [anio, mes, dia] = fecha.split("-")
                    return dia + "/" + mes + "/" + anio
                }
                 
                const validaFechaRecepcion = () => {
                    const fechaRegistro = document.querySelector("#QuejasFecRecepcion").value
                    const mesRegistro = document.querySelector("#QuejasNoMes").value
                    const [anio, mes, dia] = fechaRegistro.split("-")
                     
                    if (parseInt(mes) != parseInt(mesRegistro)) {
                        document.querySelector("#QuejasFecRecepcion").value = "$fecha"
                        return showAviso("El mes de registro no coincide con la fecha de recepción, favor de validar.")
                    }
                }
                 
                const validaCP = () => {
                    const cp = document.querySelector("#QuejasCP").value
                    if (cp.length !== 5) return limpiaCampos("El código postal debe ser de 5 dígitos.")
                    
                    const url = "https://api.condusef.gob.mx/sepomex/colonias/?cp=" + cp
                    
                    consumeAPI(url, (data) => {
                        if (data.colonias.length === 0) return limpiaCampos("Código postal no encontrado.")
                        
                        validaEstado(data.colonias)
                        validaMunicipio(data.colonias)
                        validaLocalidad(data.colonias)
                        validaColonia(data.colonias)
                    },
                    null, "json", "get", null, "Ocurrió un error al consultar el CP en SEPOMEX.")
                }
                 
                const validaEstado = (edo) => {
                    const estado = document.querySelector("#QuejasEstados")
                    const estados = getOpciones(edo, "estadoId", "estado")
                    insertaOpciones(estado, estados)
                }
                 
                const validaMunicipio = (mun) => {
                    const municipio = document.querySelector("#QuejasMunId")
                    const municipios = getOpciones(mun, "municipioId", "municipio")
                    insertaOpciones(municipio, municipios)
                }
                 
                const validaLocalidad = (loc) => {
                    const localidad = document.querySelector("#QuejasLocId")
                    const localidades = getOpciones(loc, "tipoLocalidadId", "tipoLocalidad")
                    insertaOpciones(localidad, localidades)
                }
                 
                const validaColonia = (col) => {
                    const colonia = document.querySelector("#QuejasColId")
                    const colonias = getOpciones(col, "coloniaId", "colonia")
                    insertaOpciones(colonia, colonias)
                }
                 
                const getOpciones = (elementos, key, value) => {
                    const opciones = []
                    elementos.forEach(elemento => {
                        const opcion = "<option value='" + elemento[key] + "'>" + elemento[value] + "</option>"
                        if (!opciones.includes(opcion)) opciones.push(opcion)
                    })
                    return opciones
                }

                const cambioEstatus = (noEstatus) => {
                    if (noEstatus == 1) {
                        document.querySelector("#QuejasFecResolucion").disabled = true
                        document.querySelector("#QuejasFecNotificacion").disabled = true
                        document.querySelector("#QuejasRespuesta").disabled = true
                        document.querySelector("#QuejasRespuesta").selectedIndex = 0
                    } else {
                        document.querySelector("#QuejasFecResolucion").disabled = false
                        document.querySelector("#QuejasFecNotificacion").disabled = false
                        document.querySelector("#QuejasRespuesta").disabled = false
                    }
                }

                const cambioTipoPersona = (tipoPersona) => {
                    if (tipoPersona == 1) {
                        document.querySelector("#QuejasSexo").disabled = false
                        document.querySelector("#QuejasEdad").disabled = false
                    } else {
                        document.querySelector("#QuejasSexo").disabled = true
                        document.querySelector("#QuejasEdad").disabled = true
                        document.querySelector("#QuejasEdad").value = ""
                    }
                }
                 
                const insertaOpciones = (elemento, opciones = []) => {
                    if (opciones.length > 1) opciones.unshift("<option value='' disabled>Seleccione</option>")
                    
                    elemento.html(opciones.join(""))
                    elemento.selectedIndex = 0
                    elemento.disabled = !(opciones.length > 1)
                }
         
                const validaRequeridos = () => {
                    const requeridos = [
                        "#QuejasNoMes",
                        "#QuejasNum",
                        "#QuejasFolio",
                        "#QuejasFecRecepcion",
                        "#QuejasMedio",
                        "#QuejasNivelAT",
                        "#QuejasProducto",
                        "#QuejasCausa",
                        "#QuejasPORI",
                        "#QuejasEstatus",
                        "#QuejasCP",
                        "#QuejasEstados",
                        "#QuejasMunId",
                        "#QuejasLocId",
                        "#QuejasColId",
                        "#QuejasTipoPersona",
                        "#QuejasSexo",
                        "#QuejasEdad",
                        "#QuejasFecNotificacion",
                        "#QuejasNumPenal",
                        "#QuejasPenalizacion"
                    ]
                
                    const elementos = document.querySelectorAll(requeridos.join(","))
                    let validacion = false
                
                    elementos.forEach((elemento) => {
                        if (elemento.value === "" || elemento.value === "Seleccione") {
                            validacion = true
                        }
         
                        if (elemento.id === "QuejasEdad" && Number(elemento.value) < 18) {
                            validacion = false
                        }

                        if (elemento.id == "QuejasEstatus") cambioEstatus(elemento.value)
                        if (elemento.id == "QuejasTipoPersona") cambioTipoPersona(elemento.value)
                    })
                
                    document.querySelector("#btnAgregar").disabled = validacion
                }
                
                const registrarQueja =(e) => {
                    e.preventDefault()
                    const token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1aWQiOiIwOGEwZjc3YS02YjIxLTRmODYtOGUyMC1lN2MwODhlMTk1OGIiLCJ1c2VybmFtZSI6IkN1bHRpdmFPQyIsImluc3RpdHVjaW9uaWQiOiJGOUNGMjUzMy03RjRDLTQ3RkYtOTIyNi04MEE4QjA3OCIsImluc3RpdHVjaW9uQ2xhdmUiOjE1NDk0LCJkZW5vbWluYWNpb25fc29jaWFsIjoiRmluYW5jaWVyYSBDdWx0aXZhLCBTLkEuUC5JLiBkZSBDLlYuLCBTT0ZPTSwgRS5OLlIuIiwic2VjdG9yaWQiOjY5LCJzZWN0b3IiOiJTT0NJRURBREVTIEZJTkFOQ0lFUkFTIERFIE9CSkVUTyBNVUxUSVBMRSBFTlIiLCJzeXN0ZW0iOiJSRURFQ08iLCJpYXQiOjE3NzkxNzk2NDQsImV4cCI6MTc4MTc3MTY0NH0.tNlM2Esrrsxipo9c3sd-qmI7jfpk0cKKAIrNx4b5xB4"
         
                    const datos = [{
                        "QuejasDenominacion": document.querySelector("#QuejasDenominacion").value,
                        "QuejasSector": document.querySelector("#QuejasSector").value,
                        "QuejasNoMes": Number(document.querySelector("#QuejasNoMes").value),
                        "QuejasNum": Number(document.querySelector("#QuejasNum").value),
                        "QuejasFolio": document.querySelector("#QuejasFolio").value,
                        "QuejasFecRecepcion": formatoFecha(document.querySelector("#QuejasFecRecepcion").value),
                        "QuejasMedio": Number(document.querySelector("#QuejasMedio").value),
                        "QuejasNivelAT": Number(document.querySelector("#QuejasNivelAT").value),
                        "QuejasProducto": document.querySelector("#QuejasProducto").value,
                        "QuejasCausa": document.querySelector("#QuejasCausa").value,
                        "QuejasPORI": document.querySelector("#QuejasPORI").value,
                        "QuejasEstatus": Number(document.querySelector("#QuejasEstatus").value),
                        "QuejasEstados": Number(document.querySelector("#QuejasEstados").value),
                        "QuejasMunId": Number(document.querySelector("#QuejasMunId").value),
                        "QuejasLocId": Number(document.querySelector("#QuejasLocId").value),
                        "QuejasColId": Number(document.querySelector("#QuejasColId").value),
                        "QuejasCP": Number(document.querySelector("#QuejasCP").value),
                        "QuejasTipoPersona": Number(document.querySelector("#QuejasTipoPersona").value),
                        "QuejasSexo": (document.querySelector("#QuejasTipoPersona").value == 1 ? document.querySelector("#QuejasSexo").value : null),
                        "QuejasEdad": (document.querySelector("#QuejasTipoPersona").value == 1 ? Number(document.querySelector("#QuejasEdad").value) : null),
                        "QuejasFecResolucion": (document.querySelector("#QuejasEstatus").value != 1 ? formatoFecha(document.querySelector("#QuejasFecResolucion").value) : null),
                        "QuejasFecNotificacion": (document.querySelector("#QuejasEstatus").value != 1 ? formatoFecha(document.querySelector("#QuejasFecNotificacion").value) : null),
                        "QuejasRespuesta":  (document.querySelector("#QuejasEstatus").value != 1 ? formatoFecha(document.querySelector("#QuejasRespuesta").value) : null),
                        "QuejasNumPenal": (document.querySelector("#QuejasNumPenal").value != "" ? Number(document.querySelector("#QuejasNumPenal").value) : null),
                        "QuejasPenalizacion": Number(document.querySelector("#QuejasPenalizacion").value),
                    }]
                     
                    const procesaRespuesta = (respuesta) => {
                        return showSuccess("Queja registrada exitosamente con el folio: " + document.querySelector("#QuejasFolio").value)
                    }

                    const procesaError = (respuesta) => {
                        let mensaje = "Ocurrieron los siguientes errores:\\n\\n"
                        respuesta.errors[datos[0].QuejasFolio].forEach((error, i) => {
                            mensaje += (i+1) + ".-" + error + "\\n" 
                        })
                        mensaje += "\\n" + respuesta.message
                        return showError(mensaje)
                    }
                            
                    consumeAPI("https://api.condusef.gob.mx/redeco/quejas", procesaRespuesta, datos, "json", "post", token, "Ocurrió un error de comunicación con el portal de REDECO.", procesaError)
                }
            </script>
        HTML;

        $meses = [
            "1" => "Enero",
            "2" => "Febrero",
            "3" => "Marzo",
            "4" => "Abril",
            "5" => "Mayo",
            "6" => "Junio",
            "7" => "Julio",
            "8" => "Agosto",
            "9" => "Septiembre",
            "10" => "Octubre",
            "11" => "Noviembre",
            "12" => "Diciembre"
        ];

        $opcionesMeses = "";
        foreach ($meses as $key => $value) {
            if ($key == date('m')) $opcionesMeses .= "<option value='{$key}' selected>{$value}</option>";
            else $opcionesMeses .= "<option value='{$key}'>{$value}</option>";
        }

        $medios = self::GetMediosREDECO();
        $opcionesMedios = "<option value='' disabled selected>Seleccionar</option>";
        foreach ($medios as $key => $value) {
            $opcionesMedios .= "<option value='{$value['medioId']}'>{$value['medioDsc']}</option>";
        }

        $niveles = self::GetNivelesREDECO();
        $opcionesNiveles = "<option value='' disabled selected>Seleccionar</option>";
        foreach ($niveles as $key => $value) {
            $opcionesNiveles .= "<option value='{$value['nivelDeAtencionId']}'>{$value['nivelDeAtencionDsc']}</option>";
        }

        $productos = ApiCondusefDao::GetProductos();
        $opcionesProductos = "<option value='' disabled selected>Seleccionar</option>";
        foreach ($productos as $key => $value) {
            $opcionesProductos .= "<option value='{$value['CODIGO']}'>{$value['PRODUCTO']}</option>";
        }

        $causas = ApiCondusefDao::GetCausas();
        $opcionesCausas = "<option value='' disabled selected>Seleccionar</option>";
        foreach ($causas as $key => $value) {
            $opcionesCausas .= "<option value='{$value['CODIGO']}'>{$value['DESCRIPCION']}</option>";
        }

        View::set('header', $this->_contenedor->header(self::GetExtraHeader("Registrar Quejas REDECO")));
        View::set('footer', $this->_contenedor->footer($extraFooter));
        View::set('fecha', $fecha);
        View::set('meses', $opcionesMeses);
        View::set('medios', $opcionesMedios);
        View::set('niveles', $opcionesNiveles);
        View::set('productos', $opcionesProductos);
        View::set('causas', $opcionesCausas);
        View::render("z_api_agregar_quejas_REDECO");
    }

    public function GetMediosREDECO()
    {
        $url = "https://api.condusef.gob.mx/catalogos/medio-recepcion/";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);

        if (curl_errno($ch)) return [];
        curl_close($ch);
        return json_decode($response, true)['medio'];
    }

    public function GetNivelesREDECO()
    {
        $url = "https://api.condusef.gob.mx/catalogos/niveles-atencion/";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);

        if (curl_errno($ch)) return [];
        curl_close($ch);
        return json_decode($response, true)['nivelesDeAtencion'];
    }


    public function AddReune()
    {
        $extraFooter = <<<HTML
        <script>                                                
            {$this->mensajes}
            const mediosConCP = [3, 5, 17]
        
            const formatoFecha = (fecha) => {
                return Temporal.PlainDate.from(fecha).toLocaleString('es-MX', {
                            day:   '2-digit',
                            month: '2-digit',
                            year:  'numeric'
                        })
            }

            const consumeAPI = (url, callback, datos = null, tipoDatos = 'json', tipo = "get", token = null, msgError = "", fncERR = null) => {
                showWait("Procesando...")
                $.ajax({
                    type: tipo,
                    url: url,
                    dataType: tipoDatos,
                    data: datos ? JSON.stringify(datos) : null,
                    contentType: "application/json",
                    success: () => {
                        swal.close()
                        if (callback) callback()
                    },
                    error: (resError) => {
                        swal.close()
                        console.log(resError)
                        if (fncERR) fncERR(resError.responseJSON)
                        else showError(msgError || "Ocurrió un error de comunicación con el servidor.")
                    },
                    headers: { "Authorization": token }
                })
            }
                
            const limpiaCamposCP = (mensaje = "") => {
                if (mensaje !== "") showError(mensaje)
                $("#btnCP").find("i").removeClass("fa-trash").addClass("fa-search")
                $("#EstadosId").html('<option value="9" selected>Ciudad de México</option>')
                $("#ConsultasMpioId").html('<option value="14" selected>Benito Juárez</option>')
            }
                
            const soloNumeros = (e) => {
                if (e.keyCode < 48 || e.keyCode > 57) return e.preventDefault()
            }
             
            const validaLargo = (e, largo = 1) => {
                if (e.target.value.length > largo) return e.preventDefault()
            }
             
            const validaEntradaCP = (e) =>{
                if (e.keyCode === 13) {
                    validaCP()
                    return e.preventDefault()
                }
                if (e.keyCode < 48 || e.keyCode > 57) return e.preventDefault()
            }
                
            const validaFechaRecepcion = () => {
                const fechaRegistro = $("#ConsultasFecRecepcion").val()
                const mesRegistro = $("#ConsultasTrim").val()
                const [anio, mes, dia] = fechaRegistro.split("-")
                    
                if (parseInt(mes) != parseInt(mesRegistro)) {
                    $("#ConsultasFecRecepcion").val() = Temporal.Now.plainDateISO().toString()
                    return showAviso("El mes de registro no coincide con la fecha de recepción, favor de validar.")
                }
            }
                
            const validaCP = () => {
                if ($("#btnCP").find("i").hasClass("fa-trash")) {
                    $("#ConsultasCP").val("")
                    $("#ConsultasCP").prop("disabled", false)
                    return limpiaCamposCP()
                }

                const cp = $("#ConsultasCP").val()
                if (cp.length !== 5) return limpiaCamposCP("El código postal debe ser de 5 dígitos.")

                const url = "https://api.condusef.gob.mx/sepomex/colonias/?cp=" + cp
                
                consumeAPI(url, (data) => {
                    if (data.colonias.length === 0) return limpiaCamposCP("Código postal no encontrado.")
                    
                    validaEstado(data.colonias)
                    validaMunicipio(data.colonias)
                    $("#ConsultasCP").prop("disabled", true)
                    $("#btnCP").find("i").removeClass("fa-search").addClass("fa-trash")
                    validaRequeridos()
                })
            }
                
            const validaEstado = (edo) => {
                const estado = $("#EstadosId")
                const estados = getOpciones(edo, "estadoId", "estado")
                insertaOpciones(estado, estados)
            }
                
            const validaMunicipio = (mun) => {
                const municipio = $("#ConsultasMpioId")
                const municipios = getOpciones(mun, "municipioId", "municipio")
                insertaOpciones(municipio, municipios)
            }
                
            const getOpciones = (elementos, key, value) => {
                const opciones = []
                elementos.forEach(elemento => {
                    const opcion = "<option value='" + elemento[key] + "'>" + elemento[value] + "</option>"
                    if (!opciones.includes(opcion)) opciones.push(opcion)
                })
                return opciones
            }
                
            const insertaOpciones = (elemento, opciones = []) => {
                if (opciones.length > 1) opciones.unshift("<option value='' disabled>Seleccione</option>")
                
                elemento.html(opciones.join(""))
                elemento.selectedIndex = 0
                elemento.prop("disabled", !(opciones.length > 1))
            }

            const cambioEstatus = () => {
                const noEstatus = $("#ConsultasEstatusCon").val()

                const ConsultascatnivelatenId = $("#ConsultascatnivelatenId")
                const ConsultasFecAten = $("#ConsultasFecAten")

                if (noEstatus == 2) {
                    ConsultascatnivelatenId.prop("disabled", false)
                    ConsultasFecAten.prop("disabled", false)
                } else {
                    ConsultascatnivelatenId.prop("disabled", true)
                    ConsultasFecAten.prop("disabled", true)
                }
                
                ConsultascatnivelatenId.selectedIndex = 0
                ConsultasFecAten.val(Temporal.Now.plainDateISO().toString())
            }

            const cambioMedio = () => {
                const medioRecepcion = $("#MediosId").val()
                const ConsultasCP = $("#ConsultasCP")
                const btnCP = $("#btnCP")

                $("#btnCP").find("i").removeClass("fa-trash").addClass("fa-search")

                if ([3, 5, 17].includes(Number(medioRecepcion))) {
                    ConsultasCP.prop("disabled", false)
                    btnCP.prop("disabled", false)
                } else {
                    ConsultasCP.prop("disabled", true)
                    ConsultasCP.val("")
                    btnCP.prop("disabled", true)
                    limpiaCamposCP()
                }
            }

            const cambioProducto = () => {
                const producto = $("#Producto").val()
                const tipoRegistro = $("#tipoRegistro")
                tipoRegistro.prop("disabled", producto === "")

                if (producto === "") return tipoRegistro.find("option:selected").text("Seleccione un producto")
                
                tipoRegistro.find("option:selected").text("Seleccione")
                tipoRegistro.selectedIndex = 0
                filtraCausas()
            }

            const cambioTipoRegistro = () => {
                const tipoRegistro = $("#tipoRegistro").val()
                const CausaId = $("#CausaId")
                CausaId.prop("disabled", tipoRegistro === "")

                if (tipoRegistro === "") return CausaId.find("option:selected").text("Seleccione un tipo de registro");
                
                CausaId.find("option:selected").text("Seleccione")
                CausaId.selectedIndex = 0
                filtraCausas()
            }

            const filtraCausas = () => {
                const producto = $("#Producto").val()
                const tipoRegistro = $("#tipoRegistro").val()
                const causaSelect = $("#CausaId")

                const causasProducto = causas[producto] || []
                const opcionesCausas = causasProducto.map(causa => {
                    if (tipoRegistro == 1 && causa.consulta != 1) return ""
                    if (tipoRegistro == 2 && causa.reclamacion != 1) return ""
                    if (tipoRegistro == 3 && causa.aclaracion != 1) return ""
                    return "<option value=" + causa.valor + ">" + causa.texto + "</option>"
                })
                
                causaSelect.html("<option value='' disabled selected>Seleccione</option>" + opcionesCausas.join(""))

                validaRequeridos()
            }
         
            const validaRequeridos = () => {
                const requeridos = [
                    "#ConsultasTrim",
                    "#ConsultasFolio",
                    "#ConsultasFecRecepcion",
                    "#EstadosId",
                    "#MediosId",
                    "#Producto",
                    "#CausaId",
                    "#ConsultasMpioId",
                    "#ConsultasPori",
                ]

                const noEstatus = $("#ConsultasEstatusCon").val()
                if (noEstatus == 2) {
                    const ConsultasFecAten = Temporal.PlainDate.from($("#ConsultasFecAten").val())
                    const ConsultasFecRecepcion = Temporal.PlainDate.from($("#ConsultasFecRecepcion").val())
                    const comparacion = Temporal.PlainDate.compare(ConsultasFecAten, ConsultasFecRecepcion);

                    if (comparacion < 0) {
                        $("#btnAgregar").prop("disabled", true)
                        showError("La fecha de atención no puede ser anterior a la fecha de reclamación.")
                        return
                    }

                    requeridos.push("#ConsultasFecAten")
                    requeridos.push("#ConsultascatnivelatenId")
                }

                const medioRecepcion = $("#MediosId").val()
                if (mediosConCP.includes(Number(medioRecepcion))) {
                    requeridos.push("#ConsultasCP")
                }

                let validacion = false
            
                requeridos.forEach((requerido) => {
                    const elemento = $(requerido)
                    let valor = ""

                    if (elemento.is("select")) valor = elemento.find("option:selected").val()
                    else valor = elemento.val()
                    
                    if (valor === "") validacion = true
                })
            
                $("#btnAgregar").prop("disabled", validacion)
            }
            
            const registrarQueja = (e) => {
                e.preventDefault()
                const token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpc3MiOiJhcGlydWVuZS1jb25kdXNlZiIsInN1YiI6ImFwaXJldW5lLWNvbmR1c2VmIiwiZ3JvdXBzIjpbImFjY2VzcyJdLCJleHAiOjE3ODE4MDk0NTMzODQsInVpZCI6IjEwMmE5MzQwLWJjZmMtNDY5ZC1hMjZkLWFmZjAzNWNmMjVhNCIsInVzZXJuYW1lIjoiQ3VsdGl2YU9DIiwiaW5zdGl0dWNpb25pZCI6IjRkMTI3ODg1LWVlYjUtNDNhMi05NWFjLWQ4MzVjODIzN2JiZiIsImluc3RpdHVjaW9uQ2xhdmUiOjE1NDk0LCJkZW5vbWluYWNpb25fc29jaWFsIjoiRmluYW5jaWVyYSBDdWx0aXZhLCBTLkEuUC5JLiBkZSBDLlYuLCBTT0ZPTSwgRS5OLlIuIiwic2VjdG9yaWQiOjY5LCJzZWN0b3JpbnRzaXByZXMiOjY5LCJzZWN0b3JpbnRzaW8iOjI0LCJzZWN0b3IiOiJTb2NpZWRhZGVzIEZpbmFuY2llcmFzIGRlIE9iamV0byBNw7psdGlwbGUgRS5OLlIuIiwic2VjdG9yaW50bHljb24iOiIgTEFZT1VUQ09OU1VMMV9nZW5lcmFsIiwic2VjdG9yaW50bHlyZWMiOiJMQVlPVVRSRUNMQU0xX2dlbmVyYWwiLCJzZWN0b3JpbnRseWFjbCI6IkxBWU9VVEFDTEFSMSIsInN5c3RlbSI6IlJFVU5FIiwiaWF0IjoxNzc5MTc5NjUzLCJqdGkiOiI4NzliODg0My03MDQyLTQ4ODMtYjc3MC00Y2ZmN2EzZGIyMWMifQ.Afoq_TP2aFzvDM_XF49HcmivMOM9F2m2JLBg-oSC9I6HqYvckm28TTodWUfZ2peDzSqN70ohNQi7wbv_XuN6QfOJ07Muy1-Zs7iFTOwxpCQSc9CYmWkpfBpu0-ljgakfHUnNg2p0fbBXY7rHM1UYB-_0_mgIRMTcbRE6URaf4kvGx-A0F3dcA7OLH-xHSejD-FpDJLVNDf_OHuCYhLv6A4XC5ydj08N771ubfKURweOfKutO4LdbNu-zaohCZphyjUvXb1YJp1D4PYRoLZHIeqhZwzcrslh6Pt3JJvPNizNU-aMyITWpflWRXz_0WhjQU2_4dwzA-JtcCes3FNsxhA"
                const noEstatus = Number($("#ConsultasEstatusCon").find("option:selected").val())
                const noMedio = Number($("#MediosId").find("option:selected").val())
                const datos = [{
                    InstitucionClave: $("#InstitucionClave").val(),
                    Sector: $("#Sector").val(),
                    ConsultasTrim: Number($("#ConsultasTrim").find("option:selected").val()),
                    NumConsultas: Number($("#NumConsultas").val()),
                    ConsultasFolio: $("#ConsultasFolio").val(),
                    ConsultasEstatusCon: noEstatus,
                    ConsultasFecAten: null,
                    EstadosId: Number($("#EstadosId").find("option:selected").val()),
                    ConsultasFecRecepcion: formatoFecha($("#ConsultasFecRecepcion").val()),
                    MediosId: noMedio,
                    Producto: $("#Producto").find("option:selected").val(),
                    CausaId: $("#CausaId").find("option:selected").val(),
                    ConsultasCP: null,
                    ConsultasMpioId: Number($("#ConsultasMpioId").find("option:selected").val()),
                    ConsultasLocId: null,
                    ConsultasColId: null,
                    ConsultascatnivelatenId: null,
                    ConsultasPori: $("#ConsultasPori").find("option:selected").val(),
                }]
                
                if (noEstatus == 2) {
                    datos[0].ConsultasFecAten = formatoFecha($("#ConsultasFecAten").val())
                    datos[0].ConsultascatnivelatenId = Number($("#ConsultascatnivelatenId").find("option:selected").val())
                }

                if (mediosConCP.includes(noMedio)) {
                    datos[0].ConsultasCP = Number($("#ConsultasCP").val())
                }
                    
                const procesaRespuesta = (respuesta) => {
                    return showSuccess("Queja registrada exitosamente con el folio: " + $("#ConsultasFolio").val())
                        .then(() => location.reload())
                }

                const procesaError = (respuesta) => {
                    let mensaje = "Ocurrieron los siguientes errores:\\n\\n"
                    respuesta.errors[datos[0].ConsultasFolio].forEach((error, i) => {
                        mensaje += (i+1) + ".-" + error + "\\n" 
                    })
                    mensaje += "\\n" + respuesta.message
                    return showError(mensaje)
                }

                consumeAPI("https://api-reune-pruebas.condusef.gob.mx/reune/consultas/general", procesaRespuesta, datos, "json", "post", token, "Ocurrió un error de comunicación con el portal de REUNE.", procesaError)   
            }

            $(document).ready(() => {
                const hoy = Temporal.Now.plainDateISO().toString()
                $("#ConsultasFecRecepcion").val(hoy)
                $("#ConsultasFecRecepcion").attr("max", hoy)
                $("#ConsultasFecAten").val(hoy)
                $("#ConsultasFecAten").attr("max", hoy)
            })
        </script>
        HTML;

        $trimestres = [
            "1" => "Enero - Marzo",
            "2" => "Abril - Junio",
            "3" => "Julio - Septiembre",
            "4" => "Octubre - Diciembre"
        ];

        $opcionesMeses = "";
        foreach ($trimestres as $key => $value) {
            if (intval($key) == ceil(date('m') / 3)) $opcionesMeses .= "<option value='{$key}' selected>{$value}</option>";
            else $opcionesMeses .= "<option value='{$key}'>{$value}</option>";
        }

        $producto_causa = ApiCondusefDao::GetProductos();

        $opcionesProductos = "<option value='' disabled selected>Seleccionar</option>";
        if ($producto_causa['success']) {
            $causas = $producto_causa['datos']['causas'];
            foreach ($producto_causa['datos']['productos'] as $codProd => $nombre) {
                $opcionesProductos .= "<option value='{$codProd}'>{$nombre}</option>";
            }
        }

        View::set('header', $this->_contenedor->header(self::GetExtraHeader("Registrar Quejas REUNE")));
        View::set('footer', $this->_contenedor->footer($extraFooter));
        View::set('meses', $opcionesMeses);
        View::set('productos', $opcionesProductos);
        View::set('causas', $causas);
        View::render("z_api_agregar_quejas_REUNE");
    }
}
