<?php

namespace Websyspro\DevTools;

use Websyspro\Commons\Util;

/**
 * EventWatch Class
 * 
 * Handles file system events during development by monitoring file changes
 * and providing real-time feedback with colored output based on file status.
 * This class is responsible for displaying file change notifications and
 * executing the application entry point when files are modified.
 * 
 * Features:
 * - Color-coded file status display (Added: Green, Modified: Yellow, Removed: Red)
 * - Screen clearing for clean output presentation
 * - Automatic execution of entry point after file changes
 * - CI environment detection to prevent screen clearing in non-interactive modes
 * 
 * @package Websyspro\DevTools
 * @author Websyspro Team
 * @version 1.0
 */
class EventWatch
{
  public function statup(
  ) {

  }

  /**
   * Handle file system events and display formatted output
   * 
   * This method processes file change events by clearing the screen,
   * determining the appropriate color based on file status, displaying
   * a formatted header with file information, and executing the entry point.
   * 
   * @param WatchFile $watchFile The file that was changed, containing path and timestamp
   * @param FileStatus $fileStatus The type of change (Added, Modified, or Removed)
   * @return void
   */
  public function handle(
    WatchFile $watchFile,
    FileStatus $fileStatus
  ): void {
    /* Clear screen for fresh output */
    $this->LoggerInitial();

    /* Define colors for different file status types */
    $color = match($fileStatus){
      FileStatus::Added => "\033[32m", // Green
      FileStatus::Removed => "\033[31m", // Red
      FileStatus::Modified => "\033[33m", // Yellow
    };

    /* Display formatted header with file change information */
    print Util::sprintFormat(
      "%s[%s]\033[0m %s @ %s\n", [
        $color, $fileStatus->name, $watchFile->path, $watchFile->timestamp()
      ]
    );

    /* Execute the entry point and show results */
    $this->runEntryPoint();    
  }

  /**
   * Clear the terminal screen for fresh output display
   * 
   * Uses ANSI escape codes to clear the screen and position cursor at top-left.
   * Automatically detects CI environments or redirected output to prevent
   * clearing when not appropriate (e.g., in automated builds or file redirects).
   * 
   * @return void
   */
  private function clearScreen(
  ): void {
    /* Skip clearing in CI environments or when output is redirected */
    if( function_exists( "posix_isatty" ) && !posix_isatty( STDOUT )) {
        return; // CI / redirect
    }

    /* ANSI escape codes to clear screen and move cursor to top-left */
    echo "\033[2J\033[H";
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
      "\033[1mWebsyspro DevTools · Watch/Debug\033[0m\n\n", []
    );
  }   

  /**
   * Execute the application entry point and display debug information
   * 
   * Runs the main application script (index.php) using passthru() to
   * maintain real-time output streaming. Displays debug header before
   * execution to separate file change notifications from application output.
   * 
   * @return void
   */
  private function runEntryPoint(
  ): void {
    /* Execute the main application entry point */
    passthru( "php index.php" );
  } 
}