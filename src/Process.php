<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Collection;
use Websyspro\DevTools\Shareds\RunTime;

class Process
{
  private static Collection $runTimes;

  public function __construct(
  ){
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
        Process::$runTimes->mapper(
          function(RunTime $runTime){
            if( \is_resource( $runTime->process )){
              proc_terminate( $runTime->process);
            }
          }
        );

        echo "\n[Reload] All processes terminated.\n";
      }
    );
  }  
}