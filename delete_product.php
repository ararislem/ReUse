<?php
/**
 * DELETE : supprimer une annonce (propriétaire ou admin). POST + jeton CSRF seulement.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
require_post();

$id      = (int) ($_POST['product_id'] ?? 0);
$product = find_product($id);

if (!$product || !can_manage_product($product)) {
    flash('danger', 'You are not allowed to delete this listing.');
    redirect('my_listings.php');
}

// 1) Supprimer les fichiers photos du disque (principale + supplémentaires)
delete_product_files($product);

// 2) Supprimer la ligne. ON DELETE CASCADE supprime aussi automatiquement :
//    product_images, favorites, messages, reservations, price_history
db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);

flash('success', 'The listing "' . $product['title'] . '" has been deleted.');

// Un admin qui supprime l'annonce d'un autre retourne à la liste admin
$isOwner = (int) $product['user_id'] === (int) $user['id'];
redirect($isOwner ? 'my_listings.php' : 'admin/products.php');
