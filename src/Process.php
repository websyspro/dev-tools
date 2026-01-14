<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Collection;
use Websyspro\Commons\Util;
use Websyspro\DevTools\Shareds\Run;

class Process
{
  private static Collection $runs;

  public function __construct(
  ){
    $this->registerShutdown();
  }

  public function websocket(
  ) {
    $run = new Run();
    $run->command( 
      Util::sprintFormat( 
        "php %s", [
          Util::path( [ 
            dirname(__FILE__), "Starteds", "Websocket.php" 
          ])
        ]
      )
    );

    if( $run->process !== false ){
      Process::$runs->add( $run );
    }
  }

  public function router(
  ) {}

  private function registerShutdown( 
  ) {
    register_shutdown_function(
      function () {
        Process::$runs->mapper(
          function( Run $run ){
            if( \is_resource( $run->process )){
              proc_terminate( $run->process);
            }
          }
        );

        echo "\n[Reload] All processes terminated.\n";
      }
    );
  }  
}