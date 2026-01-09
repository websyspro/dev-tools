<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Collection;

class Watching
{
  public function __construct(
    private Collection $directories = new Collection(),
    private Collection $ignoreds = new Collection()
  ){}

  public function listen(
  ): void {
    var_dump(getcwd());
  }
}