<?php
/**
 * websocket事件监听器
 */

require_once __DIR__ . '/../php/GameController.php';
 
use \GatewayWorker\Lib\Gateway;

class Events
{
   	// 当有客户端连接时，将client_id返回，让mvc框架判断当前uid并执行绑定
    public static function onConnect($client_id){
		// debug
		echo "[OnConnect] client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} client_id:$client_id\n";
		
        Gateway::sendToClient($client_id, json_encode(array(
            'type'      => 'init',
            'client_id' => $client_id,
			'rooms_model'  => GameController::getRoomStatus()		// 初始时将房间列表信息顺带带回
		)));
    }

    public static function onMessage($client_id, $message){
		
    }
	
	public static function onClose($client_id){
       // debug
       echo "[OnClose] client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} client_id:$client_id\n";
   }
}
