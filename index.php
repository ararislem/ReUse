<?php
/**
 * PAGE D'ACCUEIL : hero, recherche, catégories, derniers produits, CTA.
 */
require_once __DIR__ . '/includes/functions.php';

// Chiffres affichés sur la page (vrais chiffres de la base)
$stats = db()->query(
    "SELECT
        (SELECT COUNT(*) FROM products WHERE status = 'available') AS available,
        (SELECT COUNT(*) FROM products WHERE status = 'sold')      AS sold,
        (SELECT COUNT(*) FROM users)                               AS members"
)->fetch();

// Nombre de produits disponibles par catégorie : [id_categorie => nombre] (vue v_category_stats)
$catCounts = db()->query('SELECT id, available FROM v_category_stats')->fetchAll(PDO::FETCH_KEY_PAIR);

// Les derniers produits : SEULEMENT pour les membres connectés (annonces privées)
$recent = [];
if (is_logged_in()) {
    $recent = db()->query(
        "SELECT * FROM v_products
         WHERE status = 'available' AND seller_active = 1
         ORDER BY created_at DESC
         LIMIT 8"
    )->fetchAll();
}

// Photos décoratives des catégories (si pas d'internet : image par défaut)
$catImages = [
    'clothing-shoes'  => 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?auto=format&fit=crop&w=700&q=70',
    'electronics'     => 'https://images.unsplash.com/photo-1468495244123-6c6c332eeece?auto=format&fit=crop&w=700&q=70',
    'books-education' => 'https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=700&q=70',
    'furniture-decor' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=700&q=70',
    'sports'          => 'https://images.unsplash.com/photo-1517649763962-0c623066013b?auto=format&fit=crop&w=700&q=70',
    'other'           => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=700&q=70',
];

render('index', [
    'stats'     => $stats,
    'catCounts' => $catCounts,
    'recent'    => $recent,
    'favIds'    => favorite_ids(),
    'catImages' => $catImages,
    'fallback'  => url('assets/images/placeholder.svg'),
]);
