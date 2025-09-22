<?php
function db(array $cfg = null): PDO {
  static $pdo;
  if ($pdo instanceof PDO) return $pdo;

  if ($cfg === null) {
    $cfg = require __DIR__ . '/../_private/website.php';
  }

  $dsn = 'mysql:host='.$cfg['DB_HOST'].';dbname='.$cfg['DB_NAME'].';charset='.$cfg['DB_CHARSET'];
  $opts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];
  $pdo = new PDO($dsn, $cfg['DB_USER'], $cfg['DB_PASS'], $opts);
  return $pdo;
}
