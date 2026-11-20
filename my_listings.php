<?php
/**
 * READ : mes annonces, avec les actions modifier / supprimer / vendu.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

// Filtre : toutes, disponibles ou vendues
$filter = in_array($_GET['status'] ?? '', ['available', 'sold'], true) ? $_GET['status'] : '';

$sql    = 'SELECT p.*, (SELECT COUNT(*) FROM favorites f WHERE f.product_id = p.id) AS fav_count
           FROM v_products p
           WHERE p.user_id = ?';
$params = [$user['id']];
if ($filter !== '') {
    $sql     .= ' AND p.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY p.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Petites statistiques en haut de la page
$stmt = db()->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'available'), 0) AS available,
            COALESCE(SUM(status = 'sold'), 0)      AS sold,
            COALESCE(SUM(views), 0)                AS views
     FROM products
     WHERE user_id = ?"
);
$stmt->execute([$user['id']]);

render('my_listings', [
    'pageTitle' => 'My Listings',
    'filter'    => $filter,
    'products'  => $products,
    's'         => $stmt->fetch(),
]);
