<?php
declare(strict_types=1);

/**
 * Load aplikasi config sekali, dengan dukungan:
 * - Format baru: file _private/website.php me-return array
 * - Format lama: pakai define('DB_HOST',..), dll
 */
function cfg(): array {
  static $CFG;
  if (is_array($CFG)) return $CFG;

  $file = __DIR__ . '/../_private/website.php';
  if (!is_file($file)) {
    throw new RuntimeException('Config file not found: _private/website.php');
  }

  // Bisa mengembalikan array (baru) atau bool/int (lama)
  $loaded = require $file;

  if (is_array($loaded)) {
    $CFG = $loaded;
    return $CFG;
  }

  // Fallback legacy constants
  if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    $CFG = [
      'DB' => [
        'host'    => DB_HOST,
        'name'    => DB_NAME,
        'user'    => DB_USER,
        'pass'    => defined('DB_PASS')    ? DB_PASS    : '',
        'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
      ],
      // Secret/keys lain kalau ada constant:
      'RECAPTCHA_SITE_KEY'   => defined('RECAPTCHA_SITE_KEY')   ? RECAPTCHA_SITE_KEY   : '',
      'RECAPTCHA_SECRET_KEY' => defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '',
    ];
    return $CFG;
  }

  throw new RuntimeException('Invalid config: _private/website.php must return array OR define DB_* constants.');
}

/** Singleton PDO */
function db(): PDO {
  static $pdo;
  if ($pdo instanceof PDO) return $pdo;

  $CFG = cfg();
  if (!isset($CFG['DB']) || !is_array($CFG['DB'])) {
    throw new RuntimeException('Missing DB config (CFG["DB"]).');
  }

  $host    = $CFG['DB']['host']    ?? '127.0.0.1';
  $name    = $CFG['DB']['name']    ?? '';
  $user    = $CFG['DB']['user']    ?? '';
  $pass    = $CFG['DB']['pass']    ?? '';
  $charset = $CFG['DB']['charset'] ?? 'utf8mb4';

  if ($name === '' || $user === '') {
    throw new RuntimeException('DB name/user must not be empty.');
  }

  $dsn  = "mysql:host={$host};dbname={$name};charset={$charset}";
  $opts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];

  $pdo = new PDO($dsn, $user, $pass, $opts);
  return $pdo;
}
