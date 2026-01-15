<?php

require __DIR__ . "/../../../../autoload.php";

use Websyspro\DevTools\Shareds\WebSocket;

/**
 * WebSocket.php - WebSocket Server Initializer
 * 
 * This script initializes and starts the WebSocket server for development tools.
 * The WebSocket server enables real-time communication between the development
 * server and the browser for features like live-reload.
 * 
 * @package Starteds
 */


// Create and start the WebSocket server instance
$webSocket = new WebSocket();