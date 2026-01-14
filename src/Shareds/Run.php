<?php

namespace Websyspro\DevTools\Shareds;

use RuntimeException;

class Run
{
  public mixed $process;

  public function osSystemOrNull(
  ) {
    return strtoupper( 
      substr(
        PHP_OS,
        0,
        3
      )
    ) === "WIN" ? "NUL" : "php://null";
  }

  public function command(
    string $message, 
    bool $silence = true
  ): void {
    $descriptors = [
      0 => [ "pipe", "r" ],
      1 => $silence ? [ "file", $this->osSystemOrNull(), "w" ] : [ "pipe", "w" ],
      2 => $silence ? [ "file", $this->osSystemOrNull(), "w" ] : [ "pipe", "w" ],
    ];

    $this->process = proc_open(
      $message, 
      $descriptors, 
      $pipes
    );

    if( \is_resource( $this->process )){
      throw new RuntimeException("Could not start process: {$message}");
    }
  }
}