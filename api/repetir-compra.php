<?php
require_once __DIR__ . '/../modelo/connection.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAuth(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'message' => 'Método no permitido.'], 405);
}

$requestData = json_decode(file_get_contents('php://input'), true);
$purchaseId = (int)($requestData['purchase_id'] ?? 0);
$currentUser = getCurrentUser();
$userId = (int)($currentUser['id'] ?? 0);

if ($purchaseId <= 0) {
    sendJsonResponse(['success' => false, 'message' => 'Pedido inválido.'], 400);
}

$purchaseStatement = mysqli_prepare($conn, 'SELECT id FROM purchases WHERE id = ? AND user_id = ? LIMIT 1');
if (!$purchaseStatement) {
    sendJsonResponse(['success' => false, 'message' => 'No se pudo consultar el pedido.'], 500);
}

mysqli_stmt_bind_param($purchaseStatement, 'ii', $purchaseId, $userId);
mysqli_stmt_execute($purchaseStatement);
mysqli_stmt_store_result($purchaseStatement);
$ownsPurchase = mysqli_stmt_num_rows($purchaseStatement) === 1;
mysqli_stmt_close($purchaseStatement);

if (!$ownsPurchase) {
    sendJsonResponse(['success' => false, 'message' => 'No encontramos ese pedido en tu cuenta.'], 404);
}

$itemsStatement = mysqli_prepare(
    $conn,
    'SELECT products.id, products.name, products.price, products.image, products.origin, products.stock, purchase_items.quantity
     FROM purchase_items
     INNER JOIN products ON products.id = purchase_items.product_id
     WHERE purchase_items.purchase_id = ? ORDER BY purchase_items.id ASC'
);

if (!$itemsStatement) {
    sendJsonResponse(['success' => false, 'message' => 'No se pudieron consultar los productos del pedido.'], 500);
}

mysqli_stmt_bind_param($itemsStatement, 'i', $purchaseId);
$items = [];
$unavailableCount = 0;

if (mysqli_stmt_execute($itemsStatement)) {
    $productId = $productName = $price = $image = $origin = $stock = $purchasedQuantity = null;
    mysqli_stmt_bind_result($itemsStatement, $productId, $productName, $price, $image, $origin, $stock, $purchasedQuantity);

    while (mysqli_stmt_fetch($itemsStatement)) {
        if ((int)$stock <= 0) {
            $unavailableCount++;
            continue;
        }

        $items[] = [
            'id' => (int)$productId,
            'name' => $productName,
            'price' => (float)$price,
            'image' => $image,
            'origin' => $origin,
            'quantity' => min((int)$purchasedQuantity, (int)$stock)
        ];
    }
}

mysqli_stmt_close($itemsStatement);
sendJsonResponse(['success' => true, 'items' => $items, 'unavailable_count' => $unavailableCount]);
?>