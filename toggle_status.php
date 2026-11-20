<?php
/**
 * Passer une annonce de "Disponible" à "Vendu" (et inversement). Propriétaire seulement.
 * Quand l'article est vendu, le trigger SQL termine aussi sa réservation en cours.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
require_post();

$id      = (int) ($_POST['product_id'] ?? 0);
$product = find_product($id);

if (!$product || (int) $product['user_id'] !== (int) $user['id']) {
    flash('danger', 'You are not allowed to change this listing.');
    redirect('my_listings.php');
}

$newStatus = $product['status'] === 'sold' ? 'available' : 'sold';
db()->prepare('UPDATE products SET status = ? WHERE id = ?')->execute([$newStatus, $id]);

if ($newStatus === 'sold') {
    flash('success', 'Congratulations on your sale! The item is now marked as sold.');
} else {
    flash('success', 'The item is available again.');
}

if (($_POST['back'] ?? '') === 'details') {
    redirect('product_details.php?id=' . $id);
}
redirect('my_listings.php');
