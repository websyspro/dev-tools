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

/* Initialize route manager to handle incoming request */
$routeEntryPoint = new RouteEntryPoint();

/* Check if request is for a static file (CSS, JS, images, etc.) */
if ($routeEntryPoint->isStatic()) {
  /* Validate if static file has allowed MIME type and can be served */
  if ($routeEntryPoint->isStaticAllowed()) {
    /* Send static file with appropriate headers and terminate */
    $routeEntryPoint->sendStatic();
  }
} else {
  /* Handle dynamic PHP requests with error handling */
  try {
    /* Start output buffering to capture dynamic content */
    ob_start();
  
    /* Dynamic request: serve direct file if exists, otherwise load bootstrap */
    require $routeEntryPoint->isDynamicExist() 
      ? $routeEntryPoint->sendDirectFile()
      : $routeEntryPoint->sendBootstrap();

    /* Inject reload script and send HTML response to client */
    $routeEntryPoint->sendReader(
      ob_get_clean()
    );

  } catch( Throwable $e ) {
    /* Display formatted error page if exception occurs */
		return $routeEntryPoint->sendErrorReader( $e);    
  }
}