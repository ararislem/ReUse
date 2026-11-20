<?php
/**
 * ENVOI D'EMAIL (sans bibliothèque, en PHP natif)
 * -----------------------------------------------
 * La fonction mail() de PHP ne marche pas dans XAMPP sans configuration.
 * On parle donc directement au serveur SMTP (ex. Gmail) avec une connexion réseau.
 *
 * Configuration : constantes MAIL_* dans config/config.php
 *
 * MODE DÉMO : si MAIL_PASSWORD est vide, aucun email n'est envoyé.
 * Le code s'affiche alors à l'écran (pratique pour la soutenance sans internet).
 */

/** Le mode démo est actif quand aucun mot de passe SMTP n'est configuré. */
function mail_demo_mode(): bool
{
    return MAIL_PASSWORD === '';
}

/**
 * Envoie un email HTML. Renvoie true si le serveur SMTP a accepté le message.
 */
function send_mail(string $to, string $subject, string $html): bool
{
    if (mail_demo_mode()) {
        return false;
    }

    try {
        // 1) Connexion au serveur
        $address = (MAIL_ENCRYPTION === 'ssl' ? 'ssl://' : 'tcp://') . MAIL_HOST . ':' . MAIL_PORT;
        $smtp = stream_socket_client($address, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, smtp_ssl_context());
        if (!$smtp) {
            throw new Exception("Connection failed: $errstr");
        }
        stream_set_timeout($smtp, 15);
        smtp_expect($smtp, 220);

        // 2) Présentation + chiffrement TLS (port 587)
        smtp_command($smtp, 'EHLO localhost', 250);
        if (MAIL_ENCRYPTION === 'tls') {
            smtp_command($smtp, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('TLS failed');
            }
            smtp_command($smtp, 'EHLO localhost', 250);
        }

        // 3) Authentification (identifiant et mot de passe encodés en base64)
        if (MAIL_USERNAME !== '') {
            smtp_command($smtp, 'AUTH LOGIN', 334);
            smtp_command($smtp, base64_encode(MAIL_USERNAME), 334);
            smtp_command($smtp, base64_encode(MAIL_PASSWORD), 235);
        }

        // 4) Expéditeur, destinataire, contenu
        smtp_command($smtp, 'MAIL FROM:<' . MAIL_FROM . '>', 250);
        smtp_command($smtp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($smtp, 'DATA', 354);

        $headers = [
            'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Date: ' . date('r'),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $body = chunk_split(base64_encode($html));                 // base64 = pas de souci d'accents
        fwrite($smtp, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
        smtp_expect($smtp, 250);

        smtp_command($smtp, 'QUIT', 221);
        fclose($smtp);
        return true;
    } catch (Exception $e) {
        error_log('ReUse mail error: ' . $e->getMessage());       // visible dans les logs Apache
        return false;
    }
}

/** Envoie une commande au serveur SMTP et vérifie le code de réponse. */
function smtp_command($smtp, string $command, $expectedCode): void
{
    fwrite($smtp, $command . "\r\n");
    smtp_expect($smtp, $expectedCode);
}

/**
 * Lit la réponse du serveur. Une réponse peut faire plusieurs lignes :
 * "250-..." veut dire "il y a d'autres lignes", "250 ..." est la dernière.
 */
function smtp_expect($smtp, $expectedCode): void
{
    $response = '';
    while (($line = fgets($smtp, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, (array) $expectedCode, true)) {
        throw new Exception('SMTP error: ' . trim($response));
    }
}

/** Certificats SSL : XAMPP Windows fournit un fichier de certificats à utiliser. */
function smtp_ssl_context()
{
    $options = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]];
    $xamppCertificates = 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt';
    if (is_file($xamppCertificates)) {
        $options['ssl']['cafile'] = $xamppCertificates;
    }
    return stream_context_create($options);
}

/** Contenu HTML de l'email "mot de passe oublié". */
function reset_code_email(string $name, string $code): string
{
    return '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:24px;background:#F5F1E8;border-radius:16px">'
         . '<h2 style="color:#183F35;margin-top:0">ReUse</h2>'
         . '<p>Hello ' . e($name) . ',</p>'
         . '<p>Here is your code to reset your password:</p>'
         . '<p style="font-size:32px;font-weight:bold;letter-spacing:8px;color:#183F35;background:#fff;padding:16px;text-align:center;border-radius:12px">' . $code . '</p>'
         . '<p>This code expires in ' . RESET_CODE_MINUTES . ' minutes. If you did not ask for it, ignore this email.</p>'
         . '</div>';
}
