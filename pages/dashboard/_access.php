<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';
session_boot();

$role = auth_role(); 

if ($role !== 'admin' && $role !== 'editor') {
  header('Location: /pages/portal');
  audit_log('permission','access_denied', [
    'message'=>'Coba akses dashboard oleh non-admin',
    'meta'=>['requested'=>$_SERVER['REQUEST_URI'] ?? '']
]);
  exit;
}