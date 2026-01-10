<?php

namespace Websyspro\DevTools;

class WatchFile
{
  public string $hash;

  public function __construct(
    public string $path,
    public string $timestamp
  ){
    $this->hash = md5(
      $this->path
    );
  }

  public function timestamp(): string {
    return date( "Y-m-d H:i:s", $this->timestamp);
  }
}