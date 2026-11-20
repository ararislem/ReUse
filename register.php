<?php
/**
 * INSCRIPTION : nom, email, mot de passe + confirmation, photo de profil OBLIGATOIRE.
 */
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('marketplace.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // 1) Vérification des champs
    if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 100 || !preg_match("/^[\p{L}\s'.-]+$/u", $fullName)) {
        $errors['full_name'] = 'Enter your full name (letters only, 3 to 100 characters).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'An account already exists with this email.';
        }
    }

    if (password_error($password)) {
        $errors['password'] = password_error($password);
    }
    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // 2) Photo de profil obligatoire (enregistrée seulement si le reste est correct)
    if (empty($errors)) {
        [$picture, $uploadError] = upload_image($_FILES['profile_picture'] ?? [], UPLOAD_PROFILE_DIR, 'user');
        if ($uploadError) {
            $errors['profile_picture'] = $uploadError;
        }
    } elseif (empty($_FILES['profile_picture']['name'])) {
        $errors['profile_picture'] = 'A profile picture is required.';
    }

    // 3) Création du compte + connexion automatique
    if (empty($errors)) {
        $stmt = db()->prepare('INSERT INTO users (full_name, email, password, profile_picture) VALUES (?, ?, ?, ?)');
        $stmt->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $picture]);

        login_user((int) db()->lastInsertId());
        flash('success', 'Welcome to ReUse, ' . explode(' ', $fullName)[0] . '! Your account is ready.');
        redirect('marketplace.php');
    }
}

render('register', [
    'pageTitle' => 'Create an account',
    'errors'    => $errors,
]);
