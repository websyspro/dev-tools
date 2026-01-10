<?php

namespace Websyspro\DevTools;

/**
 * Represents a file being watched for changes.
 * Stores file path, timestamp and generates a unique hash for identification.
 */
class WatchFile
{
  public string $hash; // MD5 hash of the file path for identification

  /**
   * Constructor - creates a watch file with path and timestamp
   */
  public function __construct(
    public string $path,      // File path
    public string $timestamp  // File modification timestamp
  ){
    $this->hash = md5(
      $this->path
    );
  }

  /**
   * Formats timestamp into readable date string
   */
  public function timestamp(
  ): string {
    return date( "Y-m-d H:i:s", $this->timestamp);
  }
}