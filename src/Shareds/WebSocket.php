<?php

namespace Websyspro\DevTools\Shareds;

use Socket;
use Websyspro\Commons\Collection;
use Websyspro\Commons\Util;

class WebSocket
{
  public static Socket $socket;
  public static array $clients = [];

  public function __construct(
    private int $port = 3000
  ){
    $this->startup();
  }

  private function defineSocket(
  ) {
    if( isset( WebSocket::$socket ) === false ){
      WebSocket::$socket = socket_create( AF_INET, SOCK_STREAM,SOL_TCP );

      if( WebSocket::$socket instanceof Socket ){
        socket_set_option( WebSocket::$socket, SOL_SOCKET, SO_REUSEADDR, 1 );
        socket_bind( WebSocket::$socket, "0.0.0.0", $this->port );
        socket_listen( WebSocket::$socket ); 
        
        WebSocket::$clients[] = WebSocket::$socket;
      }
    }
  }

  private function handshake(
    Socket $client, 
    string $headers
  ) {
    preg_match( 
      "#Sec-WebSocket-Key: (.*)\r\n#",
      $headers,
      $match
    );

    $key = trim( $match[ 1 ]);

    $acceptKey = base64_encode(
      sha1(
        "{$key}258EAFA5-E914-47DA-95CA-C5AB0DC85B11", 
        true
      )
    );

    $upgrade = new Collection([
      "HTTP/1.1 101 Switching Protocols",
      "Upgrade: websocket",
      "Connection: Upgrade",
      "Sec-WebSocket-Accept: $acceptKey\r\n"
    ]);

    socket_write(
      $client, 
      $upgrade->joinWithBreak(), 
      Util::sizeText(
        $upgrade->joinWithBreak()
      )
    );
  }

  private function unmask(
    string $payload
  ) {
    $length = \ord( $payload[ 1 ]) & 127;

    if( $length === 126 ){
      $masks = substr($payload, 4, 4);
      $data  = substr($payload, 8);
    } elseif ( $length === 127 ) {
      $masks = substr($payload, 10, 4);
      $data  = substr($payload, 14);
    } else {
      $masks = substr($payload, 2, 4);
      $data  = substr($payload, 6);
    }

    $text = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $text .= $data[$i] ^ $masks[$i % 4];
    }

    return $text;
  }

  private function encode(
    string $text
  ) {
    $b1 = 0x81;
    $length = \strlen($text);

    if( $length <= 125 ){
        return pack("CC", $b1, $length) . $text;
    } elseif ( $length <= 65535 ){
        return pack("CCn", $b1, 126, $length) . $text;
    } else {
        return pack("CCNN", $b1, 127, 0, $length) . $text;
    }
  }
  
  private function clientSend(
    Socket $socket, string $message
  ) {
    $message = $this->encode(
      $message
    );

    return socket_write(
      $socket,
      $message, 
      \strlen(
        $message
      )
    );
  }
  
  public function send(
    string $message
  ) {
    Util::mapper(
      WebSocket::$clients,
      function( Socket $socket ) use( $message )  {
        if( WebSocket::$socket === $socket ){
          return $socket;
        }

        if( $socket instanceof Socket ){
          $result = $this->clientSend(
            $socket, 
            $message
          );
        } else {
          // TODO para implementar Connections para remover
          // WebSocket::$clients->remove( $socket );
        }
        
        if( $result === false ){
          socket_close( $socket );
          // TODO para implementar Connections para remover
          // WebSocket::$clients->remove( $socket );
        }
      }
    );
  }  

  private function defineListen(
  ): never {
    while(true){
      $sockets = WebSocket::$clients;

      socket_select(
        $sockets,
        $null,
        $null, 
        0,
        10
      );

      foreach( $sockets as $socket ){
        if ( WebSocket::$socket === $socket ){
          WebSocket::$clients[] = $client = socket_accept(
            WebSocket::$socket
          );

          $request = socket_read(
            $client,
            2048
          );

          $this->handshake(
            $client,
            $request
          );

          echo "Cliente conectado\n";

          continue;
        }

        $socketData = socket_read(
          $socket,
          2048
        );

        if ($socketData === false) {
          // TODO para implementar Connections para remover
          // WebSocket::$clients->remove( $socket );
          socket_close( $socket );
          echo "Cliente desconectado\n";
          continue;
        }

        $message = $this->unmask( $socketData);
        echo "Recebido: $message\n";
      }      
    }
  }

  private function startup(
  ) {
    $this->defineClients();
    $this->defineSocket();
    $this->defineListen();
  }
}