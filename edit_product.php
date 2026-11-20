<?php
/**
 * UPDATE : modifier une annonce (propriétaire ou admin).
 * On peut remplacer la photo principale, supprimer et ajouter des photos supplémentaires.
 */
require_once __DIR__ . '/includes/functions.php';

require_login();
$id      = (int) ($_GET['id'] ?? 0);
$product = find_product($id);

// Autorisation : seulement le propriétaire ou un admin
if (!$product || !can_manage_product($product)) {
    flash('danger', 'You are not allowed to edit this listing.');
    redirect('my_listings.php');
}

$errors = [];
$values = $product;                                  // le formulaire est pré-rempli

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    [$values, $errors] = validate_product_input($_POST);
    $values['status'] = ($_POST['status'] ?? '') === 'sold' ? 'sold' : 'available';

    // Nouvelle photo principale ? (facultatif)
    $newImage = null;
    if (empty($errors) && !empty($_FILES['image']['name'])) {
        [$newImage, $uploadError] = upload_image($_FILES['image'], UPLOAD_PRODUCT_DIR, 'product');
        if ($uploadError) {
            $errors['image'] = $uploadError;
        }
    }

    if (empty($errors)) {
        // 1) Mise à jour du produit (le trigger enregistre l'ancien prix s'il change)
        $stmt = db()->prepare(
            'UPDATE products
             SET category_id = ?, title = ?, description = ?, price = ?, item_condition = ?,
                 location = ?, status = ?, image = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $values['category_id'], $values['title'], $values['description'], $values['price'],
            $values['item_condition'], $values['location'], $values['status'],
            $newImage ?? $product['image'], $id,
        ]);
        if ($newImage) {
            delete_upload($product['image'], UPLOAD_PRODUCT_DIR);       // ancien fichier supprimé
        }

        // 2) Photos supplémentaires cochées "à supprimer"
        $find   = db()->prepare('SELECT filename FROM product_images WHERE id = ? AND product_id = ?');
        $delete = db()->prepare('DELETE FROM product_images WHERE id = ? AND product_id = ?');
        foreach ($_POST['delete_photos'] ?? [] as $photoId) {
            $find->execute([(int) $photoId, $id]);
            $filename = $find->fetchColumn();
            if ($filename) {                                            // la photo est bien à CE produit
                $delete->execute([(int) $photoId, $id]);
                delete_upload($filename, UPLOAD_PRODUCT_DIR);
            }
        }

        // 3) Nouvelles photos supplémentaires
        foreach (save_extra_photos($id, extra_photo_count($id)) as $message) {
            flash('warning', $message);
        }

        flash('success', 'Listing updated successfully.');
        redirect('product_details.php?id=' . $id);
    }
}

render('edit_product', [
    'pageTitle'    => 'Edit listing',
    'product'      => $product,
    'values'       => $values,
    'errors'       => $errors,
    'isEdit'       => true,
    'currentImage' => $product['image'],
    'extraPhotos'  => array_slice(product_photos($product), 1),      // sans la photo principale
]);
