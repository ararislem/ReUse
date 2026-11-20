<?php
/**
 * CONFIGURATION GÉNÉRALE
 * Modifier DB_* si MySQL a un mot de passe ou un autre port,
 * et MAIL_* pour envoyer de vrais emails (sinon : mode démo).
 */
/*
// ---- Base de données ----
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'reuse_db');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP : mot de passe vide par défaut
*/
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'reuse_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ---- Email (mot de passe oublié) ----
// Exemple Gmail : activer la validation en 2 étapes, puis créer un
// "mot de passe d'application" sur https://myaccount.google.com/apppasswords
// et le coller dans MAIL_PASSWORD (16 lettres, sans espaces).
// Laisser MAIL_PASSWORD vide = MODE DÉMO : le code s'affiche à l'écran.
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_ENCRYPTION', 'tls');                     // 'tls' (port 587), 'ssl' (port 465) ou 'none'
define('MAIL_USERNAME',   'your.address@gmail.com');
define('MAIL_PASSWORD',   '');
define('MAIL_FROM',       'your.address@gmail.com');
define('MAIL_FROM_NAME',  'ReUse');
define('RESET_CODE_MINUTES', 15);                     // durée de validité du code
define('RESET_MAX_ATTEMPTS', 5);                      // essais ratés autorisés

// ---- Annonces ----
define('MAX_EXTRA_PHOTOS', 5);                        // 1 photo principale + 5 supplémentaires

// ---- Cookies et délais d'expiration ----
define('SESSION_IDLE_TIMEOUT', 30 * 60);              // déconnexion après 30 min d'inactivité
define('REMEMBER_COOKIE',      'remember_me');        // nom du cookie "Se souvenir de moi"
define('REMEMBER_LIFETIME',    30 * 24 * 3600);       // ... valable 30 jours
define('PREF_COOKIE_LIFETIME', 30 * 24 * 3600);       // cookie de préférence (tri) : 30 jours

// ---- Application ----
define('APP_NAME', 'ReUse');
define('APP_TAGLINE', 'Give Things a Second Life');

// Adresse de base détectée automatiquement (marche dans n'importe quel dossier,
// ex. http://localhost/reuse/). Les pages admin sont un niveau plus bas.
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
if (basename($scriptDir) === 'admin') {
    $scriptDir = dirname($scriptDir);
}
define('BASE_URL', rtrim($scriptDir, '/') . '/');

// ---- Chemins ----
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('VIEWS_PATH', ROOT_PATH . 'views' . DIRECTORY_SEPARATOR);   // vues .phtml
define('UPLOAD_PROFILE_DIR', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR);
define('UPLOAD_PRODUCT_DIR', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR);

// ---- Règles des images ----
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);   // 2 MB
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
]);

// ---- Données du site ----
define('CONDITIONS', ['New', 'Like New', 'Good', 'Used']);
define('GOVERNORATES', [
    'Ariana', 'Béja', 'Ben Arous', 'Bizerte', 'Gabès', 'Gafsa', 'Jendouba', 'Kairouan',
    'Kasserine', 'Kébili', 'Le Kef', 'Mahdia', 'La Manouba', 'Médenine', 'Monastir',
    'Nabeul', 'Sfax', 'Sidi Bouzid', 'Siliana', 'Sousse', 'Tataouine', 'Tozeur', 'Tunis', 'Zaghouan',
]);

// ---- Session (cookie sécurisé) ----
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string) SESSION_IDLE_TIMEOUT);   // la session expire aussi côté serveur
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('Africa/Tunis');
