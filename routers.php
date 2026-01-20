<?php

/**
 * Application Entry Router
 * 
 * Manages HTTP request routing, differentiating between
 * static files and dynamic requests.
 * 
 * @package Websyspro\DevTools
 */

declare( strict_types=1 );

use Websyspro\DevTools\RouteEntryPoint;

// Initialize route manager
$routeEntryPoint = new RouteEntryPoint();

// Check if request is for a static file
if ($routeEntryPoint->isStatic()) {
  // Validate if static file is allowed to be served
  if ($routeEntryPoint->isStaticAllowed()) {
    $routeEntryPoint->sendStatic();
  }
} else {

  ob_start();
  
  // Dynamic request: serve direct file or load bootstrap
  require $routeEntryPoint->isDynamicExist() 
    ? $routeEntryPoint->sendDirectFile()
    : $routeEntryPoint->sendBootstrap();

  $routeEntryPointHtml = ob_get_clean();
  
  return $routeEntryPointHtml;
}