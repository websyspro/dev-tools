<?php

namespace Websyspro\DevTools\Shareds;

use Socket;
use Websyspro\Commons\Collection;
use Websyspro\Commons\Util;

class WebSocket
{
  private Socket $socket;
  private array $browsers = [];
  private array $notifiers = [];

  public function __construct(
    private int $port = 3000
  ){
    $this->startup();
  }

  private function defineSocket(): void
  {
    $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
    socket_bind($this->socket, "0.0.0.0", $this->port);
    socket_listen($this->socket);
  }

  private function handshake(Socket $client, string $headers, string $type): void
  {
    preg_match("#Sec-WebSocket-Key: (.*)\r\n#", $headers, $match);
    $key = trim($match[1]);
    $acceptKey = base64_encode(sha1("{$key}258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

    $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

    socket_write($client, $response, strlen($response));

    if($type === 'browser'){
      $this->browsers[] = $client;
    } else {
      $this->notifiers[] = $client;
    }
  }

  private function unmask(string $payload): string
  {
    if(strlen($payload) < 2) return "";

    $length = ord($payload[1]) & 127;

    if($length === 126){
      $masks = substr($payload, 4, 4);
      $data = substr($payload, 8);
    } elseif($length === 127){
      $masks = substr($payload, 10, 4);
      $data = substr($payload, 14);
    } else {
      $masks = substr($payload, 2, 4);
      $data = substr($payload, 6);
    }

    $text = '';
    for($i = 0; $i < strlen($data); $i++){
      $text .= $data[$i] ^ $masks[$i % 4];
    }

    return $text;
  }

  private function encode(string $text): string
  {
    $length = strlen($text);

    if($length <= 125){
      return pack("CC", 0x81, $length) . $text;
    } elseif($length <= 65535){
      return pack("CCn", 0x81, 126, $length) . $text;
    } else {
      return pack("CCNN", 0x81, 127, 0, $length) . $text;
    }
  }

  private function broadcast(string $message): void
  {
    $encoded = $this->encode($message);

    foreach($this->browsers as $index => $client){
      $result = @socket_write($client, $encoded, strlen($encoded));
      
      if($result === false){
        socket_close($client);
        unset($this->browsers[$index]);
      }
    }
  }

  private function isHttpRequest(string $data): bool
  {
    return strpos($data, 'POST') === 0 || 
           (strpos($data, 'GET') === 0 && strpos($data, 'Upgrade: websocket') === false);
  }

  private function handleHttpRequest(Socket $socket, string $data): void
  {
    preg_match('/\r\n\r\n(.*)$/s', $data, $match);
    $body = $match[1] ?? '';

    $message = $body ?: 'reload';
    $this->broadcast($message);

    $response = "HTTP/1.1 200 OK\r\n" .
                "Content-Type: application/json\r\n" .
                "Content-Length: 21\r\n" .
                "Connection: close\r\n\r\n" .
                '{"status":"success"}';

    socket_write($socket, $response, strlen($response));
    socket_close($socket);
    
    echo "Hot reload enviado para " . count($this->browsers) . " navegador(es)\n";
  }

  private function getClientType(string $headers): string
  {
    if(preg_match('#Sec-WebSocket-Protocol: (.*)\r\n#', $headers, $match)){
      $protocol = trim($match[1]);
      return $protocol === 'notifier' ? 'notifier' : 'browser';
    }
    return 'browser';
  }

  private function listen(): never
  {
    while(true){
      $read = array_merge([$this->socket], $this->browsers, $this->notifiers);
      
      socket_select($read, $null, $null, 0, 10);

      foreach($read as $socket){
        if($socket === $this->socket){
          $client = socket_accept($this->socket);
          $request = socket_read($client, 2048);

          if($this->isHttpRequest($request)){
            $this->handleHttpRequest($client, $request);
          } else {
            $type = $this->getClientType($request);
            $this->handshake($client, $request, $type);
            echo "Cliente {$type} conectado - Navegadores: " . count($this->browsers) . " | Notificadores: " . count($this->notifiers) . "\n";
          }

          continue;
        }

        $data = @socket_read($socket, 2048);

        if($data === false || $data === ''){
          $this->removeClient($socket);
          continue;
        }

        $message = $this->unmask($data);
        if($message && in_array($socket, $this->notifiers)){
          $this->broadcast($message);
          echo "Notificação recebida via WebSocket: {$message}\n";
        }
      }
    }
  }

  private function removeClient(Socket $socket): void
  {
    $key = array_search($socket, $this->browsers);
    if($key !== false){
      socket_close($socket);
      unset($this->browsers[$key]);
      echo "Navegador desconectado - Total: " . count($this->browsers) . "\n";
      return;
    }

    $key = array_search($socket, $this->notifiers);
    if($key !== false){
      socket_close($socket);
      unset($this->notifiers[$key]);
      echo "Notificador desconectado - Total: " . count($this->notifiers) . "\n";
    }
  }

  private function startup(): void
  {
    $this->defineSocket();
    echo "Servidor Hot Reload rodando na porta {$this->port}\n";
    $this->listen();
  }
}