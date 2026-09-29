<?php
/**
 * Kosheru - API del programa de fidelidad
 * GET  -> saldo, nivel, recompensas, cupones activos e historial
 * POST -> canjea una recompensa por un cupón
 */
require_once __DIR__ . '/../modelo/connection.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAuth(true);

$user = getCurrentUser();
$user_id = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $summary = getLoyaltySummary($conn, $user_id);
    $rewards = getLoyaltyRewardTiers();
    $coupons_result = mysqli_query(
        $conn,
        "SELECT code, discount_amount FROM loyalty_coupons
         WHERE user_id = $user_id AND is_used = 0 ORDER BY created_at DESC"
    );
    $history_result = mysqli_query(
        $conn,
        "SELECT points, type, description, created_at FROM loyalty_transactions
         WHERE user_id = $user_id ORDER BY created_at DESC, id DESC LIMIT 50"
    );

    if (!$coupons_result || !$history_result) {
        sendJsonResponse(['success' => false, 'message' => 'No fue posible cargar tu información de fidelidad.'], 500);
    }

    $coupons = [];
    while ($coupon = mysqli_fetch_assoc($coupons_result)) {
        $coupon['discount_formatted'] = formatCOP($coupon['discount_amount']);
        $coupons[] = $coupon;
    }

    $history = [];
    while ($entry = mysqli_fetch_assoc($history_result)) {
        $entry['points'] = (int)$entry['points'];
        $history[] = $entry;
    }

    foreach ($rewards as &$reward) {
        $reward['discount_formatted'] = formatCOP($reward['discount']);
    }
    unset($reward);

    sendJsonResponse([
        'success' => true,
        'balance' => $summary['balance'],
        'lifetime_earned' => $summary['lifetime_earned'],
        'tier' => $summary['tier'],
        'reward_tiers' => $rewards,
        'active_coupons' => $coupons,
        'history' => $history,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (!$input) {
        $json_input = json_decode(file_get_contents('php://input'), true);
        $input = is_array($json_input) ? $json_input : [];
    }

    $points_cost = (int)($input['points'] ?? 0);
    $selected_reward = null;
    foreach (getLoyaltyRewardTiers() as $reward) {
        if ($reward['points'] === $points_cost) {
            $selected_reward = $reward;
            break;
        }
    }

    if (!$selected_reward) {
        sendJsonResponse(['success' => false, 'message' => 'La recompensa seleccionada no es válida.'], 400);
    }

    mysqli_begin_transaction($conn);

    try {
        $created = mysqli_query(
            $conn,
            "INSERT IGNORE INTO loyalty_points (user_id, balance, lifetime_earned) VALUES ($user_id, 0, 0)"
        );
        $balance_result = mysqli_query(
            $conn,
            "SELECT balance FROM loyalty_points WHERE user_id = $user_id FOR UPDATE"
        );

        if (!$created || !$balance_result || mysqli_num_rows($balance_result) === 0) {
            throw new RuntimeException('No fue posible verificar tu saldo de puntos.');
        }

        $balance = (int)mysqli_fetch_assoc($balance_result)['balance'];
        if ($balance < $points_cost) {
            mysqli_rollback($conn);
            sendJsonResponse(['success' => false, 'message' => 'No tienes puntos suficientes para esta recompensa.'], 400);
        }

        $code = redeemLoyaltyPoints($conn, $user_id, $points_cost, $selected_reward['discount']);
        if (!$code) {
            throw new RuntimeException('No fue posible completar el canje.');
        }

        mysqli_commit($conn);

        sendJsonResponse([
            'success' => true,
            'code' => $code,
            'message' => 'Canje exitoso. Tu cupón ' . $code . ' descuenta ' . formatCOP($selected_reward['discount']) . '.',
        ]);
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        sendJsonResponse(['success' => false, 'message' => 'No fue posible completar el canje. Intenta de nuevo.'], 500);
    }
}

sendJsonResponse(['success' => false, 'message' => 'Método no permitido.'], 405);
?>