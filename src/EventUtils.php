<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Util;

class EventUtils
{
  public string $eventName = "Event";

  /**
   * Clear the terminal screen for fresh output display
   * 
   * Uses ANSI escape codes to clear the screen and position cursor at top-left.
   * Automatically detects CI environments or redirected output to prevent
   * clearing when not appropriate (e.g., in automated builds or file redirects).
   * 
   * @return void
   */
  public function loggerClearAll(
  ): void {
    /* Skip clearing in CI environments or when output is redirected */
    if( function_exists( "posix_isatty" ) && !posix_isatty( STDOUT )) {
        return; // CI / redirect
    }

    /* ANSI escape codes to clear screen and move cursor to top-left */
    echo "\033[2J\033[H";
  }  

  public function loggerHeader(
  ) {
    print Util::sprintFormat(
      "\033[1mWebsyspro DevTools · Watch/{$this->eventName}\033[0m\n\n", []
    );
  }
}