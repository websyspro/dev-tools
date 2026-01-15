<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Util;
use Websyspro\DevTools\Shareds\WebSocket;
use Websyspro\DevTools\Shareds\WebSocketNotifier;

class EventReload extends EventUtils
{
  private static Process $process;

  private static WebSocketNotifier $webSocketNotifier;

  public function statup(
    WatchConfig|null $watchConfig = null
  ) {
    $this->loggerServicesAll();
    $this->loggerClearAll();
    $this->loggerHeader();    
  }

  private function loggerServicesAll(
  ): void {
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
    $this->notifyClients();

    /* Display formatted header with file change information */
    print Util::sprintFormat(
      "\033[32m[Reload]\033[90m %s @ %s\033[0m\n\n", [
        $watchFile->path, $watchFile->timestamp()
      ]
    );
  }

  private function notifyClients(): void {
    if( isset( EventReload::$webSocketNotifier ) === false ){
      EventReload::$webSocketNotifier = new WebSocketNotifier();
    }

    EventReload::$webSocketNotifier->notify( "reload" );
  }
}