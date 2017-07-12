<?php
/**
 * 面向客户端提供的服务接口
 */

require_once __DIR__ . '\GameController.php';
require_once __DIR__ . '\GameException.php';

// 准备要返回客户端的数据
$result_code = 0;
$result_data = null;
$result_message = null;

try {
    // 请求类型
    $type = $_POST["type"];

    // 从客户端传来的信息, JSON格式
    if (isset($_POST['data'])) {
        $data = $_POST['data'];
    }

    switch ($type) {
        case "get_identity":
            // 获得玩家身份信息
            // 可能玩家已经登录过, 由于其刷新浏览器, 导致前端玩家信息丢失
            // 因此, 在刷新页面时须向服务器端请求身份信息
            $result_data = array(
                "nickname" => (isset($_SESSION["nickname"])) ? $_SESSION["nickname"] : null
            );
            break;
        case "reload_player_status":
            // 重新加载玩家状态数据
            // 刷新浏览器将导致前端数据丢失, 所以需要在加载页面时加载玩家原来的状态数据
            // 此数据在GameController中通过通送方式送达客户端
            GameController::sendPlayerStatus();
            break;
        case "sign_in" :         // 登录
            $_SESSION['nickname'] = $data ["nickname"];
            break;
        case "sign_out":        // 退出
            session_destroy();
            break;
        case "enter_room":      // 进入房间
            GameController::enterRoom($data["roomId"]);
            break;
        case "hand_up":         // 玩家举手
            GameController::handUp();
            break;
        case "show_cards":      // 出牌
            $result_message = GameController::showCards($data);
            break;
    }
} catch (GameException $e) {
    $result_code = -1;
    $result_message = $e->getMessage();
} catch (Exception $e) {
    $result_code = -999;
    $result_message = "Unhandled exception occurred on the server side : \n" . $e;
}

echo json_encode(array("code" => $result_code, "message" => $result_message, "data" => $result_data));

?>