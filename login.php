<?php
/**
 * CONNEXION avec email + mot de passe (+ case "se souvenir de moi").
 */
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('marketplace.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($password === '') {
        $errors['password'] = 'Enter your password.';
    }

    // Anti force brute : maximum 5 échecs en 5 minutes (gardés dans la session)
    $failures = array_filter($_SESSION['login_fails'] ?? [], fn($time) => $time > time() - 300);
    if (count($failures) >= 5) {
        $errors['general'] = 'Too many failed attempts. Please wait a few minutes and try again.';
    }

    if (empty($errors)) {
        $stmt = db()->prepare('SELECT id, password, is_active, full_name FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        if (!$account || !password_verify($password, $account['password'])) {
            $failures[] = time();
            $_SESSION['login_fails'] = $failures;
            $errors['general'] = 'Incorrect email or password.';
        } elseif (!$account['is_active']) {
            $errors['general'] = 'This account has been suspended. Contact the ReUse team.';
        } else {
            // Connexion réussie
            login_user((int) $account['id']);
            unset($_SESSION['login_fails']);

            // Case "se souvenir de moi" cochée : cookie valable 30 jours
            if (!empty($_POST['remember'])) {
                remember_issue((int) $account['id']);
            } else {
                remember_forget(null);
            }

            flash('success', 'Welcome back, ' . explode(' ', $account['full_name'])[0] . '!');

            // Retour à la page demandée avant la connexion (seulement une page du site)
            $target = $_SESSION['redirect_after_login'] ?? '';
            unset($_SESSION['redirect_after_login']);
            if ($target !== '' && str_starts_with($target, BASE_URL) && !str_contains($target, '//')) {
                header('Location: ' . $target);
                exit;
            }
            redirect('marketplace.php');
        }
    }
}

render('login', [
    'pageTitle' => 'Log in',
    'errors'    => $errors,
]);
