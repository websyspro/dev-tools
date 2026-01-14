<?php

require_once dirname( __FILE__ ) . "/vendor/autoload.php";

use Websyspro\DevTools\Shareds\WebSocketNotifier;

$wsn = new WebSocketNotifier();
$wsn->notify( "reload" );
