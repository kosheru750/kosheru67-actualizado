<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/modelo/connection.php';

requireAuth();

$currentUser = getCurrentUser();
$userId = (int)($currentUser['id'] ?? 0);
$profile = null;
$addresses = [];
$addressesLoaded = false;
$reviews = [];
$reviewsLoaded = false;
$csrfSessionKey = 'account_csrf_token';

if (empty($_SESSION[$csrfSessionKey])) {
    $_SESSION[$csrfSessionKey] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[$csrfSessionKey];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $addressAction = (string)($_POST['address_action'] ?? '');
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $notice = ['type' => 'error', 'message' => 'No se pudo completar la acción. Inténtalo de nuevo.'];

    if (hash_equals($csrfToken, $submittedToken)) {
        if ($addressAction === 'save_address') {
            $label = trim((string)($_POST['label'] ?? ''));
            $recipientName = trim((string)($_POST['recipient_name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            $department = trim((string)($_POST['department'] ?? ''));
            $postalCode = trim((string)($_POST['postal_code'] ?? ''));

            if ($label === '' || $recipientName === '' || $phone === '' || $address === '' || $city === '' || $department === '') {
                $notice['message'] = 'Completa los campos obligatorios de la dirección.';
            } else {
                $savedAddressQuery = mysqli_query($conn, "SELECT id FROM user_addresses WHERE user_id = $userId LIMIT 1");
                $isDefault = !empty($_POST['is_default']) || ($savedAddressQuery && mysqli_num_rows($savedAddressQuery) === 0);
                mysqli_begin_transaction($conn);

                $insertAddress = mysqli_prepare(
                    $conn,
                    "INSERT INTO user_addresses (user_id, label, recipient_name, phone, address, city, department, postal_code, is_default)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), 0)"
                );
                $saved = $insertAddress
                    && mysqli_stmt_bind_param($insertAddress, 'isssssss', $userId, $label, $recipientName, $phone, $address, $city, $department, $postalCode)
                    && mysqli_stmt_execute($insertAddress);
                $newAddressId = $saved ? mysqli_insert_id($conn) : 0;

                if ($insertAddress) {
                    mysqli_stmt_close($insertAddress);
                }

                if ($saved && $isDefault) {
                    $clearDefaults = mysqli_prepare($conn, 'UPDATE user_addresses SET is_default = 0 WHERE user_id = ?');
                    $setDefault = mysqli_prepare($conn, 'UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?');
                    $saved = $clearDefaults && mysqli_stmt_bind_param($clearDefaults, 'i', $userId) && mysqli_stmt_execute($clearDefaults);
                    $saved = $saved && $setDefault && mysqli_stmt_bind_param($setDefault, 'ii', $newAddressId, $userId) && mysqli_stmt_execute($setDefault);

                    if ($clearDefaults) {
                        mysqli_stmt_close($clearDefaults);
                    }
                    if ($setDefault) {
                        mysqli_stmt_close($setDefault);
                    }
                }

                if ($saved) {
                    mysqli_commit($conn);
                    $notice = ['type' => 'success', 'message' => 'Dirección guardada correctamente.'];
                } else {
                    mysqli_rollback($conn);
                    $notice['message'] = 'No se pudo guardar la dirección. Inténtalo de nuevo.';
                }
            }
        } elseif ($addressAction === 'set_default' || $addressAction === 'delete_address') {
            $addressId = (int)($_POST['address_id'] ?? 0);
            $lookupAddress = mysqli_prepare($conn, 'SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1');
            $foundAddress = false;
            $wasDefault = 0;

            if ($lookupAddress) {
                mysqli_stmt_bind_param($lookupAddress, 'ii', $addressId, $userId);
                if (mysqli_stmt_execute($lookupAddress)) {
                    mysqli_stmt_bind_result($lookupAddress, $wasDefault);
                    $foundAddress = mysqli_stmt_fetch($lookupAddress);
                }
                mysqli_stmt_close($lookupAddress);
            }

            if ($addressId > 0 && $foundAddress) {
                mysqli_begin_transaction($conn);

                if ($addressAction === 'set_default') {
                    $clearDefaults = mysqli_prepare($conn, 'UPDATE user_addresses SET is_default = 0 WHERE user_id = ?');
                    $setDefault = mysqli_prepare($conn, 'UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?');
                    $updated = $clearDefaults && mysqli_stmt_bind_param($clearDefaults, 'i', $userId) && mysqli_stmt_execute($clearDefaults);
                    $updated = $updated && $setDefault && mysqli_stmt_bind_param($setDefault, 'ii', $addressId, $userId) && mysqli_stmt_execute($setDefault);

                    if ($clearDefaults) {
                        mysqli_stmt_close($clearDefaults);
                    }
                    if ($setDefault) {
                        mysqli_stmt_close($setDefault);
                    }

                    if ($updated) {
                        mysqli_commit($conn);
                        $notice = ['type' => 'success', 'message' => 'Dirección predeterminada actualizada.'];
                    } else {
                        mysqli_rollback($conn);
                    }
                } else {
                    $deleteAddress = mysqli_prepare($conn, 'DELETE FROM user_addresses WHERE id = ? AND user_id = ?');
                    $deleted = $deleteAddress
                        && mysqli_stmt_bind_param($deleteAddress, 'ii', $addressId, $userId)
                        && mysqli_stmt_execute($deleteAddress)
                        && mysqli_stmt_affected_rows($deleteAddress) > 0;

                    if ($deleteAddress) {
                        mysqli_stmt_close($deleteAddress);
                    }

                    if ($deleted && (int)$wasDefault === 1) {
                        $nextAddress = mysqli_prepare($conn, 'SELECT id FROM user_addresses WHERE user_id = ? ORDER BY id DESC LIMIT 1');
                        $nextAddressId = 0;
                        $hasNextAddress = false;

                        if ($nextAddress) {
                            mysqli_stmt_bind_param($nextAddress, 'i', $userId);
                            if (mysqli_stmt_execute($nextAddress)) {
                                mysqli_stmt_bind_result($nextAddress, $nextAddressId);
                                $hasNextAddress = mysqli_stmt_fetch($nextAddress);
                            }
                            mysqli_stmt_close($nextAddress);
                        }

                        if ($hasNextAddress) {
                            $promoteAddress = mysqli_prepare($conn, 'UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?');
                            $deleted = $promoteAddress
                                && mysqli_stmt_bind_param($promoteAddress, 'ii', $nextAddressId, $userId)
                                && mysqli_stmt_execute($promoteAddress);
                            if ($promoteAddress) {
                                mysqli_stmt_close($promoteAddress);
                            }
                        }
                    }

                    if ($deleted) {
                        mysqli_commit($conn);
                        $notice = ['type' => 'success', 'message' => 'Dirección eliminada.'];
                    } else {
                        mysqli_rollback($conn);
                    }
                }
            } else {
                $notice['message'] = 'No encontramos esa dirección en tu cuenta.';
            }
        }
    } else {
        $notice['message'] = 'La sesión del formulario venció. Inténtalo de nuevo.';
    }

    $_SESSION['account_notice'] = $notice;
    header('Location: account.php#addresses');
    exit;
}

$accountNotice = $_SESSION['account_notice'] ?? null;
unset($_SESSION['account_notice']);

$profileStatement = mysqli_prepare(
    $conn,
    'SELECT name, lastname, username, email, rol, state, created_at FROM users WHERE id = ? LIMIT 1'
);

if ($profileStatement) {
    mysqli_stmt_bind_param($profileStatement, 'i', $userId);

    if (mysqli_stmt_execute($profileStatement)) {
        $name = $lastname = $username = $email = $role = $state = $createdAt = null;
        mysqli_stmt_bind_result($profileStatement, $name, $lastname, $username, $email, $role, $state, $createdAt);

        if (mysqli_stmt_fetch($profileStatement)) {
            $profile = [
                'name' => $name,
                'lastname' => $lastname,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'state' => $state,
                'created_at' => $createdAt
            ];
        }
    }

    mysqli_stmt_close($profileStatement);
}

if ($profile) {
    $addressesStatement = mysqli_prepare(
        $conn,
        'SELECT id, label, recipient_name, phone, address, city, department, postal_code, is_default
         FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC'
    );

    if ($addressesStatement) {
        mysqli_stmt_bind_param($addressesStatement, 'i', $userId);

        if (mysqli_stmt_execute($addressesStatement)) {
            $addressId = $label = $recipientName = $phone = $address = $city = $department = $postalCode = $isDefault = null;
            if (mysqli_stmt_bind_result($addressesStatement, $addressId, $label, $recipientName, $phone, $address, $city, $department, $postalCode, $isDefault)) {
                $addressesLoaded = true;
                while (mysqli_stmt_fetch($addressesStatement)) {
                    $addresses[] = [
                        'id' => (int)$addressId,
                        'label' => $label,
                        'recipient_name' => $recipientName,
                        'phone' => $phone,
                        'address' => $address,
                        'city' => $city,
                        'department' => $department,
                        'postal_code' => $postalCode,
                        'is_default' => (bool)$isDefault
                    ];
                }
            }
        }

        mysqli_stmt_close($addressesStatement);
    }

    $reviewsStatement = mysqli_prepare(
        $conn,
        'SELECT products.name, product_reviews.rating, product_reviews.comment, product_reviews.created_at
         FROM product_reviews
         INNER JOIN products ON products.id = product_reviews.product_id
         WHERE product_reviews.user_id = ?
         ORDER BY product_reviews.created_at DESC, product_reviews.id DESC'
    );

    if ($reviewsStatement) {
        mysqli_stmt_bind_param($reviewsStatement, 'i', $userId);

        if (mysqli_stmt_execute($reviewsStatement)) {
            $productName = $rating = $comment = $reviewCreatedAt = null;

            if (mysqli_stmt_bind_result($reviewsStatement, $productName, $rating, $comment, $reviewCreatedAt)) {
                $reviewsLoaded = true;

                while (mysqli_stmt_fetch($reviewsStatement)) {
                    $reviewTimestamp = strtotime((string)$reviewCreatedAt);
                    $reviews[] = [
                        'product_name' => $productName,
                        'rating' => max(0, min(5, (int)$rating)),
                        'comment' => (string)($comment ?? ''),
                        'created_at' => $reviewTimestamp === false ? 'Fecha no disponible' : date('d/m/Y', $reviewTimestamp)
                    ];
                }
            }
        }

        mysqli_stmt_close($reviewsStatement);
    }
}

if (!$profile) {
    http_response_code(404);
} else {
    $fullName = trim((string)$profile['name'] . ' ' . (string)$profile['lastname']);
    $roleLabel = $profile['role'] === 'admin' ? 'Administrador' : 'Cliente';
    $stateLabel = $profile['state'] === 'activo' ? 'Activa' : 'Inactiva';
    $createdAtTimestamp = strtotime((string)$profile['created_at']);
    $createdAtLabel = $createdAtTimestamp === false ? 'No disponible' : date('d/m/Y', $createdAtTimestamp);
}

$escapeHtml = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta | Kosheru</title>
    <script>
        (function () {
            var saved = localStorage.getItem('kosheru_theme');
            document.documentElement.setAttribute('data-theme', saved === 'dark' ? 'dark' : 'light');
        })();
    </script>
    <link rel="stylesheet" href="css/style.css?v=mobile-20260927-6">
    <link rel="stylesheet" href="css/account.css">
    <link rel="icon" href="img/logos/Isotipo transparente.png" type="image/png">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="account-page">
        <header class="account-titlebar">
            <div>
                <p class="account-eyebrow">Perfil de usuario</p>
                <h1>Mi Cuenta</h1>
                <p class="account-description">Consulta la información asociada a tu cuenta Kosheru.</p>
            </div>
            <nav class="account-title-actions" aria-label="Accesos de cuenta">
                <a class="account-loyalty-link" href="fidelidad.php">Mi fidelidad</a>
                <a class="account-back-link" href="index.php">Volver a la tienda</a>
            </nav>
        </header>

        <?php if (!$profile): ?>
            <section class="account-panel account-message" role="status">
                <h2>No encontramos tu cuenta</h2>
                <p>Inicia sesión de nuevo o contacta con soporte si el problema continúa.</p>
            </section>
        <?php else: ?>
            <section class="account-panel" aria-labelledby="accountProfileTitle">
                <div class="account-profile-summary">
                    <div>
                        <p class="account-eyebrow">Datos personales</p>
                        <h2 id="accountProfileTitle"><?= $escapeHtml($fullName) ?></h2>
                    </div>
                    <span class="account-role"><?= $escapeHtml($roleLabel) ?></span>
                </div>

                <dl class="account-fields">
                    <div class="account-field">
                        <dt>Nombre completo</dt>
                        <dd><?= $escapeHtml($fullName) ?></dd>
                    </div>
                    <div class="account-field">
                        <dt>Nombre de usuario</dt>
                        <dd><?= $escapeHtml($profile['username']) ?></dd>
                    </div>
                    <div class="account-field">
                        <dt>Correo electrónico</dt>
                        <dd><?= $escapeHtml($profile['email']) ?></dd>
                    </div>
                    <div class="account-field">
                        <dt>Estado de la cuenta</dt>
                        <dd><span class="account-state <?= $profile['state'] === 'activo' ? 'is-active' : 'is-inactive' ?>"><?= $escapeHtml($stateLabel) ?></span></dd>
                    </div>
                    <div class="account-field">
                        <dt>Tipo de cuenta</dt>
                        <dd><?= $escapeHtml($roleLabel) ?></dd>
                    </div>
                    <div class="account-field">
                        <dt>Miembro desde</dt>
                        <dd><?= $escapeHtml($createdAtLabel) ?></dd>
                    </div>
                </dl>
            </section>

            <section class="account-wishlist" id="wishlist" aria-labelledby="accountWishlistTitle">
                <div class="account-wishlist-heading">
                    <div>
                        <p class="account-eyebrow">Tus guardados</p>
                        <h2 id="accountWishlistTitle">Mi wishlist</h2>
                    </div>
                    <div class="account-wishlist-actions">
                        <button type="button" class="account-wishlist-toggle" id="accountWishlistToggle" aria-expanded="false">
                            Ver toda mi wishlist
                        </button>
                        <div class="account-wishlist-controls" id="accountWishlistControls" aria-label="Controles del carrusel">
                            <button type="button" id="accountWishlistPrevious" aria-label="Productos anteriores">&larr;</button>
                            <button type="button" id="accountWishlistNext" aria-label="Productos siguientes">&rarr;</button>
                        </div>
                    </div>
                </div>

                <p class="account-wishlist-status" id="accountWishlistStatus" role="status">Cargando tu wishlist...</p>
                <div class="account-wishlist-carousel" id="accountWishlistCarousel" aria-label="Tus seis productos agregados más recientemente" hidden>
                    <div class="account-wishlist-track" id="accountWishlistTrack"></div>
                </div>
                <div class="account-wishlist-all" id="accountWishlistAll" hidden></div>
            </section>

            <section class="account-addresses" id="addresses" aria-labelledby="accountAddressesTitle">
                <div class="account-addresses-heading">
                    <div>
                        <p class="account-eyebrow">Entrega</p>
                        <h2 id="accountAddressesTitle">Direcciones guardadas</h2>
                    </div>
                </div>

                <?php if ($accountNotice): ?>
                    <p class="account-notice <?= $escapeHtml($accountNotice['type']) ?>" role="status"><?= $escapeHtml($accountNotice['message']) ?></p>
                <?php endif; ?>

                <form class="account-panel account-address-form" method="post" action="account.php#addresses">
                    <input type="hidden" name="csrf_token" value="<?= $escapeHtml($csrfToken) ?>">
                    <input type="hidden" name="address_action" value="save_address">
                    <div class="account-address-fields">
                        <label>Nombre para identificarla
                            <input type="text" name="label" maxlength="100" placeholder="Casa, trabajo..." required>
                        </label>
                        <label>Nombre del destinatario
                            <input type="text" name="recipient_name" maxlength="150" value="<?= $escapeHtml($fullName) ?>" required>
                        </label>
                        <label>Teléfono
                            <input type="tel" name="phone" maxlength="30" required>
                        </label>
                        <label>Dirección
                            <input type="text" name="address" maxlength="255" required>
                        </label>
                        <label>Ciudad
                            <input type="text" name="city" maxlength="100" required>
                        </label>
                        <label>Departamento o municipio
                            <input type="text" name="department" maxlength="100" required>
                        </label>
                        <label>Código postal
                            <input type="text" name="postal_code" maxlength="20">
                        </label>
                    </div>
                    <label class="account-default-checkbox">
                        <input type="checkbox" name="is_default" value="1" <?= !$addresses ? 'checked' : '' ?>>
                        Usar como dirección predeterminada
                    </label>
                    <button type="submit" class="account-address-submit">Guardar dirección</button>
                </form>

                <?php if (!$addressesLoaded): ?>
                    <p class="account-reviews-empty" role="alert">No pudimos cargar tus direcciones.</p>
                <?php elseif ($addresses): ?>
                    <div class="account-panel account-address-list">
                        <?php foreach ($addresses as $savedAddress): ?>
                            <article class="account-address">
                                <div class="account-address-details">
                                    <h3><?= $escapeHtml($savedAddress['label']) ?><?php if ($savedAddress['is_default']): ?> <span class="account-address-default">Predeterminada</span><?php endif; ?></h3>
                                    <p><?= $escapeHtml($savedAddress['recipient_name']) ?> · <?= $escapeHtml($savedAddress['phone']) ?></p>
                                    <p><?= $escapeHtml($savedAddress['address']) ?>, <?= $escapeHtml($savedAddress['city']) ?>, <?= $escapeHtml($savedAddress['department']) ?><?= $savedAddress['postal_code'] ? ' · CP ' . $escapeHtml($savedAddress['postal_code']) : '' ?></p>
                                </div>
                                <div class="account-address-actions">
                                    <?php if (!$savedAddress['is_default']): ?>
                                        <form method="post" action="account.php#addresses">
                                            <input type="hidden" name="csrf_token" value="<?= $escapeHtml($csrfToken) ?>">
                                            <input type="hidden" name="address_action" value="set_default">
                                            <input type="hidden" name="address_id" value="<?= $savedAddress['id'] ?>">
                                            <button type="submit" class="account-address-action">Hacer predeterminada</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="account.php#addresses" onsubmit="return confirm('¿Eliminar esta dirección guardada?');">
                                        <input type="hidden" name="csrf_token" value="<?= $escapeHtml($csrfToken) ?>">
                                        <input type="hidden" name="address_action" value="delete_address">
                                        <input type="hidden" name="address_id" value="<?= $savedAddress['id'] ?>">
                                        <button type="submit" class="account-address-action is-danger">Eliminar</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="account-reviews-empty">Aún no has guardado direcciones.</p>
                <?php endif; ?>
            </section>

            <section class="account-reviews" aria-labelledby="accountReviewsTitle">
                <div class="account-reviews-heading">
                    <div>
                        <p class="account-eyebrow">Tu actividad</p>
                        <h2 id="accountReviewsTitle">Mis reseñas</h2>
                    </div>
                    <?php if ($reviewsLoaded): ?>
                        <span class="account-review-count"><?= count($reviews) ?> <?= count($reviews) === 1 ? 'reseña' : 'reseñas' ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!$reviewsLoaded): ?>
                    <p class="account-reviews-empty" role="alert">No pudimos cargar tus reseñas. Inténtalo de nuevo más tarde.</p>
                <?php elseif (!$reviews): ?>
                    <p class="account-reviews-empty">Aún no has dejado reseñas.</p>
                <?php else: ?>
                    <div class="account-review-list">
                        <?php foreach ($reviews as $review): ?>
                            <article class="account-review">
                                <div class="account-review-heading">
                                    <div>
                                        <h3><?= $escapeHtml($review['product_name']) ?></h3>
                                        <p class="account-review-date"><?= $escapeHtml($review['created_at']) ?></p>
                                    </div>
                                    <span class="account-review-stars" aria-label="Calificación: <?= $review['rating'] ?> de 5 estrellas">
                                        <?= str_repeat('★', $review['rating']) ?><span class="account-review-stars-empty" aria-hidden="true"><?= str_repeat('★', 5 - $review['rating']) ?></span>
                                    </span>
                                </div>
                                <p class="account-review-comment"><?= $escapeHtml($review['comment'] !== '' ? $review['comment'] : 'Sin comentario.') ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="js/app.js"></script>
    <script src="js/theme.js"></script>
    <script src="js/header.js"></script>
    <script src="js/carrito.js"></script>
    <script src="js/account-wishlist.js"></script>
</body>
</html>