<?php
/**
 * CONTACTER LE VENDEUR : enregistre un message à propos d'un produit.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
require_post();

$productId  = (int) ($_POST['product_id'] ?? 0);
$receiverId = (int) ($_POST['receiver_id'] ?? 0);
$body       = trim($_POST['body'] ?? '');
$product    = find_product($productId);

// Le destinataire doit être autorisé (voir can_message dans includes/products.php)
if (!$product || !can_message($product, (int) $user['id'], $receiverId)) {
    flash('danger', 'This message cannot be sent.');
    redirect('messages.php');
}

$threadUrl = 'messages.php?product=' . $productId . '&with=' . $receiverId;

if (mb_strlen($body) < 2 || mb_strlen($body) > 1000) {
    flash('warning', 'Your message must be between 2 and 1000 characters.');
    redirect($threadUrl);
}

$stmt = db()->prepare('INSERT INTO messages (sender_id, receiver_id, product_id, body) VALUES (?, ?, ?, ?)');
$stmt->execute([$user['id'], $receiverId, $productId, $body]);

// Message de confirmation seulement depuis la page produit (pas dans la discussion)
if (($_POST['from'] ?? '') !== 'thread') {
    flash('success', 'Message sent! The seller will reply in your inbox.');
}
redirect($threadUrl);
