<?php
function pw_is_strong(string $pw): bool {
  return (bool)preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $pw);
}
function phone_is_indo(string $p): bool {
  return (bool)preg_match('/^(\+62|62|0)8\d{8,11}$/', trim($p));
}
function phone_to_e164_id(string $p): string {
  $p = trim($p);
  if (strpos($p, '+62') === 0) return $p;
  $digits = preg_replace('/\D+/', '', $p);
  if (strpos($digits, '62') === 0) return '+'.$digits;
  if (strpos($digits, '0') === 0) return '+62'.substr($digits,1);
  return '+62'.$digits; // fallback
}
