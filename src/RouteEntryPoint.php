<?php

namespace Websyspro\DevTools;

use Dom\Document;
use Throwable;
use Websyspro\DevTools\Enums\MimeType;
use Websyspro\Commons\Collection;
use Websyspro\Commons\File;
use Websyspro\Commons\Util;

if( defined('PUBLICS') === false ){
  define( 'PUBLICS', []);
}

if( defined( "ROUTE_ROOT" ) === false ){
  define( "ROUTE_ROOT", __DIR__ );
}

class RouteEntryPoint
{
  /**
   * Initialize the route entry point and process the request
   * 
   * @param string|null $requestUri The request URI path
   * @param string|null $requestQuery The query string parameters
   * @param Collection $publics Collection of public directories to search
   */
  public function __construct(
    public string|null $requestUri = null,
    public string|null $requestQuery = null,
    public Collection $publics = new Collection(PUBLICS)
  ){
    $this->defineEnvironments();
    $this->defineRequestUri();
    $this->defineServerVars();
    $this->definePublicUrl();
  }

  public function defineEnvironments(
  ): void {
    $envs = new Collection(
      file( Util::join(
        DIRECTORY_SEPARATOR, [
          ROUTE_ROOT, ".env"
        ]
      ))
    );

    $envs = $envs->where( fn(string $line) => preg_match( "#^(\#|;)#", $line) === 0 );
    $envs = $envs->where( fn(string $line) => empty( trim( $line )) === false );
    $envs = $envs->mapper( fn(string $line) => explode( "=", $line ));
    $envs = $envs->mapper( function( array $env ){
      [ $key, $val ] = $env;

      putenv( Util::sprintFormat(
        "%s=%s", [
          trim( $key ), 
          trim( $val, " \t\n\r\0\x0B\"'" )
        ]
      ));
    });
  }

  /**
   * Parse and normalize the request URI from $_SERVER
   * Appends index.php to directory paths without file extensions
   * 
   * @return void
   */
  public function defineRequestUri(
  ): void {
    /* Extract the URI path without query string */
    [ $this->requestUri ] = explode( 
      "?", $_SERVER[ "REQUEST_URI" ]
    );

    /* Parse and normalize the URI path */
    $this->requestUri = parse_url(
      $this->requestUri,
      PHP_URL_PATH
    ) ?? "/";

    /* Append index.php if URI doesn't contain a file extension */
    if( Util::match( 
      "#^.*\..*$#", 
      $this->requestUri
    ) === false ){
      $this->requestUri = Util::sprintFormat( 
        "%s/index.php", 
        [ rtrim( $this->requestUri, "/" )]
      );
    }
  } 
  
  /**
   * Filter public directories to find which contains the requested file
   * 
   * @return void
   */
  public function definePublicUrl(
  ): void {
    /* Filter public directories to find where the requested file exists */
    $this->publics = $this->publics->where(
      fn( string $public ) => is_file(
        "{$public}{$this->requestUri}"
      )
    );
  }

  /**
   * Get the full file system path to the requested resource
   * 
   * @return string Full path combining public directory and request URI
   */
  public function requestUri(
  ): string {
    /* Build the full file path by combining public directory with request URI */
    return Util::sprintFormat(
      "%s%s", [
        $this->publics->first(), $this->requestUri
      ]
    );
  }   

  /**
   * Check if the requested resource is a static file (non-PHP)
   * 
   * @return bool True if static resource, false if PHP file
   */
  public function isStatic(
  ): bool {
    /* Check if the requested file is not a PHP file (static resource) */
    return Util::match(
      "#.*\.php$#", 
      $this->requestUri
    ) === false;
  }

  /**
   * Determine the MIME type of the requested static file
   * 
   * @return MimeType|null MIME type enum or null if file not found
   */
  private function mimeTypeStatic(
  ): MimeType|null {
    /* Return null if no public directory contains the file */
    if($this->publics->exist() === false){
      return null;
    }

    /* Determine MIME type based on file extension */
    return MimeType::fromExtension(
      File::ext( $this->requestUri())
    );
  }  

  /**
   * Verify if the static file has a valid MIME type and can be served
   * 
   * @return bool True if file can be served as static resource
   */
  public function isStaticAllowed(
  ): bool {
    /* Check if MIME type is valid and file can be served as static */
    if( $this->mimeTypeStatic() === false ){
      return false;
    }

    return $this->mimeTypeStatic() instanceof MimeType;
  }
  
  /**
   * Generate Content-Type HTTP header for static file
   * 
   * @return string Formatted Content-Type header
   */
  private function getHeaderContentType(
  ): string {
    /* Build Content-Type header with the file's MIME type */
    return Util::sprintFormat(
      "Content-Type: %s", [ $this->mimeTypeStatic()->value ]
    );
  }

  /**
   * Generate Content-Length HTTP header for static file
   * 
   * @return string Formatted Content-Length header with file size
   */
  private function getHeaderContentLength(
  ): string {
    /* Build Content-Length header with the file size in bytes */
    return Util::sprintFormat(
      "Content-Length: %s", [
        File::size( $this->requestUri())
      ]
    );
  }  

  /**
   * Send static file to client with appropriate headers and terminate
   * 
   * @return void
   */
  public function sendStatic(
  ): void {
    /* Send HTTP headers and output static file content */
    header( $this->getHeaderContentType());
    header( $this->getHeaderContentLength());
    readfile( $this->requestUri());
    exit();
  }

  /**
   * Update $_SERVER superglobal with normalized PHP_SELF variable
   * 
   * @return void
   */
  public function defineServerVars(
  )  {
    /* Extract URI without query string */
    [ $requestUri ] = explode( 
      "?", $_SERVER[
        "REQUEST_URI"
      ]
    );

    /* Update $_SERVER superglobal with PHP_SELF variable */
    $_SERVER = array_merge(
      $_SERVER, [
        "PHP_SELF" => $requestUri,
      ]
    );
  }

  /**
   * Check if the requested dynamic file exists in any public directory
   * 
   * @return bool True if file exists in public directories
   */
  public function isDynamicExist(
  ): bool {
    /* Check if any public directory contains the requested file */
    return $this->publics->exist();
  }

  /**
   * Get the full path to the requested file for direct inclusion
   * 
   * @return string Full file system path
   */
  public function sendDirectFile(
  ): string {
    /* Return the full path to the requested file */
    return $this->requestUri();
  }

  /**
   * Find and return the bootstrap index.php file path
   * Falls back to notfound.php if no bootstrap exists
   * 
   * @return string Path to bootstrap file or notfound.php
   */
  public function sendBootstrap(
  ): string {
    /* Find public directories that contain an index.php file */
    $bootstraps = new Collection(
      Util::where(
        PUBLICS, fn( string $public ) => is_file(
          "{$public}/index.php"
        )
      )
    );

    /* Return first available bootstrap or fallback to notfound.php */
    return $bootstraps->exist()
      ? "{$bootstraps->first()}/index.php"
      : "notfound.php";
  }

  private function getServerRuntime(
  ): bool {
    if( php_sapi_name() === "cli-server" ){
      return true;
    }

    if( !empty( $_SERVER[ "SERVER_SOFTWARE" ])){
      if( stripos($_SERVER[ "SERVER_SOFTWARE" ], "apache" ) !== false){
        return false;
      }

      if( stripos($_SERVER[ "SERVER_SOFTWARE" ], "nginx" ) !== false){
        return false;
      }
    }

    return false;
  }  

  /**
   * Get the path to the reload.js script file
   * 
   * @return string Full path to reload.js
   */
  public function scriptReload(
  ): string {
    /* Return the absolute path to the live-reload script */
    return __DIR__ . "/Scripts/reload.js";
  }

  /**
   * Inject live-reload script into HTML and send response to client
   * 
   * @param string $html The HTML content to be sent
   * @return void
   */
  public function sendReader(
    string $html
  ): void {
    $isScriptReload = file_exists(
      $this->scriptReload()
    ) && $this->getServerRuntime();

    /* Check if reload script exists and inject it into HTML */
    if( $isScriptReload === true ){
      /* If HTML has closing body tag, inject script before it */
      if( stripos( $html, "</body>" )){
        $html = str_replace(
          "</body>", Util::sprintFormat(
            "<script>%s</script></body>", 
            [
              file_get_contents(
                $this->scriptReload()
              )
            ]
          ), $html
        );
      } else {
        /* Otherwise append script at the end of HTML */
        $html .= Util::sprintFormat( 
          "<script>%s</script>", [
            file_get_contents(
              $this->scriptReload()
            )
          ]
        );
      }
    }

    /* Calculate content length for HTTP header */
    $contentLength = Util::sizeText( $html );

    /* Send HTTP headers with content type and length */
    header( "Content-Type: text/html;charset=UTF-8" );
    header( "Content-Length: {$contentLength}" );
    
    /* Output the final HTML content */
    print $html;
  }

  /**
   * Display a formatted error page for development debugging
   * Shows error type, message, location, and stack trace
   * 
   * @param Throwable $throwable The exception or error to display
   * @return bool Always returns true
   */
  public function sendErrorReader(
    Throwable $throwable
  ): bool {
    $isScriptReload = file_exists(
      $this->scriptReload()
    ) && $this->getServerRuntime();

		/* Clean up output buffer if active */
		if( ob_get_level() ){
			ob_end_clean();
		}

		/* Set HTTP 500 status and content type */
		http_response_code( 500 );
		header( 'Content-Type: text/html');

		/* Output HTML structure with dark theme styling */
		echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error - Development Server</title><style>body{font-family:system-ui,sans-serif;margin:0;padding:20px;background:#1e1e1e;color:#d4d4d4}h1{color:#f48771;margin:0 0 20px}h2{color:#4ec9b0;font-size:18px;margin:20px 0 10px}.error-box{background:#252526;border-left:4px solid #f48771;padding:15px;margin:10px 0;border-radius:4px}.error-message{color:#ce9178;font-size:16px;margin-bottom:10px}.error-file{color:#9cdcfe;font-size:14px}.error-line{color:#b5cea8;font-weight:bold}.stack-trace{background:#1e1e1e;border:1px solid #3e3e42;padding:15px;border-radius:4px;overflow-x:auto;font-family:"Courier New",monospace;font-size:13px;line-height:1.6;color:#cccccc}</style></head><body>';
		
		/* Display error page title */
		echo '<h1>Development Error</h1>';
		
		/* Display error details in formatted box */
		echo '<div class="error-box">';
		echo '<h2>Error Type</h2>';
		echo '<div class="error-message">' . htmlspecialchars(get_class($throwable)) . '</div>';
		echo '<h2>Message</h2>';
		echo '<div class="error-message">' . htmlspecialchars($throwable->getMessage()) . '</div>';
		echo '<h2>Location</h2>';
		echo '<div class="error-file">File: ' . htmlspecialchars($throwable->getFile()) . '</div>';
		echo '<div class="error-line">Line: ' . $throwable->getLine() . '</div>';
		echo '</div>';
		
		/* Display stack trace for debugging */
		echo '<h2>Stack Trace</h2>';
		echo '<div class="stack-trace">' . nl2br(htmlspecialchars($throwable->getTraceAsString())) . '</div>';
		
		/* Inject reload script and close HTML */
		if( $isScriptReload === true ){
      echo Util::sprintFormat( "<script>%s</script></body></html>", [
        file_get_contents( $this->scriptReload() )
      ]);
    }

    return true;
  }  
}