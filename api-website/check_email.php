<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
app_boot(['skip_maintenance_for'=>['/api-website/']]);

header('Content-Type: application/json; charset=utf-8');

$email = strtolower(trim((string)($_GET['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok'=>true,'exists'=>false]); exit;
}
try {
  $db = db();
  $st = $db->prepare('SELECT 1 FROM users WHERE email=:e LIMIT 1');
  $st->execute([':e'=>$email]);
  $exists = (bool)$st->fetchColumn();
  echo json_encode(['ok'=>true,'exists'=>$exists]);
} catch (Throwable $e){
  http_response_code(200);
  echo json_encode(['ok'=>true,'exists'=>false]);
}