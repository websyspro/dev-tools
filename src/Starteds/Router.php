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
		http_response_code( 500);
		header( 'Content-Type: text/html');

		echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error - Development Server</title><style>body{font-family:system-ui,sans-serif;margin:0;padding:20px;background:#1e1e1e;color:#d4d4d4}h1{color:#f48771;margin:0 0 20px}h2{color:#4ec9b0;font-size:18px;margin:20px 0 10px}.error-box{background:#252526;border-left:4px solid #f48771;padding:15px;margin:10px 0;border-radius:4px}.error-message{color:#ce9178;font-size:16px;margin-bottom:10px}.error-file{color:#9cdcfe;font-size:14px}.error-line{color:#b5cea8;font-weight:bold}.stack-trace{background:#1e1e1e;border:1px solid #3e3e42;padding:15px;border-radius:4px;overflow-x:auto;font-family:"Courier New",monospace;font-size:13px;line-height:1.6;color:#cccccc}</style></head><body>';
		echo '<h1>Development Error</h1>';
		echo '<div class="error-box">';
		echo '<h2>Error Type</h2>';
		echo '<div class="error-message">' . htmlspecialchars(get_class($e)) . '</div>';
		echo '<h2>Message</h2>';
		echo '<div class="error-message">' . htmlspecialchars($e->getMessage()) . '</div>';
		echo '<h2>Location</h2>';
		echo '<div class="error-file">File: ' . htmlspecialchars($e->getFile()) . '</div>';
		echo '<div class="error-line">Line: ' . $e->getLine() . '</div>';
		echo '</div>';
		echo '<h2>Stack Trace</h2>';
		echo '<div class="stack-trace">' . nl2br(htmlspecialchars($e->getTraceAsString())) . '</div>';
		echo Util::sprintFormat( "<script>%s</script></body></html>", [
			file_get_contents( __DIR__ . "/../Scripts/reload.js" )
		]);
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