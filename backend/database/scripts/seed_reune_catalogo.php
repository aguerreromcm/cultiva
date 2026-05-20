<?php
/**
 * Carga del catálogo REUNE desde CatalogoDeProductosCausasREUNE.xls
 * Uso: php backend/database/scripts/seed_reune_catalogo.php
 */

define('PROJECTPATH', dirname(__DIR__, 2));
define('APPPATH', PROJECTPATH . '/App');

spl_autoload_register(function ($class_name) {
    $filename = PROJECTPATH . '/' . str_replace('\\', '/', $class_name) . '.php';
    if (is_file($filename)) {
        include_once $filename;
    }
});

use Core\Database;

function limpiarCodigo(string $valor): string
{
    return ltrim(trim($valor), "'");
}

function flagSi(string $valor): int
{
    return strtoupper(trim($valor)) === 'SI' ? 1 : 0;
}

function parsearCatalogo(string $ruta): array
{
    $html = file_get_contents($ruta);
    if ($html === false) {
        throw new RuntimeException("No se pudo leer el catálogo: $ruta");
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);

    $productos = [];
    $causas = [];

    foreach ($dom->getElementsByTagName('tr') as $row) {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length < 9) {
            continue;
        }

        $vals = [];
        for ($i = 0; $i < 9; $i++) {
            $vals[] = trim(preg_replace('/\s+/', ' ', $cells->item($i)->textContent));
        }

        if ($vals[4] === '' || stripos($vals[4], 'Código') !== false) {
            continue;
        }

        $codigoProducto = limpiarCodigo($vals[4]);
        $codigoCausa = limpiarCodigo($vals[5]);

        $productos[$codigoProducto] = [
            'categoria' => $vals[0],
            'nombre' => $vals[1],
            'nombre_corto' => $vals[2],
            'codigo_producto' => $codigoProducto,
        ];

        $causas[] = [
            'codigo_producto' => $codigoProducto,
            'descripcion' => $vals[3],
            'codigo_causa' => $codigoCausa,
            'consulta' => flagSi($vals[6]),
            'reclamacion' => flagSi($vals[7]),
            'aclaracion' => flagSi($vals[8]),
        ];
    }

    return [$productos, $causas];
}

$rutaCatalogo = dirname(PROJECTPATH) . '/CatalogoDeProductosCausasREUNE.xls';
[$productos, $causas] = parsearCatalogo($rutaCatalogo);

$db = new Database();
if ($db->db_activa === null) {
    fwrite(STDERR, "Error: base de datos no disponible.\n");
    exit(1);
}

$insertProducto = <<<'SQL'
    INSERT INTO REUNE_PRODUCTOS (CATEGORIA, NOMBRE, NOMBRE_CORTO, CODIGO_PRODUCTO, ACTIVO)
    SELECT :categoria, :nombre, :nombre_corto, :codigo_producto, 1 FROM DUAL
    WHERE NOT EXISTS (
        SELECT 1 FROM REUNE_PRODUCTOS WHERE CODIGO_PRODUCTO = :codigo_producto_chk
    )
SQL;

$updateProducto = <<<'SQL'
    UPDATE REUNE_PRODUCTOS SET
        CATEGORIA = :categoria,
        NOMBRE = :nombre,
        NOMBRE_CORTO = :nombre_corto,
        ACTIVO = 1
    WHERE CODIGO_PRODUCTO = :codigo_producto
SQL;

$insertadosProd = 0;
$actualizadosProd = 0;

foreach ($productos as $producto) {
    $params = [
        'categoria' => $producto['categoria'],
        'nombre' => $producto['nombre'],
        'nombre_corto' => $producto['nombre_corto'],
        'codigo_producto' => $producto['codigo_producto'],
        'codigo_producto_chk' => $producto['codigo_producto'],
    ];

    if ($db->insert($insertProducto, $params)) {
        $insertadosProd++;
    }

    if ($db->insert($updateProducto, [
        'categoria' => $producto['categoria'],
        'nombre' => $producto['nombre'],
        'nombre_corto' => $producto['nombre_corto'],
        'codigo_producto' => $producto['codigo_producto'],
    ])) {
        $actualizadosProd++;
    }
}

$mapaProductos = [];
$rows = $db->queryAll('SELECT ID, CODIGO_PRODUCTO FROM REUNE_PRODUCTOS');
foreach ($rows as $row) {
    $mapaProductos[$row['CODIGO_PRODUCTO']] = (int) $row['ID'];
}

$insertCausa = <<<'SQL'
    INSERT INTO REUNE_CAUSAS (
        PRODUCTO_ID, DESCRIPCION, CODIGO_CAUSA,
        CONSULTA, RECLAMACION, ACLARACION, ACTIVO
    )
    SELECT :producto_id, :descripcion, :codigo_causa,
           :consulta, :reclamacion, :aclaracion, 1
    FROM DUAL
    WHERE NOT EXISTS (
        SELECT 1 FROM REUNE_CAUSAS
        WHERE PRODUCTO_ID = :producto_id_chk AND CODIGO_CAUSA = :codigo_causa_chk
    )
SQL;

$updateCausa = <<<'SQL'
    UPDATE REUNE_CAUSAS SET
        DESCRIPCION = :descripcion,
        CONSULTA = :consulta,
        RECLAMACION = :reclamacion,
        ACLARACION = :aclaracion,
        ACTIVO = 1
    WHERE PRODUCTO_ID = :producto_id AND CODIGO_CAUSA = :codigo_causa
SQL;

$insertadasCausas = 0;
$omitidas = 0;

foreach ($causas as $causa) {
    $productoId = $mapaProductos[$causa['codigo_producto']] ?? null;
    if ($productoId === null) {
        $omitidas++;
        continue;
    }

    $params = [
        'producto_id' => $productoId,
        'descripcion' => $causa['descripcion'],
        'codigo_causa' => $causa['codigo_causa'],
        'consulta' => $causa['consulta'],
        'reclamacion' => $causa['reclamacion'],
        'aclaracion' => $causa['aclaracion'],
        'producto_id_chk' => $productoId,
        'codigo_causa_chk' => $causa['codigo_causa'],
    ];

    if ($db->insert($insertCausa, $params)) {
        $insertadasCausas++;
    }

    $db->insert($updateCausa, [
        'producto_id' => $productoId,
        'descripcion' => $causa['descripcion'],
        'codigo_causa' => $causa['codigo_causa'],
        'consulta' => $causa['consulta'],
        'reclamacion' => $causa['reclamacion'],
        'aclaracion' => $causa['aclaracion'],
    ]);
}

echo "Catálogo REUNE cargado.\n";
echo "Productos en archivo: " . count($productos) . " (nuevos: $insertadosProd)\n";
echo "Causas en archivo: " . count($causas) . " (nuevas: $insertadasCausas, omitidas: $omitidas)\n";
echo "Productos en BD: " . count($mapaProductos) . "\n";

$totalCausas = $db->queryOne('SELECT COUNT(*) AS TOTAL FROM REUNE_CAUSAS');
echo "Causas en BD: " . ($totalCausas['TOTAL'] ?? 0) . "\n";
