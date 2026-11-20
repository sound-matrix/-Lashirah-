<?php
declare(strict_types=1);

/**
 * Enquiry sender. Mailbox configuration lives OUTSIDE public_html:
 * domains/lashirah.com/private/mail.local.php (PHP file returning an array).
 *
 * Hostinger SMTP (smtp.hostinger.com:465 over TLS) is used when configured.
 * Until configuration is provided, retain the existing PHP mail() path to
 * avoid silently dropping live customer enquiries. Log this fallback so
 * it is not mistaken for authenticated production delivery.
 */
function lash_send_enquiry(string $recipient, string $visitorName, string $visitorEmail, string $subject, string $body): bool
{
    $configFile = dirname(__DIR__, 2) . '/private/mail.local.php';
    $config = is_file($configFile) ? require $configFile : null;

    if (is_array($config) && !empty($config['username']) && !empty($config['password'])) {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            error_log('Lashirah contact: PHPMailer unavailable; SMTP not attempted.');
            return false;
        }
        require_once $autoload;
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.hostinger.com';
            $mail->SMTPAuth = true;
            $mail->Username = (string)$config['username'];
            $mail->Password = (string)$config['password'];
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->Timeout = 15;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom((string)$config['username'], 'Lashirah Enquiries');
            $mail->addAddress($recipient);
            $mail->addReplyTo($visitorEmail, $visitorName);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);
            return $mail->send();
        } catch (\Throwable $error) {
            error_log('Lashirah contact: authenticated SMTP delivery failed: '.$error->getMessage());
            return false;
        }
    }

    // Temporary legacy fallback: configured SMTP is necessary to improve delivery.
    error_log('Lashirah contact: unauthenticated PHP mail fallback in use; configure SMTP.');
    $headers = [
        'From' => 'Lashirah Enquiries <no-reply@lashirah.com>',
        'Reply-To' => $visitorName . ' <' . $visitorEmail . '>',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer' => 'Lashirah Enquiry Form',
    ];
    return function_exists('mail') && @mail($recipient, $subject, $body, $headers);
}
