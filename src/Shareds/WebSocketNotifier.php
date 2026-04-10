<?php

namespace Websyspro\DevTools\Shareds;

use Socket;

/**
 * WebSocketNotifier - Client for sending hot reload notifications
 * 
 * This class acts as a WebSocket client that connects to the WebSocket server
 * and sends reload notifications. Used by file watchers to trigger browser reloads.
 * 
 * @package Websyspro\DevTools\Shareds
 */
class WebSocketNotifier
{
  /* WebSocket connection socket */
  private Socket $socket;
  
  /* Connection state flag */
  private bool $connected = false;

  /**
   * Initialize notifier with server connection details
   * 
   * @param string $host WebSocket server host (default: localhost)
   * @param int $port WebSocket server port (default: 3000)
   */
  public function __construct(
    private string $host = 'localhost',
    private int $port = 3036
  ){}

  /**
   * Establish WebSocket connection to server
   * 
   * Performs WebSocket handshake with "notifier" protocol.
   * Generates random key and sends upgrade request.
   * 
   * @return bool True if connection successful, false otherwise
   */
  public function connect(): bool
  {
    /* Create TCP socket */
    $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    
    /* Attempt connection to server */
    if(!@socket_connect($this->socket, $this->host, $this->port)){
      return false;
    }

    /* Generate random WebSocket key */
    $key = base64_encode(random_bytes(16));
    
    /* Build WebSocket handshake request with notifier protocol */
    $request = "GET / HTTP/1.1\r\n" .
               "Host: {$this->host}:{$this->port}\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Key: {$key}\r\n" .
               "Sec-WebSocket-Protocol: notifier\r\n" .
               "Sec-WebSocket-Version: 13\r\n\r\n";

    /* Send handshake request */
    socket_write($this->socket, $request, strlen($request));
    
    /* Read handshake response (not validated) */
    socket_read($this->socket, 2048);

    $this->connected = true;
    return true;
  }

  /**
   * Encode text into masked WebSocket frame
   * 
   * Client-to-server frames must be masked per WebSocket protocol.
   * Uses random 4-byte mask and XOR encoding.
   * 
   * @param string $text Message to encode
   * @return string Binary WebSocket frame with mask
   */
  private function encode(string $text): string
  {
    $length = strlen($text);
    
    /* Generate random 4-byte mask */
    $mask = pack('N', rand(1, 0x7FFFFFFF));

    /* Build frame header based on payload length */
    if($length <= 125){
      /* Length fits in 7 bits, set mask bit (0x80) */
      $header = pack('CC', 0x81, $length | 0x80);
    } elseif($length <= 65535){
      /* Use 16-bit extended length */
      $header = pack('CCn', 0x81, 126 | 0x80, $length);
    } else {
      /* Use 64-bit extended length */
      $header = pack('CCNN', 0x81, 127 | 0x80, 0, $length);
    }

    /* Mask payload by XORing each byte with mask */
    $masked = '';
    for($i = 0; $i < $length; $i++){
      $masked .= $text[$i] ^ $mask[$i % 4];
    }

    return $header . $mask . $masked;
  }

  /**
   * Send notification message to WebSocket server
   * 
   * Automatically connects if not already connected.
   * Encodes and sends message to trigger browser reload.
   * 
   * @param string $message Message to send (default: "reload")
   * @return bool True if message sent successfully, false otherwise
   */
  public function notify(string $message = 'reload'): bool
  {
    /* Connect if not already connected */
    if(!$this->connected && !$this->connect()){
      return false;
    }

    /* Encode and send message */
    $encoded = $this->encode($message);
    $result = @socket_write($this->socket, $encoded, strlen($encoded));

    return $result !== false;
  }

  /**
   * Close WebSocket connection
   * 
   * Closes socket and resets connection state.
   */
  public function close(): void
  {
    if($this->connected){
      socket_close($this->socket);
      $this->connected = false;
    }
  }

  /**
   * Destructor - ensures connection is closed on object destruction
   */
  public function __destruct()
  {
    $this->close();
  }
}
