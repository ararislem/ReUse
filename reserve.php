<?php
/**
 * RÉSERVATION 24 H (action en POST)
 *   action = reserve : appelle la procédure stockée reserver_produit
 *                      (SELECT ... FOR UPDATE => pas de double réservation)
 *   action = cancel  : l'acheteur annule sa réservation
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
require_post();

$productId = (int) ($_POST['product_id'] ?? 0);
$action    = $_POST['action'] ?? '';

if ($action === 'reserve') {
    $code = reserve_product($productId, (int) $user['id']);
    [$type, $message] = reservation_message($code);
    flash($type, $message);
}

if ($action === 'cancel') {
    // On ne peut annuler QUE sa propre réservation en cours
    $stmt = db()->prepare(
        "UPDATE reservations SET status = 'cancelled'
         WHERE product_id = ? AND buyer_id = ? AND status = 'active'"
    );
    $stmt->execute([$productId, $user['id']]);
    flash('info', $stmt->rowCount() > 0 ? 'Your reservation was cancelled. The item is free again.' : 'No active reservation to cancel.');
}

// Retour à la page d'où vient l'utilisateur
if (($_POST['back'] ?? '') === 'reservations') {
    redirect('my_reservations.php');
}
redirect('product_details.php?id=' . $productId);
