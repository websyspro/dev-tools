<?php

namespace Websyspro\DevTools\Shareds;

use Socket;

class WebSocketNotifier
{
  private Socket $socket;
  private bool $connected = false;

  public function __construct(
    private string $host = 'localhost',
    private int $port = 3000
  ){}

  public function connect(): bool
  {
    $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    
    if(!@socket_connect($this->socket, $this->host, $this->port)){
      return false;
    }

    $key = base64_encode(random_bytes(16));
    
    $request = "GET / HTTP/1.1\r\n" .
               "Host: {$this->host}:{$this->port}\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Key: {$key}\r\n" .
               "Sec-WebSocket-Protocol: notifier\r\n" .
               "Sec-WebSocket-Version: 13\r\n\r\n";

    socket_write($this->socket, $request, strlen($request));
    socket_read($this->socket, 2048);

    $this->connected = true;
    return true;
  }

  private function encode(string $text): string
  {
    $length = strlen($text);
    $mask = pack('N', rand(1, 0x7FFFFFFF));

    if($length <= 125){
      $header = pack('CC', 0x81, $length | 0x80);
    } elseif($length <= 65535){
      $header = pack('CCn', 0x81, 126 | 0x80, $length);
    } else {
      $header = pack('CCNN', 0x81, 127 | 0x80, 0, $length);
    }

    $masked = '';
    for($i = 0; $i < $length; $i++){
      $masked .= $text[$i] ^ $mask[$i % 4];
    }

    return $header . $mask . $masked;
  }

  public function notify(string $message = 'reload'): bool
  {
    if(!$this->connected && !$this->connect()){
      return false;
    }

    $encoded = $this->encode($message);
    $result = @socket_write($this->socket, $encoded, strlen($encoded));

    return $result !== false;
  }

  public function close(): void
  {
    if($this->connected){
      socket_close($this->socket);
      $this->connected = false;
    }
  }

  public function __destruct()
  {
    $this->close();
  }
}
