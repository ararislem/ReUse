<?php
/**
 * DÉCONNEXION (en POST + jeton CSRF : un lien sur un autre site ne peut pas déconnecter l'utilisateur).
 */
require_once __DIR__ . '/includes/functions.php';

require_post();

// 1) Supprimer le cookie "se souvenir de moi" et ses jetons en base
remember_forget($_SESSION['user_id'] ?? null);

// 2) Vider et détruire la session (+ son cookie)
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
session_destroy();

// 3) Nouvelle session vide, juste pour afficher le message
session_start();
flash('info', 'You have been logged out. See you soon!');
redirect('index.php');
