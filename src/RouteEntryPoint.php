<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Collection;
use Websyspro\Commons\File;
use Websyspro\Commons\Util;
use Websyspro\DevTools\Enums\MimeType;

if( defined( "PUBLICS" ) === false ){
  define( "PUBLICS", []);
}

class RouteEntryPoint
{
  public function __construct(
    public string|null $requestUri = null,
    public Collection $publics = new Collection(PUBLICS)
  ){
    $this->defineRequestUri();
    $this->definePublicUrl();
  }

  public function defineRequestUri(
  ): void {
    [ $this->requestUri ] = explode( 
      "?", $_SERVER[ "REQUEST_URI" ]
    );

    $this->requestUri = parse_url(
      $this->requestUri,
      PHP_URL_PATH
    ) ?? "/";

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
  
  public function definePublicUrl(
  ): void {
    $this->publics = $this->publics->where(
      fn( string $public ) => is_file(
        "{$public}{$this->requestUri}"
      )
    );
  }

  public function requestUri(
  ): string {
    return Util::sprintFormat(
      "%s%s", [
        $this->publics->first(), $this->requestUri
      ]
    );
  }   

  public function isStatic(
  ): bool {
    return Util::match(
      "#.*\.php$#", 
      $this->requestUri
    ) === false;
  }

  private function mimeTypeStatic(
  ): MimeType|null {
    if($this->publics->exist() === false){
      return null;
    }

    return MimeType::fromExtension(
      File::ext( $this->requestUri())
    );
  }  

  public function isStaticAllowed(
  ): bool {
    if( $this->mimeTypeStatic() === false ){
      return false;
    }

    return $this->mimeTypeStatic() instanceof MimeType;
  }
  
  private function getHeaderContentType(
  ): string {
    return Util::sprintFormat(
      "Content-Type: %s", [ $this->mimeTypeStatic()->value ]
    );
  }

  private function getHeaderContentLength(
  ): string {
    return Util::sprintFormat(
      "Content-Length: %s", [
        File::size( $this->requestUri())
      ]
    );
  }  

  public function sendStatic(
  ): void {
    header( $this->getHeaderContentType());
    header( $this->getHeaderContentLength());
    readfile( $this->requestUri());
    exit();
  }

  public function isDynamicExist(
  ): bool {
    return $this->publics->exist();
  }

  public function sendDirectFile(
  ): string {
    return $this->requestUri();
  }

  public function sendBootstrap(
  ): string {
    $bootstraps = new Collection(
      Util::where(
        PUBLICS, fn( string $public ) => is_file(
          "{$public}/index.php"
        )
      )
    );

    return $bootstraps->exist()
      ? "{$bootstraps->first()}/index.php"
      : "notfound.php";
  }
}