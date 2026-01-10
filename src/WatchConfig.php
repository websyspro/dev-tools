<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Collection;

class WatchConfig
{
  public Collection $directories;

  public function __construct(
    array $directories = []
  ){
    $this->directories = new Collection( $directories );
  }

  public function exist(
  ): bool {
    return $this->directories->exist();
  }
}