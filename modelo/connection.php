<?php
/**
 * Kosheru - Configuración Centralizada de Base de Datos y Auto-Migración
 * Compatible con XAMPP (Localhost) e InfinityFree (Hosting compartido sin SSH / sin mysqlnd)
 */

// =========================================================================
// CONFIGURACIÓN DE CREDENCIALES
// Para desplegar en InfinityFree, modifica las siguientes 4 variables:
// =========================================================================
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "if0_42541968_logic_nova_db";

// Conexión MySQLi
$conn = @mysqli_connect($db_host, $db_user, $db_pass);

if (!$conn) {
    die("Error crítico de conexión a MySQL: " . mysqli_connect_error());
}

// Asegurar conjunto de caracteres UTF-8
mysqli_set_charset($conn, "utf8mb4");

// Intentar seleccionar la base de datos o crearla si es localhost
if (!@mysqli_select_db($conn, $db_name)) {
    $create_db_sql = "CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (mysqli_query($conn, $create_db_sql)) {
        mysqli_select_db($conn, $db_name);
    } else {
        die("Error al seleccionar la base de datos: " . mysqli_error($conn));
    }
}

/**
 * Función de Auto-Migración y Verificación del Esquema
 * Se ejecuta automáticamente para garantizar que todas las tablas y datos semilla existan.
 */
function setupDatabaseSchema($conn) {
    // 1. Tabla users
    $sql_users = "CREATE TABLE IF NOT EXISTS `users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `lastname` VARCHAR(100) NOT NULL,
      `username` VARCHAR(50) NOT NULL UNIQUE,
      `email` VARCHAR(120) NOT NULL UNIQUE,
      `password` VARCHAR(255) NOT NULL,
      `rol` ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
      `state` ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql_users);

    // 2. Tabla categories
    $sql_categories = "CREATE TABLE IF NOT EXISTS `categories` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `slug` VARCHAR(100) NOT NULL UNIQUE,
      `name` VARCHAR(100) NOT NULL,
      `description` TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql_categories);

    // 3. Tabla products
    $sql_products = "CREATE TABLE IF NOT EXISTS `products` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(150) NOT NULL,
      `category` VARCHAR(100) NOT NULL,
      `origin` VARCHAR(100) NOT NULL,
      `price` DECIMAL(12,2) NOT NULL,
      `stock` INT NOT NULL DEFAULT 0,
      `image` VARCHAR(255) NOT NULL,
      `description` TEXT NOT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_category` (`category`),
      INDEX `idx_name` (`name`),
      INDEX `idx_price` (`price`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql_products);

    // 4. Tabla providers
    $sql_providers = "CREATE TABLE IF NOT EXISTS `providers` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(150) NOT NULL,
      `contact_name` VARCHAR(100) NULL,
      `phone` VARCHAR(30) NULL,
      `email` VARCHAR(120) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql_providers);

    // 5. Tabla purchases
    $sql_purchases = "CREATE TABLE IF NOT EXISTS `purchases` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `invoice_number` VARCHAR(30) NOT NULL UNIQUE,
      `user_id` INT NOT NULL,
      `user_name` VARCHAR(150) NOT NULL,
      `user_email` VARCHAR(120) NOT NULL,
      `document_id` VARCHAR(30) NOT NULL,
      `address` VARCHAR(255) NOT NULL,
      `city` VARCHAR(100) NOT NULL,
      `phone` VARCHAR(30) NOT NULL,
      `payment_method` VARCHAR(50) NOT NULL,
      `subtotal` DECIMAL(12,2) NOT NULL,
      `tax_iva` DECIMAL(12,2) NOT NULL,
      `total_price` DECIMAL(12,2) NOT NULL,
      `order_date` DATE NOT NULL,
      `order_time` TIME NOT NULL,
    `status` ENUM('pendiente', 'preparando', 'enviado', 'completado', 'cancelado') NOT NULL DEFAULT 'pendiente',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_user_id` (`user_id`),
      INDEX `idx_invoice_number` (`invoice_number`),
      INDEX `idx_order_date` (`order_date`),
      INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql_purchases);

    // 6. Tabla purchase_items
    $sql_items = "CREATE TABLE IF NOT EXISTS `purchase_items` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `purchase_id` INT NOT NULL,
      `product_id` INT NOT NULL,
      `product_name` VARCHAR(150) NOT NULL,
      `quantity` INT NOT NULL,
      `unit_price` DECIMAL(12,2) NOT NULL,
      `total_price` DECIMAL(12,2) NOT NULL,
      INDEX `idx_purchase_id` (`purchase_id`),
      INDEX `idx_product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql_items);

    // 7. Tabla invoices
    $sql_invoices = "CREATE TABLE IF NOT EXISTS `invoices` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `invoice_number` VARCHAR(30) NOT NULL UNIQUE,
      `purchase_id` INT NOT NULL,
      `user_id` INT NOT NULL,
      `customer_name` VARCHAR(150) NOT NULL,
      `customer_email` VARCHAR(120) NOT NULL,
      `document_id` VARCHAR(30) NOT NULL,
      `address` VARCHAR(255) NOT NULL,
      `payment_method` VARCHAR(50) NOT NULL,
      `issue_date` DATE NOT NULL,
      `issue_time` TIME NOT NULL,
      `subtotal` DECIMAL(12,2) NOT NULL,
      `tax_iva` DECIMAL(12,2) NOT NULL,
      `total_amount` DECIMAL(12,2) NOT NULL,
      `status` ENUM('completado', 'pendiente', 'cancelado') NOT NULL DEFAULT 'completado',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_invoice_number_inv` (`invoice_number`),
      INDEX `idx_user_id_inv` (`user_id`),
      INDEX `idx_purchase_id_inv` (`purchase_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql_invoices);

    // 8. Tabla stock_movements
    $sql_stock_mov = "CREATE TABLE IF NOT EXISTS `stock_movements` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `product_id` INT NOT NULL,
      `movement_type` ENUM('entrada', 'salida') NOT NULL,
      `quantity` INT NOT NULL,
      `previous_stock` INT NOT NULL,
      `new_stock` INT NOT NULL,
      `reason` VARCHAR(255) NOT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_stock_product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql_stock_mov);

    // 9. Tabla suggestions
    $sql_suggestions = "CREATE TABLE IF NOT EXISTS `suggestions` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `email` VARCHAR(120) NOT NULL,
      `message` TEXT NOT NULL,
      `status` ENUM('pendiente', 'revisado') NOT NULL DEFAULT 'pendiente',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_sugg_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql_suggestions);

    // 10. Tabla product_reviews (reseñas y calificación con estrellas)
    $sql_reviews = "CREATE TABLE IF NOT EXISTS `product_reviews` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `product_id` INT NOT NULL,
            `user_id` INT NULL,
      `customer_name` VARCHAR(100) NOT NULL,
      `rating` TINYINT UNSIGNED NOT NULL,
      `comment` TEXT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_review_product_id` (`product_id`),
            INDEX `idx_review_user_id` (`user_id`),
      CHECK (`rating` BETWEEN 1 AND 5)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql_reviews);

    // --- MIGRACIÓN DE SEMILLAS (SÓLO SI NO EXISTEN) ---

    $check_admin = mysqli_query($conn, "SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    if ($check_admin && mysqli_num_rows($check_admin) == 0) {
        $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $sql_admin = "INSERT INTO users (name, lastname, username, email, password, rol, state) 
                      VALUES ('Administrador', 'Kosheru', 'admin', 'admin@kosheru.com', '$admin_pass', 'admin', 'activo')";
        mysqli_query($conn, $sql_admin);
    }

    $check_cat = mysqli_query($conn, "SELECT COUNT(*) as total FROM categories");
    $cat_row = mysqli_fetch_assoc($check_cat);
    if ($cat_row['total'] == 0) {
        $sql_cats = "INSERT INTO categories (slug, name, description) VALUES
            ('proteinas-especiales', 'Proteínas Especiales', 'Proteínas de origen vegetal y alta biodisponibilidad orgánicas e importadas.'),
            ('super-alimentos', 'Súper Alimentos', 'Alimentos densos en nutrientes, antioxidantes, fibras y vitaminas puras.'),
            ('tes-condimentos', 'Tés & Condimentos', 'Selección refinada de tés ancestrales, especias y sazonadores naturales.')";
        mysqli_query($conn, $sql_cats);
    }

    $check_prod = mysqli_query($conn, "SELECT COUNT(*) as total FROM products");
    $prod_row = mysqli_fetch_assoc($check_prod);
    if ($prod_row['total'] == 0) {
        $seeds = [
            // Proteínas Especiales
            ['Tofu Orgánico Extra Firme', 'Proteínas Especiales', 'JAPÓN 🇯🇵', 18900, 45, 'img/Tofu Ahumado Premium.png', 'Tofu de alta pureza rico en proteínas vegetales orgánicas, sin OMG ni conservantes.'],
            ['Proteína de Guisante Aislada 80%', 'Proteínas Especiales', 'BÉLGICA 🇧🇪', 65000, 30, 'img/Proteína Vegetal de Arveja.png', 'Proteína aislada de guisante orgánico hipoalergénica con perfil completo de aminoácidos.'],
            ['Proteína de Cáñamo Orgánica (Hemp)', 'Proteínas Especiales', 'CANADÁ 🇨🇦', 72000, 25, 'img/proteina de cañamo.png', 'Polvo de semilla de cáñamo natural cargado de edestina, albúmina y Omega 3.'],
            ['Spirulina Crunch Orgánica', 'Proteínas Especiales', 'HAWÁI 🇺🇸', 58000, 40, 'img/Espirulina Azul en Polvo.png', 'Microalga spirulina cultivada en aguas oceánicas puras, rica en fitocianina y hierro.'],
            ['Proteína de Semilla de Calabaza', 'Proteínas Especiales', 'AUSTRIA 🇦🇹', 49000, 20, 'img/Proteína Vegetal de Arveja.png', 'Concentrado orgánico de semilla de calabaza alto en zinc, magnesio y proteína vegana.'],
            ['Edamame Deshidratado Orgánico', 'Proteínas Especiales', 'JAPÓN 🇯🇵', 24000, 50, 'img/Avena Integral Orgánica.png', 'Snack crocante de vainas de soya tierna ricas en fibra soluble y aminoácidos esenciales.'],
            ['Tempeh de Soya Tradicional', 'Proteínas Especiales', 'INDONESIA 🇮🇩', 22000, 15, 'img/Tempeh de Soya Orgánica.png', 'Alimento fermentado artesanal súper digestivo y cargado de probióticos y proteína.'],
            ['Proteína de Arroz Integral Germinado', 'Proteínas Especiales', 'TAILANDIA 🇹🇭', 54000, 28, 'img/Proteína Vegetal de Arveja.png', 'Proteína hipoalergénica germinada a baja temperatura para conservar nutrientes.'],

            // Súper Alimentos
            ['Maca Andina Negra Orgánica', 'Súper Alimentos', 'PERÚ 🇵🇪', 38000, 60, 'img/Maca Andina en Polvo.png', 'Raíz sagrada de los Andes peruano recomendada para vigor, energía y balance hormonal.'],
            ['Polvo de Açai Orgánico Liofilizado', 'Súper Alimentos', 'BRASIL 🇧🇷', 68000, 35, 'img/Acai.png', 'Fruto silvestre de la Amazonía brasileña concentrado en antioxidantes y polifenoles.'],
            ['Semillas de Chía Orgánica Negra', 'Súper Alimentos', 'PARAGUAY 🇵🇾', 16500, 80, 'img/Semillas de Chía Negra.png', 'Supersemilla rica en fibra mucilaginosa y ácidos grasos esenciales Omega 3.'],
            ['Quinoa Real Blanca en Grano', 'Súper Alimentos', 'BOLIVIA 🇧🇴', 19800, 75, 'img/Quinoa Orgánica Premium.png', 'Grano ancestral de los salares andinos sin gluten con proteína completa.'],
            ['Hifas de Cordyceps Militaris', 'Súper Alimentos', 'TÍBET 🇨🇳', 95000, 12, 'img/cordiceps.png', 'Hongo adaptógeno milenario valorado para optimizar el rendimiento físico y respiratorio.'],
            ['Melena de León (Lion\'s Mane)', 'Súper Alimentos', 'JAPÓN 🇯🇵', 88000, 18, 'img/melena de leon.png', 'Hongo funcional puro procesado para fomentar la claridad mental y neurogénesis.'],
            ['Baobab en Polvo Orgánico', 'Súper Alimentos', 'SENEGAL 🇸🇳', 42000, 22, 'img/baobab.png', 'Superfruta africana rica en vitamina C natural, electrolitos y prebiotic pectina.'],
            ['Bayas de Goji Silvestres', 'Súper Alimentos', 'HIMALAYA 🇳🇵', 36000, 40, 'img/Bayas Goji Premium.png', 'Frutos secos del Himalaya conocidos como la baya de la longevidad.'],
            ['Camu Camu Salvaje en Polvo', 'Súper Alimentos', 'PERÚ 🇵🇪', 46000, 30, 'img/camu camu.png', 'La mayor fuente vegetal de Vitamina C bioasimilable del planeta Tierra.'],
            ['Cacao Puro en Nibs Orgánico', 'Súper Alimentos', 'ECUADOR 🇪🇨', 29000, 50, 'img/nibs de cacao.png', 'Trozos crudos de grano de cacao fino de aroma rico en magnesio y anandamina.'],

            // Tés & Condimentos
            ['Té Matcha Grado Ceremonial', 'Tés & Condimentos', 'UJI JAPÓN 🇯🇵', 79000, 50, 'img/Matcha Ceremonial Grado A+.png', 'Hojas de té verde finamente molidas en piedra volcánica con L-teanina concentrada.'],
            ['Cúrcuma Cero Toxinas en Polvo', 'Tés & Condimentos', 'INDIA 🇮🇳', 21000, 65, 'img/Cúrcuma Molida Premium.png', 'Raíz aromática con 5% garantizado de curcuminoides activos y piperina.'],
            ['Jengibre Dorado Orgánico molido', 'Tés & Condimentos', 'INDIA 🇮🇳', 18500, 60, 'img/Jengibre en Polvo.png', 'Sazonador termogénico y digestivo cultivado de forma 100% libre de pesticidas.'],
            ['Té Oolong Gran Reserva Wuyi', 'Tés & Condimentos', 'CHINA 🇨🇳', 45000, 25, 'img/té oolong.png', 'Té azul semifermentado artesanal de fragancia orquídea y matices tostados.'],
            ['Sal Rosa del Himalaya Molida', 'Tés & Condimentos', 'PAKISTÁN 🇵🇰', 14000, 100, 'img/sal rosa del himalaya.png', 'Sal cristalina sin refinación química con más de 84 oligoelementos naturales.'],
            ['Ashwagandha KSM-66 en Polvo', 'Tés & Condimentos', 'INDIA 🇮🇳', 52000, 35, 'img/ashwagandha.png', 'Raíz adaptógena india idónea para conciliar calma mental y reducir cortisol.'],
            ['Garcinia Cambogia Extracto', 'Tés & Condimentos', 'SRI LANKA 🇱🇰', 48000, 20, 'img/garcinia.png', 'Extracto estandarizado en ácido hidroxicítrico (HCA) natural.']
        ];

        foreach ($seeds as $s) {
            $name = mysqli_real_escape_string($conn, $s[0]);
            $category = mysqli_real_escape_string($conn, $s[1]);
            $origin = mysqli_real_escape_string($conn, $s[2]);
            $price = (float)$s[3];
            $stock = (int)$s[4];
            $image = mysqli_real_escape_string($conn, $s[5]);
            $desc = mysqli_real_escape_string($conn, $s[6]);

            $sql_p = "INSERT INTO products (name, category, origin, price, stock, image, description) 
                      VALUES ('$name', '$category', '$origin', $price, $stock, '$image', '$desc')";
            mysqli_query($conn, $sql_p);
        }
    }

    // --- MIGRACIÓN ACTIVA DE DATOS EXISTENTES (IMÁGENES Y CATEGORÍAS) ---
    $correct_images = [
        'Tofu Orgánico Extra Firme' => 'img/Tofu Ahumado Premium.png',
        'Proteína de Guisante Aislada 80%' => 'img/Proteína Vegetal de Arveja.png',
        'Proteína de Cáñamo Orgánica (Hemp)' => 'img/proteina de cañamo.png',
        'Spirulina Crunch Orgánica' => 'img/Espirulina Azul en Polvo.png',
        'Proteína de Semilla de Calabaza' => 'img/Proteína Vegetal de Arveja.png',
        'Edamame Deshidratado Orgánico' => 'img/Avena Integral Orgánica.png',
        'Tempeh de Soya Tradicional' => 'img/Tempeh de Soya Orgánica.png',
        'Proteína de Arroz Integral Germinado' => 'img/Proteína Vegetal de Arveja.png',
        'Maca Andina Negra Orgánica' => 'img/Maca Andina en Polvo.png',
        'Polvo de Açai Orgánico Liofilizado' => 'img/Acai.png',
        'Semillas de Chía Orgánica Negra' => 'img/Semillas de Chía Negra.png',
        'Quinoa Real Blanca en Grano' => 'img/Quinoa Orgánica Premium.png',
        'Hifas de Cordyceps Militaris' => 'img/cordiceps.png',
        'Melena de León (Lion\'s Mane)' => 'img/melena de leon.png',
        'Baobab en Polvo Orgánico' => 'img/baobab.png',
        'Bayas de Goji Silvestres' => 'img/Bayas Goji Premium.png',
        'Camu Camu Salvaje en Polvo' => 'img/camu camu.png',
        'Cacao Puro en Nibs Orgánico' => 'img/nibs de cacao.png',
        'Té Matcha Grado Ceremonial' => 'img/Matcha Ceremonial Grado A+.png',
        'Cúrcuma Cero Toxinas en Polvo' => 'img/Cúrcuma Molida Premium.png',
        'Jengibre Dorado Orgánico molido' => 'img/Jengibre en Polvo.png',
        'Té Oolong Gran Reserva Wuyi' => 'img/té oolong.png',
        'Sal Rosa del Himalaya Molida' => 'img/sal rosa del himalaya.png',
        'Ashwagandha KSM-66 en Polvo' => 'img/ashwagandha.png',
        'Garcinia Cambogia Extracto' => 'img/garcinia.png',
        'Hamburguesa Vegana Gourmet' => 'img/Hamburguesa Vegana Gourmet.jpg',
        'Seitán Artesanal' => 'img/Seitán Artesanal.jpg',
        'Granola Artesanal Premium' => 'img/Granola Artesanal Premium.jpg',
        'Leche de Almendras Orgánica' => 'img/Leche de Almendras Orgánica.png',
        'Avena Integral Orgánica' => 'img/Avena Integral Orgánica.png'
    ];

    foreach ($correct_images as $prod_name => $correct_img_path) {
        $esc_name = mysqli_real_escape_string($conn, $prod_name);
        $esc_img = mysqli_real_escape_string($conn, $correct_img_path);
        // Actualizamos si contiene .svg o si tiene .../ delante
        mysqli_query($conn, "UPDATE products SET image = '$esc_img' WHERE name = '$esc_name'");
    }

    // Corregir también cualquier categoría codificada como entidad HTML
    mysqli_query($conn, "UPDATE products SET category = 'Tés & Condimentos' WHERE category = 'Tés &amp; Condimentos'");

    // Asegurar que las nuevas categorías existan en la tabla categories
    $new_categories = [
        ['vegetarianos', 'Vegetarianos', 'Alimentos 100% vegetarianos libres de ingredientes de origen animal.'],
        ['sin-gluten', 'sin gluten', 'Productos certificados libres de gluten y trazas.'],
        ['veganos', 'Veganos', 'Productos 100% veganos a base de plantas.'],
        ['reemplazos-de-carne', 'reemplazos de carne', 'Sustitutos cárnicos saludables y altos en proteína vegetal.']
    ];
    foreach ($new_categories as $cat) {
        $slug = $cat[0];
        $name = $cat[1];
        $desc = $cat[2];
        $check = mysqli_query($conn, "SELECT id FROM categories WHERE slug = '$slug' LIMIT 1");
        if ($check && mysqli_num_rows($check) == 0) {
            mysqli_query($conn, "INSERT INTO categories (slug, name, description) VALUES ('$slug', '$name', '$desc')");
        }
    }

    // Sembrar nuevos productos reales basados en las imágenes en img/
    $new_products = [
        ['Hamburguesa Vegana Gourmet', 'reemplazos de carne', 'COLOMBIA 🇨🇴', 24900, 20, 'img/Hamburguesa Vegana Gourmet.jpg', 'Hamburguesa a base de plantas con sabor ahumado gourmet y alta proteína.'],
        ['Seitán Artesanal', 'reemplazos de carne', 'COLOMBIA 🇨🇴', 16900, 15, 'img/Seitán Artesanal.jpg', 'Carne vegetal de trigo tierna, sazonada con especias naturales y lista para cocinar.'],
        ['Granola Artesanal Premium', 'Súper Alimentos', 'COLOMBIA 🇨🇴', 18500, 35, 'img/Granola Artesanal Premium.jpg', 'Mezcla crujiente de avena, frutos secos y endulzada naturalmente con miel de agave.'],
        ['Leche de Almendras Orgánica', 'Veganos', 'ESTADOS UNIDOS 🇺🇸', 12500, 40, 'img/Leche de Almendras Orgánica.png', 'Bebida vegetal cremosa de almendras seleccionadas sin azúcar añadida.'],
        ['Avena Integral Orgánica', 'sin gluten', 'CANADÁ 🇨🇦', 9500, 50, 'img/Avena Integral Orgánica.png', 'Hojuelas de avena integral ricas en fibra soluble, ideales para desayunos saludables.']
    ];

    foreach ($new_products as $p) {
        $name = mysqli_real_escape_string($conn, $p[0]);
        $cat = mysqli_real_escape_string($conn, $p[1]);
        $origin = mysqli_real_escape_string($conn, $p[2]);
        $price = (float)$p[3];
        $stock = (int)$p[4];
        $img = mysqli_real_escape_string($conn, $p[5]);
        $desc = mysqli_real_escape_string($conn, $p[6]);

        $check = mysqli_query($conn, "SELECT id FROM products WHERE name = '$name' LIMIT 1");
        if ($check && mysqli_num_rows($check) == 0) {
            mysqli_query($conn, "INSERT INTO products (name, category, origin, price, stock, image, description) 
                                 VALUES ('$name', '$cat', '$origin', $price, $stock, '$img', '$desc')");
        }
    }

    // --- SEMBRAR RESEÑAS DE EJEMPLO (SOLO SI LA TABLA ESTÁ VACÍA) ---
    // Reseñas de muestra para que la tienda no se vea "vacía" en la demo.
    // Puedes editarlas o borrarlas desde phpMyAdmin cuando tengas reseñas reales.
    $check_reviews = mysqli_query($conn, "SELECT COUNT(*) as total FROM product_reviews");
    $reviews_row = mysqli_fetch_assoc($check_reviews);
    if ($reviews_row['total'] == 0) {
        $review_seeds = [
            'Tofu Orgánico Extra Firme' => [
                ['Camila R.', 5, 'Excelente textura, no se deshace al cocinar. Ya es parte fija de mi dieta.'],
                ['Andrés M.', 4, 'Buen producto, aunque me gustaría que trajera más unidades por el precio.'],
                ['Laura V.', 5, 'El mejor tofu que he probado en Colombia, se nota el origen japonés.']
            ],
            'Té Matcha Grado Ceremonial' => [
                ['Sofía P.', 5, 'Sabor increíble, nada que ver con el matcha de supermercado.'],
                ['Julián T.', 5, 'Vino muy bien empacado y llegó rápido. Totalmente recomendado.']
            ],
            'Quinoa Real Blanca en Grano' => [
                ['Mariana G.', 4, 'Buena calidad, grano parejo y sin impurezas.'],
                ['Esteban C.', 5, 'La compro cada mes, nunca falla.']
            ],
            'Spirulina Crunch Orgánica' => [
                ['Daniela H.', 4, 'Se siente el cambio de energía después de dos semanas tomándola.'],
                ['Ricardo A.', 3, 'Buen producto pero el sabor es fuerte, hay que mezclarla bien.']
            ],
            'Maca Andina Negra Orgánica' => [
                ['Valentina S.', 5, 'Notable diferencia en energía diaria, y el empaque llegó perfecto.']
            ],
            'Ashwagandha KSM-66 en Polvo' => [
                ['Felipe N.', 5, 'Me ayudó bastante con el estrés del trabajo, la recomiendo.'],
                ['Paula D.', 4, 'Buena calidad, aunque el sabor no es el más agradable solo.']
            ],
            'Cacao Puro en Nibs Orgánico' => [
                ['Nicolás F.', 5, 'Perfecto para agregar al yogurt en las mañanas, sabor intenso y natural.']
            ],
            'Hamburguesa Vegana Gourmet' => [
                ['Isabella K.', 5, 'Sorprendente, la textura es casi idéntica a la carne. Toda mi familia quedó encantada.'],
                ['Santiago O.', 4, 'Muy buena opción vegana, el sabor ahumado se nota bastante.']
            ],
        ];

        foreach ($review_seeds as $product_name => $reviews) {
            $esc_pname = mysqli_real_escape_string($conn, $product_name);
            $res_prod = mysqli_query($conn, "SELECT id FROM products WHERE name = '$esc_pname' LIMIT 1");
            if ($res_prod && mysqli_num_rows($res_prod) > 0) {
                $prod_row = mysqli_fetch_assoc($res_prod);
                $pid = (int)$prod_row['id'];

                foreach ($reviews as $r) {
                    $esc_customer = mysqli_real_escape_string($conn, $r[0]);
                    $rating = (int)$r[1];
                    $esc_comment = mysqli_real_escape_string($conn, $r[2]);
                    mysqli_query($conn, "INSERT INTO product_reviews (product_id, customer_name, rating, comment) 
                                         VALUES ($pid, '$esc_customer', $rating, '$esc_comment')");
                }
            }
        }
    }
}

// =========================================================================
// GUARD DE MIGRACIÓN: solo ejecuta setupDatabaseSchema() UNA VEZ.
// Antes corría en cada request (15-20+ queries por carga de página).
// Ahora se usa un archivo bandera: si ya existe, se salta toda la
// migración con un solo file_exists() (una operación de disco, no de BD).
// Si alguna vez agregas una tabla nueva o más semillas, borra el archivo
// ".schema_migrated" en esta misma carpeta para forzar que se vuelva a
// ejecutar una sola vez.
// =========================================================================
$schema_flag_file = __DIR__ . '/.schema_migrated';
$image_flag_file = __DIR__ . '/.product_images_migrated';

if (!file_exists($schema_flag_file) || !file_exists($image_flag_file)) {
    setupDatabaseSchema($conn);
    @file_put_contents($schema_flag_file, 'Migración ejecutada: ' . date('Y-m-d H:i:s'));
    @file_put_contents($image_flag_file, 'Migración de imágenes ejecutada: ' . date('Y-m-d H:i:s'));
}

$user_addresses_flag_file = __DIR__ . '/.user_addresses_migrated';
if (!file_exists($user_addresses_flag_file)) {
    $user_addresses_result = mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `user_addresses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `label` VARCHAR(100) NOT NULL,
        `recipient_name` VARCHAR(150) NOT NULL,
        `phone` VARCHAR(30) NOT NULL,
        `address` VARCHAR(255) NOT NULL,
        `city` VARCHAR(100) NOT NULL,
        `department` VARCHAR(100) NOT NULL,
        `postal_code` VARCHAR(20) NULL,
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_addresses_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($user_addresses_result) {
        @file_put_contents($user_addresses_flag_file, 'Migración de direcciones ejecutada: ' . date('Y-m-d H:i:s'));
    }
}

$purchase_tracking_flag_file = __DIR__ . '/.purchase_tracking_migrated';
if (!file_exists($purchase_tracking_flag_file)) {
    $purchase_status_column = mysqli_query($conn, "SHOW COLUMNS FROM `purchases` LIKE 'status'");
    if ($purchase_status_column) {
        $purchase_tracking_migration = mysqli_query(
            $conn,
            "ALTER TABLE `purchases` MODIFY `status` ENUM('pendiente', 'preparando', 'enviado', 'completado', 'cancelado') NOT NULL DEFAULT 'pendiente'"
        );

        if ($purchase_tracking_migration) {
            @file_put_contents($purchase_tracking_flag_file, 'Migración de seguimiento de pedidos ejecutada: ' . date('Y-m-d H:i:s'));
        }
    }
}

$review_user_id_flag_file = __DIR__ . '/.review_user_id_migrated';
if (!file_exists($review_user_id_flag_file)) {
    $review_user_id_column = mysqli_query($conn, "SHOW COLUMNS FROM `product_reviews` LIKE 'user_id'");
    if ($review_user_id_column) {
        $review_migration_ok = true;

        if (mysqli_num_rows($review_user_id_column) === 0) {
            $review_migration_ok = mysqli_query(
                $conn,
                'ALTER TABLE `product_reviews` ADD COLUMN `user_id` INT NULL AFTER `product_id`'
            );
        }

        if ($review_migration_ok) {
            $review_user_id_index = mysqli_query(
                $conn,
                "SHOW INDEX FROM `product_reviews` WHERE Key_name = 'idx_review_user_id'"
            );

            if ($review_user_id_index && mysqli_num_rows($review_user_id_index) === 0) {
                $review_migration_ok = mysqli_query(
                    $conn,
                    'ALTER TABLE `product_reviews` ADD INDEX `idx_review_user_id` (`user_id`)'
                );
            } elseif (!$review_user_id_index) {
                $review_migration_ok = false;
            }
        }

        if ($review_migration_ok) {
            @file_put_contents($review_user_id_flag_file, 'Migración de usuario en reseñas ejecutada: ' . date('Y-m-d H:i:s'));
        }
    }
}

$category_relation_flag_file = __DIR__ . '/.product_categories_migrated';
if (!file_exists($category_relation_flag_file)) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `product_categories` (
        `product_id` INT NOT NULL,
        `category_id` INT NOT NULL,
        PRIMARY KEY (`product_id`, `category_id`),
        INDEX `idx_product_categories_category` (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($conn, "INSERT IGNORE INTO product_categories (product_id, category_id)
        SELECT p.id, c.id
        FROM products p
        INNER JOIN categories c ON c.name = p.category");

    @file_put_contents($category_relation_flag_file, 'Migración de categorías múltiples ejecutada: ' . date('Y-m-d H:i:s'));
}

function syncProductCategories($conn, $product_id, $category_names) {
    $product_id = (int)$product_id;
    if ($product_id <= 0) return;

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `product_categories` (
        `product_id` INT NOT NULL,
        `category_id` INT NOT NULL,
        PRIMARY KEY (`product_id`, `category_id`),
        INDEX `idx_product_categories_category` (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    mysqli_query($conn, "DELETE FROM product_categories WHERE product_id = $product_id");

    foreach (array_unique(array_filter(array_map('trim', (array)$category_names))) as $category_name) {
        $escaped_category = mysqli_real_escape_string($conn, $category_name);
        $category_result = mysqli_query($conn, "SELECT id FROM categories WHERE name = '$escaped_category' LIMIT 1");
        if ($category_result && ($category = mysqli_fetch_assoc($category_result))) {
            $category_id = (int)$category['id'];
            mysqli_query($conn, "INSERT IGNORE INTO product_categories (product_id, category_id) VALUES ($product_id, $category_id)");
        }
    }
}

// Productos de la carpeta img/ que corresponden a reemplazos de carne.
$meat_replacement_flag_file = __DIR__ . '/.meat_replacements_migrated';
if (!file_exists($meat_replacement_flag_file)) {
    $meat_replacement_products = [
        ['Bocaditos Veganos de Tocino', 'COLOMBIA 🇨🇴', 15900, 'img/alternativa_vegana_bocaditos_de_tocino.png', 'Bocaditos vegetales con sabor ahumado, ideales para reemplazar el tocino.'],
        ['Carne de Res a Base de Plantas', 'COLOMBIA 🇨🇴', 28900, 'img/carne_de_res_a_base_de_plantas.png', 'Alternativa vegetal con textura y sabor inspirados en la carne de res.'],
        ['Hamburguesa Vegetariana', 'COLOMBIA 🇨🇴', 22900, 'img/hamburguesa_vegetariana.png', 'Hamburguesa vegetal lista para preparar como alternativa a la carne.'],
        ['Milanesa de Quinoa', 'COLOMBIA 🇨🇴', 19900, 'img/milanesa_de_quinoa.avif', 'Milanesa vegetal de quinoa, crujiente y lista para cocinar.'],
        ['Morcilla Vegana', 'COLOMBIA 🇨🇴', 21900, 'img/morcilla_vegana.avif', 'Alternativa vegetal sazonada con el perfil tradicional de la morcilla.'],
        ['Nuggets de Soya', 'COLOMBIA 🇨🇴', 17900, 'img/Nuggets_de_soya.png', 'Nuggets vegetales de soya, ideales para reemplazar los nuggets de pollo.'],
        ['Sustituto de Carne de Soya', 'COLOMBIA 🇨🇴', 14900, 'img/sustituto_de_carne_de_soya.png', 'Proteína vegetal de soya para preparar recetas tradicionalmente hechas con carne.']
    ];

    foreach ($meat_replacement_products as $product) {
        $product_name = mysqli_real_escape_string($conn, $product[0]);
        $product_origin = mysqli_real_escape_string($conn, $product[1]);
        $product_image = mysqli_real_escape_string($conn, $product[3]);
        $product_description = mysqli_real_escape_string($conn, $product[4]);
        $existing_product = mysqli_query($conn, "SELECT id FROM products WHERE name = '$product_name' LIMIT 1");

        if ($existing_product && mysqli_num_rows($existing_product) === 0) {
            mysqli_query($conn, "INSERT INTO products (name, category, origin, price, stock, image, description)
                VALUES ('$product_name', 'reemplazos de carne', '$product_origin', {$product[2]}, 25, '$product_image', '$product_description')");
        } else {
            mysqli_query($conn, "UPDATE products SET category = 'reemplazos de carne', image = '$product_image' WHERE name = '$product_name'");
        }
    }

    @file_put_contents($meat_replacement_flag_file, 'Migración de reemplazos de carne ejecutada: ' . date('Y-m-d H:i:s'));
}

// Productos vegetarianos de img/ que todavía no estaban registrados.
$vegetarian_products_flag_file = __DIR__ . '/.vegetarian_products_migrated';
if (!file_exists($vegetarian_products_flag_file)) {
    $vegetarian_products = [
        ['Miso Rojo Fermentado', 'JAPÓN 🇯🇵', 12900, 'img/Miso Rojo Fermentado.png', 'Pasta de soya fermentada, versátil para sopas, salsas y preparaciones vegetarianas.'],
        ['Aceite de Coco Extra Virgen', 'FILIPINAS 🇵🇭', 18900, 'img/Aceite de Coco Extra Virgen.png', 'Aceite vegetal extra virgen para cocinar y complementar recetas vegetarianas.'],
        ['Condimento a Base de Plantas', 'COLOMBIA 🇨🇴', 9900, 'img/condimento_a_base_de_plantas.png', 'Condimento vegetal para dar sabor a preparaciones vegetarianas.'],
        ['Proteína Vegetal', 'COLOMBIA 🇨🇴', 29900, 'img/proteína_vegetal.png', 'Proteína de origen vegetal para complementar una alimentación vegetariana.']
    ];

    foreach ($vegetarian_products as $product) {
        $product_name = mysqli_real_escape_string($conn, $product[0]);
        $product_origin = mysqli_real_escape_string($conn, $product[1]);
        $product_image = mysqli_real_escape_string($conn, $product[3]);
        $product_description = mysqli_real_escape_string($conn, $product[4]);
        $existing_product = mysqli_query($conn, "SELECT id FROM products WHERE name = '$product_name' LIMIT 1");

        if ($existing_product && mysqli_num_rows($existing_product) === 0) {
            mysqli_query($conn, "INSERT INTO products (name, category, origin, price, stock, image, description)
                VALUES ('$product_name', 'Vegetarianos', '$product_origin', {$product[2]}, 25, '$product_image', '$product_description')");
        } else {
            mysqli_query($conn, "UPDATE products SET category = 'Vegetarianos', image = '$product_image' WHERE name = '$product_name'");
        }
    }

    @file_put_contents($vegetarian_products_flag_file, 'Migración de vegetarianos ejecutada: ' . date('Y-m-d H:i:s'));
}

// Productos naturalmente sin gluten.
$gluten_free_flag_file = __DIR__ . '/.gluten_free_products_migrated';
if (!file_exists($gluten_free_flag_file)) {
    $gluten_free_products = [
        'Quinoa Real Blanca en Grano',
        'Semillas de Chía Orgánica Negra',
        'Avena Integral Orgánica',
        'Proteína de Arroz Integral Germinado'
    ];

    foreach ($gluten_free_products as $product_name) {
        $escaped_name = mysqli_real_escape_string($conn, $product_name);
        mysqli_query($conn, "UPDATE products SET category = 'sin gluten' WHERE name = '$escaped_name'");
    }

    @file_put_contents($gluten_free_flag_file, 'Migración sin gluten ejecutada: ' . date('Y-m-d H:i:s'));
}

// Productos cuya descripción identifica explícitamente una opción vegana.
$vegan_products_flag_file = __DIR__ . '/.vegan_products_migrated';
if (!file_exists($vegan_products_flag_file)) {
    $vegan_products = [
        'Tofu Orgánico Extra Firme',
        'Tempeh de Soya Tradicional',
        'Miso Rojo Fermentado',
        'Proteína Vegetal',
        'Leche de Almendras Orgánica',
        'Bocaditos Veganos de Tocino',
        'Carne de Res a Base de Plantas',
        'Hamburguesa Vegana Gourmet',
        'Hamburguesa Vegetariana',
        'Seitán Artesanal',
        'Morcilla Vegana',
        'Nuggets de Soya',
        'Sustituto de Carne de Soya'
    ];

    foreach ($vegan_products as $product_name) {
        $escaped_name = mysqli_real_escape_string($conn, $product_name);
        mysqli_query($conn, "UPDATE products SET category = 'Veganos' WHERE name = '$escaped_name'");
    }

    @file_put_contents($vegan_products_flag_file, 'Migración de veganos ejecutada: ' . date('Y-m-d H:i:s'));
}

$category_primary_sync_flag_file = __DIR__ . '/.product_categories_primary_synced';
if (!file_exists($category_primary_sync_flag_file)) {
    mysqli_query($conn, "INSERT IGNORE INTO product_categories (product_id, category_id)
        SELECT p.id, c.id
        FROM products p
        INNER JOIN categories c ON c.name = p.category");
    @file_put_contents($category_primary_sync_flag_file, 'Categorías principales sincronizadas: ' . date('Y-m-d H:i:s'));
}

$product_categories_assignment_flag_file = __DIR__ . '/.product_categories_assignments_migrated';
if (!file_exists($product_categories_assignment_flag_file)) {
    $product_category_assignments = [
        'Tofu Orgánico Extra Firme' => ['Proteínas Especiales', 'Vegetarianos', 'Veganos', 'reemplazos de carne'],
        'Proteína de Guisante Aislada 80%' => ['Proteínas Especiales', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Proteína de Cáñamo Orgánica (Hemp)' => ['Proteínas Especiales', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Spirulina Crunch Orgánica' => ['Proteínas Especiales', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Proteína de Semilla de Calabaza' => ['Proteínas Especiales', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Edamame Deshidratado Orgánico' => ['Proteínas Especiales', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Tempeh de Soya Tradicional' => ['Proteínas Especiales', 'Vegetarianos', 'Veganos', 'reemplazos de carne', 'sin gluten'],
        'Proteína de Arroz Integral Germinado' => ['Proteínas Especiales', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Maca Andina Negra Orgánica' => ['Súper Alimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Polvo de Açai Orgánico Liofilizado' => ['Súper Alimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Semillas de Chía Orgánica Negra' => ['Súper Alimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Quinoa Real Blanca en Grano' => ['Súper Alimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Hifas de Cordyceps Militaris' => ['Súper Alimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Melena de León (Lion\'s Mane)' => ['Súper Alimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Baobab en Polvo Orgánico' => ['Súper Alimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Bayas de Goji Silvestres' => ['Súper Alimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Camu Camu Salvaje en Polvo' => ['Súper Alimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Cacao Puro en Nibs Orgánico' => ['Súper Alimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Té Matcha Grado Ceremonial' => ['Tés & Condimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Cúrcuma Cero Toxinas en Polvo' => ['Tés & Condimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Jengibre Dorado Orgánico molido' => ['Tés & Condimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Té Oolong Gran Reserva Wuyi' => ['Tés & Condimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Sal Rosa del Himalaya Molida' => ['Tés & Condimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Ashwagandha KSM-66 en Polvo' => ['Tés & Condimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Garcinia Cambogia Extracto' => ['Tés & Condimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Hamburguesa Vegana Gourmet' => ['reemplazos de carne', 'Vegetarianos', 'Veganos'],
        'Seitán Artesanal' => ['reemplazos de carne', 'Vegetarianos', 'Veganos'],
        'Granola Artesanal Premium' => ['Súper Alimentos', 'Vegetarianos', 'Veganos'],
        'Leche de Almendras Orgánica' => ['Vegetarianos', 'Veganos', 'sin gluten'],
        'Avena Integral Orgánica' => ['Vegetarianos', 'Veganos', 'sin gluten'],
        'Bocaditos Veganos de Tocino' => ['reemplazos de carne', 'Vegetarianos', 'Veganos'],
        'Carne de Res a Base de Plantas' => ['reemplazos de carne', 'Vegetarianos', 'Veganos'],
        'Hamburguesa Vegetariana' => ['reemplazos de carne', 'Vegetarianos', 'Veganos'],
        'Milanesa de Quinoa' => ['reemplazos de carne', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Morcilla Vegana' => ['reemplazos de carne', 'Vegetarianos', 'Veganos'],
        'Nuggets de Soya' => ['reemplazos de carne', 'Vegetarianos', 'Veganos'],
        'Sustituto de Carne de Soya' => ['reemplazos de carne', 'Vegetarianos', 'Veganos'],
        'Miso Rojo Fermentado' => ['Tés & Condimentos', 'Vegetarianos', 'Veganos'],
        'Aceite de Coco Extra Virgen' => ['Vegetarianos', 'Veganos', 'sin gluten'],
        'Condimento a Base de Plantas' => ['Tés & Condimentos', 'Vegetarianos', 'Veganos', 'sin gluten'],
        'Proteína Vegetal' => ['Proteínas Especiales', 'Vegetarianos', 'Veganos', 'sin gluten']
    ];

    foreach ($product_category_assignments as $product_name => $categories) {
        $escaped_name = mysqli_real_escape_string($conn, $product_name);
        $product_result = mysqli_query($conn, "SELECT id FROM products WHERE name = '$escaped_name' LIMIT 1");
        if ($product_result && ($product = mysqli_fetch_assoc($product_result))) {
            syncProductCategories($conn, $product['id'], $categories);
        }
    }

    @file_put_contents($product_categories_assignment_flag_file, 'Asignación múltiple de categorías ejecutada: ' . date('Y-m-d H:i:s'));
}

$loyalty_program_flag_file = __DIR__ . '/.loyalty_program_migrated';
if (!file_exists($loyalty_program_flag_file)) {
    $loyalty_points_result = mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `loyalty_points` (
        `user_id` INT NOT NULL PRIMARY KEY,
        `balance` INT NOT NULL DEFAULT 0,
        `lifetime_earned` INT NOT NULL DEFAULT 0,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $loyalty_tx_result = mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `loyalty_transactions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `points` INT NOT NULL,
        `type` ENUM('earned', 'redeemed') NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `purchase_id` INT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_loyalty_tx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $loyalty_coupons_result = mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `loyalty_coupons` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(20) NOT NULL UNIQUE,
        `user_id` INT NOT NULL,
        `discount_amount` DECIMAL(12,2) NOT NULL,
        `points_spent` INT NOT NULL,
        `is_used` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `used_at` DATETIME NULL,
        INDEX `idx_loyalty_coupon_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($loyalty_points_result && $loyalty_tx_result && $loyalty_coupons_result) {
        @file_put_contents($loyalty_program_flag_file, 'Migración de programa de fidelidad ejecutada: ' . date('Y-m-d H:i:s'));
    }
}
?>