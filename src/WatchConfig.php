<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Collection;

/**
 * Configuration class for file watching functionality.
 * Manages the directories to be monitored for changes.
 */
class WatchConfig
{
  /**
   * Constructor - initializes with directories to watch
   */
  public function __construct(
    public Collection $directories = new Collection(),
    public int $port = 3001
  ){}

  /**
   * Checks if there are directories configured for watching
   */
  public function exist(
  ): bool {
    return $this->directories->exist();
  }
}