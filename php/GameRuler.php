<?php
/**
 *  游戏规则定义
 */
require_once __DIR__ . '\GameException.php';

class GameRuler
{
    // 按照当前游戏规则, 牌的点序(只考虑点数, 不考虑花色时的顺序)
    // NOTE: 大小王算不同花色, 但是同一点
    // 如:斗地主中, 最小是3点, 牌点序是 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 1, 2, 14, 其中14为大小王的点数
    private static $pointOrder = array(3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 1, 2, 14);

    /**
     * 获得指定的单张牌在当前游戏规则下只考虑点数时的顺序号
     */
    public static function getPointIndex($card)
    {
        try {
            $pIndex = array_search($card["p"], self::$pointOrder);
            if ($pIndex === false) throw new Exception("Illegal Card Point!");
            return $pIndex;
        } catch (Exception $e) {
            throw new Exception("Illegal Card Point!");
        }
    }

    /**
     * 获得指定的单张牌在当前游戏规则下的顺序号(从小到大, 同时考虑花色与点数)
     */
    public static function getCardIndex($card)
    {
        $pIndex = self::getPointIndex($card);

        return ($card["k"] >= 5) ? ($pIndex - 13 + (4 * 13) + ($card["k"] - 5)) : (($card["k"] - 1) + $pIndex * 4);
    }

    /**
     * 为牌数据添加索引顺序信息
     */
    public static function setCardIndex(&$card)
    {
        $card["pi"] = self::getPointIndex($card);
        $card["i"] = self::getCardIndex($card);
    }

    /**
     * *** 根据游戏规则, 对给定的2张牌比大小, $a小则返回-1, 相同返回0, $a大返回1 ***
     * 当前游戏规则下, 牌序信息存储于外部文件 card_order.json
     */
    public static function cardComparator($card0, $card1)
    {
        return $card0["i"] - $card1["i"];
    }

    /**
     * *** 根据游戏规则, 对给定的2张牌比大小, $a小则返回1, 相同返回0, $a大返回-1 ***
     * 当前游戏规则下, 牌序信息存储于外部文件 card_order.json
     * NOTE: 此方法用于降序排列
     */
    public static function cardComparatorDesc($card0, $card1)
    {
        return (-self::cardComparator($card0, $card1));
    }

    /**
     * 为牌数据模型附加在本组牌中同点数牌的数量
     */
    public static function setSamePointNum(&$cards)
    {
        $points = array();
        foreach ($cards as $c) {
            if (isset($points[$c["pi"]])) {
                $points[$c["pi"]]++;
            } else {
                $points[$c["pi"]] = 1;
            }
        }
        for ($i = 0; $i < sizeof($cards); $i++) {
            $cards[$i]["spn"] = $points[$cards[$i]["pi"]];
        }
    }

    /**
     * 优先考虑点数相同牌的数量, 再考虑点数的排序比较器
     */
    public static function samePointFirstComparator($card0, $card1)
    {
        if (!(isset($card0["spn"]) && isset($card1["spn"]))) {
            // 若还没计算本组牌中同点数牌的数量则抛出异常
            throw new GameException("You NEED call setSamePointNum(cards) first!");
        }

        if ($card0["spn"] === $card1["spn"]) {
            return $card0["i"] - $card1["i"];
        } else {
            return $card1["spn"] - $card0["spn"];
        }
    }

    /**
     * 判断牌型
     * @param $cards 待判断的牌
     * @return  牌型的描述
     * @throws 不符合任意出牌规则则抛出异常
     */
    public static function getCardsType(&$cards)
    {
        if (!isset($cards) || !is_array($cards) || sizeof($cards) < 1) {
            throw new GameException("Illegal Cards !");
        }

        // 先对牌进行排序, 优先排同点数多的牌, 再考虑牌的大小
        GameRuler::setSamePointNum($cards);
        usort($cards, "GameRuler::samePointFirstComparator");

        $size = sizeof($cards);

        // 判断是否单牌
        if ($size == 1) return "DAN_PAI";

        // 判断是否王炸
        // 此判定放在对子判定前, 避免误判为普通对子
        if ($size == 2 && $cards[0]["pi"] == 14 && $cards[1]["pi"] == 14) return "WANG_ZHA";

        // 判断是否对子, 如:33, 44, KK
        if ($size == 2 && $cards[0]["spn"] == 2) return "DUI_ZI";

        // 判断是否3不带, 如: 333, 444, KKK
        if ($size == 3 && $cards[0]["spn"] == 3) return "SAN_BU_DAI";

        // 判断是否炸弹, 如: 4444
        if ($size == 4 && $cards[0]["spn"] == 4) return "ZHA_DAN";

        // 判断是否3带1, 如:3334
        if ($size == 4 && $cards[0]["spn"] == 3 && $cards[3]["spn"] == 1) return "SAN_DAI_YI";

        // 判断是否3带2, 如:33344
        if ($size == 5 && $cards[0]["spn"] == 3 && $cards[3]["spn"] == 2) return "SAN_DAI_DUI";

        // 判断是否连对, 如: 334455, 3344556677, 连对中不能出现2和王
        if ($size >= 6 && ($size % 2 == 0)) {    // 6张以上双数牌
            $matched = true;    // 先假设匹配, 若遍历过程中发现不匹配则设为false, 并break
            for ($i = 0; $i + 2 < $size; $i = $i + 2) {
                if ($cards[$i]["spn"] != 2 || $cards[$i + 2]["spn"] != 2 || ($cards[$i]["pi"] - $cards[$i + 2]["pi"] != -1) || ($cards[$i]["pi"] > 11 || $cards[$i + 2]["pi"] > 11)) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) return "LIAN_DUI";
        }

        // 判断是否飞机, 如: 333444, 333444555, 3张同点牌中不能含2
        if ($size >= 6 && $size % 3 == 0) {
            $matched = true;    // 先假设匹配, 若遍历过程中发现不匹配则设为false, 并break
            for ($i = 0; $i + 3 < $size; $i = $i + 3) {
                if ($cards[$i]["spn"] != 3 || $cards[$i + 3]["spn"] != 3 || ($cards[$i]["pi"] - $cards[$i + 3]["pi"] != -1) || ($cards[$i]["pi"] > 11 || $cards[$i + 3]["pi"] > 11)) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) return "FEI_JI";
        }

        // 判断是否飞机带对, 如: 3334445566, 333444555778899, 3张同点牌中不能含2
        if ($size >= 10 && $size % 5 == 0) {
            $n = $size / 5;      // 3带对的组数
            $matched = true;    // 先假设匹配, 若遍历过程中发现不匹配则设为false, 并break

            // 判断是否有$n组3张同点牌, 且3张同点牌不含2
            for ($g = 0; $g + 1 < $n; $g++) {
                if ($cards[$g * 3]["spn"] != 3 || $cards[($g + 1) * 3]["spn"] != 3
                    || ($cards[$g * 3]["pi"] - $cards[($g + 1) * 3]["pi"] != -1)
                    || $cards[$g * 3]["pi"] > 11 || $cards[($g + 1) * 3]["pi"] > 11) {
                    $matched = false;
                    break;
                }
            }

            // 判断带的牌是否均为对
            for ($i = $n * 3; $i < $size; $i = $i + 2) {
                if ($cards[$i]["spn"] != 2) {
                    $matched = false;
                    break;
                }
            }

            if ($matched) return "FEI_JI_DAI_DUI";
        }

        // 判断是否飞机带单, 如: 33344456, 333444555789, 3张同点牌中不能含2
        if ($size >= 8 && $size % 8 == 0) {
            $n = $size / 4;      // 3带1的组数
            $matched = true;    // 先假设匹配, 若遍历过程中发现不匹配则设为false, 并break
            for ($g = 0; $g + 1 < $n; $g++) {
                if ($cards[$g * 3]["spn"] != 3 || $cards[($g + 1) * 3]["spn"] != 3
                    || ($cards[$g * 3]["pi"] - $cards[($g + 1) * 3]["pi"] != -1)
                    || $cards[$g * 3]["pi"] > 11 || $cards[($g + 1) * 3]["pi"] > 11) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) return "FEI_JI_DAI_DAN";
        }

        // 判断是否4带2张单, 如: 333345
        if ($size == 6 && $cards[0]["spn"] == 4) return "SI_DAI_ER";

        // 判断是否4带对, 如: 33334455
        if ($size == 8 && $cards[0]["spn"] == 4 && $cards[4]["spn"] == 2 && $cards[6]["spn"] == 2) return "SI_DAI_DUI";

        // 判断是否顺子(5张以上连牌), 如: 34567, 456789
        // 最多只可能是12张连牌: 3-A
        if ($size >= 5 && $size <= 12) {
            $matched = true;
            for ($i = 0; $i < $size - 1; $i++) {
                if (($cards[$i]["pi"] - $cards[$i + 1]["pi"]) != -1 || $cards[$i]["pi"] > 11 || $cards[$i + 1]["pi"] > 11) // 前一张牌的点数应比后一张牌小1, 且不含2和王
                {
                    $matched = false;
                    break;
                }
            }
            if ($matched) return "SHUN_ZI";
        }

        // 不符合上述任意规则
        throw new GameException("Illegal Card Type!");
    }

    /**
     * 判定当前玩家的牌是否可出
     * @param $lastCards 最后一个出牌玩家出的牌
     * @param $cards 当前玩家出的牌
     * 若不符合出牌规则则抛异常, 否则正常return
     */
    public static function judgePlayable(&$lastCards, &$cards)
    {
        if (isset($lastCards) && !is_array($lastCards)) {
            throw new GameException("Illegal last Cards !");
        }

        if (!isset($cards) || !is_array($cards) || sizeof($cards) < 1) {
            throw new GameException("Illegal Cards !");
        }

        for ($i = 0; $i < sizeof($cards); $i++) {
            GameRuler::setCardIndex($cards[$i]);
        }

        for ($i = 0; $i < sizeof($lastCards); $i++) {
            GameRuler::setCardIndex($lastCards[$i]);
        }

        // 当前玩家出的牌型
        $curCardType = self::getCardsType($cards);      // 若牌型不符合一般规则, 则表调用该函数时就已经抛出异常

        // 前面未出牌, 只要符合任意牌型要求均可出牌
        if (!isset($lastCards) || sizeof($lastCards) <= 0) return;

        // 最后一个出牌玩家出的牌型
        $lastCardType = self::getCardsType($lastCards);
        // 当前玩家出牌张数
        $curCardsCount = sizeof($cards);
        // 最后一个出牌玩家出的张数
        $lastCardsCount = sizeof($lastCards);

        // 王炸必定可出
        if ($curCardType == "WANG_ZHA") return;

        if ($curCardType == "ZHA_DAN") {    // 普通炸弹
            if ($lastCardType != "ZHA_DAN") {
                return;     // 前面出的不是炸弹, 可出
            } else {
                // 前面出的是炸弹, 本家出更大的炸弹, OK
                if ($cards[0]["pi"] > $lastCards[0]["pi"]) return;
            }
        } else {    // 一般牌(不是炸弹)
            if ($curCardsCount == $lastCardsCount && $curCardType == $lastCardType) {       // 出牌数相同, 出的牌型相同
                if ($cards[0]["pi"] > $lastCards[0]["pi"]) return;      // 当前玩家出的最小一张比大小的牌点序较大, 可出

                if ($curCardsCount == 1 && $cards[0]["i"] == 53) return;       // 出单牌时, 当前玩家出大王, 必定可出
            }
        }

        // 不满足上述规则要求, 不可出
        throw new GameException("Illegal Card Type!");
    }
}
