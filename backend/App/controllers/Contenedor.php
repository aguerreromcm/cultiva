<?php

namespace App\controllers;

defined("APPPATH") or die("Access denied");

use \Core\Controller;

require_once dirname(__DIR__) . '../../libs/mpdf/mpdf.php';
require_once dirname(__DIR__) . '../../libs/PhpSpreadsheet/PhpSpreadsheet.php';
require_once dirname(__DIR__) . '../../libs/CDC/SignatureService.php';

class Contenedor extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Definición del menú lateral. Cada sección puede tener:
     * - titulo, icono (clase glyphicon), permisos (array), items (array de {texto, url, permisos opcional})
     * - condicion_extra (opcional): 'herramientas' exige además App::herramientasHabilitado()
     * Si un ítem tiene 'permisos', se valida aparte; si no, se muestra a quien ve la sección.
     * @return array
     */
    private function getMenuDefinition()
    {
        return [
            [
                'titulo'   => 'PLD',
                'icono'    => 'glyphicon-th-list',
                'permisos' => ['AMGM', 'GASC', 'GBNA', 'LMVH', 'FECR', 'MAJL', 'AFJJ', 'LSOC', 'EZJL'],
                'items'    => [
                    ['texto' => 'Reporte Desembolsos', 'url' => '/Operaciones/ReportePLDDesembolsos/'],
                    ['texto' => 'Reporte Pagos', 'url' => '/Operaciones/ReportePLDPagos/'],
                    ['texto' => 'Reporte Pagos Edad', 'url' => '/Operaciones/ReportePLDPagosNacimiento/'],
                    ['texto' => 'Reporte Auditoría', 'url' => '/Operaciones/ReporteAuditoria/'],
                    ['texto' => 'Identificación (Clientes)', 'url' => '/Operaciones/IdentificacionClientes/'],
                    ['texto' => 'Cuentas Relacionadas', 'url' => '/Operaciones/CuentasRelacionadas/'],
                    ['texto' => 'Perfil Transaccional', 'url' => '/Operaciones/PerfilTransaccional/'],
                ],
            ],
            [
                'titulo'   => 'Api Condusef',
                'icono'    => 'glyphicon-globe',
                'permisos' => ['AMGM', 'GASC', 'GBNA', 'LMVH'],
                'items'    => [
                    ['texto' => 'Registrar Quejas REDECO', 'url' => '/ApiCondusef/AddRedeco/'],
                    ['texto' => 'Registrar Quejas REUNE', 'url' => '/ApiCondusef/AddReune/'],
                ],
            ],
            [
                'titulo'   => 'Créditos',
                'icono'    => 'glyphicon-usd',
                'permisos' => ['AMGM', 'PHEE', 'GASC', 'LSOC', 'MAJL', 'AFJJ'],
                'items'    => [
                    ['texto' => 'Reporte de Referencias', 'url' => '/Creditos/ReporteReferencias/'],
                    ['texto' => 'Reporte de Prestamos', 'url' => '/Creditos/ReportePrestamos/'],
                ],
            ],
            [
                'titulo'   => 'Tesorería',
                'icono'    => 'glyphicon-piggy-bank',
                'permisos' => ['AMGM', 'PLMV', 'LGFR', 'MCDP', 'GASC', 'MAJL', 'AFJJ', 'JCMG', 'JACJ', 'MACI'],
                'items'    => [
                    ['texto' => 'Consulta Clientes Solicitudes', 'url' => '/Tesoreria/'],
                    ['texto' => 'Reingresar Clientes a Grupo', 'url' => '/Tesoreria/ReingresarClientesCredito/'],
                ],
            ],
            [
                'titulo'   => 'Circulo de Crédito',
                'icono'    => 'glyphicon-ok-circle',
                'permisos' => ['AMGM', 'GASC', 'LSOC', 'ADMIN', 'AMOCA', 'MAJL', 'AFJJ'],
                'items'    => [
                    ['texto' => 'Consulta por Cliente', 'url' => '/CDC/Consulta', 'permisos' => ['AMGM', 'AMOCA']],
                    ['texto' => 'Mis Consultas', 'url' => '/CDC/ConsultaGrupal', 'permisos' => ['AMGM', 'AMOCA']],
                    ['texto' => 'Consulta por Cliente (Admin)', 'url' => '/CDC/ConsultaAdmin', 'permisos' => ['AMGM', 'GASC', 'LSOC', 'ADMIN']],
                    ['texto' => 'Consulta Global', 'url' => '/CDC/ConsultaGlobal', 'permisos' => ['AMGM', 'GASC', 'LSOC', 'ADMIN']],
                ],
            ],
            [
                'titulo'   => 'Operaciones',
                'icono'    => 'glyphicon-cog',
                'permisos' => ['AMGM', 'GASC', 'LSOC', 'MAJL', 'AFJJ'],
                'items'    => [
                    ['texto' => 'Cambio de Sucursal', 'url' => '/Creditos/CambioSucursal/', 'permisos' => ['ADMIN', 'CAMAG', 'ORHM', 'MAPH']],
                ],
            ],
            [
                'titulo'   => 'Contabilidad',
                'icono'    => 'glyphicon-briefcase',
                'permisos' => ['AMGM', 'GASC', 'LSOC', 'ADMIN', 'AMOCA', 'MAJL', 'AFJJ'],
                'items'    => [
                    ['texto' => 'Consulta por grupo', 'url' => '/contabilidad/ConsultaGrupo', 'permisos' => ['AMGM', 'AMOCA']],
                ],
            ],
            [
                'titulo'         => 'Herramientas',
                'icono'          => 'glyphicon-wrench',
                'permisos'       => ['AMGM', 'GASC', 'LSOC', 'MAJL', 'AFJJ'],
                'condicion_extra'=> 'herramientas',
                'items'          => [
                    ['texto' => 'Rep Dia de Atraso', 'url' => '/Herramientas/RepDiaAtraso/'],
                ],
            ],
        ];
    }

    /**
     * Comprueba si se debe mostrar una sección (permisos + condicion_extra si existe).
     * @param array $seccion
     * @return bool
     */
    private function debeMostrarSeccion(array $seccion)
    {
        if (!$this->ValidaPermiso($seccion['permisos'])) {
            return false;
        }
        if (!empty($seccion['condicion_extra']) && $seccion['condicion_extra'] === 'herramientas') {
            return \Core\App::herramientasHabilitado();
        }
        return true;
    }

    /**
     * Genera el HTML de un ítem de menú hijo (<li><a href="...">texto</a></li>).
     * Si el ítem tiene 'permisos', se valida; si no, se muestra (la sección ya se validó).
     * @param array $item {texto, url, permisos opcional}
     * @return string
     */
    private function renderMenuItem(array $item)
    {
        if (isset($item['permisos']) && !$this->ValidaPermiso($item['permisos'])) {
            return '';
        }
        $url   = htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8');
        $texto = htmlspecialchars($item['texto'], ENT_QUOTES, 'UTF-8');
        return '<li><a href="' . $url . '">' . $texto . '</a></li>';
    }

    /**
     * Genera el HTML de una sección del menú (grupo colapsable con hijos).
     * @param array $seccion
     * @return string
     */
    private function renderMenuSection(array $seccion)
    {
        if (!$this->debeMostrarSeccion($seccion)) {
            return '';
        }
        $partes = [];
        foreach ($seccion['items'] as $item) {
            $partes[] = $this->renderMenuItem($item);
        }
        $hijos = implode('', $partes);
        if ($hijos === '') {
            return '';
        }
        $titulo = htmlspecialchars($seccion['titulo'], ENT_QUOTES, 'UTF-8');
        $icono  = htmlspecialchars($seccion['icono'], ENT_QUOTES, 'UTF-8');
        return <<<HTML
            <ul class="nav side-menu">
                <li><a><i class="glyphicon {$icono}">&nbsp;</i>{$titulo}<span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                        {$hijos}
                    </ul>
                </li>
            </ul>
            HTML;
    }

    /**
     * Construye el HTML completo del menú lateral a partir de getMenuDefinition().
     * @return string
     */
    private function buildMenu()
    {
        $html = '';
        foreach ($this->getMenuDefinition() as $seccion) {
            $html .= $this->renderMenuSection($seccion);
        }
        return $html;
    }

    public function header($extra = '')
    {
        $usuario = $this->__usuario;
        $nombre = $this->__nombre;
        $perfil = $this->__perfil;

        $header = <<<HTML
        <!DOCTYPE html>
        <html lang="es">
            <head>
                <meta http-equiv="Expires" content="0">
                <meta http-equiv="Last-Modified" content="0">
                <meta http-equiv="Cache-Control" content="no-cache, mustrevalidate">
                <meta http-equiv="Pragma" content="no-cache">
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                <meta charset="utf-8">
                
                <link rel="shortcut icon" href="/img/logo_ico.png">
                <link rel="stylesheet" type="text/css" href="/css/nprogress.css">
                <link rel="stylesheet" type="text/css" href="/css/loader.css">
                <link rel="stylesheet" type="text/css" href="/css/tabla/sb-admin-2.css">
                <link rel="stylesheet" type="text/css" href="/css/bootstrap/datatables.bootstrap.css">
                <link rel="stylesheet" type="text/css" href="/css/bootstrap/bootstrap.css">
                <link rel="stylesheet" type="text/css" href="/css/bootstrap/bootstrap-switch.css">
                <link rel="stylesheet" type="text/css" href="/css/validate/screen.css">
                <link rel="stylesheet" type="text/css" href="/css/bootstrap/bootstrap.min.css">
                <link rel="stylesheet" type="text/css" href="/css/font-awesome.min.css">
                <link rel="stylesheet" type="text/css" href="/css/menu/menu5custom.min.css">
                <link rel="stylesheet" type="text/css" href="/css/green.css">
                <link rel="stylesheet" type="text/css" href="/css/custom.min.css">
                $extra 
            </head>
        HTML;

        $menuHtml = $this->buildMenu();

        $menu = <<<HTML
        <body class="nav-md">
            <div class="container body" >
                <div class="main_container" style="background: #ffffff">
                    <div class="col-md-3 left_col">
                        <div class="left_col scroll-view">
                            <div class="navbar nav_title">
                                <a href="/Principal/" class="site_title" style="display: flex; align-items: center; justify-content: center; padding: 0; margin: 0;">
                                    <img src="/img/logo_ico.png" alt="Inicio" width="50px" id="ico_home" style="display: none;">
                                    <img src="/img/logo_nombre.png" alt="Login" width="210px" id="img_home">
                                </a>
                            </div>
                            <div class="clearfix"></div>
                            <div class="profile clearfix">
                                <div class="profile_pic">
                                    <img src="/img/profile_default.jpg" alt="..." class="img-circle profile_img">
                                </div>
                                <div class="profile_info">
                                    <span><b>USUARIO:</b> {$usuario}</span>
                                    <br>
                                    <span><b>PERFIL:</b> <span class="fa fa-key"></span> {$perfil}</span>
                                </div>
                            </div>
                            <hr>
                            <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
                                <div class="menu_section">
                                    <h3>GENERAL </h3>
                                    {$menuHtml}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="top_nav ">
                        <div class="nav_menu">
                            <nav>
                                <div class="nav toggle">
                                    <a id="menu_toggle"><i class="fa fa-bars"></i></a>
                                </div>
                                <ul class="nav navbar-nav navbar-right">
                                    <li class="">
                                        <a href="" class="user-profile dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                            <span class=" fa fa-user"></span> {$nombre}
                                            <span class=" fa fa-angle-down"></span>
                                        </a>
                                        <ul class="dropdown-menu dropdown-usermenu pull-right">
                                            <li><a href="/Login/cerrarSession">
                                                <i class="fa fa-sign-out pull-right"></i>Cerrar Sesión</a>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
        HTML;
        return $header . $menu;
    }

    public function footer($extra = '')
    {
        $footer = <<<HTML
                </div>
                <script src="/js/jquery.min.js"></script>
                <script src="/js/moment/moment.min.js"></script>
                <script src="/js/bootstrap.min.js"></script>
                <script src="/js/bootstrap/bootstrap-switch.js"></script>
                <script src="/js/nprogress.js"></script>
                <script src="/js/custom.min.js"></script>
                <script src="/js/validate/jquery.validate.js"></script>
                <script src="/js/tabla/jquery.dataTables.min.js"></script>
                <script src="/js/tabla/dataTables.bootstrap.min.js"></script>
                <script src="/js/tabla/jquery.tablesorter.js"></script>
                <script src="//cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js" ></script>
                <script src="//cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.32/pdfmake.min.js" ></script>
                <script src="//cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.32/vfs_fonts.js" ></script>
                <script src="//cdn.datatables.net/buttons/1.4.2/js/buttons.html5.min.js" ></script>
                <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
                <script src="https://cdn.datatables.net/buttons/1.4.2/js/dataTables.buttons.min.js" ></script>
                $extra
            </body>
        </html>
        HTML;

        return $footer;
    }

    public function ValidaPermiso($permisos)
    {
        return in_array($this->__perfil, $permisos) || in_array($this->__usuario, $permisos);
    }
}
