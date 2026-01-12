<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Util;

class EventWatch
{
  public function handle(
    WatchFile $watchFile,
    FileStatus $fileStatus
  ): void {
    /* Clear screen for fresh output */
    $this->clearScreen();

    /* Define colors for different file status types */
    $color = match($fileStatus){
      FileStatus::Added => "\033[32m",    // Green
      FileStatus::Modified => "\033[33m", // Yellow
      FileStatus::Removed => "\033[31m",  // Red
    };

    /* Display formatted header with file change information */
    print Util::sprintFormat(
      "\033[1mWebsyspro DevTools · Watch\033[0m\n\n%s[%s]\033[0m %s @ %s\n", [
        $color, $fileStatus->name, $watchFile->path, $watchFile->timestamp()
      ]
    );

    /* Execute the entry point and show results */
    $this->runEntryPoint();    
  }

  private function clearScreen(
  ): void {
    /* Skip clearing in CI environments or when output is redirected */
    if( function_exists( "posix_isatty" ) && !posix_isatty( STDOUT )) {
        return; // CI / redirect
    }

    /* ANSI escape codes to clear screen and move cursor to top-left */
    echo "\033[2J\033[H";
  }

  private function runEntryPoint(
  ): void {
    /* Display debug info with execution time followed by script output */
    echo "\n[Debug]\n\n";

    /* Execute the main application entry point */
    passthru( "php index.php" );
  } 
}