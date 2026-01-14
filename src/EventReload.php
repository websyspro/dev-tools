<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Collection;
use Websyspro\DevTools\Shareds\WebSocket;

class EventReload
{
  private static WebSocket $webSocket;
  private static Collection $processes;

  public function statup(
    WatchConfig|null $watchConfig = null
  ) {
    // Enviar commandos processs
    echo "Server runing at: XXXXXXXXXXXXXXXXXXXXX\n";
  }

  public function handle(
    WatchFile $watchFile,
    FileStatus $fileStatus
  ): void {
    /* Clear screen for fresh output */
    $this->clearScreen();

    var_dump( getcwd() );
    var_dump( __FILE__ );

    // EventReload::$webSocket->send( 
    //   "reloads from Menssage"
    // );
  }  

  /**
   * Clear the terminal screen for fresh output display
   * 
   * Uses ANSI escape codes to clear the screen and position cursor at top-left.
   * Automatically detects CI environments or redirected output to prevent
   * clearing when not appropriate (e.g., in automated builds or file redirects).
   * 
   * @return void
   */
  private function clearScreen(
  ): void {
    /* Skip clearing in CI environments or when output is redirected */
    if( function_exists( "posix_isatty" ) && !posix_isatty( STDOUT )) {
        return; // CI / redirect
    }

    /* ANSI escape codes to clear screen and move cursor to top-left */
    echo "\033[2J\033[H";
  } 

  private function defineWebSocket(
  ): void {
    if( isset( EventReload::$webSocket ) === false ){
      EventReload::$webSocket = new WebSocket( 3000);
    }
  }
}