<?php

namespace Websyspro\DevTools;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Websyspro\Commons\Collection;
use Websyspro\Commons\File;
use Websyspro\Commons\Util;

/**
 * File watching system for monitoring directory changes.
 * Continuously monitors directories for file additions, modifications, and deletions.
 */
class Watching
{
  private WatchConfig $watchConfig;  // Configuration for watching
  private Collection $watchCurrent;  // Current state of watched files
  private Collection $watchLasted;   // Previous state for comparison

  /**
   * Constructor - initializes with directories and ignored patterns
   */
  public function __construct(
    private Collection $directories = new Collection(),
    private Collection $ignoreds = new Collection()
  ){}

  /**
   * Starts the file watching process
   */
  public function listen(
  ): void {
    /* Initialize the watching system */
    $this->start();
    /* Start the infinite monitoring loop */
    $this->loop();
  }

  /**
   * Gets the path to the configuration file
   */
  private function configFile(
  ): string {
    /* Build the full path to watch.json in current working directory */
    return implode(
      DIRECTORY_SEPARATOR, 
      [
        getcwd(), "watch.json"
      ]
    );
  }

  /**
   * Loads configuration from JSON file
   */
  private function configFromJson(
  ): void {
    /* Check if config file exists and load it, otherwise use default empty config */
    File::exist( $this->configFile())
      ? $this->watchConfig = new WatchConfig(
        json_decode( 
          File::get( $this->configFile())
        )->directories
      ) : $this->watchConfig = new WatchConfig();
  }

  /**
   * Initializes the watching system
   */
  private function start(
  ): void {
    /* Load configuration from JSON file */
    $this->configFromJson();    
  }

  /**
   * Recursively scans directory for PHP files
   */
  private function filesFromDirectory(
    string $directory,
    array $files = []
  ): array {
    /* Return empty array if directory doesn't exist */
    if( File::exist($directory) === false ){
      return [];
    }

    /* Create recursive iterator to traverse directory tree */
    $directoryInterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator(
        $directory,
        RecursiveDirectoryIterator::SKIP_DOTS
      )
    );

    /* Iterate through all files in directory tree */
    foreach( $directoryInterator as $file ){
      /* Only process PHP files */
      if( $file->isFile() === true && $file->getExtension() === "php" ){
        /* Create WatchFile object with path and modification time */
        $files[] = new WatchFile(
          $file->getPathname(),
          $file->getMTime()
        );
      }
    }

    return $files;
  }

  /**
   * Gets all files from configured watch directories
   */
  private function watchFiles(
  ): Collection {
    /* Reduce all directories into a single collection of files */
    return new Collection(
      $this->watchConfig->directories->reduce(
        [], fn(array $curr, string $directory) => Util::merge(
          $curr, $this->filesFromDirectory( $directory )
        )
      )
    );
  }

  /**
   * Checks if this is a loop event (not first iteration)
   */
  private function isLoopEvent(
  ): bool {
    /* Verify both current and previous collections exist and have content */
    return isset($this->watchCurrent) && $this->watchCurrent->exist()
        && isset($this->watchLasted) && $this->watchCurrent->exist();
  }

  /**
   * Updates current watch files collection
   */
  private function defineWatchCurrentFiles(
  ): void {
    /* Scan all configured directories and update current state */
    $this->watchCurrent = $this->watchFiles();
  }

  /**
   * Saves current files as previous state for comparison
   */
  private function defineWatchLastedFiles(
  ): void {
    $this->watchLasted = $this->watchCurrent;
  }

  /**
   * Clears the terminal screen
   */
  public function clearScreen(
  ): void {
    if (function_exists( "posix_isatty" ) && !posix_isatty(STDOUT)) {
        return; // CI / redirect
    }

    echo "\033[2J\033[H";
  }

  private function doEntryPoint(
  ): string {
    ob_start();
    passthru( "php index.php" );
    return ob_get_clean();
  }
  
  /**
   * Executes the main entry point script
   */
  private function runEntryPoint(
  ): void {
    $startTime = microtime(true);
    $output = $this->doEntryPoint();
    $endTime = microtime(true);

    $executionTime = round(($endTime - $startTime) * 1000, 2);
    echo "\n[Debug] {$executionTime}ms\n\n {$output}";
  }  

  /**
   * Displays formatted log message for file changes
   */
  private function defineLogger(
    WatchFile $watchFile,
    FileStatus $fileStatus
  ): void {
    $this->clearScreen();

    $color = match($fileStatus){
      FileStatus::Added => "\033[32m",    // Green
      FileStatus::Modified => "\033[33m", // Yellow
      FileStatus::Removed => "\033[31m",  // Red
    };

    print Util::sprintFormat(
      "\033[1mWebsyspro DevTools · Watch\033[0m\n\n%s[%s]\033[0m %s @ %s\n", [
        $color, $fileStatus->name, $watchFile->path, $watchFile->timestamp()
      ]
    );

    $this->runEntryPoint();
  }

  /**
   * Checks if files were modified (same count, different timestamps)
   */
  private function isWatchModified(
  ): bool {
    return $this->watchCurrent->count() === $this->watchLasted->count(); 
  }

  /**
   * Finds files that have been modified
   */
  private function watchModifiedDiff(
  ): Collection {
    return $this->watchCurrent->where( fn( WatchFile $current ) => (
      $this->watchLasted->where( fn( WatchFile $lasted ) => (
        $lasted->hash === $current->hash && $lasted->timestamp !== $current->timestamp
      ))->exist()
    ));
  }  

  /**
   * Processes and logs modified files
   */
  private function watchModified(
  ): void {
    $this->watchModifiedDiff()->mapper( function(WatchFile $watchFile ) {
      $this->defineLogger( $watchFile, FileStatus::Modified );
    });
  }

  /**
   * Checks if files were removed (current count less than previous)
   */
  private function isWatchRemoved(
  ): bool {
    return $this->watchCurrent->count() < $this->watchLasted->count(); 
  }  

  /**
   * Finds files that have been removed
   */
  private function watchRemovedDiff(
  ): Collection {
    return $this->watchLasted->where( fn( WatchFile $lasted ) => (
      $this->watchCurrent->where( fn( WatchFile $current ) => (
        $lasted->hash === $current->hash
      ))->exist() === false
    ));
  }
  
  /**
   * Processes and logs removed files
   */
  private function watchRemoved(
  ): void {
    $this->watchRemovedDiff()->mapper( function(WatchFile $watchFile ) {
      $this->defineLogger( $watchFile, FileStatus::Removed );
    });
  }
  
  /**
   * Checks if files were added (current count greater than previous)
   */
  private function isWatchAdded(
  ): bool {
    return $this->watchCurrent->count() > $this->watchLasted->count(); 
  }
  
  /**
   * Finds files that have been added
   */
  private function watchAddedDiff(
  ): Collection {
    return $this->watchCurrent->where( fn( WatchFile $current ) => (
      $this->watchLasted->where( fn( WatchFile $lasted ) => (
        $lasted->hash === $current->hash
      ))->exist() === false
    ));
  }
  
  /**
   * Processes and logs added files
   */
  private function watchAdded(
  ): void {
    $this->watchAddedDiff()->mapper( function(WatchFile $watchFile ) {
      $this->defineLogger( $watchFile, FileStatus::Added );
    });
  }

  /**
   * Displays initial logger message when starting to watch
   */
  private function LoggerInitial(
  ): void {
    $this->clearScreen();
    print Util::sprintFormat(
      "\033[1mWebsyspro DevTools · Watch\033[0m", [
      ]
    );
  } 

  /**
   * Processes a single loop iteration for file change detection
   */
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
    } else {
      $this->LoggerInitial();
    }

    $this->defineWatchLastedFiles();
  }

  /**
   * Main monitoring loop that runs indefinitely
   */
  private function loop(
  ): never {
    while(true){
      clearstatcache(); // Clear file system cache
      sleep( 1 );       // Wait 1 second between checks

      if($this->watchConfig->exist()){
        $this->loopEvent();
      }
    }
  }
}