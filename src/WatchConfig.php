<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Collection;

/**
 * Configuration class for file watching functionality.
 * Manages the directories to be monitored for changes.
 */
class WatchConfig
{
  public Collection $directories; // Collection of directories to watch

  /**
   * Constructor - initializes with directories to watch
   */
  public function __construct(
    array $directories = []
  ){
    $this->directories = new Collection( $directories );
  }

  /**
   * Checks if there are directories configured for watching
   */
  public function exist(
  ): bool {
    return $this->directories->exist();
  }
}