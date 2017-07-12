/*
 * 整个游戏的Service
 */

// 如果浏览器不支持websocket，会使用这个flash自动模拟websocket协议，此过程对开发者透明
WEB_SOCKET_SWF_LOCATION = "/swf/WebSocketMain.swf";

// 开启flash的websocket debug
WEB_SOCKET_DEBUG = true;

angular.module('app')

    .factory('gameService', function ($http) {

        var playerId;

        var webSocketListener;

        function setWebSocketListener(listener) {
            webSocketListener = listener;
        }

        function webSocketConnect() {
            // 创建websocket
            ws = new WebSocket("ws://" + document.domain + ":17000");

            // 注册websocket事件回调
            ws.onopen = function () {
                console.log("websocket握手成!");
            };
            ws.onmessage = onWebSocketMessage;
            ws.onclose = function () {
                console.log("连接关闭，定时重连");
                webSocketConnect();
            };
            ws.onerror = function () {
                console.log("websocket出现错误");
            };

        }

        // 服务端发来消息时
        function onWebSocketMessage(e) {
            var data = JSON.parse(e.data);
            var type = data.type || '';
            switch (type) {
                case 'ping':
                    break;
                case 'init':
                    playerId = data.client_id;

                    // 利用jquery发起ajax请求，将client_id发给后端进行uid绑定
                    $http.post("gateway/bind.php", {"client_id": data.client_id});

                    if (webSocketListener) {
                        webSocketListener({'type': 'rooms_model', 'data': data.rooms_model});
                    }
                    break;
                case "bind_completed":
                    // 请求服务端推送当前玩家状态数据(若玩家之前已经连接, 因为点击"刷新"页面需要重现之前的游戏状态)
                    $http.post("php/service.php", {type: "reload_player_status"});
                default :
                    console.log("websocket =>" + e.data);
                    if (webSocketListener) {
                        webSocketListener(data);
                    }
                    break;
            }
        }

        // //////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // // 暴露的属性和方法
        // //////////////////////////////////////////////////////////////////////////////////////////////////////////////

        return {
            setWebSocketListener: setWebSocketListener,
            webSocketConnect: webSocketConnect,
            sendMessage: function (data) {
                return $http.post('php/send_message.php', data).then(function (resp) {
                    //debugger;
                });
            },

            getIndentity: function () {
                return $http.post('php/service.php', {"type": "get_identity"}).then(function (resp) {
                    return resp.data;
                });
            },

            signIn: function (params) {
                return $http.post('php/service.php', {type: "sign_in", data: params});
            },

            signOut: function () {
                return $http.post('php/service.php', {type: "sign_out"});
            },

            enterRoom: function (params) {
                return $http.post('php/service.php', {type: "enter_room", data: params});
            },

            handUp: function () {
                return $http.post('php/service.php', {type: "hand_up"});
            },

            showCards: function (cards) {
                return $http.post('php/service.php', {type: "show_cards", data: cards}).then(function(resp){
                    return resp.data;
                });
            },
            pass: function () {
                return $http.post('php/service.php', {type: "pass"});
            }
        };
    });