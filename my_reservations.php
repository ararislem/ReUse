<?php
/**
 * MES RÉSERVATIONS : les articles que j'ai réservés (en cours + historique).
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

// On met d'abord à jour les réservations dont les 24 h sont passées (procédure stockée)
release_expired_reservations();

$stmt = db()->prepare(
    'SELECT r.id, r.status, r.expires_at, r.created_at,
            p.id AS product_id, p.title, p.price, p.image, p.status AS product_status, p.location,
            u.full_name AS seller_name, u.id AS seller_id
     FROM reservations r
     JOIN products p ON p.id = r.product_id
     JOIN users u    ON u.id = p.user_id
     WHERE r.buyer_id = ?
     ORDER BY (r.status = \'active\') DESC, r.created_at DESC
     LIMIT 50'
);
$stmt->execute([$user['id']]);

render('my_reservations', [
    'pageTitle'    => 'My reservations',
    'reservations' => $stmt->fetchAll(),
]);
