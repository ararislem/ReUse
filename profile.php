<?php
/**
 * MON PROFIL : modifier ses informations, sa photo et son mot de passe.
 * La page contient 2 formulaires, distingués par le champ caché "action".
 */
require_once __DIR__ . '/includes/functions.php';

$user     = require_login();
$errors   = [];          // erreurs du formulaire "informations"
$pwErrors = [];          // erreurs du formulaire "mot de passe"
$action   = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

/* ---------- Formulaire 1 : informations personnelles ---------- */
if ($action === 'profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $city     = $_POST['city'] ?? '';
    $bio      = trim($_POST['bio'] ?? '');

    if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 100 || !preg_match("/^[\p{L}\s'.-]+$/u", $fullName)) {
        $errors['full_name'] = 'Enter your full name (letters only, 3 to 100 characters).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already used by another account.';
        }
    }

    // Numéro tunisien : 8 chiffres, avec ou sans +216
    if ($phone !== '' && !preg_match('/^(\+216\s?)?[2-9]\d(\s?\d{3}){2}$/', $phone)) {
        $errors['phone'] = 'Enter a Tunisian number, e.g. +216 22 123 456.';
    }
    if ($city !== '' && !in_array($city, GOVERNORATES, true)) {
        $errors['city'] = 'Choose a governorate.';
    }
    if (mb_strlen($bio) > 500) {
        $errors['bio'] = 'Bio is limited to 500 characters.';
    }

    // Nouvelle photo de profil ? (facultatif)
    $newPicture = null;
    if (empty($errors) && !empty($_FILES['profile_picture']['name'])) {
        [$newPicture, $uploadError] = upload_image($_FILES['profile_picture'], UPLOAD_PROFILE_DIR, 'user');
        if ($uploadError) {
            $errors['profile_picture'] = $uploadError;
        }
    }

    if (empty($errors)) {
        $stmt = db()->prepare(
            'UPDATE users SET full_name = ?, email = ?, phone = ?, city = ?, bio = ?, profile_picture = ? WHERE id = ?'
        );
        $stmt->execute([
            $fullName, $email, $phone ?: null, $city ?: null, $bio ?: null,
            $newPicture ?? $user['profile_picture'], $user['id'],
        ]);
        if ($newPicture) {
            delete_upload($user['profile_picture'], UPLOAD_PROFILE_DIR);
        }
        flash('success', 'Your profile has been updated.');
        redirect('profile.php');
    }
}

/* ---------- Formulaire 2 : changer le mot de passe ---------- */
if ($action === 'password') {
    $newPassword = $_POST['password'] ?? '';

    if (!password_verify($_POST['current_password'] ?? '', $user['password'])) {
        $pwErrors['current_password'] = 'Current password is incorrect.';
    }
    if (password_error($newPassword)) {
        $pwErrors['password'] = password_error($newPassword);
    }
    if ($newPassword !== ($_POST['confirm_password'] ?? '')) {
        $pwErrors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($pwErrors)) {
        db()->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        remember_forget((int) $user['id']);           // les autres appareils doivent se reconnecter
        session_regenerate_id(true);
        flash('success', 'Password changed successfully.');
        redirect('profile.php');
    }
}

// Statistiques en haut du profil
$stmt = db()->prepare(
    "SELECT (SELECT COUNT(*) FROM products     WHERE user_id = ?)                     AS listings,
            (SELECT COUNT(*) FROM products     WHERE user_id = ? AND status = 'sold') AS sold,
            (SELECT COUNT(*) FROM favorites    WHERE user_id = ?)                     AS favorites,
            (SELECT COUNT(*) FROM reservations WHERE buyer_id = ? AND status = 'active' AND expires_at > NOW()) AS reservations"
);
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);

render('profile', [
    'pageTitle'     => 'My Profile',
    'errors'        => $errors,
    'pwErrors'      => $pwErrors,
    'stats'         => $stmt->fetch(),
    'isProfilePost' => $action === 'profile',
    // valeur d'un champ : ce qui a été tapé, sinon la valeur enregistrée
    'val'           => fn($key) => e($_POST[$key] ?? $user[$key] ?? ''),
]);
