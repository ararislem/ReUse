<?php
/**
 * CONNEXION À LA BASE (PDO)
 * Utilisation : db()->prepare('SELECT ...')
 * La connexion est créée une seule fois puis réutilisée ("static").
 */
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // une erreur SQL = exception
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                  // vraies requêtes préparées (anti injection SQL)
            ]);
            // Même fuseau horaire que PHP (Africa/Tunis) pour que "il y a x min" soit juste
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            exit('<h2 style="font-family:sans-serif;color:#183F35">Database connection failed.</h2>'
               . '<p style="font-family:sans-serif">Start MySQL in XAMPP and import <b>database.sql</b> in phpMyAdmin.</p>');
        }
    }
    return $pdo;
}
