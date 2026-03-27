<?php echo $header; ?>
<div class="right_col">
    <div class="col-md-12 col-sm-12 col-xs-12 col-lg-12">
        <div class="panel panel-body">
            <div class="x_title">
                <h3>Consulta de Persona (PUI)</h3>
                <div class="clearfix"></div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Modo</strong></div>
                        <div class="panel-body">
                            <span id="modo" class="label label-info">cargando...</span>
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
                        <div class="panel-heading"><strong>Consulta por CURP</strong></div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label>CURP (18 caracteres)</label>
                                <input id="curp" class="form-control" type="text" maxlength="18" placeholder="AAAA000000HDFBBB00">
                            </div>
                            <button id="btnConsultar" class="btn btn-success"><i class="fa fa-search"></i> Consultar</button>
                            <hr>
                            <div id="resultado"></div>
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
    var logsEl = document.getElementById("logs");
    var authMsgEl = document.getElementById("authMsg");
    var resultadoEl = document.getElementById("resultado");

    function addLog(title, data) {
        var time = new Date().toISOString();
        var chunk = "[" + time + "] " + title + "\n" + JSON.stringify(data, null, 2) + "\n\n";
        logsEl.textContent = chunk + logsEl.textContent;
    }

    function curpValida(curp) {
        return /^[A-Z0-9]{18}$/.test(curp);
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
        authMsgEl.textContent = res.ok ? "Token guardado en localStorage." : ("Error login (" + res.status + ")");
        authMsgEl.style.color = res.ok ? "#1f7a1f" : "#a60000";
        addLog("POST " + (res.endpoint || "/api/pui/login"), res);
    });

    document.getElementById("btnLogout").addEventListener("click", function () {
        PuiApi.clearToken();
        authMsgEl.textContent = "Token eliminado.";
        authMsgEl.style.color = "";
    });

    document.getElementById("btnConsultar").addEventListener("click", async function () {
        var curp = document.getElementById("curp").value.trim().toUpperCase();
        if (!curpValida(curp)) {
            resultadoEl.innerHTML = '<p class="text-danger">CURP inválida. Debe tener 18 caracteres alfanuméricos.</p>';
            return;
        }

        var res = await PuiApi.consultarPersona(curp);
        addLog("GET " + (res.endpoint || ("/api/pui/persona/" + curp)), res);
        if (!res.ok) {
            resultadoEl.innerHTML = '<p class="text-danger">Error ' + res.status + ': ' + ((res.data && res.data.error && res.data.error.mensaje) || "No se pudo consultar.") + '</p>';
            return;
        }

        var p = (res.data && res.data.persona) || {};
        var d = p.domicilio || {};
        resultadoEl.innerHTML = ''
            + '<p><strong>Nombre completo:</strong> ' + (p.nombre_completo || '-') + '</p>'
            + '<p><strong>CURP:</strong> ' + (p.curp || '-') + '</p>'
            + '<p><strong>RFC:</strong> ' + (p.rfc || '-') + '</p>'
            + '<p><strong>Fecha nacimiento:</strong> ' + (p.fecha_nacimiento || '-') + '</p>'
            + '<p><strong>Domicilio:</strong> ' + (d.direccion || '-') + ' | CP: ' + (d.codigo_postal || '-') + ' | Estado: ' + (d.estado || '-') + '</p>';
    });

    cargarModo();
})();
</script>
