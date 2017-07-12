<?php
/**
 *  整个游戏的总控制器
 */

/*
 * 每张牌的数据模型为1个关联数组,
 * 其中:
 * 键"k"对应牌的花色, 方块/梅花/红桃/黑桃/王 分别为 1-5
 * 键"p"对应牌的点数, A-K分别为1-13, 小王为14, 大王为15
 */

session_start();

require_once __DIR__ . '/../gateway/vendor/GatewayClient-3.0.0/Gateway.php';
require_once __DIR__ . '/GameException.php';
require_once __DIR__ . '/GameRuler.php';

use GatewayClient\Gateway;

Gateway::$registerAddress = '127.0.0.1:15000';


/**
 * 玩家信息
 * 封装玩家信息为对象, 便于后续使用
 */
class PlayerInfo
{
    public function __get($name)
    {
        if ($name == "clientId") {
            $client_id = Gateway::getClientIdByUid($this->playerId);
            if (!isset($client_id)) throw new GameException("Can't get client_id by playerId");
            return $client_id[0];
        } else {
            return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
        }
    }

    public function __set($name, $value)
    {
        $_SESSION[$name] = $value;
    }
}

/**
 * 游戏控制器
 */
class GameController
{
    const CACHE_FILE_NAME = "/cache.json";

    public static $playerInfo;     // 当前玩家信息

    /**
     * 初始化, 应仅在启动游戏 Gateway 时调用一次
     */
    public static function initialize()
    {
        $func = new ReflectionClass('GameController');
        $cache_file = dirname($func->getFileName()) . self::CACHE_FILE_NAME;
        $fp = fopen($cache_file, "w+");
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, "");
            flock($fp, LOCK_UN);
        } else {
            throw new GameException("Couldn't lock the file !");
        }
        fclose($fp);
    }

    /**
     * 从缓存文件中读到游戏数据模型
     */
    private static function getGameModel()
    {
        $func = new ReflectionClass('GameController');
        $cache_file = dirname($func->getFileName()) . self::CACHE_FILE_NAME;
        $cache = file_get_contents($cache_file);

        // 若缓存文件中已有数据模型则直接读取, 并返回
        if (strlen($cache) > 0) {
            $game_model = json_decode($cache, true);
            return $game_model;
        }

        /*
         * 缓存文件中无游戏缓存数据则初始化一个数据模型
         */
        $game_model = array();
        // 初始化10个房间
        for ($i = 0; $i < 3; $i++) {
            $room = array(
                'roomStatus' => 'waiting',          // waiting, playing
                'biggestSite' => 0,                 // 当前出牌最大的玩家
                'isRoundBeginning' => true,         // 是否为一轮出牌的开始
                'playerNum' => 0,                    // 玩家数
                'lastCards' => array()              // 上一玩家出的牌
            );

            // 每个房间初始化3个位置
            $sites = array();
            for ($j = 0; $j < 3; $j++) {
                array_push($sites, array(
                    'playerId' => null,                 // 玩家ID
                    'nickname' => null,                 // 玩家昵称
                    'status' => 'nobody',               // 该座位当前的状态: nobody, waiting, ready, active
                    'isPass' => false,                  // 是否PASS
                    'hand' => array(),                  // 手牌数据
                    'out' => array()                    // 本轮出牌数据
                ));
            }
            $room['sites'] = $sites;

            $game_model['ROOM-' . $i] = $room;
        }

        self::saveGameModel($game_model);

        return $game_model;
    }

    /**
     * 保存数据模型到缓存文件
     */
    private static function saveGameModel($game_model)
    {
        $func = new ReflectionClass('GameController');
        $cache_file = dirname($func->getFileName()) . self::CACHE_FILE_NAME;
        $fp = fopen($cache_file, "w+");
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, json_encode($game_model));
            flock($fp, LOCK_UN);
        } else {
            echo "Couldn't lock the file !";
        }
        fclose($fp);
    }

    /**
     * 初始化牌数据, 洗牌, 并将牌分3堆分别给3个玩家
     */
    private static function startGame(&$roomModel)
    {
        // 初始化牌, 将54张牌的数据暂存于数组cards
        $cards = array();
        for ($k = 1; $k <= 4; $k++) {
            for ($p = 1; $p <= 13; $p++) {
                array_push($cards, array('k' => $k, 'p' => $p));
            }
        }
        array_push($cards, array('k' => 5, 'p' => 14)); // 小王
        array_push($cards, array('k' => 6, 'p' => 14)); // 大王

        for ($i = 0; $i < sizeof($cards); $i++) {
            GameRuler::setCardIndex($cards[$i]);
        }

        // 使用PHP数组方法, 打乱数组元素顺序
        shuffle($cards);
        shuffle($cards);
        shuffle($cards);

        // 将牌分3组分别放入3个玩家的手牌数组
        for ($i = 0; $i < 3; $i++) {
            // 将18张牌分给当前玩家
            $roomModel['sites'][$i]['hand'] = array_slice($cards, $i * 18, 18);

            // 对玩家手牌进行排序, card_sort
            usort($roomModel['sites'][$i]['hand'], "GameRuler::cardComparatorDesc");

            // 清空已出牌数组
            array_splice($roomModel['sites'][$i]["out"], 0);
            $roomModel['sites'][$i]["isPass"] = false;
            $roomModel['sites'][$i]["status"] = ($i === 0) ? "active" : "ready";
        }
        $roomModel["biggestSite"] = 0;
        $roomModel["roomStatus"] = "playing";
        $roomModel["isRoundBeginning"] = true;
        array_splice($roomModel['lastCards'], 0);
    }

    /**
     * 推送当前玩家状态数据(用于客户端点击"刷新"时重现原数据状态)
     */
    public static function sendPlayerStatus()
    {
        if (self::$playerInfo->playerId === null || self::$playerInfo->roomId === null || self::$playerInfo->siteIndex === null) return;

        $playerId = self::$playerInfo->playerId;
        $roomId = self::$playerInfo->roomId;
        $siteIndex = self::$playerInfo->siteIndex;

        $gameModel = self::getGameModel();

        // 若房间不存在, 直接返回
        if (!array_key_exists($roomId, $gameModel)) return;

        if ($gameModel[$roomId]['sites'][$siteIndex]['playerId'] !== $playerId) return;

        Gateway::sendToUid($playerId, json_encode(array(
            'type' => 'reload',
            'data' => array(
                'roomId' => $roomId,
                'siteIndex' => $siteIndex,
                'roomModel' => $gameModel[$roomId]
            )
        )));
    }

    /**
     * 向所有客户端广播房间状态
     */
    public static function broadcastRoomStatus()
    {
        Gateway::sendToAll(json_encode(getRoomStatus()));
    }

    /**
     * 返回房间数据模型
     */
    public static function getRoomStatus()
    {
        $roomList = array();
        $gameModel = self::getGameModel();
        foreach ($gameModel as $roomId => $roomInfo) {
            array_push($roomList, array(
                'roomId' => $roomId,
                'roomStatus' => $roomInfo['roomStatus'],
                'playerNum' => $roomInfo['playerNum']
            ));
        }
        return $roomList;
    }

    /**
     * 玩家进入房间
     */
    public static function enterRoom($roomId)
    {
        $gameModel = self::getGameModel();
        $playerId = self::$playerInfo->playerId;
        $siteIndex = self::$playerInfo->siteIndex;

        // 玩家已不在原房间
        if ($gameModel[self::$playerInfo->roomId]["sites"][$siteIndex]["playerId"] !== $playerId) {
            self::$playerInfo->roomId = null;
            self::$playerInfo->siteIndex = null;
        }

        // 若房间不存在, 直接返回
        if (!array_key_exists($roomId, $gameModel)) throw new GameException("Illegal Parameter: roomId");

        $roomModel = &$gameModel[$roomId];

        // 遍历该房间所有位置, 给玩家安排位置
        $arranged = false;
        foreach ($roomModel["sites"] as $i => &$site) {
            if ($site['status'] == 'nobody') {
                // 没有人坐在该位置, 安排给新进入的玩家
                $site['playerId'] = $playerId;
                $site['nickname'] = self::$playerInfo->nickname;
                $site['status'] = 'waiting';

                // 离开原来的房间(如果有的话)
                if (self::$playerInfo->roomId != null && $roomModel["sites"][$siteIndex]["playerId"] === $playerId) {
                    self::leaveRoom($gameModel);
                }

                // 记录当前玩家所在的房间和座位序号
                self::$playerInfo->roomId = $roomId;
                self::$playerInfo->siteIndex = $i;

                // 该房间玩家数加1
                $roomModel["playerNum"]++;

                // 将当前玩家加入到该房间的群组
                Gateway::joinGroup(self::$playerInfo->clientId, $roomId);

                /*
                 * 向当前玩家推送进入房间的通知
                 */
                $message = array(
                    'type' => 'enter_room',
                    'data' => array(
                        'roomId' => $roomId,
                        'siteIndex' => $i
                    )
                );
                Gateway::sendToUid($playerId, json_encode($message));

                // 新新房间所有玩家推送该房间数据模型
                Gateway::sendToGroup($roomId, json_encode(array('type' => 'room_model', 'data' => $roomModel)));

                $arranged = true;
                break;
            }
        }
        unset($site);

        self::saveGameModel($gameModel);

        if (!$arranged) throw new GameException("The room is full.");
    }

    /**
     * 玩家离开房间
     */
    private static function leaveRoom(&$gameModel)
    {
        $roomId = self::$playerInfo->roomId;
        $siteIndex = self::$playerInfo->siteIndex;
        $roomModel = &$gameModel[$roomId];
        $siteModel = &$roomModel["sites"][$siteIndex];

        Gateway::leaveGroup(self::$playerInfo->clientId, $roomId);

        // 通知房间其它玩家, 当前玩家已离开
        Gateway::sendToGroup($roomId, json_encode(
            array('type' => 'leave_room', 'data' => $siteModel["nickname"], "roomId" => $roomId)
        ));

        // 重置房间状态
        $roomModel['roomStatus'] = 'waiting';
        $roomModel['playerNum']--;
        $roomModel['biggestSite'] = 0;
        $siteModel["playerId"] = null;
        $siteModel["nickname"] = null;
        $siteModel["status"] = "nobody";

        // 遍历房间中所有座位, 重置其状态
        foreach ($roomModel['sites'] as &$site) {
            $site['isPass'] = false;
            array_splice($site['hand'], 0);
            array_splice($site['out'], 0);
        }
        unset($site);

        self::$playerInfo->roomId = null;
        self::$playerInfo->siteIndex = null;
    }

    /**
     * 玩家举手
     */
    public static function handUp()
    {
        $playerId = self::$playerInfo->playerId;
        $roomId = self::$playerInfo->roomId;

        if ($playerId === null || $roomId === null) return;

        $gameModel = self::getGameModel();
        if (!array_key_exists($roomId, $gameModel)) return;
        $roomModel = &$gameModel[$roomId];

        foreach ($roomModel['sites'] as &$site) {
            if ($site['playerId'] === $playerId) {
                $site["status"] = "ready";

                // 通知房间其它玩家
                Gateway::sendToGroup($roomId, json_encode(array('type' => 'room_model', 'data' => $roomModel)));
                break;
            }
        }
        unset($site);

        // 统计目前已举手的玩家数, 若3个玩家均已举手则发牌, 开始游戏
        $readyCount = 0;
        foreach ($roomModel['sites'] as $site) {
            if ($site["status"] == "ready") $readyCount++;
        }
        if ($readyCount >= $roomModel["playerNum"]) {
            self::startGame($roomModel);

            // 将牌数据模型广播给房间内所有玩家
            Gateway::sendToGroup($roomId, json_encode(array('type' => 'room_model', 'data' => $roomModel)));
        }

        self::saveGameModel($gameModel);
    }

    /**
     * 出牌
     */
    public static function showCards($cards)
    {
        $gameModel = self::getGameModel();
        $playerId = self::$playerInfo->playerId;
        $roomId = self::$playerInfo->roomId;
        $siteIndex = self::$playerInfo->siteIndex;

        if ($playerId === null || $roomId === null || $siteIndex === null) throw new Exception("Illegal Player Status.");
        if (!array_key_exists($roomId, $gameModel)) throw new Exception("Illegal room_id.");
        if (!isset($cards) || !is_array($cards)) throw new Exception("Illegal parameter: cards => $cards");

        $roomModel = &$gameModel[$roomId];
        $curPlayerModel = &$roomModel["sites"][$siteIndex];

        // 清除当前玩家上轮出牌
        array_splice($curPlayerModel['out'], 0);

        if (sizeof($cards) === 1 && $cards[0]["k"] === "pass") {
            // 当前玩家PASS
            $curPlayerModel["isPass"] = true;
        } else {

            GameRuler::judgePlayable($roomModel['lastCards'], $cards);

            // 在当前玩家手牌中找到对应出的牌, 并将其移到本轮出牌数组
            array_splice($roomModel['lastCards'], 0);
            foreach ($cards as $card) {
                for ($i = 0; $i < count($curPlayerModel['hand']); $i++) {
                    if ($curPlayerModel['hand'][$i]['k'] == $card['k'] && $curPlayerModel['hand'][$i]['p'] == $card['p']) {
                        // 找到了对应的牌, 从手牌数组移除
                        array_splice($curPlayerModel['hand'], $i, 1);
                        // 插入到本轮出牌数组
                        $c = array("k" => $card["k"], "p" => $card["p"]);
                        array_push($curPlayerModel['out'], $c);
                        array_push($roomModel["lastCards"], $c);
                        break;
                    }
                }
            }
            // 记录当前玩家为出牌最大的玩家
            $roomModel["biggestSite"] = $siteIndex;
            $curPlayerModel["isPass"] = false;
        }

        // 切换当前玩家状态
        $curPlayerModel["status"] = "ready";
        $roomModel["isRoundBeginning"] = false;

        Gateway::sendToGroup($roomId, json_encode(array('type' => 'room_model', 'data' => $roomModel)));

        sleep(1);       // 延迟1秒切换后续状态, 以让玩家看清上家出牌

        if (count($curPlayerModel["hand"]) <= 0) {      // 若当前玩家出牌后已无手牌, j, 当前玩家获胜
            // 将房间内玩家状态重置为"等待开局"状态
            foreach ($roomModel["sites"] as &$site) {
                $site["status"] = "waiting";
            }
            unset($site);
            array_splice($roomModel['lastCards'], 0);

            // 广播游戏结束
            Gateway::sendToGroup($roomId, json_encode(array('type' => 'game_over', 'data' => $curPlayerModel)));
        } else {
            // 轮转出牌权到下一个玩家
            $nextIndex = ($siteIndex >= sizeof($roomModel["sites"]) - 1) ? 0 : ($siteIndex + 1);
            $roomModel['sites'][$nextIndex]['isPass'] = false;                         // 清除下一玩家的PASS状态
            array_splice($roomModel['sites'][$nextIndex]['out'], 0);            // 清除下一玩家已出的牌
            $roomModel['sites'][$nextIndex]['status'] = "active";

            if ($roomModel["biggestSite"] === $nextIndex) {     // 本轮出牌结束
                $roomModel["isRoundBeginning"] = true;
                foreach ($roomModel["sites"] as &$site) {
                    array_splice($site['out'], 0);
                    $site["isPass"] = false;
                }
                unset($site);

                array_splice($roomModel['lastCards'], 0);
            }
        }

        // 将牌数据模型广播给房间内所有玩家
        Gateway::sendToGroup($roomId, json_encode(array('type' => 'room_model', 'data' => $roomModel)));

        self::saveGameModel($gameModel);
    }
}

GameController::$playerInfo = new PlayerInfo();