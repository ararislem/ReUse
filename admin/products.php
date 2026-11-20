<?php
/**
 * ADMIN - Annonces : rechercher, filtrer, changer le statut, supprimer les annonces inappropriées.
 */
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id      = (int) ($_POST['product_id'] ?? 0);
    $action  = $_POST['action'] ?? '';
    $product = find_product($id);

    if (!$product) {
        flash('danger', 'Product not found.');
    } elseif ($action === 'delete') {
        delete_product_files($product);
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        flash('success', 'Listing "' . $product['title'] . '" removed.');
    } elseif ($action === 'toggle_status') {
        $newStatus = $product['status'] === 'sold' ? 'available' : 'sold';
        db()->prepare('UPDATE products SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        flash('success', 'Status changed to ' . $newStatus . '.');
    }

    $query = $_SERVER['QUERY_STRING'] ?? '';
    redirect('admin/products.php' . ($query ? '?' . $query : ''));
}

/* ---------- Filtres ---------- */
$q        = trim(mb_substr($_GET['q'] ?? '', 0, 80));
$category = (int) ($_GET['category'] ?? 0);
$status   = in_array($_GET['status'] ?? '', ['available', 'sold'], true) ? $_GET['status'] : '';

$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = '(p.title LIKE ? OR p.seller_name LIKE ?)';
    $like     = '%' . addcslashes($q, '%_\\') . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($category > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $category;
}
if ($status !== '') {
    $where[]  = 'p.status = ?';
    $params[] = $status;
}

$sql = 'SELECT p.*, u.email AS seller_email FROM v_products p JOIN users u ON u.id = p.user_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);

render('admin/products', [
    'pageTitle' => 'Products',
    'admin'     => $admin,
    'q'         => $q,
    'category'  => $category,
    'status'    => $status,
    'products'  => $stmt->fetchAll(),
], 'layout_admin');
