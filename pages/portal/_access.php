<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app.php';
app_boot();

$role = auth_role(); 

if ($role === 'editor') {
  header('Location: /pages/dashboard');
  audit_log('permission','access_denied', [
    'message'=>'Coba akses dashboard oleh non-admin',
    'meta'=>['requested'=>$_SERVER['REQUEST_URI'] ?? '']
]);
  exit;
}