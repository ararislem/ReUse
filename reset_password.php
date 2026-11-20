<?php
/**
 * MOT DE PASSE OUBLIÉ - étape 2 : l'utilisateur tape le code reçu
 * et choisit un nouveau mot de passe.
 *   - le code expire après 15 minutes
 *   - 5 essais ratés maximum, ensuite il faut demander un nouveau code
 */
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('profile.php');
}

$email  = $_SESSION['reset_email'] ?? '';
$errors = [];

if ($email === '') {
    redirect('forgot_password.php');                 // il faut d'abord passer par l'étape 1
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $code     = preg_replace('/\D/', '', $_POST['code'] ?? '');   // on garde seulement les chiffres
    $password = $_POST['password'] ?? '';

    if (strlen($code) !== 6) {
        $errors['code'] = 'Enter the 6-digit code you received.';
    }
    if (password_error($password)) {
        $errors['password'] = password_error($password);
    }
    if ($password !== ($_POST['confirm_password'] ?? '')) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        // Le dernier code encore valable de cet utilisateur
        $stmt = db()->prepare(
            'SELECT r.id, r.user_id, r.code_hash, r.attempts
             FROM password_resets r
             JOIN users u ON u.id = r.user_id
             WHERE u.email = ? AND r.used_at IS NULL AND r.expires_at > NOW()
             ORDER BY r.id DESC
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $reset = $stmt->fetch();

        if (!$reset || $reset['attempts'] >= RESET_MAX_ATTEMPTS) {
            $errors['code'] = 'This code has expired or was tried too many times. Ask for a new one.';
        } elseif (!password_verify($code, $reset['code_hash'])) {
            db()->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?')->execute([$reset['id']]);
            $left = RESET_MAX_ATTEMPTS - $reset['attempts'] - 1;
            $errors['code'] = 'Wrong code. ' . $left . ' attempt(s) left.';
        } else {
            // Code correct : nouveau mot de passe + code marqué comme utilisé
            db()->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
            db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$reset['id']]);
            remember_forget((int) $reset['user_id']);          // déconnecte les autres appareils

            unset($_SESSION['reset_email'], $_SESSION['demo_reset_code']);
            login_user((int) $reset['user_id']);
            flash('success', 'Your password has been changed. You are now logged in.');
            redirect('marketplace.php');
        }
    }
}

render('reset_password', [
    'pageTitle' => 'Reset password',
    'email'     => $email,
    'errors'    => $errors,
    'demoCode'  => mail_demo_mode() ? ($_SESSION['demo_reset_code'] ?? null) : null,
]);
