<?php

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Watch
{
  private array $directoriesSnapshotCurr = [];
  private array $directoriesSnapshotLast = [];

  public function __construct(
    private array $directories = [],
    private array $directoriesIgnored = [],
  ){}

  public function listen(
  ): void {
    $this->validateDirectories();
    
    if( \sizeof( $this->directories ) !== 0 ){
      $this->directoriesSnapshotLast = $this->directoriesSnapshot();
      
      while( true ){
        $this->eventLoop();
      }
    }
  }

  private function path(
    string $director
  ): string {
    return implode(
      DIRECTORY_SEPARATOR, [
        getcwd(), $director
      ]
    );
  }

  private function exist(
    string $director
  ): string {
    return is_dir($director);
  }
  
  private function directoryParseTOPath(
  ): array {
    return array_map( 
      fn( $directory ) => $this->path( $directory ),
      $this->directories
    );
  }

  private function directoryExist(
  ): array {
    return array_filter(
      $this->directories,
      fn( $directory ) => $this->exist( $directory )
    );
  }

  private function validateDirectories(
  ): void {
    $this->directories = $this->directoryParseTOPath();
    $this->directories = $this->directoryExist();
  }

  private function directoryFiles(
    string $directory,
    array $files = []
  ): array {
    $directoryInterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator(
        $directory, RecursiveDirectoryIterator::SKIP_DOTS
      )
    ); 
    
    foreach( $directoryInterator as $file ){
      if( $file->isFile() === true && $file->getExtension() === "php" ){
        $files[ md5($file->getPathname())] = [
          "path" => $file->getPathname(),
          "timestamp" => $file->getMTime()
        ];
      }
    }

    return $files;
  }

  private function directoriesSnapshot(
    array $filesInDirectory = []
  ): array {
    foreach( $this->directories as $directory ){
      $filesInDirectory = array_merge(
        $filesInDirectory, $this->directoryFiles( 
          $directory
        )
      );
    }

    return $filesInDirectory;
  }  

  private function directoriesSnapshotDiff(
  ): array {
    return array_filter(
      $this->directoriesSnapshotCurr, fn( array $file, string $key ) => (
        $this->directoriesSnapshotLast[$key]["timestamp"] !== $file["timestamp"]
      ), ARRAY_FILTER_USE_BOTH
    );
  }

  public function clearScreen(
  ): void {
    if (function_exists( "posix_isatty" ) && !posix_isatty(STDOUT)) {
        return; // CI / redirect
    }

    echo "\033[2J\033[H";
  }  

  public function showHeader(
  ): void {
    echo "\033[1mWebsyspro DevTools · Watch\033[0m\n\n";
  }

  private function showChangeLog(
    string $event,
    string $path,
    int $timestamp
  ): void {
    $colors = [
      'added'    => "\033[32m", // green
      'modified' => "\033[33m", // yellow
      'removed'  => "\033[31m", // red
    ];

    $color = $colors[$event] ?? "";
    $reset = "\033[0m";

    printf(
      "%s[%s]%s %s @ %s\n", ...[
        $color,
        strtoupper( $event ),
        $reset,
        $path,
        date( 'Y-m-d H:i:s', $timestamp )
      ]
    );
  }

  public function doCommand(
  ): void {
    echo "\nLog\n\n";
    passthru("php index.php");
  }  

  private function eventLoop(
    array $changesFiles = []
  ):void {
    clearstatcache();
    sleep( 1 );

    $this->directoriesSnapshotCurr = $this->directoriesSnapshot();

    foreach( $this->directoriesSnapshotDiff() as $file ){
      $this->clearScreen();
      $this->showHeader();
      $this->showChangeLog(
        "modified",
        $file["path"], 
        $file["timestamp"]
      );
      $this->doCommand();
    }


    $this->directoriesSnapshotLast = $this->directoriesSnapshotCurr;
  }  
}