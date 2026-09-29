<?php
/**
 * Kosheru - API de Productos
 * Devuelve productos desde MySQL en formato JSON con soporte de filtros, búsquedas
 * y promedio de calificación (estrellas) por producto.
 *
 * IMPORTANTE: si la consulta con reseñas falla por cualquier motivo (por
 * ejemplo, la tabla product_reviews aún no existe porque no se ha corrido
 * la migración), esta API NUNCA debe devolver la tienda vacía. En ese caso
 * cae automáticamente a una consulta simple sin calificaciones.
 */
require_once __DIR__ . '/../modelo/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;

$where_clauses = [];

if (!empty($categoria) && $categoria !== 'Todos') {
    $esc_cat = mysqli_real_escape_string($conn, $categoria);
    $where_clauses[] = "EXISTS (
        SELECT 1 FROM product_categories pc_filter
        INNER JOIN categories c_filter ON c_filter.id = pc_filter.category_id
        WHERE pc_filter.product_id = p.id AND c_filter.name = '$esc_cat'
    )";
}

if (!empty($search)) {
    $esc_search = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(p.name LIKE '%$esc_search%' OR p.category LIKE '%$esc_search%' OR p.origin LIKE '%$esc_search%' OR p.description LIKE '%$esc_search%' OR EXISTS (
        SELECT 1 FROM product_categories pc_search
        INNER JOIN categories c_search ON c_search.id = pc_search.category_id
        WHERE pc_search.product_id = p.id AND c_search.name LIKE '%$esc_search%'
    ))";
}

$where_sql = !empty($where_clauses) ? " WHERE " . implode(' AND ', $where_clauses) : "";
$limit_sql = $limit > 0 ? " LIMIT " . $limit : "";

// --- Intento 1: consulta completa con promedio de reseñas ---
$category_select = "(SELECT GROUP_CONCAT(c_list.name ORDER BY c_list.name SEPARATOR '||')
    FROM product_categories pc_list
    INNER JOIN categories c_list ON c_list.id = pc_list.category_id
    WHERE pc_list.product_id = p.id) AS categories_list";

$sql_with_reviews = "SELECT p.id, p.name, p.category, $category_select, p.origin, p.price, p.stock, p.image, p.description, p.created_at,
               COALESCE(AVG(r.rating), 0) AS avg_rating,
               COUNT(r.id) AS review_count
        FROM products p
        LEFT JOIN product_reviews r ON r.product_id = p.id"
        . $where_sql . " GROUP BY p.id ORDER BY p.id DESC" . $limit_sql;

$query = @mysqli_query($conn, $sql_with_reviews);
$used_fallback = false;

// --- Plan B: si falló (ej. la tabla product_reviews no existe todavía),
//     usar la consulta simple de siempre, sin calificaciones. La tienda
//     sigue funcionando aunque la migración de reseñas no se haya corrido.
if (!$query) {
    $used_fallback = true;
    $sql_simple = "SELECT p.id, p.name, p.category, $category_select, p.origin, p.price, p.stock, p.image, p.description, p.created_at
                   FROM products p" . $where_sql . " ORDER BY p.id DESC" . $limit_sql;
    $query = @mysqli_query($conn, $sql_simple);
}

$productos = [];

if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $row['price_formatted'] = formatCOP($row['price']);
        $row['avg_rating'] = isset($row['avg_rating']) ? round((float)$row['avg_rating'], 1) : 0;
        $row['review_count'] = isset($row['review_count']) ? (int)$row['review_count'] : 0;
        $row['categories'] = !empty($row['categories_list']) ? explode('||', $row['categories_list']) : [$row['category']];
        $row['category'] = implode(', ', $row['categories']);
        unset($row['categories_list']);
        $productos[] = $row;
    }
}

$response = [
    'success' => true,
    'total' => count($productos),
    'productos' => $productos
];

// Bandera visible solo para depuración manual (no afecta el frontend actual,
// pero te sirve para confirmar desde la pestaña Network si cayó al plan B).
if ($used_fallback) {
    $response['reviews_unavailable'] = true;
}

sendJsonResponse($response);
?>
