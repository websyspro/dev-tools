<?php

namespace Websyspro\DevTools\Shareds;

use Socket;
use Websyspro\Commons\Collection;
use Websyspro\Commons\Util;
use Websyspro\DevTools\Enums\Protocol;

/**
 * WebSocket Server for Hot Reload functionality
 * 
 * This class implements a WebSocket server that manages two types of clients:
 * - Browsers: receive hot reload notifications
 * - Notifiers: send hot reload triggers to browsers
 * 
 * The server also accepts HTTP POST/GET requests for triggering reloads.
 */
class WebSocket
{
  /* Main server socket */
  private static Socket $socket;
  
  /* Connected browser clients */
  private static array $browsers = [];
  
  /* Connected notifier clients */
  private static array $notifiers = [];

  /**
   * Initialize WebSocket server
   * 
   * @param int $port Server port (default: 3000)
   */
  public function __construct(
    private int $port = 3000
  ){
    $this->startup();
  }

  /**
   * Create and configure the server socket
   * 
   * Sets up TCP socket with SO_REUSEADDR option,
   * binds to all interfaces (0.0.0.0) and starts listening
   */
  private function socket(
  ): void {
    /* Create TCP socket */
    WebSocket::$socket = socket_create(
      AF_INET,
      SOCK_STREAM,
      SOL_TCP
    );

    /* Allow socket reuse to avoid "Address already in use" errors */
    socket_set_option( 
      WebSocket::$socket,
      SOL_SOCKET,
      SO_REUSEADDR,
      1
    );

    /* Bind to all network interfaces on specified port */
    socket_bind( 
      WebSocket::$socket,
      "0.0.0.0", 
      $this->port
    );

    /* Start listening for incoming connections */
    socket_listen( 
      WebSocket::$socket
    );
  }

  /**
   * Extract protocol type from WebSocket handshake headers
   * 
   * @param string $headers Raw HTTP headers from client
   * @return string|null Protocol name (e.g., "notifier") or null if not specified
   */
  private function protocolFromHandshake(
    string $headers
  ): string|null {
    /* Search for Sec-WebSocket-Protocol header */
    preg_match(
      "#Sec-WebSocket-Protocol: (.*)\r\n#",
      $headers,
      $match
    );

    /* Return null if protocol header not found */
    if( Util::sizeArray( $match ) === 0 ){
      return null;
    }
    
    /* Extract and return protocol value */
    [ , $protocol ] = $match;
    return trim( $protocol );
  }

  /**
   * Extract WebSocket key from handshake headers
   * 
   * @param string $headers Raw HTTP headers from client
   * @return string Base64 encoded WebSocket key
   */
  private function keyFromHandshake(
    string $headers
  ): string {
    /* Search for Sec-WebSocket-Key header */
    preg_match(
      "#Sec-WebSocket-Key: (.*)\r\n#",
      $headers,
      $match
    );
    
    /* Extract and return key value */
    [ , $key ] = $match;
    return trim( $key );
  }

  /**
   * Build WebSocket handshake response
   * 
   * @param string $acceptKey Computed accept key for WebSocket protocol
   * @return string Complete HTTP response for handshake
   */
  private function responseFromhandshake(
    string $acceptKey
  ): string {
    /* Build HTTP 101 Switching Protocols response */
    $response = new Collection(
      [
        "HTTP/1.1 101 Switching Protocols",
        "Upgrade: websocket",
        "Connection: Upgrade",
        "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n"
      ]
    );

    return $response->joinWithBreak();
  }

  /**
   * Perform WebSocket handshake with client
   * 
   * Computes accept key using SHA1 hash of client key + magic string,
   * sends handshake response, and registers client by protocol type
   * 
   * @param Socket $client Client socket connection
   * @param string $headers Raw HTTP headers from client
   * @param Protocol $protocol Client type (Browser or Notifier)
   */
  private function handshake(
    Socket $client, 
    string $headers, 
    Protocol $protocol
  ): void {
    /* Compute accept key: base64(sha1(clientKey + magic string)) */
    $response = $this->responseFromhandshake(
      base64_encode(
        sha1( 
          Util::sprintFormat(
            "%s258EAFA5-E914-47DA-95CA-C5AB0DC85B11", [
              $this->keyFromHandshake( $headers )
            ]
          ), 
          true
        )
      )
    );

    /* Send handshake response to client */
    socket_write( 
      $client, 
      $response, 
      Util::sizeText( 
        $response
      )
    );

    /* Register client in appropriate list based on protocol type */
    if( $protocol === Protocol::Browser ){
      WebSocket::$browsers[] = $client;
    } else {
      WebSocket::$notifiers[] = $client;
    }
  }

  /**
   * Unmask WebSocket frame payload
   * 
   * Client-to-server messages are masked using XOR with a 4-byte key.
   * This method extracts the mask and decodes the payload.
   * 
   * @param string $payload Raw WebSocket frame data
   * @return string Unmasked message text
   */
  private function unmask(
    string $payload
  ): string {
    /* Validate minimum payload size */
    if( Util::sizeText( $payload ) < 2){
      return "";
    }

    /* Extract payload length from second byte (bits 0-6) */
    $length = \ord( $payload[ 1 ]) & 127;

    /* Determine mask and data positions based on payload length */
    if( $length === 126 ){
      /* 16-bit extended length */
      $masks = substr($payload, 4, 4);
      $data = substr($payload, 8);
    } elseif( $length === 127 ){
      /* 64-bit extended length */
      $masks = substr($payload, 10, 4);
      $data = substr($payload, 14);
    } else {
      /* Length fits in 7 bits */
      $masks = substr($payload, 2, 4);
      $data = substr($payload, 6);
    }

    /* Unmask data by XORing each byte with corresponding mask byte */
    $text = '';
    for($i = 0; $i < strlen($data); $i++){
      $text .= $data[$i] ^ $masks[$i % 4];
    }

    return $text;
  }

  /**
   * Encode text into WebSocket frame format
   * 
   * Creates a binary WebSocket frame with opcode 0x81 (text frame, FIN bit set).
   * Server-to-client frames are not masked.
   * 
   * @param string $text Message to encode
   * @return string Binary WebSocket frame
   */
  private function encode(
    string $text
  ): string {
    $length = Util::sizeText(
      $text
    );

    /* Use different frame formats based on payload length */
    if($length <= 125){
      /* Length fits in 7 bits */
      return pack("CC", 0x81, $length) . $text;
    } elseif($length <= 65535){
      /* Use 16-bit extended length */
      return pack("CCn", 0x81, 126, $length) . $text;
    } else {
      /* Use 64-bit extended length */
      return pack("CCNN", 0x81, 127, 0, $length) . $text;
    }
  }

  /**
   * Broadcast message to all connected browsers
   * 
   * Sends encoded WebSocket frame to all browser clients.
   * Automatically removes disconnected clients.
   * 
   * @param string $message Message to broadcast
   */
  private function broadcast(
    string $message
  ): void {
    /* Encode message once for all clients */
    $encoded = $this->encode($message);

    /* Send to each connected browser */
    foreach( WebSocket::$browsers as $index => $client ){
      $result = @socket_write(
        $client, 
        $encoded, 
        Util::sizeText(
          $encoded
        )
      );
      
      /* Remove client if write failed (disconnected) */
      if( $result === false ){
        socket_close( $client );
        unset( WebSocket::$browsers[ $index ]);
      }
    }
  }

  /**
   * Check if incoming request is HTTP (not WebSocket)
   * 
   * @param string $data Raw request data
   * @return bool True if HTTP POST or non-WebSocket GET request
   */
  private function isHttpRequest(
    string $data
  ): bool {
    /* POST requests are always HTTP */
    /* GET without "Upgrade: websocket" is HTTP */
    return strpos($data, "POST" ) === 0 
       || (strpos($data, "GET" ) === 0 
        && strpos($data, "Upgrade: websocket" ) === false);
  }

  /**
   * Handle HTTP POST/GET request for triggering hot reload
   * 
   * Extracts message from request body (defaults to "reload"),
   * broadcasts to all browsers, and sends JSON success response.
   * 
   * @param Socket $socket Client socket
   * @param string $data Raw HTTP request data
   */
  private function handleHttpRequest(
    Socket $socket, 
    string $data
  ): void {
    /* Extract request body after headers */
    preg_match( 
      "#\r\n\r\n(.*)$#s",
      $data,
      $match
    );

    $body = $match[1] ?? "";

    /* Use body as message, default to "reload" */
    $message = $body ?: "reload";
    $this->broadcast($message);

    /* Send HTTP 200 response with JSON status */
    $response = "HTTP/1.1 200 OK\r\n" .
                "Content-Type: application/json\r\n" .
                "Content-Length: 21\r\n" .
                "Connection: close\r\n\r\n" .
                '{"status":"success"}';

    socket_write( $socket, $response, strlen($response));
    socket_close( $socket );
    
    echo "Hot reload enviado para " . count($this->browsers) . " navegador(es)\n";
  }

  /**
   * Determine client protocol type from headers
   * 
   * @param string $headers Raw HTTP headers
   * @return Protocol Client type (defaults to Browser if not specified)
   */
  private function getProtocolType(
    string $headers
  ): Protocol {
    /* Extract protocol from Sec-WebSocket-Protocol header */
    $protocol = $this->protocolFromHandshake( $headers );
    if( $protocol ){
      /* "notifier" protocol = Notifier, anything else = Browser */
      return $protocol === "notifier" 
        ? Protocol::Notifier 
        : Protocol::Browser;
    }

    /* Default to Browser if no protocol specified */
    return Protocol::Browser;
  }

  /**
   * Main event loop - listen for connections and messages
   * 
   * Uses socket_select() for non-blocking I/O multiplexing.
   * Handles new connections, HTTP requests, and WebSocket messages.
   * 
   * @return never This method runs indefinitely
   */
  private function listen(
  ): never {
    while(true){
      /* Prepare array of all sockets to monitor */
      $read = array_merge(
        [ WebSocket::$socket ], 
        WebSocket::$browsers,
        WebSocket::$notifiers
      );
      
      /* Wait for activity on any socket (10ms timeout) */
      socket_select(
        $read, 
        $null,
        $null, 
        0, 
        10
      );

      /* Process each socket with activity */
      foreach( $read as $socket ){
        /* New connection on server socket */
        if( $socket === WebSocket::$socket ){
          $client = socket_accept( WebSocket::$socket );
          $request = socket_read( $client, 2048 );

          /* Handle HTTP or WebSocket request */
          if($this->isHttpRequest( $request )){
            $this->handleHttpRequest($client, $request);
          } else {
            /* WebSocket handshake */
            $protocol = $this->getProtocolType($request);
            $this->handshake($client, $request, $protocol);
            echo "Cliente {$protocol->name} conectado - Navegadores: " . count(WebSocket::$browsers) . " | Notificadores: " . count(WebSocket::$notifiers) . "\n";
          }

          continue;
        }

        /* Read data from existing client */
        $data = @socket_read($socket, 2048);

        /* Handle disconnection */
        if($data === false || $data === ''){
          $this->removeClient($socket);
          continue;
        }

        /* Process message from notifier and broadcast to browsers */
        $message = $this->unmask($data);
        if($message && in_array($socket, WebSocket::$notifiers)){
          $this->broadcast($message);
          echo "Notificação recebida via WebSocket: {$message}\n";
        }
      }
    }
  }

  /**
   * Remove disconnected client from appropriate list
   * 
   * Searches for client in browsers and notifiers lists,
   * closes socket and removes from tracking.
   * 
   * @param Socket $socket Client socket to remove
   */
  private function removeClient(
    Socket $socket
  ): void {
    /* Check if client is a browser */
    $key = array_search(
      $socket, 
      WebSocket::$browsers
    );

    if( $key !== false ){
      socket_close( $socket );
      unset(WebSocket::$browsers[ $key ]);
      echo "Navegador desconectado - Total: " . count( WebSocket::$browsers ) . "\n";
      return;
    }

    /* Check if client is a notifier */
    $key = array_search(
      $socket, 
      WebSocket::$notifiers
    );

    if( $key !== false ){
      socket_close( $socket );
      unset( WebSocket::$notifiers[ $key ]);
      echo "Notificador desconectado - Total: " . count(WebSocket::$notifiers) . "\n";
    }
  }

  /**
   * Initialize and start the WebSocket server
   * 
   * Creates socket and enters main event loop.
   * This method never returns.
   */
  private function startup(
  ): void {
    $this->socket();
    $this->listen();
  }
}