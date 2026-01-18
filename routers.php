<?php

declare(strict_types=1);

if(defined("ROUTE_ROOT") === false){
  define( "ROUTE_ROOT", __DIR__ );
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

/*
|--------------------------------------------------------------------------
| 1️⃣ Servir arquivos estáticos (uploads, assets, themes)
|--------------------------------------------------------------------------
*/
$mimes = [
  'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 
  'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
  'pdf' => 'application/pdf', 'js' => 'application/javascript', 
  'css' => 'text/css', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
  'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject',
  'ico' => 'image/x-icon'
];

if (str_starts_with($uri, '/uploads')) {
    $file = ROUTE_ROOT . '/src' . $uri;
    if (is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

if (str_starts_with($uri, '/wp-content/')) {
    $file = ROUTE_ROOT . '/src' . substr($uri, 12);
    if (is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| 2️⃣ Bootstrap
|--------------------------------------------------------------------------
*/
//require ROUTE_ROOT . '/vendor/autoload.php';

$wpRoot = ROUTE_ROOT . '/vendor/websyspro/wpengine/src/Core';

define('WP_CONTENT_DIR', ROUTE_ROOT . '/src');
define('WP_CONTENT_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('WP_SITEURL', 'http://' . $_SERVER['HTTP_HOST']);
define('WP_HOME', 'http://' . $_SERVER['HTTP_HOST']);

/*
|--------------------------------------------------------------------------
| 3️⃣ Verificar se é arquivo físico do WordPress
|--------------------------------------------------------------------------
*/
$wpFile = $wpRoot . str_replace('/', DIRECTORY_SEPARATOR, $uri);

// Se é diretório, tenta index.php dentro dele
if (is_dir($wpFile)) {
    $wpFile = rtrim($wpFile, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
}



if (is_file($wpFile)) {
    // É arquivo físico: ajusta $_SERVER e executa
    $_SERVER['SCRIPT_FILENAME'] = $wpFile;
    $_SERVER['SCRIPT_NAME'] = $uri;
    $_SERVER['PHP_SELF'] = $uri;
    
    require $wpFile;
} else {
    // NÃO é arquivo físico: é um slug, deixa o WordPress processar
    require $wpRoot . '/index.php';
}