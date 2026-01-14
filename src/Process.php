<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Collection;

class Process
{
  private static Collection $processes;

  public function __construct(
  ){
    $this->registerRun();
    $this->registerShutdown();
  }

  public function registerRun(
  ) {}

  public function websocket(
  ) {}

  public function router(
  ) {}

  private function registerShutdown(
  ) {
    register_shutdown_function(
      function () {
        Process::$processes->mapper(
          function($process){
            if( \is_resource( $process )){
              proc_terminate( $process);
            }
          }
        );

        echo "\n[Reload] All processes terminated.\n";
      }
    );
  }  
}

new Process();