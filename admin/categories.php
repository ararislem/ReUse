<?php
/**
 * ADMIN - Catégories : ajouter, renommer, changer l'icône, supprimer (si vide).
 */
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();

/** Transforme un nom en "slug" pour l'URL : "Kids & Toys" -> "kids-toys" */
function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;     // enlève les accents
    $text = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $text));
    return trim($text, '-') ?: 'category';
}

$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);              // ?edit=3 => on modifie la catégorie 3

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $icon   = trim($_POST['icon'] ?? 'bi-box');

    /* ---- Ajouter ou modifier ---- */
    if ($action === 'create' || $action === 'update') {
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors['name'] = 'Name must be 2 to 80 characters.';
        }
        if (!preg_match('/^bi-[a-z0-9-]+$/', $icon)) {
            $errors['icon'] = 'Use a Bootstrap Icons class, e.g. bi-tag.';
        }

        $stmt = db()->prepare('SELECT id FROM categories WHERE (name = ? OR slug = ?) AND id <> ?');
        $stmt->execute([$name, slugify($name), $id]);
        if ($stmt->fetch()) {
            $errors['name'] = 'A category with this name already exists.';
        }

        if (empty($errors)) {
            if ($action === 'create') {
                db()->prepare('INSERT INTO categories (name, slug, icon) VALUES (?, ?, ?)')
                    ->execute([$name, slugify($name), $icon]);
                flash('success', 'Category "' . $name . '" created.');
            } else {
                db()->prepare('UPDATE categories SET name = ?, slug = ?, icon = ? WHERE id = ?')
                    ->execute([$name, slugify($name), $icon, $id]);
                flash('success', 'Category updated.');
            }
            redirect('admin/categories.php');
        }
        $editId = $action === 'update' ? $id : 0;
    }

    /* ---- Supprimer (seulement si aucune annonce n'utilise la catégorie) ---- */
    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            flash('warning', 'This category still contains products. Move or delete them first.');
        } else {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash('success', 'Category deleted.');
        }
        redirect('admin/categories.php');
    }
}

// Catégories + nombre d'annonces (vue v_category_stats)
$cats = db()->query(
    'SELECT c.*, s.total FROM categories c JOIN v_category_stats s ON s.id = c.id ORDER BY c.id'
)->fetchAll();

$editing = null;
foreach ($cats as $cat) {
    if ((int) $cat['id'] === $editId) {
        $editing = $cat;
    }
}

render('admin/categories', [
    'pageTitle' => 'Categories',
    'admin'     => $admin,
    'errors'    => $errors,
    'cats'      => $cats,
    'editing'   => $editing,
], 'layout_admin');
