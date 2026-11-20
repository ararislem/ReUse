<?php
/**
 * PROTECTION CSRF
 * ---------------
 * Problème : un autre site pourrait envoyer un formulaire à notre place
 * (ex. "supprimer mon annonce") pendant que l'utilisateur est connecté.
 * Solution : chaque formulaire contient un jeton secret stocké en session.
 * Si le jeton reçu n'est pas le bon, on refuse la requête.
 */

/** Jeton secret de la session (créé une seule fois). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Champ caché à mettre dans CHAQUE formulaire POST : <?= csrf_field() ?> */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/** Vérifie le jeton envoyé par le formulaire. */
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Invalid or expired form. Please go back and try again.');
    }
}

/** Pour les scripts d'action (supprimer, réserver...) : POST obligatoire + jeton valide. */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
    verify_csrf();
}
