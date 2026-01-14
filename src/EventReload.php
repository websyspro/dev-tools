<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Collection;
use Websyspro\DevTools\Shareds\WebSocket;

class EventReload
{
  private static WebSocket $webSocket;
  private static Process $process;

  public function statup(
    WatchConfig|null $watchConfig = null
  ) {
    if( isset( EventReload::$process ) === false ){
      EventReload::$process = new Process();
    }

    EventReload::$process->websocket();
  }

  public function handle(
    WatchFile $watchFile,
    FileStatus $fileStatus
  ): void {
    /* Clear screen for fresh output */
    $this->clearScreen();
    $this->notifyClients();
  }

  private function notifyClients(): void {
    $socket = @socket_create( AF_INET, SOCK_STREAM, SOL_TCP);
    if( $socket ){
      if( socket_connect( $socket, '127.0.0.1', 3000 ) === true){
        socket_write( $socket, "notification" );
        socket_close( $socket );
      }
    }
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
}