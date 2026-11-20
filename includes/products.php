<?php
/**
 * PRODUITS, PHOTOS, RÉSERVATIONS
 * ------------------------------
 * La plupart des lectures passent par la VUE SQL v_products (voir database.sql) :
 * elle contient déjà la catégorie, le vendeur et la réservation en cours.
 */

/** Toutes les catégories (la requête n'est faite qu'une fois par page). */
function get_categories(): array
{
    static $categories = null;
    if ($categories === null) {
        $categories = db()->query('SELECT * FROM categories ORDER BY id')->fetchAll();
    }
    return $categories;
}

/** Liste des id des produits mis en favoris par l'utilisateur connecté. */
function favorite_ids(): array
{
    if (!is_logged_in()) {
        return [];
    }
    $stmt = db()->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Nombre de messages non lus (pastille dans le menu). */
function unread_messages_count(): int
{
    if (!is_logged_in()) {
        return 0;
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
    $stmt->execute([$_SESSION['user_id']]);
    return (int) $stmt->fetchColumn();
}

/** Un produit complet (vue v_products), ou null s'il n'existe pas. */
function find_product(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM v_products WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Le propriétaire ou un admin peut modifier / supprimer le produit. */
function can_manage_product(array $product): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    return (int) $product['user_id'] === (int) $user['id'] || $user['role'] === 'admin';
}

/**
 * Vérifie les champs du formulaire produit.
 * Renvoie [$data, $errors] : les valeurs nettoyées + les erreurs par champ.
 */
function validate_product_input(array $input): array
{
    $data = [
        'title'          => trim($input['title'] ?? ''),
        'description'    => trim($input['description'] ?? ''),
        'price'          => str_replace(',', '.', trim($input['price'] ?? '')),
        'category_id'    => (int) ($input['category_id'] ?? 0),
        'item_condition' => $input['item_condition'] ?? '',
        'location'       => $input['location'] ?? '',
    ];
    $errors = [];

    $titleLength = mb_strlen($data['title']);
    if ($titleLength < 3 || $titleLength > 120) {
        $errors['title'] = 'Title must be 3 to 120 characters.';
    }

    $descLength = mb_strlen($data['description']);
    if ($descLength < 10) {
        $errors['description'] = 'Description must be at least 10 characters.';
    } elseif ($descLength > 3000) {
        $errors['description'] = 'Description is too long (3000 max).';
    }

    if (!is_numeric($data['price']) || $data['price'] < 0 || $data['price'] > 1000000) {
        $errors['price'] = 'Enter a valid price between 0 and 1 000 000 DT.';
    }

    $categoryIds = array_map('intval', array_column(get_categories(), 'id'));
    if (!in_array($data['category_id'], $categoryIds, true)) {
        $errors['category_id'] = 'Choose a category.';
    }
    if (!in_array($data['item_condition'], CONDITIONS, true)) {
        $errors['item_condition'] = 'Choose a condition.';
    }
    if (!in_array($data['location'], GOVERNORATES, true)) {
        $errors['location'] = 'Choose a location.';
    }

    return [$data, $errors];
}

/* ------------------------------------------------------------------
 * Photos (1 photo principale dans products.image + photos supplémentaires
 * dans la table product_images)
 * ------------------------------------------------------------------ */

/** Toutes les photos d'un produit : la principale d'abord, puis les supplémentaires. */
function product_photos(array $product): array
{
    $photos = [['id' => null, 'filename' => $product['image']]];      // id null = photo principale

    $stmt = db()->prepare('SELECT id, filename FROM product_images WHERE product_id = ? ORDER BY id');
    $stmt->execute([$product['id']]);
    return array_merge($photos, $stmt->fetchAll());
}

/** Nombre de photos supplémentaires d'un produit. */
function extra_photo_count(int $productId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
    $stmt->execute([$productId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Enregistre des photos supplémentaires (champ "photos[]").
 * $alreadyThere = nombre de photos supplémentaires déjà en base.
 * Renvoie la liste des erreurs (vide si tout va bien).
 */
function save_extra_photos(int $productId, int $alreadyThere = 0): array
{
    $errors = [];
    $files  = uploaded_files_list('photos');
    $free   = MAX_EXTRA_PHOTOS - $alreadyThere;          // places restantes

    if (count($files) > $free) {
        $errors[] = 'Maximum ' . MAX_EXTRA_PHOTOS . ' extra photos: only the first ' . max(0, $free) . ' were kept.';
        $files = array_slice($files, 0, max(0, $free));
    }

    $insert = db()->prepare('INSERT INTO product_images (product_id, filename) VALUES (?, ?)');
    foreach ($files as $file) {
        [$name, $error] = upload_image($file, UPLOAD_PRODUCT_DIR, 'product');
        if ($error) {
            $errors[] = $file['name'] . ': ' . $error;
        } else {
            $insert->execute([$productId, $name]);
        }
    }
    return $errors;
}

/** Supprime du disque toutes les photos d'un produit (avant de supprimer le produit). */
function delete_product_files(array $product): void
{
    foreach (product_photos($product) as $photo) {
        delete_upload($photo['filename'], UPLOAD_PRODUCT_DIR);
    }
}

/* ------------------------------------------------------------------
 * Réservation 24 h
 * La logique "anti double réservation" est dans la procédure stockée
 * reserver_produit (SELECT ... FOR UPDATE), voir database.sql.
 * ------------------------------------------------------------------ */

/** Appelle la procédure stockée et renvoie son code : OK, SOLD, ALREADY_RESERVED... */
function reserve_product(int $productId, int $buyerId): string
{
    $stmt = db()->prepare('CALL reserver_produit(?, ?)');
    $stmt->execute([$productId, $buyerId]);
    $result = $stmt->fetchColumn();
    $stmt->closeCursor();                 // obligatoire après un CALL avant une autre requête
    return (string) $result;
}

/** Message à afficher pour chaque code renvoyé par la procédure. */
function reservation_message(string $code): array
{
    $messages = [
        'OK'               => ['success', 'Item reserved for 24 hours! Contact the seller to arrange the meeting.'],
        'NOT_FOUND'        => ['danger',  'This product does not exist anymore.'],
        'OWN_PRODUCT'      => ['warning', 'You cannot reserve your own item.'],
        'SOLD'             => ['warning', 'Too late, this item has been sold.'],
        'ALREADY_RESERVED' => ['warning', 'Someone has just reserved this item. Try again later.'],
        'LIMIT_REACHED'    => ['warning', 'You already have 3 active reservations. Cancel one first.'],
    ];
    return $messages[$code] ?? ['danger', 'Reservation failed, please try again.'];
}

/** Libère les réservations dont les 24 h sont passées (procédure stockée). */
function release_expired_reservations(): void
{
    db()->exec('CALL liberer_reservations_expirees()');
}

/* ------------------------------------------------------------------
 * Messagerie
 * ------------------------------------------------------------------ */

/**
 * Est-ce que $meId peut écrire à $otherId à propos de ce produit ?
 *   - un acheteur peut toujours écrire au vendeur
 *   - le vendeur peut répondre à quelqu'un qui lui a écrit, ou qui a réservé l'article
 */
function can_message(array $product, int $meId, int $otherId): bool
{
    $sellerId = (int) $product['user_id'];

    if ($otherId === $meId) {
        return false;
    }
    if ($otherId === $sellerId) {
        return true;                                  // acheteur -> vendeur
    }
    if ($meId !== $sellerId) {
        return false;                                 // deux acheteurs entre eux : interdit
    }

    // Vendeur -> acheteur : l'acheteur a déjà écrit OU a réservé l'article
    $stmt = db()->prepare(
        'SELECT (SELECT COUNT(*) FROM messages     WHERE product_id = ? AND sender_id = ? AND receiver_id = ?)
              + (SELECT COUNT(*) FROM reservations WHERE product_id = ? AND buyer_id = ?)'
    );
    $stmt->execute([$product['id'], $otherId, $meId, $product['id'], $otherId]);
    return (int) $stmt->fetchColumn() > 0;
}
