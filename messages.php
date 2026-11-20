<?php
/**
 * MESSAGERIE : liste des conversations (une par produit + personne) et la discussion ouverte.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$me   = (int) $user['id'];

// 1) Liste des conversations : pour chaque (produit, autre personne) -> dernier message + non lus
$stmt = db()->prepare(
    "SELECT t.product_id, t.other_id, t.unread,
            m.body AS last_body, m.created_at AS last_at, m.sender_id AS last_sender,
            p.title, p.image, p.price, p.user_id AS seller_id,
            u.full_name AS other_name, u.profile_picture AS other_picture
     FROM (
        SELECT product_id,
               IF(sender_id = ?, receiver_id, sender_id) AS other_id,
               MAX(id) AS last_id,
               SUM(receiver_id = ? AND is_read = 0) AS unread
        FROM messages
        WHERE sender_id = ? OR receiver_id = ?
        GROUP BY product_id, other_id
     ) t
     JOIN messages m ON m.id = t.last_id
     JOIN products p ON p.id = t.product_id
     JOIN users u    ON u.id = t.other_id
     ORDER BY t.last_id DESC"
);
$stmt->execute([$me, $me, $me, $me]);
$convos = $stmt->fetchAll();

// 2) Conversation ouverte : celle de l'URL, sinon la plus récente
$productId = (int) ($_GET['product'] ?? ($convos[0]['product_id'] ?? 0));
$withId    = (int) ($_GET['with'] ?? ($convos[0]['other_id'] ?? 0));

$active = null;
foreach ($convos as $i => $convo) {
    if ((int) $convo['product_id'] === $productId && (int) $convo['other_id'] === $withId) {
        $active = $convo;
        $convos[$i]['unread'] = 0;                    // on va la lire maintenant
    }
}

// Nouvelle conversation (pas encore de message), ex. le vendeur écrit à l'acheteur qui a réservé
if (!$active && $productId && $withId) {
    $product = find_product($productId);
    if ($product && can_message($product, $me, $withId)) {
        $stmt = db()->prepare('SELECT full_name, profile_picture FROM users WHERE id = ?');
        $stmt->execute([$withId]);
        $other = $stmt->fetch();
        if ($other) {
            $active = [
                'product_id' => $productId, 'other_id' => $withId,
                'title' => $product['title'], 'image' => $product['image'], 'price' => $product['price'],
                'seller_id' => $product['user_id'], 'other_name' => $other['full_name'],
            ];
        }
    }
}

// 3) Messages de la conversation ouverte + on les marque comme lus
$thread = [];
if ($active) {
    $stmt = db()->prepare(
        'SELECT * FROM messages
         WHERE product_id = ?
           AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
         ORDER BY created_at, id'
    );
    $stmt->execute([$productId, $me, $withId, $withId, $me]);
    $thread = $stmt->fetchAll();

    db()->prepare('UPDATE messages SET is_read = 1 WHERE product_id = ? AND sender_id = ? AND receiver_id = ?')
        ->execute([$productId, $withId, $me]);
}

render('messages', [
    'pageTitle' => 'Messages',
    'me'        => $me,
    'convos'    => $convos,
    'active'    => $active,
    'thread'    => $thread,
    'productId' => $productId,
    'withId'    => $withId,
]);
