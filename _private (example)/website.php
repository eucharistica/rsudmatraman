<?php

return [
  // DB Website (khusus CMS/portal)
  'DB_HOST' => '',
  'DB_NAME' => '',
  'DB_USER' => '',
  'DB_PASS' => '',
  'DB_CHARSET' => 'utf8mb4',

  // Google OAuth (Web application credentials)
  'GOOGLE_CLIENT_ID'     => '',
  'GOOGLE_CLIENT_SECRET' => '',
  'GOOGLE_REDIRECT_URI'  => '',

  // Keamanan sesi
  'APP_COOKIE_DOMAIN' => '', 
  'APP_SECURE_COOKIES' => true, 

  'DEFAULT_ROLE' => 'user',
  'DEFAULT_PORTAL_PATH' => '/pages/portal',
  'DEFAULT_DASH_PATH' => '/pages/dashboard',
  
  'RECAPTCHA_SITE_KEY'   => '',
  'RECAPTCHA_SECRET_KEY' => '',
];