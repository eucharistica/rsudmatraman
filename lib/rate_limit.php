<?php
declare(strict_types=1);

// sangat sederhana — simpan counter di session. Nanti bisa pindah ke Redis/DB.
function login_throttle_check(): bool {
  $now = time();
  $w   = $_SESSION['login_window'] ?? ['start'=>$now,'fail'=>0];
  if ($now - $w['start'] > 300) $w = ['start'=>$now,'fail'=>0]; // reset 5 menit
  $_SESSION['login_window'] = $w;
  // true artinya masih boleh mencoba
  return $w['fail'] < 5;
}
function login_throttle_fail(): void {
  $now = time();
  $w   = $_SESSION['login_window'] ?? ['start'=>$now,'fail'=>0];
  if ($now - $w['start'] > 300) $w = ['start'=>$now,'fail'=>0];
  $w['fail']++;
  $_SESSION['login_window'] = $w;
}
function login_throttle_reset(): void {
  unset($_SESSION['login_window']);
}
