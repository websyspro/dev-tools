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

  private function createRun(
    string $message
  ) {
    if( !isset( Process::$runs )){
      Process::$runs = new Collection();
    }

    $run = new Run();
    $run->command( $message );

    if( $run->process !== false ){
      Process::$runs->add( $run );
    }
  }

  public function websocket(
  ) {
    $this->createRun(
      Util::sprintFormat( 
        "php %s", [
          Util::path( [ 
            dirname(__FILE__), "Starteds", "Websocket.php" 
          ])
        ]
      )
    );
  }

  public function router(
  ) {
    $this->createRun(
      Util::sprintFormat( 
        "php -S localhost:8080 %s", [
          Util::path( [ 
            dirname(__FILE__), "Starteds", "Router.php" 
          ])
        ]
      )
    );    
  }

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