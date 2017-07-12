<?php

require_once __DIR__ . '/GameException.php';
require_once __DIR__ . '/GameRuler.php';

$cards = array();
for ($k = 1; $k <= 4; $k++) {
    for ($p = 1; $p <= 13; $p++) {
        array_push($cards, array('k' => $k, 'p' => $p));
    }
}
array_push($cards, array('k' => 5, 'p' => 14));
array_push($cards, array('k' => 6, 'p' => 14));
for($i =0;$i<sizeof($cards);$i++) {
    GameRuler::setCardIndex($cards[$i]);
}
GameRuler::setSamePointNum($cards);

echo json_encode($cards);

?>