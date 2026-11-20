<?php
/**
 * MARKETPLACE : recherche, filtres (catégorie, prix, état, ville), tri et pagination.
 * Réservé aux membres connectés.
 */
require_once __DIR__ . '/includes/functions.php';

require_login();

// 1) Lecture des filtres envoyés dans l'URL (?q=...&category=...)
$q         = trim(mb_substr($_GET['q'] ?? '', 0, 80));
$category  = (int) ($_GET['category'] ?? 0);
$minPrice  = is_numeric($_GET['min'] ?? '') ? max(0, (float) $_GET['min']) : null;
$maxPrice  = is_numeric($_GET['max'] ?? '') ? max(0, (float) $_GET['max']) : null;
$condition = in_array($_GET['condition'] ?? '', CONDITIONS, true) ? $_GET['condition'] : '';
$location  = in_array($_GET['location'] ?? '', GOVERNORATES, true) ? $_GET['location'] : '';
$showSold  = !empty($_GET['sold']);
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 12;

// 2) Tri : choix dans l'URL, sinon le dernier choix gardé dans le cookie "pref_sort"
$sorts = [
    'newest'     => 'created_at DESC',
    'price_asc'  => 'price ASC',
    'price_desc' => 'price DESC',
    'popular'    => 'views DESC',
];
$sort = $_GET['sort'] ?? $_COOKIE['pref_sort'] ?? 'newest';
if (!is_string($sort) || !isset($sorts[$sort])) {
    $sort = 'newest';
}
if (isset($_GET['sort'])) {
    set_app_cookie('pref_sort', $sort, PREF_COOKIE_LIFETIME);     // cookie valable 30 jours
}

// 3) Construction du WHERE : uniquement des paramètres liés (?) => pas d'injection SQL
$where  = ['seller_active = 1'];
$params = [];

if (!$showSold) {
    $where[] = "status = 'available'";
}
if ($q !== '') {
    $where[]  = '(title LIKE ? OR description LIKE ?)';
    $like     = '%' . addcslashes($q, '%_\\') . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($category > 0) {
    $where[]  = 'category_id = ?';
    $params[] = $category;
}
if ($minPrice !== null) {
    $where[]  = 'price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[]  = 'price <= ?';
    $params[] = $maxPrice;
}
if ($condition !== '') {
    $where[]  = 'item_condition = ?';
    $params[] = $condition;
}
if ($location !== '') {
    $where[]  = 'location = ?';
    $params[] = $location;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

// 4) Nombre total de résultats -> nombre de pages
$stmt = db()->prepare("SELECT COUNT(*) FROM v_products $whereSql");
$stmt->execute($params);
$total  = (int) $stmt->fetchColumn();
$pages  = max(1, (int) ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

// 5) Les produits de la page courante
$stmt = db()->prepare("SELECT * FROM v_products $whereSql ORDER BY {$sorts[$sort]} LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

// 6) Données de la colonne de filtres
$catCounts  = db()->query('SELECT id, available FROM v_category_stats')->fetchAll(PDO::FETCH_KEY_PAIR);
$maxDbPrice = (float) db()->query('SELECT COALESCE(MAX(price), 1000) FROM products')->fetchColumn();

$catName = '';
foreach (get_categories() as $cat) {
    if ((int) $cat['id'] === $category) {
        $catName = $cat['name'];
    }
}

/** Lien du marketplace qui garde les filtres actuels et en change certains. */
function mp_url(array $change = []): string
{
    $params = array_merge($_GET, $change);
    $params = array_filter($params, fn($value) => $value !== '' && $value !== null && $value !== 0 && $value !== '0');
    return url('marketplace.php' . ($params ? '?' . http_build_query($params) : ''));
}

render('marketplace', [
    'pageTitle'  => 'Marketplace',
    'q'          => $q,
    'category'   => $category,
    'catName'    => $catName,
    'minPrice'   => $minPrice,
    'maxPrice'   => $maxPrice,
    'condition'  => $condition,
    'location'   => $location,
    'showSold'   => $showSold,
    'sort'       => $sort,
    'page'       => $page,
    'pages'      => $pages,
    'total'      => $total,
    'products'   => $products,
    'catCounts'  => $catCounts,
    'allCount'   => array_sum($catCounts),
    'maxDbPrice' => $maxDbPrice,
    'favIds'     => favorite_ids(),
]);
