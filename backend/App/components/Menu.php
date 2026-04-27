<?php

namespace App\components;

/**
 * Clase Menu
 *
 * Componente del menú del sistema (Cultiva). La definición de accesos se organiza por
 * secciones y opciones; cada opción puede ser un enlace o un submenú con items.
 * Estructura de opción enlace: titulo, url['directorio','permisos'], icono (opcional).
 * Estructura de opción submenú: titulo, icono, items[] (cada item como enlace).
 * Réplica de la restructuración del componente Menu de MCM.
 */
class Menu
{
    /** @var string Perfil del usuario */
    private $perfil;

    /** @var string Usuario */
    private $usuario;

    /** @var bool Mostrar menú Herramientas (según config) */
    private $mostrarHerramientas;

    public function __construct($_perfil, $_usuario, $mostrarHerramientas = false)
    {
        $this->perfil = $_perfil;
        $this->usuario = $_usuario;
        $this->mostrarHerramientas = (bool) $mostrarHerramientas;
    }

    /**
     * Devuelve la estructura completa del menú (secciones con opciones).
     */
    private function obtenerEstructuraMenu()
    {
        return [
            $this->seccionGeneral(),
        ];
    }

    /**
     * Helper: define un ítem de menú que es un enlace.
     *
     * @param string $titulo
     * @param string $ruta   URL (ej. /Operaciones/ReportePLDDesembolsos/)
     * @param array  $permisos
     * @param string|null $icono Clase CSS del icono (opcional)
     * @return array
     */
    private function enlace($titulo, $ruta, array $permisos, $icono = null)
    {
        $item = [
            'titulo' => $titulo,
            'url'    => ['directorio' => $ruta, 'permisos' => $permisos],
        ];
        if ($icono !== null) {
            $item['icono'] = $icono;
        }
        return $item;
    }

    /**
     * Helper: define un ítem de menú que es un submenú (desplegable).
     *
     * @param string $titulo
     * @param string $icono  Clase CSS del icono
     * @param array  $items  Lista de ítems (enlaces)
     * @return array
     */
    private function submenu($titulo, $icono, array $items)
    {
        return [
            'titulo' => $titulo,
            'icono'  => $icono,
            'items'  => $items,
        ];
    }

    /** Opciones del submenú PLD */
    private function opcionesPLD()
    {
        $permisos = ['AMGM', 'GASC', 'GBNA', 'LMVH', 'FECR', 'MAJL', 'AFJJ', 'LSOC', 'EZJL'];
        return [
            $this->enlace('Reporte Desembolsos', '/Operaciones/ReportePLDDesembolsos/', $permisos),
            $this->enlace('Reporte Pagos', '/Operaciones/ReportePLDPagos/', $permisos),
            $this->enlace('Reporte Pagos Edad', '/Operaciones/ReportePLDPagosNacimiento/', $permisos),
            $this->enlace('Reporte Auditoría', '/Operaciones/ReporteAuditoria/', $permisos),
            $this->enlace('Identificación (Clientes)', '/Operaciones/IdentificacionClientes/', $permisos),
            $this->enlace('Cuentas Relacionadas', '/Operaciones/CuentasRelacionadas/', $permisos),
            $this->enlace('Perfil Transaccional', '/Operaciones/PerfilTransaccional/', $permisos),
        ];
    }

    /** Opciones del submenú Api Condusef */
    private function opcionesApiCondusef()
    {
        $permisos = ['AMGM', 'GASC', 'GBNA', 'LMVH'];
        return [
            $this->enlace('Registrar Quejas REDECO', '/ApiCondusef/AddRedeco/', $permisos),
            $this->enlace('Registrar Quejas REUNE', '/ApiCondusef/AddReune/', $permisos),
        ];
    }

    /** Opciones del submenú Créditos */
    private function opcionesCreditos()
    {
        $permisos = ['AMGM', 'PHEE', 'GASC', 'LSOC', 'MAJL', 'AFJJ'];
        return [
            $this->enlace('Reporte de Referencias', '/Creditos/ReporteReferencias/', $permisos),
            $this->enlace('Reporte de Prestamos', '/Creditos/ReportePrestamos/', $permisos),
        ];
    }

    /** Opciones del submenú Tesorería */
    private function opcionesTesoreria()
    {
        $permisos = ['AMGM', 'PLMV', 'LGFR', 'MCDP', 'GASC', 'MAJL', 'AFJJ', 'JCMG', 'JACJ', 'MACI', 'GOCD'];
        return [
            $this->enlace('Consulta Clientes Solicitudes', '/Tesoreria/', $permisos),
            $this->enlace('Reingresar Clientes a Grupo', '/Tesoreria/ReingresarClientesCredito/', $permisos),
        ];
    }

    /** Opciones del submenú Círculo de Crédito */
    private function opcionesCirculoCredito()
    {
        return [
            $this->enlace('Consulta por Cliente', '/CDC/Consulta', ['AMGM', 'AMOCA']),
            $this->enlace('Mis Consultas', '/CDC/ConsultaGrupal', ['AMGM', 'AMOCA']),
            $this->enlace('Consulta por Cliente (Admin)', '/CDC/ConsultaAdmin', ['AMGM', 'GASC', 'LSOC', 'ADMIN']),
            $this->enlace('Consulta Global', '/CDC/ConsultaGlobal', ['AMGM', 'GASC', 'LSOC', 'ADMIN']),
        ];
    }

    /** Opciones del submenú Operaciones */
    private function opcionesOperaciones()
    {
        return [
            $this->enlace('Cambio de Sucursal', '/Creditos/CambioSucursal/', ['ADMIN', 'CAMAG', 'ORHM', 'MAPH']),
        ];
    }

    /** Opciones del submenú Contabilidad */
    private function opcionesContabilidad()
    {
        return [
            $this->enlace('Consulta por grupo', '/contabilidad/ConsultaGrupo', ['AMGM', 'AMOCA']),
            $this->enlace('Reporte GL', '/contabilidad/ReporteGL', ['AMGM', 'AMOCA', 'LSOC', 'BGJF']),
        ];
    }

    /** Opciones del submenú Herramientas (visibilidad según config) */
    private function opcionesHerramientas()
    {
        $permisos = ['AMGM'];
        return [
            $this->enlace('Rep Dia de Atraso', '/Herramientas/RepDiaAtraso/', $permisos),
            $this->enlace('Auditoría Devengo', '/Herramientas/AuditoriaDevengo/', $permisos),
            $this->enlace('Estatus BD', '/Herramientas/EstatusBD/', $permisos),
        ];
    }

    /** Sección: GENERAL (todas las opciones principales) */
    private function seccionGeneral()
    {
        $opciones = [
            $this->submenu('PLD', 'glyphicon glyphicon-th-list', $this->opcionesPLD()),
            $this->submenu('Api Condusef', 'glyphicon glyphicon-globe', $this->opcionesApiCondusef()),
            $this->submenu('Créditos', 'glyphicon glyphicon-usd', $this->opcionesCreditos()),
            $this->submenu('Tesorería', 'glyphicon glyphicon-piggy-bank', $this->opcionesTesoreria()),
            $this->submenu('Circulo de Crédito', 'glyphicon glyphicon-ok-circle', $this->opcionesCirculoCredito()),
            $this->submenu('Operaciones', 'glyphicon glyphicon-cog', $this->opcionesOperaciones()),
            $this->submenu('Contabilidad', 'glyphicon glyphicon-briefcase', $this->opcionesContabilidad()),
        ];

        if ($this->mostrarHerramientas) {
            $opciones[] = $this->submenu('Herramientas', 'glyphicon glyphicon-wrench', $this->opcionesHerramientas());
        }

        return [
            'seccion'  => 'GENERAL',
            'opciones' => $opciones,
        ];
    }

    public function mostrar()
    {
        $menu = $this->obtenerEstructuraMenu();
        $html = <<<HTML
            <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
                <div class="menu_section" style="overflow: auto">
        HTML;

        foreach ($menu as $seccion) {
            $html .= $this->mostrarSeccion($seccion);
        }

        $html .= <<<HTML
                </div>
            </div>
        HTML;

        return $html;
    }

    private function mostrarSeccion($seccion)
    {
        $html = '';
        foreach ($seccion['opciones'] as $opcion) {
            $html .= $this->mostrarOpcion($opcion);
        }

        if ($html !== '') {
            $tituloSeccion = htmlspecialchars($seccion['seccion'], ENT_QUOTES, 'UTF-8');
            return <<<HTML
                <hr>
                <h3>{$tituloSeccion}</h3>
                <ul class="nav side-menu">
                    {$html}
                </ul>
            HTML;
        }
        return '';
    }

    private function mostrarOpcion($opcion)
    {
        if (isset($opcion['items'])) {
            $html = '';
            foreach ($opcion['items'] as $item) {
                $html .= $this->mostrarOpcion($item);
            }

            if ($html !== '') {
                $titulo = htmlspecialchars($opcion['titulo'], ENT_QUOTES, 'UTF-8');
                $icono  = htmlspecialchars($opcion['icono'], ENT_QUOTES, 'UTF-8');
                $activeParent = strpos($html, 'class="active"') !== false ? ' class="active"' : '';
                return <<<HTML
                    <li{$activeParent}>
                        <a>
                            <i class="{$icono}"></i>&nbsp;{$titulo}<span class="fa fa-chevron-down"></span>
                        </a>
                        <ul class="nav child_menu">
                            {$html}
                        </ul>
                    </li>
                HTML;
            }
            return '';
        }

        if ($this->ValidaPermisos($opcion['url']['permisos'])) {
            $titulo = htmlspecialchars($opcion['titulo'], ENT_QUOTES, 'UTF-8');
            $url    = htmlspecialchars($opcion['url']['directorio'], ENT_QUOTES, 'UTF-8');
            $icono  = isset($opcion['icono']) ? '<i class="' . htmlspecialchars($opcion['icono'], ENT_QUOTES, 'UTF-8') . '"></i>&nbsp;' : '';
            $active = $this->esRutaActiva($opcion['url']['directorio']) ? ' class="active"' : '';
            return <<<HTML
                <li{$active}>
                    <a href="{$url}">
                        {$icono}{$titulo}
                    </a>
                </li>
            HTML;
        }
        return '';
    }

    private function ValidaPermisos($permisos)
    {
        return in_array($this->perfil, $permisos) || in_array($this->usuario, $permisos);
    }

    private function esRutaActiva($ruta)
    {
        $actual = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $normalActual = '/' . trim($actual, '/');
        $normalRuta = '/' . trim((string) $ruta, '/');

        return strcasecmp($normalActual, $normalRuta) === 0 || stripos($normalActual . '/', $normalRuta . '/') === 0;
    }
}
