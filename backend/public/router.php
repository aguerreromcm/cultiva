<?php
// Router para php -S: emula el .htaccess y pasa la ruta como ?url=...
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // servir el archivo estático
}
$_GET['url'] = trim((string) $uri, '/');
require __DIR__ . '/index.php';
