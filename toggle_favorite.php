<?php
/**
 * Ajouter / retirer un favori.
 * Répond en JSON si la demande vient du JavaScript (AJAX), sinon recharge la page.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
require_post();

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$id     = (int) ($_POST['product_id'] ?? 0);

/** Envoie une réponse JSON au JavaScript et arrête le script. */
function json_response(array $data): void
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if (!find_product($id)) {
    if ($isAjax) {
        json_response(['ok' => false, 'error' => 'Product not found']);
    }
    redirect('marketplace.php');
}

// Déjà en favori ? -> on le retire. Sinon -> on l'ajoute.
$delete = db()->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?');
$delete->execute([$user['id'], $id]);

$isFavorite = false;
if ($delete->rowCount() === 0) {
    db()->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)')->execute([$user['id'], $id]);
    $isFavorite = true;
}

if ($isAjax) {
    json_response(['ok' => true, 'favorite' => $isFavorite]);
}

flash('success', $isFavorite ? 'Saved to your favorites.' : 'Removed from your favorites.');
redirect('favorites.php');
