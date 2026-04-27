<?php echo $header; ?>
<div class="right_col">
    <div class="col-md-12 col-sm-12 col-xs-12 col-lg-12">
        <div class="panel panel-body">
            <div class="x_title">
                <h3>Estatus de Base de Datos</h3>
                <div class="clearfix"></div>
            </div>

            <div class="card col-md-12 estatus-page">
                <div class="estatus-toolbar">
                    <button id="btn-actualizar" type="button" class="btn btn-primary btn-circle">
                        <i class="fa fa-refresh"></i> <b>Actualizar</b>
                    </button>
                    <span id="estatus-cargando" class="estatus-cargando" style="display: none;">
                        <i class="fa fa-spinner fa-spin"></i> Consultando...
                    </span>
                    <span id="estatus-actualizado" class="estatus-actualizado-badge" title="Hora de la última consulta en servidor"></span>
                </div>

                <hr class="estatus-hr" />

                <div class="estatus-grid">
                    <!-- DB_CULTIVA -->
                    <div id="cultiva-card" class="estatus-card border-warn">
                        <div class="estatus-card-kpi">
                            <span class="estatus-card-titulo">DB_CULTIVA</span>
                            <div class="estatus-kpi-main" id="cultiva-kpi-wrap" aria-live="polite">
                                <span class="estatus-kpi-ico" id="cultiva-archive-ico" aria-hidden="true"></span>
                                <span id="cultiva-archive-status" class="estatus-kpi-text">--</span>
                            </div>
                        </div>

                        <div class="estatus-block">
                            <div class="estatus-block-titulo">Archive destination <span class="estatus-hint">(dest_id = 2)</span></div>
                            <div id="cultiva-archive-error" class="estatus-alert" style="display: none;" role="alert"></div>
                        </div>

                        <div class="estatus-block estatus-block-almacen">
                            <div class="estatus-block-titulo">Recovery file destination</div>
                            <div id="cultiva-rec-name" class="estatus-rec-nombre" title="Ruta de destino">--</div>
                            <div id="cultiva-rec-metricas" class="estatus-metricas-wrap" title="Límite, usado y espacio reutilizable">--</div>
                            <div id="cultiva-rec-error" class="estatus-alert" style="display: none;" role="alert"></div>
                            <div class="estatus-bar-row">
                                <div class="estatus-bar-wrap" title="Porcentaje usado del límite">
                                    <div id="cultiva-rec-bar" class="estatus-bar estatus-warn" style="width: 0%;"></div>
                                </div>
                                <span id="cultiva-rec-pct" class="estatus-bar-pct-label">--</span>
                            </div>
                        </div>
                    </div>

                    <!-- DB_MCM -->
                    <div id="mcm-card" class="estatus-card border-warn">
                        <div class="estatus-card-kpi">
                            <span class="estatus-card-titulo">DB_MCM</span>
                            <div class="estatus-kpi-main" id="mcm-kpi-wrap">
                                <span class="estatus-kpi-ico" id="mcm-archive-ico" aria-hidden="true"></span>
                                <span id="mcm-archive-status" class="estatus-kpi-text">--</span>
                            </div>
                        </div>

                        <div class="estatus-block">
                            <div class="estatus-block-titulo">Archive destination <span class="estatus-hint">(dest_id = 2)</span></div>
                            <div id="mcm-archive-error" class="estatus-alert" style="display: none;" role="alert"></div>
                        </div>

                        <div class="estatus-block estatus-block-almacen">
                            <div class="estatus-block-titulo">Recovery file destination</div>
                            <div id="mcm-rec-name" class="estatus-rec-nombre" title="Ruta de destino">--</div>
                            <div id="mcm-rec-metricas" class="estatus-metricas-wrap" title="Límite, usado y espacio reutilizable">--</div>
                            <div id="mcm-rec-error" class="estatus-alert" style="display: none;" role="alert"></div>
                            <div class="estatus-bar-row">
                                <div class="estatus-bar-wrap" title="Porcentaje usado del límite">
                                    <div id="mcm-rec-bar" class="estatus-bar estatus-warn" style="width: 0%;"></div>
                                </div>
                                <span id="mcm-rec-pct" class="estatus-bar-pct-label">--</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="estatus-leyenda" aria-label="Leyenda de colores de uso de almacenamiento">
                    <span class="estatus-leyenda-item"><span class="estatus-leyenda-cuadro estatus-ok"></span> Uso &lt; 85&nbsp;%</span>
                    <span class="estatus-leyenda-item"><span class="estatus-leyenda-cuadro estatus-warn"></span> 85–90&nbsp;%</span>
                    <span class="estatus-leyenda-item"><span class="estatus-leyenda-cuadro estatus-error"></span> &gt; 90&nbsp;%</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* —— Toolbar y timestamp —— */
.estatus-page .estatus-toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px 16px;
    margin-bottom: 6px;
}
.estatus-cargando { color: #555; font-size: 13px; }
.estatus-actualizado-badge {
    display: inline-block;
    margin-left: auto;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 6px;
    color: #3d4a5c;
    background: #eef1f4;
    border: 1px solid #d8dee4;
    letter-spacing: 0.02em;
    min-height: 30px;
    line-height: 1.4;
    max-width: 100%;
    text-align: right;
    word-break: break-all;
}
.estatus-actualizado-badge:empty { display: none; }
.estatus-actualizado-badge.is-live { font-weight: 700; border-color: #c5ccd3; }
.estatus-hr { border-top: 1px solid #787878; margin: 10px 0 4px; }

/* —— Grid y altura de cards —— */
.estatus-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 18px;
    margin-top: 14px;
    align-items: stretch;
}
.estatus-card {
    display: flex;
    flex-direction: column;
    min-height: 300px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    border-left: 6px solid #f0ad4e;
    padding: 0;
    transition: border-color 0.25s ease, box-shadow 0.2s ease;
    overflow: hidden;
}
.estatus-card.border-ok     { border-left-color: #28a745; }
.estatus-card.border-warn   { border-left-color: #f0ad4e; }
.estatus-card.border-error  { border-left-color: #dc3545; }

/* —— KPI: estado primero (VALID / …) —— */
.estatus-card-kpi {
    padding: 16px 18px 12px;
    background: linear-gradient(180deg, rgba(0,0,0,0.02) 0%, transparent 100%);
    border-bottom: 1px solid #eee;
}
.estatus-kpi-main {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 48px;
    margin-top: 6px;
}
.estatus-kpi-ico { font-size: 28px; line-height: 1; user-select: none; flex-shrink: 0; }
.estatus-kpi-text {
    font-size: 20px;
    font-weight: 800;
    letter-spacing: 0.04em;
    line-height: 1.1;
    text-align: center;
    word-break: break-word;
    text-transform: uppercase;
    font-family: "Segoe UI", system-ui, sans-serif;
}
.estatus-kpi-main.estatus-ok  .estatus-kpi-ico   { color: #198754; }
.estatus-kpi-main.estatus-ok  .estatus-kpi-text { color: #14532d; }
.estatus-kpi-main.estatus-warn .estatus-kpi-ico,
.estatus-kpi-main.estatus-warn .estatus-kpi-text { color: #b45309; }
.estatus-kpi-main.estatus-error .estatus-kpi-ico,
.estatus-kpi-main.estatus-error .estatus-kpi-text { color: #a71d2a; }

/* Badge legacy no usada en header principal; mantiene compat si añadimos clases al wrap */
.estatus-kpi-main .estatus-badge { display: none; }

/* —— Título DB —— */
.estatus-card-titulo {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin: 0;
    text-align: center;
}
.estatus-block { padding: 0 16px; margin-top: 0; }
.estatus-block + .estatus-block { border-top: 1px solid #eee; }
.estatus-block-titulo {
    font-size: 11px;
    font-weight: 600;
    color: #5a6a7a;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 10px 0 6px;
}
.estatus-hint { font-weight: 500; text-transform: none; color: #8b99a5; }
.estatus-block-almacen { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.estatus-rec-nombre {
    font-size: 12px;
    color: #495057;
    line-height: 1.35;
    word-break: break-all;
    margin-bottom: 6px;
    max-height: 2.7em;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

/* —— Almacenamiento: bloque de 3 métricas (misma data, lectura más rápida) —— */
.estatus-metricas-wrap { margin-bottom: 10px; }
.estatus-metricas-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: stretch;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    background: linear-gradient(180deg, #fafbfc 0%, #f1f5f9 100%);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
}
.estatus-metrica {
    padding: 10px 8px 12px;
    text-align: center;
    border-left: 1px solid #e2e8f0;
    min-width: 0;
}
.estatus-metrica:first-child { border-left: 0; }
.estatus-metrica-lbl {
    display: block;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    margin-bottom: 5px;
    line-height: 1.2;
}
.estatus-metrica-val {
    display: block;
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    line-height: 1.25;
    word-break: break-word;
}
.estatus-metrica-val--muted { color: #94a3b8; font-weight: 600; }
.estatus-metricas-grid--error {
    opacity: 0.88;
    border-style: dashed;
    border-color: #cbd5e1;
    background: #f1f5f9;
}
@media (max-width: 420px) {
    .estatus-metrica { padding: 8px 4px 10px; }
    .estatus-metrica-lbl { font-size: 9px; }
    .estatus-metrica-val { font-size: 14px; }
}

/* —— Alertas compactas (ORA / error fila) —— */
.estatus-alert {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 11.5px;
    line-height: 1.35;
    margin: 0 0 8px;
    max-height: 3.1em;
    padding: 6px 8px 6px 6px;
    border-radius: 6px;
    word-break: break-word;
    overflow: hidden;
    color: #842029;
    background: #fdecea;
    border: 1px solid #f0b4b0;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.02);
}
.estatus-alert::before { content: "\26a0\fe0f"; font-size: 12px; line-height: 1.25; flex-shrink: 0; }
.estatus-alert.estatus-alert--sql::before { content: "\274C\fe0f"; font-size: 11px; }
.estatus-alert .estatus-alert-cuerpo { flex: 1; min-width: 0; }
.estatus-alert[style*="display: none"] { display: none !important; }

/* —— Barra + % al lado (colores: &lt;85 / 85–90 / &gt;90) —— */
.estatus-bar-row { display: flex; align-items: center; gap: 10px; margin: 4px 0 14px; }
.estatus-bar-wrap {
    flex: 1;
    min-width: 0;
    height: 12px;
    background: #e9ecef;
    border-radius: 6px;
    overflow: hidden;
}
.estatus-bar {
    height: 100%;
    border-radius: 6px;
    transition: width 0.45s ease, background-color 0.25s ease;
}
.estatus-bar.estatus-ok    { background: #28a745; }
.estatus-bar.estatus-warn  { background: #e9a00d; }
.estatus-bar.estatus-error { background: #dc3545; }
.estatus-bar-pct-label {
    flex-shrink: 0;
    min-width: 3.2em;
    text-align: right;
    font-size: 14px;
    font-weight: 800;
    color: #1a1a1a;
    font-variant-numeric: tabular-nums;
}

/* —— Leyenda —— */
.estatus-leyenda { margin: 16px 0 6px; display: flex; flex-wrap: wrap; gap: 12px 20px; font-size: 11px; color: #6b7280; }
.estatus-leyenda-item { display: inline-flex; align-items: center; gap: 6px; }
.estatus-leyenda-cuadro { width: 12px; height: 12px; border-radius: 2px; display: inline-block; flex-shrink: 0; }
.estatus-leyenda-cuadro.estatus-ok    { background: #28a745; }
.estatus-leyenda-cuadro.estatus-warn  { background: #e9a00d; }
.estatus-leyenda-cuadro.estatus-error { background: #dc3545; }

/* —— Modo oscuro (prefers-color-scheme) —— */
@media (prefers-color-scheme: dark) {
    .estatus-cargando { color: #8b949e; }
    .estatus-actualizado-badge {
        color: #c9d1d9;
        background: #1f242b;
        border-color: #3d444d;
    }
    .estatus-actualizado-badge.is-live { border-color: #8b949e; color: #e6edf3; }
    .estatus-hr { border-color: #3d444d; }
    .estatus-card { background: #2d333b; box-shadow: 0 1px 4px rgba(0,0,0,0.4); }
    .estatus-card-kpi { background: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, transparent 100%); border-bottom-color: #3d444d; }
    .estatus-block + .estatus-block { border-color: #3d444d; }
    .estatus-card-titulo { color: #8b949e; }
    .estatus-block-titulo { color: #9ca8b3; }
    .estatus-hint { color: #6e7a86; }
    .estatus-rec-nombre { color: #c9d1d9; }
    .estatus-metricas-grid {
        border-color: #3d444d;
        background: linear-gradient(180deg, #2a3038 0%, #232a32 100%);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
    }
    .estatus-metrica { border-color: #3d444d; }
    .estatus-metrica-lbl { color: #8b949e; }
    .estatus-metrica-val { color: #f0f6fc; }
    .estatus-metrica-val--muted { color: #6e7a86; }
    .estatus-metricas-grid--error {
        border-color: #4a5568;
        background: #1a1f25;
    }
    .estatus-bar-wrap { background: #1f2428; }
    .estatus-bar-pct-label { color: #f0f6fc; }
    .estatus-leyenda { color: #8b949e; }
    .estatus-kpi-main.estatus-ok  .estatus-kpi-text   { color: #7ee2a0; }
    .estatus-kpi-main.estatus-ok  .estatus-kpi-ico   { color: #3dd67e; }
    .estatus-kpi-main.estatus-warn .estatus-kpi-text,
    .estatus-kpi-main.estatus-warn .estatus-kpi-ico  { color: #e9a00d; }
    .estatus-kpi-main.estatus-error .estatus-kpi-text,
    .estatus-kpi-main.estatus-error .estatus-kpi-ico { color: #f85149; }
    .estatus-alert { color: #ffc9c0; background: #3a1c1c; border-color: #5a2a2a; }
}

@media (max-width: 420px) {
    .estatus-metrica-val { font-size: 13px; }
}
</style>

<?php echo $footer; ?>
