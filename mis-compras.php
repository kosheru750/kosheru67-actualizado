<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/modelo/connection.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth(); // Redirige a login si no hay sesión

$user = getCurrentUser();
$user_id = (int)$user['id'];

// Obtener compras del usuario autenticado directo de MySQL
$sql = "SELECT id, invoice_number, subtotal, tax_iva, total_price, order_date, order_time, status 
        FROM purchases 
        WHERE user_id = $user_id 
        ORDER BY id DESC";
$res = mysqli_query($conn, $sql);
$compras = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['total_formatted'] = formatCOP($row['total_price']);
        $compras[] = $row;
    }
}

$fac_param = isset($_GET['fac']) ? htmlspecialchars(trim($_GET['fac'])) : '';
$tracking_steps = [
    'pendiente' => 'Pedido recibido',
    'preparando' => 'En preparación',
    'enviado' => 'En camino',
    'completado' => 'Entregado'
];
$purchase_status_labels = $tracking_steps + ['cancelado' => 'Cancelado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Compras & Facturas - Kosheru</title>
    <script>
        (function () {
            var saved = localStorage.getItem('kosheru_theme');
            document.documentElement.setAttribute('data-theme', saved === 'dark' ? 'dark' : 'light');
        })();
    </script>
    <link rel="stylesheet" href="css/style.css?v=mobile-20260927-6">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/factura.css">
    <link rel="icon" href="img/logos/Isotipo transparente.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@600;800;900&display=swap" rel="stylesheet">
        <style>
      body {
                background-color: var(--color-bg-card);
                background-image: url('img/logos/fondo_claro.jpg');
                background-size: cover;
                background-position: center top;
                background-attachment: scroll;
        color: var(--color-texto-blanco);
    }

        html[data-theme="dark"] body {
                background-image: url('img/logos/fondo_oscuro.jpg');
        }

    .section-title-wrap { text-align: center; max-width: 700px; margin: 0 auto 2.5rem auto; }
    .mis-compras-page .section-title { font-size: 2.2rem; font-weight: 800; color: var(--color-texto-blanco); }
    .mis-compras-page .section-subtitle { color: var(--color-texto-suave) !important; }

        .mis-compras-page {
            width: 100%;
            max-width: 1100px;
            min-width: 0;
            box-sizing: border-box;
            margin: 2rem auto 4rem;
            padding: 0 1.25rem;
        }

        .mis-compras-panel {
            min-width: 0;
            max-width: 100%;
            background: var(--color-bg-card);
            border: 1px solid var(--color-borde-card);
            border-radius: 22px;
            box-shadow: var(--sombra-card);
            padding: 1.5rem;
            overflow-x: auto;
        }

        .mis-compras-panel table {
            width: 100%;
            border-collapse: collapse;
            color: var(--color-texto-blanco);
        }

        .mis-compras-panel thead tr {
            background: var(--color-bg-panel);
            color: var(--color-texto-blanco);
        }

        .mis-compras-panel th,
        .mis-compras-panel td {
            padding: 1rem;
            border-bottom: 1px solid var(--color-borde-card);
        }

        .mis-compras-panel tbody tr:hover {
            background: var(--color-bg-panel);
        }

        .mis-compras-search {
            background: var(--color-bg-card);
            border: 1px solid var(--color-borde-card);
            border-radius: 18px;
            box-shadow: var(--sombra-suave);
        }

        .mis-compras-search input {
            background: transparent;
            color: var(--color-texto-blanco);
        }

        .mis-compras-search input::placeholder {
            color: var(--color-texto-muted);
        }

        .mis-compras-page .btn-primary {
            background: linear-gradient(135deg, var(--color-rojo-kosheru), var(--color-rojo-oscuro));
            color: #fff;
        }

        #invoiceModalContainer {
            background: color-mix(in srgb, var(--color-rojo-oscuro) 55%, transparent);
        }

        @media (max-width: 640px) {
            .mis-compras-page {
                margin: 1.25rem auto 3rem;
                padding: 0 1rem;
            }

            .mis-compras-page .section-title {
                font-size: 1.75rem;
            }

            .mis-compras-search {
                margin-bottom: 1.25rem !important;
            }

            .mis-compras-panel {
                padding: 1rem 0.75rem;
                overflow: visible;
            }

            .purchase-history-table,
            .purchase-history-table tbody {
                display: block;
                width: 100%;
            }

            .purchase-history-table thead {
                display: none;
            }

            .purchase-history-table tbody {
                display: grid;
                gap: 0.8rem;
            }

            .purchase-history-table tbody tr {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.55rem 0.75rem;
                padding: 0.85rem;
                border: 1px solid var(--color-borde-card) !important;
                border-radius: 8px;
                background: var(--color-bg-card);
            }

            .purchase-history-table tbody td {
                display: flex;
                min-width: 0;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
                padding: 0 !important;
                border: 0;
                text-align: left !important;
                overflow-wrap: anywhere;
            }

            .purchase-history-table tbody td::before {
                content: attr(data-label);
                color: var(--color-texto-muted);
                font-size: 0.68rem;
                font-weight: 800;
                text-transform: uppercase;
            }

            .purchase-history-table tbody td[data-label="Factura"],
            .purchase-history-table tbody td[data-label="Estado"],
            .purchase-history-table tbody td[data-label="Acciones"] {
                grid-column: 1 / -1;
            }

            .purchase-history-table tbody td[data-label="Factura"] {
                padding-bottom: 0.5rem !important;
                border-bottom: 1px solid var(--color-borde-card);
            }

            .purchase-history-table tbody td[data-label="Estado"] {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
                flex-wrap: wrap;
            }

            .purchase-history-table .order-tracking {
                margin: 0;
            }

            .purchase-history-table tbody td[data-label="Acciones"] {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.5rem;
                padding-top: 0.55rem !important;
                border-top: 1px solid var(--color-borde-card);
            }

            .purchase-history-table tbody td[data-label="Acciones"]::before {
                grid-column: 1 / -1;
            }

            .purchase-history-table tbody td[data-label="Acciones"] .btn-primary {
                width: 100%;
                min-height: 42px;
                margin-top: 0 !important;
                padding: 0.45rem 0.5rem !important;
                font-size: 0.78rem !important;
            }
        }

    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="mis-compras-page">
        <div class="section-title-wrap">
            <h1 class="section-title">Mis Compras</h1>
            <p class="section-subtitle" style="text-transform: none; font-size: 1rem; color: var(--color-texto-suave); margin-top: 0.3rem;">
                Historial de pedidos y consulta de Facturas Electrónicas
            </p>
        </div>

        <!-- Buscador de Factura Especifica FAC -->
        <div class="tienda-search-bar mis-compras-search" style="margin-bottom: 2.5rem;">
            <img src="img/icons/credit-card-alt.svg" alt="Buscar factura" class="search-icon" aria-hidden="true">
            <input type="text" id="inputSearchInvoice" placeholder="Consultar número de factura, ej. FAC-91622" value="<?= $fac_param ?>">
        </div>

        <!-- Historial de Compras del Usuario -->
        <div class="mis-compras-panel">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--color-dorado); margin-bottom: 1.2rem; border-bottom: 2px solid var(--color-borde-card); padding-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.04em;">
                Historial de Pedidos Realizados
            </h3>

            <?php if (empty($compras)): ?>
                <div class="empty-state" style="padding: 2.5rem 1rem;">
                    <div class="empty-state-icon"><img src="img/icons/basket.svg" alt="No hay compras" class="inline-icon" aria-hidden="true"></div>
                    <h3 class="empty-state-title">No tienes compras aún</h3>
                    <p class="empty-state-desc">Explora nuestra tienda de superalimentos y realiza tu primer pedido.</p>
                    <a href="tienda.php" class="btn-primary" style="margin-top: 1rem; inline-size: fit-content;">Ir a la Tienda</a>
                </div>
            <?php else: ?>
                <table class="purchase-history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: var(--color-bg-panel); text-transform: uppercase; font-size: 0.85rem; font-weight: 800; color: var(--color-texto-blanco);">
                            <th style="padding: 0.8rem 1rem; text-align: left;">N° Factura</th>
                            <th style="padding: 0.8rem 1rem; text-align: left;">Fecha</th>
                            <th style="padding: 0.8rem 1rem; text-align: right;">Total</th>
                            <th style="padding: 0.8rem 1rem; text-align: center;">Estado</th>
                            <th style="padding: 0.8rem 1rem; text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras as $c): ?>
                            <?php
                            $purchase_status = (string)$c['status'];
                            $status_label = $purchase_status_labels[$purchase_status] ?? ucfirst($purchase_status);
                            $tracking_index = array_search($purchase_status, array_keys($tracking_steps), true);
                            ?>
                            <tr style="border-bottom: 1px solid var(--color-borde-card);">
                                <td data-label="Factura" style="padding: 1rem; font-weight: 800; color: var(--color-naranja-brillante);"><?= htmlspecialchars($c['invoice_number']) ?></td>
                                <td data-label="Fecha" style="padding: 1rem; font-size: 0.9rem; color: var(--color-texto-suave);"><?= htmlspecialchars($c['order_date']) ?></td>
                                <td data-label="Total" style="padding: 1rem; text-align: right; font-weight: 800; color: var(--color-texto-blanco);"><?= $c['total_formatted'] ?></td>
                                <td data-label="Estado" style="padding: 1rem; text-align: center;">
                                    <span class="factura-status-badge badge-<?= htmlspecialchars($purchase_status) ?>">
                                        <?= htmlspecialchars($status_label) ?>
                                    </span>
                                    <details class="order-tracking">
                                        <summary>Seguimiento</summary>
                                        <?php if ($purchase_status === 'cancelado'): ?>
                                            <p class="order-tracking-cancelled">Este pedido fue cancelado.</p>
                                        <?php else: ?>
                                            <ol class="order-tracking-steps">
                                                <?php $stepIndex = 0; foreach ($tracking_steps as $stepStatus => $stepLabel): ?>
                                                    <li class="<?= $tracking_index !== false && $stepIndex <= $tracking_index ? 'is-complete' : '' ?><?= $purchase_status === $stepStatus ? ' is-current' : '' ?>">
                                                        <?= htmlspecialchars($stepLabel) ?>
                                                    </li>
                                                <?php $stepIndex++; endforeach; ?>
                                            </ol>
                                        <?php endif; ?>
                                    </details>
                                </td>
                                <td data-label="Acciones" style="padding: 1rem; text-align: center;">
                                    <button type="button" class="btn-primary" style="padding: 0.4rem 1rem; font-size: 0.85rem;" onclick="KosheruMisCompras.cargarFactura('<?= htmlspecialchars($c['invoice_number']) ?>')">
                                        Ver factura
                                    </button>
                                    <button type="button" class="btn-primary" style="padding: 0.4rem 1rem; margin-top: 0.4rem; font-size: 0.85rem;" onclick="KosheruMisCompras.repetirCompra(<?= (int)$c['id'] ?>)">
                                        Repetir compra
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Modal / Vista de Factura Electrónica -->
        <div id="invoiceModalContainer" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); z-index: 3500; overflow-y: auto; padding: 2rem 1rem;">
            <div style="max-width: 880px; margin: 0 auto; position: relative;">
                <button type="button" onclick="document.getElementById('invoiceModalContainer').style.display='none';" style="position: absolute; right: 15px; top: 15px; z-index: 10; background: var(--color-bg-card); border-radius: 50%; width: 40px; height: 40px; font-size: 1.5rem; font-weight: bold; border: 2px solid var(--color-rojo-kosheru); color: var(--color-rojo-kosheru);">
                    &times;
                </button>
                <div id="invoiceModalContent"></div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="js/app.js"></script>
    <script src="js/theme.js"></script>
    <script src="js/header.js"></script>
    <script src="js/carrito.js"></script>
    <script src="js/checkout.js"></script>
    <script src="js/mis-compras.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const facParam = '<?= $fac_param ?>';
            if (facParam) {
                KosheruMisCompras.cargarFactura(facParam);
            }
        });
    </script>
</body>
</html>
