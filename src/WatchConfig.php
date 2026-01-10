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
  public Collection $files;

  /**
   * Constructor - initializes with directories to watch
   */
  public function __construct(
    array $directories = [],
    array $files = []
  ){
    $this->directories = new Collection( $directories );
    $this->files = new Collection( $files );
  }

  /**
   * Checks if there are directories configured for watching
   */
  public function exist(
  ): bool {
    return $this->directories->exist();
  }
}