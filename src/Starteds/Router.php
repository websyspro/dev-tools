<?php

require __DIR__ . "/../../../../autoload.php";

use Websyspro\Commons\Util;

/**
 * Router.php - Development Server Router
 * 
 * This router handles HTTP requests for the PHP built-in development server.
 * It processes index routes, injects live-reload functionality, handles errors,
 * and serves static files.
 * 
 * @package Starteds
 */

// Extract request URI and document root from server variables
[ "REQUEST_URI" => $requestUri,
  "DOCUMENT_ROOT" => $documentRoot
] = $_SERVER;

// Parse the request URI to extract only the path component (removes query strings)
$requestUri = parse_url(
  $requestUri,
  PHP_URL_PATH
);

// Handle index routes (root and index.php)
if( Util::inArray( $requestUri, [ "/", "/index.php" ])) {
	try {
		// Start output buffering to capture the index.php output
		ob_start();
		require "{$documentRoot}/index.php";
		$html = ob_get_clean();

		// Inject live-reload script for development hot-reloading
		if( file_exists(__DIR__ . "/../Scripts/reload.js" )){
			// If HTML has a closing body tag, inject script before it
			if( stripos( $html, "</body>" ) !== false ){
					$html = str_replace(
						"</body>",
						Util::sprintFormat( "<script>%s</script></body>", [
							file_get_contents( __DIR__ . "/../Scripts/reload.js" )
						]),
						$html
					);
			} else {
				// Otherwise, append script at the end of the HTML
				$html .= Util::sprintFormat( "<script>%s</script>", [
					file_get_contents( __DIR__ . "/../Scripts/reload.js" )
				]);
			}
		}

		// Send the modified HTML response
		header('Content-Type: text/html');
		echo $html;
		return true;

	} catch ( Throwable $e ){
		// Clean up output buffer if an error occurs
		if( ob_get_level() ){
			ob_end_clean();
		}

		// Display formatted error page for development debugging
		http_response_code(500);
		header('Content-Type: text/html');

		echo '<h1>Dev Error</h1>';
		echo '<pre>' . htmlspecialchars((string)$e) . '</pre>';
		return true;
	}
}

// Check if the requested URI corresponds to an actual file
$file = "{$documentRoot}{$requestUri}";
if( is_file( $file )){
    // Return false to let PHP's built-in server handle static file serving
    return false;
}

// If no file found and not an index route, return 404
http_response_code( 404 );
echo 'Not Found';
return true;