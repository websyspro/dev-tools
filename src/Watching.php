<?php

namespace Websyspro\DevTools;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Websyspro\Commons\Collection;
use Websyspro\Commons\File;
use Websyspro\Commons\Util;

class Watching
{
  private WatchConfig $watchConfig;
  private Collection $watchCurrent;
  private Collection $watchLasted;

  public function __construct(
    private Collection $directories = new Collection(),
    private Collection $ignoreds = new Collection()
  ){}

  public function listen(
  ): void {
    $this->start();
    $this->loop();
  }

  private function configFile(
  ): string {
    return implode(
      DIRECTORY_SEPARATOR, 
      [
        getcwd(), "watch.json"
      ]
    );
  }

  private function configFromJson(
  ): void {
    File::exist( $this->configFile())
      ? $this->watchConfig = new WatchConfig(
        json_decode( 
          File::get( $this->configFile())
        )->directories
      ) : $this->watchConfig = new WatchConfig();
  }

  private function start(
  ): void {
    $this->configFromJson();    
  }

  private function filesFromDirectory(
    string $directory,
    array $files = []
  ): array {
    if( File::exist($directory) === false ){
      return [];
    }

    $directoryInterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator(
        $directory,
        RecursiveDirectoryIterator::SKIP_DOTS
      )
    );

    foreach( $directoryInterator as $file ){
      if( $file->isFile() === true && $file->getExtension() === "php" ){
        $files[] = new WatchFile(
          $file->getPathname(),
          $file->getMTime()
        );
      }
    }

    return $files;
  }

  private function watchFiles(
  ): Collection {
    return new Collection(
      $this->watchConfig->directories->reduce(
        [], fn(array $curr, string $directory) => Util::merge(
          $curr, $this->filesFromDirectory( $directory )
        )
      )
    );
  }

  private function isLoopEvent(
  ): bool {
    return isset($this->watchCurrent) && $this->watchCurrent->exist()
        && isset($this->watchLasted) && $this->watchCurrent->exist();
  }

  private function defineWatchCurrentFiles(
  ): void {
    $this->watchCurrent = $this->watchFiles();
  }

  private function defineWatchLastedFiles(
  ): void {
    $this->watchLasted = $this->watchCurrent;
  }

  public function clearScreen(
  ): void {
    if (function_exists( "posix_isatty" ) && !posix_isatty(STDOUT)) {
        return; // CI / redirect
    }

    echo "\033[2J\033[H";
  }
  
  private function runEntryPoint(
  ): void {
    echo "\n[Logger]\n\n";
    passthru( "php index.php" );
  }  

  private function defineLogger(
    WatchFile $watchFile,
    FileStatus $fileStatus
  ): void {
    $this->clearScreen();

    $color = match($fileStatus){
      FileStatus::Added => "\033[32m",
      FileStatus::Modified => "\033[33m",
      FileStatus::Removed => "\033[31m",
    };

    print Util::sprintFormat(
      "\033[1mWebsyspro DevTools · Watch\033[0m\n\n%s[%s]\033[0m %s @ %s\n", [
        $color, $fileStatus->name, $watchFile->path, $watchFile->timestamp()
      ]
    );

    $this->runEntryPoint();
  }

  private function isWatchModified(
  ): bool {
    return $this->watchCurrent->count() === $this->watchLasted->count(); 
  }

  private function watchModifiedDiff(
  ): Collection {
    return $this->watchCurrent->where( fn( WatchFile $current ) => (
      $this->watchLasted->where( fn( WatchFile $lasted ) => (
        $lasted->hash === $current->hash && $lasted->timestamp !== $current->timestamp
      ))->exist()
    ));
  }  

  private function watchModified(
  ): void {
    $this->watchModifiedDiff()->mapper( function(WatchFile $watchFile ) {
      $this->defineLogger( $watchFile, FileStatus::Modified );
    });
  }

  private function isWatchRemoved(
  ): bool {
    return $this->watchCurrent->count() < $this->watchLasted->count(); 
  }  

  private function watchRemovedDiff(
  ): Collection {
    return $this->watchLasted->where( fn( WatchFile $lasted ) => (
      $this->watchCurrent->where( fn( WatchFile $current ) => (
        $lasted->hash === $current->hash
      ))->exist() === false
    ));
  }
  
  private function watchRemoved(
  ): void {
    $this->watchRemovedDiff()->mapper( function(WatchFile $watchFile ) {
      $this->defineLogger( $watchFile, FileStatus::Removed );
    });
  }
  
  private function isWatchAdded(
  ): bool {
    return $this->watchCurrent->count() > $this->watchLasted->count(); 
  }
  
  private function watchAddedDiff(
  ): Collection {
    return $this->watchCurrent->where( fn( WatchFile $current ) => (
      $this->watchLasted->where( fn( WatchFile $lasted ) => (
        $lasted->hash === $current->hash
      ))->exist() === false
    ));
  }
  
  private function watchAdded(
  ): void {
    $this->watchAddedDiff()->mapper( function(WatchFile $watchFile ) {
      $this->defineLogger( $watchFile, FileStatus::Added );
    });
  }

  private function loopEvent(
  ): void {
    $this->defineWatchCurrentFiles();

    if( $this->isLoopEvent() ) {
      if( $this->isWatchModified() ){
        $this->watchModified();    
      } else
      if( $this->isWatchRemoved() ){
        $this->watchRemoved();
      } else
      if( $this->isWatchAdded() ){
        $this->watchAdded();
      }
    }

    $this->defineWatchLastedFiles();
  }

  private function loop(
  ): never {
    while(true){
      clearstatcache();
      sleep( 1 );

      if($this->watchConfig->exist()){
        $this->loopEvent();
      }
    }
  }
}