<?php

namespace Websyspro\DevTools;

use Websyspro\DevTools\Shareds\WebSocket;
use Websyspro\DevTools\Shareds\WebSocketNotifier;

class EventReload
{
  private static Process $process;

  private static WebSocketNotifier $webSocketNotifier;

  public function statup(
    WatchConfig|null $watchConfig = null
  ) {
    if( isset( EventReload::$process ) === false ){
      EventReload::$process = new Process();
    }

    EventReload::$process->websocket();
    EventReload::$process->router();
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
    if( isset( EventReload::$webSocketNotifier ) === false ){
      EventReload::$webSocketNotifier = new WebSocketNotifier();
    }

    EventReload::$webSocketNotifier->notify( "reload" );
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