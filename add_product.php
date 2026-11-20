<?php
/**
 * CREATE : publier une nouvelle annonce (1 photo principale + jusqu'à 5 photos supplémentaires).
 */
require_once __DIR__ . '/includes/functions.php';

$user   = require_login();
$errors = [];
$values = ['location' => $user['city'] ?? ''];        // ville du profil proposée par défaut

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // 1) Vérifier les champs texte
    [$values, $errors] = validate_product_input($_POST);

    // 2) Photo principale obligatoire
    if (empty($errors)) {
        [$image, $uploadError] = upload_image($_FILES['image'] ?? [], UPLOAD_PRODUCT_DIR, 'product');
        if ($uploadError) {
            $errors['image'] = $uploadError;
        }
    } elseif (empty($_FILES['image']['name'])) {
        $errors['image'] = 'A product photo is required.';
    } else {
        $errors['image'] = 'Please select the photo again after fixing the errors.';
    }

    // 3) Enregistrer l'annonce, puis les photos supplémentaires
    if (empty($errors)) {
        $stmt = db()->prepare(
            'INSERT INTO products (user_id, category_id, title, description, price, item_condition, location, image)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'], $values['category_id'], $values['title'], $values['description'],
            $values['price'], $values['item_condition'], $values['location'], $image,
        ]);
        $productId = (int) db()->lastInsertId();

        $photoErrors = save_extra_photos($productId);
        foreach ($photoErrors as $message) {
            flash('warning', $message);
        }

        flash('success', 'Your listing is live! Good luck with the sale.');
        redirect('product_details.php?id=' . $productId);
    }
}

render('add_product', [
    'pageTitle'    => 'Sell an item',
    'values'       => $values,
    'errors'       => $errors,
    'isEdit'       => false,
    'currentImage' => null,
    'extraPhotos'  => [],
]);
