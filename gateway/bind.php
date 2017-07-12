<?php
/**
 * 绑定client_id与玩家ID
 */
session_start();
 
require_once __DIR__ . '/vendor/GatewayClient-3.0.0/Gateway.php';

use GatewayClient\Gateway;
Gateway::$registerAddress = '127.0.0.1:15000';

$client_id 	= $_POST['client_id'];

// 若session中无玩家ID信息(首次连接), 则将client_id作为该玩家的player_id
// 否则将玩家ID与client_id绑定
if (!isset($_SESSION['playerId'])) {
	$playerId = $client_id;
	$_SESSION['playerId'] = $playerId;
} else {
    $playerId = $_SESSION['playerId'];
}

// client_id与player_id绑定
Gateway::bindUid($client_id, $playerId);

// 若玩家处于某个房间则将其加入该房间群组
if (isset($_SESSION['roomId'])) {
	Gateway::joinGroup($client_id, $_SESSION['roomId']);
}

Gateway::sendToClient($client_id, json_encode(array("type"=>"bind_completed")));
?>