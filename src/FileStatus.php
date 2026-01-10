<?php

namespace Websyspro\DevTools;

/**
 * Enumeration representing the different states a file can have during file watching.
 * 
 * This enum is used to track and categorize file system changes when monitoring
 * directories for modifications, additions, or deletions.
 */
enum FileStatus
{
  case Added;    // File was added to the watched directory
  case Modified; // File was modified in the watched directory
  case Removed;  // File was removed from the watched directory
}