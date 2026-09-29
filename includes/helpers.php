<?php
/**
 * Kosheru - Funciones de Ayuda y Utilidades
 */

require_once __DIR__ . '/session.php';

/**
 * Emite una respuesta JSON limpia compatible con InfinityFree sin contaminación de warnings/notices
 */
function sendJsonResponse($data, $statusCode = 200) {
    ob_start();
    error_reporting(0);
    ini_set('display_errors', '0');
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function safeJSONResponse($data, $statusCode = 200) {
    sendJsonResponse($data, $statusCode);
}

/**
 * Formatea un precio numérico a Pesos Colombianos (COP)
 * Ejemplo: 18900 => "$18.900 COP"
 */
function formatCOP($amount) {
    return '$' . number_format((float)$amount, 0, ',', '.') . ' COP';
}

/**
 * Sanitiza una cadena de texto para evitar XSS
 */
function sanitize($input) {
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

/**
 * Genera un código de factura único con formato FAC-XXXXX (ej: FAC-91622)
 */
function generateInvoiceNumber($conn) {
    do {
        $rand_num = rand(10000, 99999);
        $code = "FAC-" . $rand_num;
        $esc_code = mysqli_real_escape_string($conn, $code);
        $res = mysqli_query($conn, "SELECT id FROM purchases WHERE invoice_number = '$esc_code' LIMIT 1");
    } while ($res && mysqli_num_rows($res) > 0);
    
    return $code;
}

/**
 * Escribe un registro transaccional en logs/pedidos_log.txt
 */
function logOrderTransaction($invoice_number, $user_name, $total) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $file = $log_dir . '/pedidos_log.txt';
    $date = date('Y-m-d H:i:s');
    $line = "[$date] Factura: $invoice_number | Usuario: $user_name | Total: " . formatCOP($total) . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND);
}

/**
 * ===================== PROGRAMA DE FIDELIDAD =====================
 */

/** 1 punto por cada $1.000 COP del total pagado (redondeado hacia abajo). */
define('LOYALTY_COP_PER_POINT', 1000);

/**
 * Recompensas fijas canjeables. "points" = costo en puntos,
 * "discount" = descuento en COP que genera el cupón resultante.
 */
function getLoyaltyRewardTiers() {
    return [
        ['points' => 500,  'discount' => 5000,  'label' => '$5.000 COP de descuento'],
        ['points' => 1000, 'discount' => 12000, 'label' => '$12.000 COP de descuento'],
        ['points' => 2000, 'discount' => 28000, 'label' => '$28.000 COP de descuento'],
    ];
}

/** Nivel de fidelidad según puntos acumulados históricos (no baja al canjear). */
function getLoyaltyTier($lifetimeEarned) {
    $lifetimeEarned = (int)$lifetimeEarned;
    $tiers = [
        ['name' => 'Bronce', 'min' => 0],
        ['name' => 'Plata',  'min' => 500],
        ['name' => 'Oro',    'min' => 1500],
    ];

    $current = $tiers[0];
    $next = null;
    foreach ($tiers as $i => $t) {
        if ($lifetimeEarned >= $t['min']) {
            $current = $t;
            $next = $tiers[$i + 1] ?? null;
        }
    }

    $progressPercent = 100;
    if ($next) {
        $span = $next['min'] - $current['min'];
        $progressPercent = $span > 0 ? min(100, round((($lifetimeEarned - $current['min']) / $span) * 100)) : 100;
    }

    return [
        'name' => $current['name'],
        'next_name' => $next['name'] ?? null,
        'next_threshold' => $next['min'] ?? null,
        'points_to_next' => $next ? max(0, $next['min'] - $lifetimeEarned) : 0,
        'progress_percent' => $progressPercent,
    ];
}

/** Devuelve balance, acumulado histórico y nivel de un usuario (crea el registro si no existe). */
function getLoyaltySummary($conn, $userId) {
    $userId = (int)$userId;
    $res = mysqli_query($conn, "SELECT balance, lifetime_earned FROM loyalty_points WHERE user_id = $userId LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;

    if (!$row) {
        $balance = 0;
        $lifetime = 0;
    } else {
        $balance = (int)$row['balance'];
        $lifetime = (int)$row['lifetime_earned'];
    }

    return [
        'balance' => $balance,
        'lifetime_earned' => $lifetime,
        'tier' => getLoyaltyTier($lifetime),
    ];
}

/**
 * Otorga puntos por una compra. Se llama dentro de la misma transacción
 * del checkout, así que si algo falla después, el rollback también
 * revierte los puntos otorgados.
 */
function awardLoyaltyPoints($conn, $userId, $purchaseId, $totalPrice) {
    $userId = (int)$userId;
    $purchaseId = (int)$purchaseId;
    $points = (int)floor($totalPrice / LOYALTY_COP_PER_POINT);

    if ($points <= 0) {
        return 0;
    }

    $points_result = mysqli_query($conn, "INSERT INTO loyalty_points (user_id, balance, lifetime_earned) 
        VALUES ($userId, $points, $points)
        ON DUPLICATE KEY UPDATE balance = balance + $points, lifetime_earned = lifetime_earned + $points");
    if (!$points_result) {
        throw new RuntimeException('No fue posible actualizar los puntos de fidelidad.');
    }

    $description = mysqli_real_escape_string($conn, "Puntos ganados por compra #{$purchaseId}");
    $transaction_result = mysqli_query($conn, "INSERT INTO loyalty_transactions (user_id, points, type, description, purchase_id) 
        VALUES ($userId, $points, 'earned', '$description', $purchaseId)");
    if (!$transaction_result) {
        throw new RuntimeException('No fue posible registrar los puntos de fidelidad.');
    }

    return $points;
}

/**
 * Canjea puntos por un cupón de descuento. Devuelve el código generado
 * o false si el usuario no tiene puntos suficientes.
 */
function redeemLoyaltyPoints($conn, $userId, $pointsCost, $discountAmount) {
    $userId = (int)$userId;
    $pointsCost = (int)$pointsCost;
    $discountAmount = (float)$discountAmount;

    $summary = getLoyaltySummary($conn, $userId);
    if ($summary['balance'] < $pointsCost) {
        return false;
    }

    $code = 'KOSH-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

    $balance_result = mysqli_query($conn, "UPDATE loyalty_points SET balance = balance - $pointsCost WHERE user_id = $userId");
    if (!$balance_result) {
        throw new RuntimeException('No fue posible descontar los puntos canjeados.');
    }

    $description = mysqli_real_escape_string($conn, "Canje de {$pointsCost} puntos por cupón {$code}");
    $transaction_result = mysqli_query($conn, "INSERT INTO loyalty_transactions (user_id, points, type, description) 
        VALUES ($userId, -$pointsCost, 'redeemed', '$description')");
    if (!$transaction_result) {
        throw new RuntimeException('No fue posible registrar el canje de puntos.');
    }

    $esc_code = mysqli_real_escape_string($conn, $code);
    $coupon_result = mysqli_query($conn, "INSERT INTO loyalty_coupons (code, user_id, discount_amount, points_spent) 
        VALUES ('$esc_code', $userId, $discountAmount, $pointsCost)");
    if (!$coupon_result) {
        throw new RuntimeException('No fue posible crear el cupón de descuento.');
    }

    return $code;
}
?>