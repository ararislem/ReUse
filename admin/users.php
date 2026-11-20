<?php
/**
 * ADMIN - Membres : rechercher, suspendre / réactiver, nommer admin, supprimer.
 */
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();

/* ---------- Actions (POST + jeton CSRF) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if ($id === (int) $admin['id']) {
        flash('warning', 'You cannot modify your own admin account from here.');
    } elseif (!$target) {
        flash('danger', 'User not found.');
    } elseif ($action === 'toggle_active') {
        db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('success', $target['full_name'] . ($target['is_active'] ? ' has been suspended.' : ' has been reactivated.'));
    } elseif ($action === 'toggle_role') {
        $newRole = $target['role'] === 'admin' ? 'user' : 'admin';
        db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $id]);
        flash('success', $target['full_name'] . ' is now ' . ($newRole === 'admin' ? 'an administrator.' : 'a regular user.'));
    } elseif ($action === 'delete') {
        // 1) Supprimer les photos de toutes ses annonces
        $stmt = db()->prepare('SELECT * FROM products WHERE user_id = ?');
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll() as $product) {
            delete_product_files($product);
        }
        delete_upload($target['profile_picture'], UPLOAD_PROFILE_DIR);

        // 2) Supprimer le membre (CASCADE : annonces, favoris, messages, réservations...)
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        flash('success', 'User ' . $target['full_name'] . ' and all their listings were deleted.');
    }

    $query = !empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : '';
    redirect('admin/users.php' . $query);
}

/* ---------- Liste (avec recherche) ---------- */
$q      = trim(mb_substr($_GET['q'] ?? '', 0, 80));
$sql    = 'SELECT u.*, (SELECT COUNT(*) FROM products p WHERE p.user_id = u.id) AS listings FROM users u';
$params = [];
if ($q !== '') {
    $sql   .= ' WHERE u.full_name LIKE ? OR u.email LIKE ?';
    $like   = '%' . addcslashes($q, '%_\\') . '%';
    $params = [$like, $like];
}
$sql .= ' ORDER BY u.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);

render('admin/users', [
    'pageTitle' => 'Users',
    'admin'     => $admin,
    'q'         => $q,
    'users'     => $stmt->fetchAll(),
], 'layout_admin');
