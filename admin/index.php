<?php
/**
 * ADMIN - Tableau de bord : statistiques du site.
 */
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
release_expired_reservations();                     // procédure stockée : chiffres à jour

// Chiffres clés (une seule requête avec des sous-requêtes)
$k = db()->query(
    "SELECT
        (SELECT COUNT(*) FROM users)                                              AS users,
        (SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY)   AS new_users,
        (SELECT COUNT(*) FROM products)                                           AS products,
        (SELECT COUNT(*) FROM products WHERE status = 'available')                AS available,
        (SELECT COUNT(*) FROM products WHERE status = 'sold')                     AS sold,
        (SELECT COALESCE(SUM(price), 0) FROM products WHERE status = 'sold')      AS sold_value,
        (SELECT COUNT(*) FROM messages)                                           AS messages,
        (SELECT COUNT(*) FROM favorites)                                          AS favorites,
        (SELECT COUNT(*) FROM reservations WHERE status = 'active')               AS reservations,
        (SELECT COUNT(*) FROM price_history)                                      AS price_changes"
)->fetch();

// Annonces par catégorie : vue SQL v_category_stats
$byCategory = db()->query('SELECT name, icon, total FROM v_category_stats ORDER BY total DESC')->fetchAll();

// Les 6 villes avec le plus d'annonces
$byLocation = db()->query(
    'SELECT location, COUNT(*) AS total FROM products GROUP BY location ORDER BY total DESC LIMIT 6'
)->fetchAll();

// Derniers produits et derniers membres
$latestProducts = db()->query(
    'SELECT id, title, price, status, image, created_at, seller_name AS full_name
     FROM v_products ORDER BY created_at DESC LIMIT 6'
)->fetchAll();
$latestUsers = db()->query(
    'SELECT full_name, email, profile_picture, created_at FROM users ORDER BY created_at DESC LIMIT 6'
)->fetchAll();

render('admin/index', [
    'pageTitle'      => 'Dashboard',
    'admin'          => $admin,
    'k'              => $k,
    'byCategory'     => $byCategory,
    'maxCat'         => max([1, ...array_column($byCategory, 'total')]),     // pour la largeur des barres
    'byLocation'     => $byLocation,
    'maxLoc'         => max([1, ...array_column($byLocation, 'total')]),
    'latestProducts' => $latestProducts,
    'latestUsers'    => $latestUsers,
], 'layout_admin');
