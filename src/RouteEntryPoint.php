<?php

namespace Websyspro\DevTools;

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
}