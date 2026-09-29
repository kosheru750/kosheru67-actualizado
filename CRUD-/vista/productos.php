<?php
require_once __DIR__ . '/../includes/auth-admin.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Kosheru Admin</title>
    <link rel="stylesheet" href="../css/admin.css?v=admin-20260927-1">
    <link rel="stylesheet" href="../css/tablas.css?v=admin-20260927-1">
    <link rel="stylesheet" href="../css/formularios.css?v=admin-20260927-1">
    <link rel="stylesheet" href="../css/responsive-admin.css?v=admin-20260927-1">
    <link rel="icon" href="../../img/logos/Isotipo transparente.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@600;800;900&display=swap" rel="stylesheet">
</head>
<body class="admin-body">

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="admin-main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="admin-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h1 class="admin-page-title" style="margin: 0;">Gestión de Productos</h1>
                <button type="button" class="btn-primary" onclick="AdminProductos.openModal()" style="border: none;">
                    ➕ Agregar Nuevo Producto
                </button>
            </div>

            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Origen</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="prodTableBody">
                        <!-- Carga dinámicamente vía JS -->
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal Agregar / Editar Producto -->
    <div class="admin-modal-overlay" id="modalProdOverlay">
        <div class="admin-modal-box">
            <div class="admin-modal-header">
                <div>
                    <div class="admin-modal-kicker">Catálogo Kosheru</div>
                    <div class="admin-modal-title" id="modalProdTitle">Agregar Producto</div>
                    <p class="admin-modal-subtitle">Completa la ficha para incorporar un producto al catálogo.</p>
                </div>
                <button type="button" class="admin-modal-close" onclick="AdminProductos.closeModal()" aria-label="Cerrar formulario">&times;</button>
            </div>
            <form id="formProductModal" class="product-form" enctype="multipart/form-data">
                <input type="hidden" name="id" id="prodId">

                <div class="form-group">
                    <label for="prodName">Nombre del Producto *</label>
                    <input type="text" name="name" id="prodName" placeholder="ej. Tofu Orgánico Extra Firme" required>
                </div>

                <div class="product-form-grid">
                    <div class="form-group">
                        <label>Categorías *</label>
                        <div class="category-checkboxes" id="prodCategory">
                            <label class="category-checkbox"><input type="checkbox" name="categories[]" value="Proteínas Especiales"> Proteínas Especiales</label>
                            <label class="category-checkbox"><input type="checkbox" name="categories[]" value="Súper Alimentos"> Súper Alimentos</label>
                            <label class="category-checkbox"><input type="checkbox" name="categories[]" value="Tés & Condimentos"> Tés & Condimentos</label>
                            <label class="category-checkbox"><input type="checkbox" name="categories[]" value="Vegetarianos"> Vegetarianos</label>
                            <label class="category-checkbox"><input type="checkbox" name="categories[]" value="sin gluten"> Sin Gluten</label>
                            <label class="category-checkbox"><input type="checkbox" name="categories[]" value="Veganos"> Veganos</label>
                            <label class="category-checkbox"><input type="checkbox" name="categories[]" value="reemplazos de carne"> Reemplazos de Carne</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="prodOrigin">Origen *</label>
                        <input type="text" name="origin" id="prodOrigin" placeholder="ej. JAPÓN 🇯🇵" required>
                    </div>
                </div>

                <div class="product-form-grid">
                    <div class="form-group">
                        <label for="prodPrice">Precio (COP) *</label>
                        <input type="number" step="100" name="price" id="prodPrice" placeholder="18900" required>
                    </div>

                    <div class="form-group">
                        <label for="prodStock">Stock Inicial *</label>
                        <input type="number" name="stock" id="prodStock" placeholder="45" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="prodImageFile">Subir Imagen (JPG, PNG, WEBP)</label>
                    <input type="file" name="image_file" id="prodImageFile" accept="image/jpeg,image/png,image/webp">
                </div>

                <div class="form-group">
                    <label for="prodDescription">Descripción Breve *</label>
                    <textarea name="description" id="prodDescription" rows="3" placeholder="Descripción clara del superalimento..." required></textarea>
                </div>

                <div class="product-form-actions">
                    <button type="submit" class="btn-primary product-form-submit">Guardar Producto</button>
                    <button type="button" class="btn-secondary product-form-cancel" onclick="AdminProductos.closeModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../js/app.js"></script>
    <script src="../js/admin.js"></script>
    <script src="../js/productos.js"></script>
</body>
</html>
