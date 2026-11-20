<?php
/**
 * AFFICHAGE DES PAGES
 * -------------------
 * Une page est découpée en 2 fichiers :
 *   - le contrôleur  (ex. login.php)          : PHP + SQL, traite le formulaire
 *   - la vue         (ex. views/login.phtml)  : seulement du HTML
 *
 * Le contrôleur termine par :   render('login', ['errors' => $errors]);
 * render() affiche la vue À L'INTÉRIEUR du layout (en-tête + menu + pied de page).
 */

/**
 * Affiche views/$view.phtml dans le layout.
 *   $view   : nom de la vue, ex. 'marketplace' ou 'admin/users'
 *   $data   : variables envoyées à la vue, ex. ['products' => $products]
 *   $layout : 'layout' (site public) ou 'layout_admin' (espace admin)
 */
function render(string $view, array $data = [], string $layout = 'layout'): void
{
    // Variables disponibles dans TOUTES les vues et le layout
    $data['user']    = current_user();
    $data['unread']  = $data['user'] ? unread_messages_count() : 0;
    $data['current'] = basename($_SERVER['SCRIPT_NAME']);        // pour le lien actif du menu
    $data['flashes'] = get_flashes();

    // extract() transforme ['products' => ...] en variable $products
    extract($data);

    // 1) On "capture" le HTML de la vue dans $content
    ob_start();
    require VIEWS_PATH . $view . '.phtml';
    $content = ob_get_clean();

    // 2) Le layout affiche $content au bon endroit
    require VIEWS_PATH . $layout . '.phtml';
}

/** Affiche un petit morceau réutilisable : views/partials/$name.phtml */
function partial(string $name, array $data = []): void
{
    extract($data);
    require VIEWS_PATH . 'partials/' . $name . '.phtml';
}

/** Classe CSS "active" pour le lien du menu de la page courante. */
function nav_active(string $file, string $current): string
{
    return $file === $current ? ' active' : '';
}

/** Protection XSS : à utiliser pour TOUT texte affiché qui vient de l'utilisateur. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Adresse complète d'une page du site, ex. url('login.php') -> /reuse/login.php */
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/** Redirige vers une autre page et arrête le script. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Message affiché UNE fois sur la page suivante. $type : success | danger | warning | info */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Récupère les messages flash puis les efface. */
function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Remet la valeur tapée dans un champ après une erreur de formulaire. */
function old(string $key, $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

/** Ajoute la classe Bootstrap "is-invalid" si le champ a une erreur. */
function invalid(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

/** Affiche le message d'erreur d'un champ (sous le champ). */
function feedback(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="invalid-feedback">' . e($errors[$key]) . '</div>' : '';
}
