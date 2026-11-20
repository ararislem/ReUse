<?php
/**
 * AUTHENTIFICATION
 * ----------------
 * - L'utilisateur connecté est identifié par $_SESSION['user_id'].
 * - require_login() protège les pages privées, require_admin() les pages admin.
 * - Cookies avec délai d'expiration :
 *     1. "Se souvenir de moi" : reste connecté 30 jours
 *     2. Inactivité            : la session se ferme après 30 min sans activité
 *     3. Préférence de tri     : (voir marketplace.php) gardée 30 jours
 */

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/** Ligne de la table users de la personne connectée, ou null. */
function current_user(): ?array
{
    static $user = false;            // "static" = la requête SQL n'est faite qu'une fois par page

    if ($user === false) {
        $user = null;
        if (is_logged_in()) {
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;

            if (!$user) {            // compte supprimé ou suspendu entre-temps
                session_unset();
            }
        }
    }
    return $user;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

/** À mettre en haut d'une page privée. Renvoie l'utilisateur connecté. */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Please log in to continue.');
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';   // on y reviendra après la connexion
        redirect('login.php');
    }
    return $user;
}

/** À mettre en haut d'une page admin. */
function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        flash('danger', 'Access denied: administrators only.');
        redirect('index.php');
    }
    return $user;
}

/** Connecte un utilisateur (après login, inscription ou nouveau mot de passe). */
function login_user(int $userId): void
{
    session_regenerate_id(true);             // nouvel identifiant de session (anti "fixation de session")
    $_SESSION['user_id']       = $userId;
    $_SESSION['last_activity'] = time();
}

/* ------------------------------------------------------------------
 * Cookies
 * ------------------------------------------------------------------ */

/**
 * Crée un cookie qui expire après $lifetime secondes.
 * Si $lifetime <= 0, le cookie est supprimé (date d'expiration dans le passé).
 */
function set_app_cookie(string $name, string $value, int $lifetime): void
{
    setcookie($name, $value, [
        'expires'  => $lifetime > 0 ? time() + $lifetime : time() - 3600,
        'path'     => '/',
        'httponly' => true,          // JavaScript ne peut pas lire ce cookie
        'samesite' => 'Lax',
    ]);

    // On met aussi à jour $_COOKIE pour la page en cours
    if ($lifetime > 0) {
        $_COOKIE[$name] = $value;
    } else {
        unset($_COOKIE[$name]);
    }
}

/**
 * "Se souvenir de moi" : crée un jeton en base + le cookie (30 jours).
 * Le cookie contient   selector:validator
 *   - selector  : sert à retrouver la ligne en base
 *   - validator : secret, seul son hash SHA-256 est enregistré en base
 */
function remember_issue(int $userId): void
{
    $selector  = bin2hex(random_bytes(9));      // 18 caractères
    $validator = bin2hex(random_bytes(32));     // 64 caractères
    $expiresAt = date('Y-m-d H:i:s', time() + REMEMBER_LIFETIME);

    db()->exec('DELETE FROM remember_tokens WHERE expires_at < NOW()');   // ménage des vieux jetons

    $stmt = db()->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $selector, hash('sha256', $validator), $expiresAt]);

    set_app_cookie(REMEMBER_COOKIE, $selector . ':' . $validator, REMEMBER_LIFETIME);
}

/** Supprime le cookie "se souvenir de moi" (et les jetons de l'utilisateur si $userId est donné). */
function remember_forget(?int $userId): void
{
    if ($userId) {
        db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$userId]);
    }
    set_app_cookie(REMEMBER_COOKIE, '', 0);
}

/** Reconnecte automatiquement l'utilisateur si son cookie est valide. */
function remember_login(): void
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';

    // Format attendu : 18 caractères hexa ":" 64 caractères hexa
    if (!preg_match('/^([a-f0-9]{18}):([a-f0-9]{64})$/', (string) $cookie, $parts)) {
        remember_forget(null);
        return;
    }
    $selector  = $parts[1];
    $validator = $parts[2];

    $stmt = db()->prepare(
        'SELECT t.id, t.user_id, t.token_hash
         FROM remember_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.selector = ? AND t.expires_at > NOW() AND u.is_active = 1'
    );
    $stmt->execute([$selector]);
    $token = $stmt->fetch();

    if ($token && hash_equals($token['token_hash'], hash('sha256', $validator))) {
        // Jeton valide : on connecte et on remplace le jeton par un nouveau
        db()->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([$token['id']]);
        login_user((int) $token['user_id']);
        remember_issue((int) $token['user_id']);
    } else {
        // Jeton expiré ou falsifié : on le supprime
        db()->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$selector]);
        remember_forget(null);
    }
}

/**
 * Exécutée sur chaque page (voir functions.php) :
 *   1. ferme la session après 30 min d'inactivité
 *   2. reconnecte avec le cookie "se souvenir de moi" si besoin
 */
function auth_bootstrap(): void
{
    $expired = false;

    if (is_logged_in()) {
        $inactiveFor = time() - ($_SESSION['last_activity'] ?? time());
        if ($inactiveFor > SESSION_IDLE_TIMEOUT) {
            $_SESSION = [];
            session_regenerate_id(true);
            $expired = true;
        }
    }

    if (!is_logged_in() && isset($_COOKIE[REMEMBER_COOKIE])) {
        remember_login();
    }

    if (is_logged_in()) {
        $_SESSION['last_activity'] = time();
    } elseif ($expired) {
        flash('warning', 'Your session expired after ' . (SESSION_IDLE_TIMEOUT / 60) . ' minutes of inactivity. Please log in again.');
    }
}

/** Règle du mot de passe : 8 caractères minimum, avec lettres ET chiffres. Renvoie l'erreur ou null. */
function password_error(string $password): ?string
{
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must be at least 8 characters and contain letters and numbers.';
    }
    return null;
}
