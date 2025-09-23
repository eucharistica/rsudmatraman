<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/app.php'; // pastikan config_get/db tersedia

// Autoload PHPMailer (Composer)
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
  require_once $vendorAutoload;
}

/**
 * Buat instance PHPMailer terkonfigurasi dari settings.
 * Fallback ke mail() kalau SMTP disabled/PHPMailer tak tersedia.
 */
function mailer_new(): PHPMailer {
  if (!class_exists(PHPMailer::class)) {
    throw new RuntimeException('PHPMailer belum terpasang. Jalankan: composer require phpmailer/phpmailer');
  }

  $m = new PHPMailer(true);
  $m->CharSet = 'UTF-8';
  $m->isHTML(true);

  $enabled = (bool) config_get('smtp.enabled', false);
  if ($enabled) {
    $host   = (string) config_get('smtp.host', 'smtp.gmail.com');
    $port   = (int)    config_get('smtp.port', 587);
    $user   = (string) config_get('smtp.username', '');
    $pass   = (string) config_get('smtp.password', '');
    $secure = (string) config_get('smtp.secure', 'tls'); // '', 'tls', 'ssl'

    $m->isSMTP();
    $m->Host = $host;
    $m->Port = $port;
    if ($secure === 'ssl') {
      $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($secure === 'tls') {
      $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
      $m->SMTPSecure = false;
    }
    $m->SMTPAuth = ($user !== '' || $pass !== '');
    $m->Username = $user;
    $m->Password = $pass;

    // Gmail kadang perlu ini:
    $m->SMTPOptions = [
      'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
    ];
  } else {
    $m->isMail(); // pakai mail() bawaan
  }

  $fromEmail = (string) config_get('smtp.from_email', 'no-reply@localhost');
  $fromName  = (string) config_get('smtp.from_name', config_get('site.name', 'Website'));
  $m->setFrom($fromEmail, $fromName);

  return $m;
}

/**
 * Kirim email sederhana.
 */
function mailer_send(string $toEmail, string $toName, string $subject, string $html, string $alt=''): bool {
  $m = mailer_new();
  $m->addAddress($toEmail, $toName ?: $toEmail);
  $m->Subject = $subject;
  $m->Body    = $html;
  $m->AltBody = $alt ?: strip_tags($html);

  return $m->send();
}
