<?php

require __DIR__ . "/../../../../autoload.php";

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/**
 * 1️⃣ Servir o script de reload
 */
if ($uri === '/reload.js') {
    header('Content-Type: application/javascript');
    readfile(__DIR__ . '/../Scripts/reload.js');
    return true;
}

/**
 * 2️⃣ Página principal (HTML)
 */
if ($uri === '/' || $uri === '/index.php') {

    try {
        ob_start();
        require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
        $html = ob_get_clean();

        if (stripos($html, '</body>') !== false) {
            $html = str_replace(
                '</body>',
                '<script src="/reload.js"></script></body>',
                $html
            );
        }

        header('Content-Type: text/html');
        echo $html;
        return true;

    } catch (Throwable $e) {

        if (ob_get_level()) {
            ob_end_clean();
        }

        http_response_code(500);
        header('Content-Type: text/html');

        echo '<h1>Dev Error</h1>';
        echo '<pre>' . htmlspecialchars((string)$e) . '</pre>';
        return true;
    }
}

/**
 * 3️⃣ Arquivos físicos normais
 */
$file = $_SERVER['DOCUMENT_ROOT'] . $uri;
if (is_file($file)) {
    return false;
}

/**
 * 4️⃣ Fallback
 */
http_response_code(404);
echo 'Not Found';
return true;