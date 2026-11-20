<?php
/**
 * FAVORIS : les produits sauvegardés par l'utilisateur connecté.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

$stmt = db()->prepare(
    'SELECT p.*
     FROM favorites f
     JOIN v_products p ON p.id = f.product_id
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC'
);
$stmt->execute([$user['id']]);
$products = $stmt->fetchAll();

render('favorites', [
    'pageTitle' => 'Favorites',
    'products'  => $products,
    'favIds'    => array_map(fn($p) => (int) $p['id'], $products),
]);
