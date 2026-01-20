<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Util;
use Websyspro\DevTools\Shareds\WebSocket;
use Websyspro\DevTools\Shareds\WebSocketNotifier;

class EventReload extends EventUtils
{
  public string $eventName = "Reload";
  private static Process $process;

  private static WebSocketNotifier $webSocketNotifier;

  public function __construct(
    public WatchConfig $watchConfig
  ){}

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
      EventReload::$process = new Process(
        $this->watchConfig
      );
    }

    EventReload::$process->websocket();
    EventReload::$process->router();
  }

  public function loggerHeader(
  ) {
    print Util::sprintFormat(
      "\033[1mWebsyspro DevTools · Watch/{$this->eventName}\033[0m\n\nLocal: http://localhost:%s\n\n", [
        $this->watchConfig->port
      ]
    );
  }  

  public function handle(
    WatchFile $watchFile,
    FileStatus $fileStatus
  ): void {
    $this->loggerNotifyAll();

    /* Display formatted header with file change information */
    print Util::sprintFormat(
      "\033[32m[Reload]\033[90m %s @ %s\033[0m\n", [
        $watchFile->path, $watchFile->timestamp()
      ]
    );
  }

  private function loggerNotifyAll(): void {
    if( isset( EventReload::$webSocketNotifier ) === false ){
      EventReload::$webSocketNotifier = new WebSocketNotifier();
    }

    EventReload::$webSocketNotifier->notify( "reload" );
  }
}