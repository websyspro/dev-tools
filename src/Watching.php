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
   * Clears the terminal screen for better output visibility
   */
  public function clearScreen(
  ): void {
    /* Skip clearing in CI environments or when output is redirected */
    if (function_exists( "posix_isatty" ) && !posix_isatty(STDOUT)) {
        return; // CI / redirect
    }

    /* ANSI escape codes to clear screen and move cursor to top-left */
    echo "\033[2J\033[H";
  }

  /**
   * Executes the main entry point script with performance monitoring
   * Displays execution time before showing the script output
   */
  private function runEntryPoint(
  ): void {
    /* Display debug info with execution time followed by script output */
    echo "\n[Debug]\n\n";

    /* Execute the main application entry point */
    passthru( "php index.php" );
  }  

  /**
   * Displays formatted log message for file changes with colored output
   * @param WatchFile $watchFile The file that was changed
   * @param FileStatus $fileStatus The type of change (Added/Modified/Removed)
   */
  private function defineLogger(
    WatchFile $watchFile,
    FileStatus $fileStatus
  ): void {
    /* Clear screen for fresh output */
    $this->clearScreen();

    /* Define colors for different file status types */
    $color = match($fileStatus){
      FileStatus::Added => "\033[32m",    // Green
      FileStatus::Modified => "\033[33m", // Yellow
      FileStatus::Removed => "\033[31m",  // Red
    };

    /* Display formatted header with file change information */
    print Util::sprintFormat(
      "\033[1mWebsyspro DevTools · Watch\033[0m\n\n%s[%s]\033[0m %s @ %s\n", [
        $color, $fileStatus->name, $watchFile->path, $watchFile->timestamp()
      ]
    );

    /* Execute the entry point and show results */
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
   * Shows the main header without any file change information
   */
  private function LoggerInitial(
  ): void {
    /* Clear screen and display initial watch header */
    $this->clearScreen();
    print Util::sprintFormat(
      "\033[1mWebsyspro DevTools · Watch\033[0m", [
      ]
    );
  } 

  /**
   * Processes a single loop iteration for file change detection
   * Compares current file state with previous state to detect changes
   */
  private function loopEvent(
  ): void {
    /* Update current file state */
    $this->defineWatchCurrentFiles();

    /* Check if this is not the first iteration */
    if( $this->isLoopEvent() ) {
      /* Determine type of change and process accordingly */
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
      /* First iteration - show initial message */
      $this->LoggerInitial();
    }

    /* Save current state as previous for next iteration */
    $this->defineWatchLastedFiles();
  }

  /**
   * Main monitoring loop that runs indefinitely
   * Continuously checks for file changes every second
   */
  private function loop(
  ): never {
    while(true){
      /* Clear file system cache to ensure fresh file stats */
      clearstatcache();
      /* Wait 1 second between checks to avoid excessive CPU usage */
      sleep( 1 );

      /* Only process if watch configuration exists */
      if($this->watchConfig->exist()){
        $this->loopEvent();
      }
    }
  }
}