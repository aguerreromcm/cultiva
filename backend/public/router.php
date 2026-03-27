<?php
// Router para php -S: emula el .htaccess y pasa la ruta como ?url=...
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // servir el archivo estático
}
$normalized = rtrim((string) $uri, '/');
if (stripos($normalized, '/api/pui') === 0) {
    require __DIR__ . '/api/pui/index.php';
} elseif ($normalized === '/pui/consulta-persona') {
    $_GET['url'] = 'Pui/ConsultaPersona';
    require __DIR__ . '/index.php';
} elseif ($normalized === '/pui/movimientos') {
    $_GET['url'] = 'Pui/Movimientos';
    require __DIR__ . '/index.php';
} else {
    $_GET['url'] = trim((string) $uri, '/');
    require __DIR__ . '/index.php';
}
