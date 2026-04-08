<?php echo $header; ?>
<div class="right_col">
    <div class="col-md-12 col-sm-12 col-xs-12 col-lg-12">
        <div class="panel panel-body">
            <div class="x_title">
                <h3>Reportes / Movimientos (PUI)</h3>
                <div class="clearfix"></div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Estado general</strong></div>
                        <div class="panel-body">
                            <p><strong>Modo:</strong> <span id="modo" class="label label-info">cargando...</span></p>
                            <p><strong>Estado reporte:</strong> <span id="estado" class="label label-default">Sin acciones</span></p>
                        </div>
                    </div>

                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Autenticación</strong></div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label>Usuario</label>
                                <input id="usuario" class="form-control" type="text" value="PUI">
                            </div>
                            <div class="form-group">
                                <label>Clave</label>
                                <input id="clave" class="form-control" type="password">
                            </div>
                            <button id="btnLogin" class="btn btn-primary">Obtener token</button>
                            <button id="btnLogout" class="btn btn-default" type="button">Limpiar token</button>
                            <p id="authMsg" style="margin-top: 10px;"></p>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Formulario de reporte</strong></div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label>ID Reporte</label>
                                <input id="idReporte" class="form-control" type="text" placeholder="UUID o FUB-UUID">
                            </div>
                            <div class="form-group">
                                <label>CURP</label>
                                <input id="curp" class="form-control" type="text" maxlength="18" placeholder="AAAA000000HDFBBB00">
                            </div>
                            <div class="form-group">
                                <label>Lugar de nacimiento</label>
                                <input id="lugarNacimiento" class="form-control" type="text" placeholder="CIUDAD DE MEXICO">
                            </div>
                            <div class="form-group">
                                <label>Nombre</label>
                                <input id="nombre" class="form-control" type="text">
                            </div>
                            <div class="form-group">
                                <label>Primer apellido</label>
                                <input id="primerApellido" class="form-control" type="text">
                            </div>
                            <div class="form-group">
                                <label>Segundo apellido</label>
                                <input id="segundoApellido" class="form-control" type="text">
                            </div>

                            <button id="btnActivar" class="btn btn-success">Activar reporte</button>
                            <button id="btnDesactivar" class="btn btn-danger">Desactivar reporte</button>
                        </div>
                    </div>

                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Logs</strong></div>
                        <div class="panel-body">
                            <pre id="logs" style="background:#101010;color:#b8f5b8;min-height:170px;padding:10px;"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php echo $footer; ?>
<script src="/pui/js/pui-api.js"></script>
<script>
(function () {
    var modoEl = document.getElementById("modo");
    var estadoEl = document.getElementById("estado");
    var authMsgEl = document.getElementById("authMsg");
    var logsEl = document.getElementById("logs");

    function addLog(title, data) {
        var time = new Date().toISOString();
        logsEl.textContent = "[" + time + "] " + title + "\n" + JSON.stringify(data, null, 2) + "\n\n" + logsEl.textContent;
    }

    function curpValida(curp) {
        return /^[A-Z0-9]{18}$/.test(curp);
    }

    function setEstado(text, tipo) {
        estadoEl.textContent = text;
        estadoEl.className = "label " + (tipo || "label-default");
    }

    function getPayloadActivar() {
        return {
            id: document.getElementById("idReporte").value.trim(),
            curp: document.getElementById("curp").value.trim().toUpperCase(),
            lugar_nacimiento: document.getElementById("lugarNacimiento").value.trim(),
            nombre: document.getElementById("nombre").value.trim(),
            primer_apellido: document.getElementById("primerApellido").value.trim(),
            segundo_apellido: document.getElementById("segundoApellido").value.trim()
        };
    }

    async function cargarModo() {
        var res = await PuiApi.getModo();
        modoEl.textContent = res.ok ? (res.data.modo_integracion || "N/D") : "ERROR";
        addLog("GET " + (res.endpoint || "/api/pui/salud"), res);
    }

    document.getElementById("btnLogin").addEventListener("click", async function () {
        var usuario = document.getElementById("usuario").value.trim();
        var clave = document.getElementById("clave").value;
        var res = await PuiApi.login(usuario, clave);
        authMsgEl.textContent = res.ok ? "Token guardado." : ("Error login (" + res.status + ")");
        authMsgEl.style.color = res.ok ? "#1f7a1f" : "#a60000";
        addLog("POST " + (res.endpoint || "/api/pui/login"), res);
    });

    document.getElementById("btnLogout").addEventListener("click", function () {
        PuiApi.clearToken();
        authMsgEl.textContent = "Token eliminado.";
        authMsgEl.style.color = "";
    });

    document.getElementById("btnActivar").addEventListener("click", async function () {
        var payload = getPayloadActivar();
        if (!payload.id) {
            setEstado("ID de reporte requerido", "label-danger");
            return;
        }
        if (!curpValida(payload.curp)) {
            setEstado("CURP inválida", "label-danger");
            return;
        }

        var res = await PuiApi.activarReportePrueba(payload);
        addLog("POST " + (res.endpoint || "/api/pui/activar-reporte-prueba"), { payload: payload, response: res });
        if (res.ok) {
            setEstado("ACTIVO", "label-success");
        } else if (res.status === 401 || res.status === 403) {
            setEstado("No autorizado", "label-danger");
        } else {
            setEstado("Error al activar", "label-danger");
        }
    });

    document.getElementById("btnDesactivar").addEventListener("click", async function () {
        var id = document.getElementById("idReporte").value.trim();
        if (!id) {
            setEstado("ID de reporte requerido", "label-danger");
            return;
        }
        var res = await PuiApi.desactivarReporte(id);
        addLog("POST " + (res.endpoint || "/api/pui/desactivar-reporte"), { payload: { id: id }, response: res });
        if (res.ok) {
            setEstado("CERRADO", "label-success");
        } else if (res.status === 401 || res.status === 403) {
            setEstado("No autorizado", "label-danger");
        } else {
            setEstado("Error al desactivar", "label-danger");
        }
    });

    cargarModo();
})();
</script>
