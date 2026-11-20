<?php
/**
 * MOT DE PASSE OUBLIÉ - étape 1 : l'utilisateur donne son email,
 * on lui envoie un code à 6 chiffres valable 15 minutes.
 */
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('profile.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (empty($errors)) {
        $tooSoon = false;
        $stmt = db()->prepare('SELECT id, full_name FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        if ($account) {
            // Anti-spam : un seul code par minute
            $stmt = db()->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 MINUTE');
            $stmt->execute([$account['id']]);
            $tooSoon = (int) $stmt->fetchColumn() > 0;

            if ($tooSoon) {
                flash('warning', 'A code was just sent. Please wait one minute before asking again.');
            } else {
                // 1) Les anciens codes ne servent plus
                db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
                    ->execute([$account['id']]);

                // 2) Nouveau code : on enregistre seulement son hash (comme un mot de passe)
                $code = (string) random_int(100000, 999999);
                db()->prepare('INSERT INTO password_resets (user_id, code_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ' . RESET_CODE_MINUTES . ' MINUTE)')
                    ->execute([$account['id'], password_hash($code, PASSWORD_DEFAULT)]);

                // 3) Envoi par email (ou affichage à l'écran en mode démo)
                $sent = send_mail($email, 'Your ReUse password reset code', reset_code_email($account['full_name'], $code));
                if (mail_demo_mode()) {
                    $_SESSION['demo_reset_code'] = $code;       // affiché sur la page suivante
                } elseif (!$sent) {
                    flash('danger', 'The email could not be sent. Check the MAIL_* settings in config/config.php.');
                }
            }
        }

        // Même message que le compte existe ou non (on ne révèle pas quels emails sont inscrits)
        $_SESSION['reset_email'] = $email;
        if (!$tooSoon) flash('success', 'If an account exists for ' . $email . ', a 6-digit code has been sent to it.');
        redirect('reset_password.php');
    }
}

render('forgot_password', [
    'pageTitle' => 'Forgot password',
    'errors'    => $errors,
]);
