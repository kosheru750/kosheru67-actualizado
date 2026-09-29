<?php
/**
 * Kosheru - API de Gestión de Compras Admin
 */
require_once __DIR__ . '/../../../modelo/connection.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';

requireAdmin(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestData = json_decode(file_get_contents('php://input'), true);
    $purchaseId = (int)($requestData['purchase_id'] ?? 0);
    $status = (string)($requestData['status'] ?? '');
    $allowedStatuses = ['pendiente', 'preparando', 'enviado', 'completado', 'cancelado'];

    if ($purchaseId <= 0 || !in_array($status, $allowedStatuses, true)) {
        sendJsonResponse(['success' => false, 'message' => 'Pedido o estado inválido.'], 400);
    }

    $purchaseStatement = mysqli_prepare($conn, 'SELECT id FROM purchases WHERE id = ? LIMIT 1');
    if (!$purchaseStatement) {
        sendJsonResponse(['success' => false, 'message' => 'No se pudo consultar el pedido.'], 500);
    }

    mysqli_stmt_bind_param($purchaseStatement, 'i', $purchaseId);
    mysqli_stmt_execute($purchaseStatement);
    mysqli_stmt_store_result($purchaseStatement);
    $purchaseExists = mysqli_stmt_num_rows($purchaseStatement) === 1;
    mysqli_stmt_close($purchaseStatement);

    if (!$purchaseExists) {
        sendJsonResponse(['success' => false, 'message' => 'No encontramos ese pedido.'], 404);
    }

    $updateStatement = mysqli_prepare($conn, 'UPDATE purchases SET status = ? WHERE id = ?');
    if (!$updateStatement) {
        sendJsonResponse(['success' => false, 'message' => 'No se pudo actualizar el pedido.'], 500);
    }

    mysqli_stmt_bind_param($updateStatement, 'si', $status, $purchaseId);
    $updated = mysqli_stmt_execute($updateStatement);
    mysqli_stmt_close($updateStatement);

    if (!$updated) {
        sendJsonResponse(['success' => false, 'message' => 'No se pudo actualizar el estado del pedido.'], 500);
    }

    sendJsonResponse(['success' => true, 'message' => 'Estado del pedido actualizado.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(['success' => false, 'message' => 'Método no permitido.'], 405);
}

$sql = "SELECT id, invoice_number, user_name, user_email, order_date, order_time, payment_method, total_price, status 
        FROM purchases ORDER BY id DESC";
$res = mysqli_query($conn, $sql);
$compras = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['total_formatted'] = formatCOP($row['total_price']);
        $compras[] = $row;
    }
}

sendJsonResponse(['success' => true, 'compras' => $compras]);
?>
