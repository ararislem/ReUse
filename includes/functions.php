<?php
/**
 * POINT D'ENTRÉE DES FONCTIONS
 * ----------------------------
 * Chaque page commence par : require_once __DIR__ . '/includes/functions.php';
 *
 * Ce fichier charge tous les petits fichiers de fonctions, rangés par thème :
 *
 *   view.php      -> afficher une page (render, partial), e(), url(), redirect(), flash()
 *   security.php  -> protection CSRF des formulaires
 *   auth.php      -> connexion, session, "se souvenir de moi", délai d'inactivité
 *   upload.php    -> envoi et suppression des images
 *   format.php    -> affichage des prix, dates, avatars, badges
 *   products.php  -> lecture des produits, photos, réservations, validation
 *   mail.php      -> envoi d'email (code "mot de passe oublié")
 */
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/view.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/format.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/mail.php';

// À chaque chargement de page : délai d'inactivité + connexion automatique par cookie
auth_bootstrap();
