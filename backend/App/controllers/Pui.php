<?php

namespace App\controllers;

defined("APPPATH") or die("Access denied");

use \Core\View;
use \Core\Controller;

class Pui extends Controller
{
    private $_contenedor;

    public function __construct()
    {
        parent::__construct();
        $this->_contenedor = new Contenedor;
    }

    public function ConsultaPersona()
    {
        View::set('header', $this->_contenedor->header($this->extraHeader('PUI - Consulta de Persona')));
        View::set('footer', $this->_contenedor->footer());
        View::render('pui_consulta_persona');
    }

    public function Movimientos()
    {
        View::set('header', $this->_contenedor->header($this->extraHeader('PUI - Reportes / Movimientos')));
        View::set('footer', $this->_contenedor->footer());
        View::render('pui_movimientos');
    }

    private function extraHeader($title)
    {
        $safe = htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8');
        return '<title>' . $safe . '</title>';
    }
}
