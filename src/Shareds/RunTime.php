<?php

namespace Websyspro\DevTools\Shareds;

use RuntimeException;

class RunTime
{
  public static function osSystemOrNull(
  ) {
    return strtoupper( 
      substr(
        PHP_OS,
        0,
        3
      )
    ) === "WIN" ? "NUL" : "php://null";
  }

  public static function run(
    string $command, 
    bool $silence = true
  ) {
    $descriptors = [
      0 => [ "pipe", "r" ],
      1 => $silence ? [ "file", RunTime::osSystemOrNull(), "w" ] : [ "pipe", "w" ],
      2 => $silence ? [ "file", RunTime::osSystemOrNull(), "w" ] : [ "pipe", "w" ],
    ];

    $process = proc_open(
      $command, 
      $descriptors, 
      $pipes
    );

    if( \is_resource( $process )){
      throw new RuntimeException("Could not start process: {$command}");
    }    

    return $process;
  }
}