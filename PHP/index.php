<?php
$path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if ($path === '' || $path === 'index') $path = 'home';

$script = $path . '.php';
$file   = __DIR__ . '/' . $script;

if (is_file($file)) {
  require $file;
  exit;
}

http_response_code(404);
echo "Page '$path' not found";
