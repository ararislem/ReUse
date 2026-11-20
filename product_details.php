<?php
/**
 * DÉTAIL D'UN PRODUIT : galerie photos, infos, réservation 24 h, vendeur,
 * contact, actions du propriétaire / admin, produits similaires.
 */
require_once __DIR__ . '/includes/functions.php';

$user    = require_login();
$id      = (int) ($_GET['id'] ?? 0);
$product = find_product($id);

if (!$product) {
    flash('warning', 'This product does not exist or has been removed.');
    redirect('marketplace.php');
}

$isOwner = (int) $product['user_id'] === (int) $user['id'];

// +1 vue (pas pour le vendeur, et une seule fois par session)
if (!$isOwner && empty($_SESSION['viewed'][$id])) {
    db()->prepare('UPDATE products SET views = views + 1 WHERE id = ?')->execute([$id]);
    $_SESSION['viewed'][$id] = true;
    $product['views']++;
}

// Statistiques du vendeur
$stmt = db()->prepare("SELECT COUNT(*) AS total, SUM(status = 'sold') AS sold FROM products WHERE user_id = ?");
$stmt->execute([$product['user_id']]);
$sellerStats = $stmt->fetch();

// Ancien prix (rempli automatiquement par le trigger trg_products_after_update)
$stmt = db()->prepare('SELECT old_price FROM price_history WHERE product_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$id]);
$oldPrice = $stmt->fetchColumn();
if ($oldPrice !== false && $oldPrice <= $product['price']) {
    $oldPrice = false;                              // on affiche seulement les BAISSES de prix
}

// Réservation en cours (colonnes reserved_by / reserved_until de la vue v_products)
$reservedByMe = (int) $product['reserved_by'] === (int) $user['id'];
$buyer = null;
if ($product['reserved_by'] && ($isOwner || is_admin())) {
    $stmt = db()->prepare('SELECT id, full_name, profile_picture FROM users WHERE id = ?');
    $stmt->execute([$product['reserved_by']]);
    $buyer = $stmt->fetch();                        // le vendeur voit qui a réservé
}

// Produits similaires (même catégorie)
$stmt = db()->prepare(
    "SELECT * FROM v_products
     WHERE category_id = ? AND id <> ? AND status = 'available' AND seller_active = 1
     ORDER BY created_at DESC
     LIMIT 4"
);
$stmt->execute([$product['category_id'], $id]);
$similar = $stmt->fetchAll();

$favIds = favorite_ids();

render('product_details', [
    'pageTitle'    => $product['title'],
    'id'           => $id,
    'product'      => $product,
    'photos'       => product_photos($product),
    'isOwner'      => $isOwner,
    'isSold'       => $product['status'] === 'sold',
    'isFav'        => in_array($id, $favIds, true),
    'favIds'       => $favIds,
    'sellerStats'  => $sellerStats,
    'oldPrice'     => $oldPrice,
    'reservedByMe' => $reservedByMe,
    'buyer'        => $buyer,
    'similar'      => $similar,
]);
