angular.module("app")

    .controller("RoomController", function ($rootScope, $scope, toaster, gameService) {
        var myIndex;

        $rootScope.roomId = null;       // 房间ID
        $scope.roomModel = {};          // 当前房间的数据模型
        $scope.selfInfo = {};           // 当前玩家数据模型
        $scope.leftInfo = {};           // 左边玩家数据模型
        $scope.rightInfo = {};          // 右边玩家数据模型

        // 监听玩家进入房间事件
        $scope.$on('enter_room', function (e, data) {
            $rootScope.roomId = data.roomId;
            myIndex = data.siteIndex;
            $rootScope.$apply();        // 触发数据绑定
            toaster.pop('info', 'Welcome join the game, please click the \"READY\" button to start game...');
        });

        // 有玩家离开房间
        $scope.$on('leave_room', function (e, data) {
            $.info(data + ' leave the room, game over!');
        });

        // 用户点击"刷新"按钮刷新页面, 重新呈现游戏状态
        $scope.$on('reload', function (e, data) {
            $scope.roomId = data.roomId;
            myIndex = data.siteIndex;
            refreshRoomView(data.roomModel);
        });

        // 服务端推送的房间数据模型到达
        $scope.$on('room_model', function (e, data) {
            refreshRoomView(data);
        });

        $scope.$on("game_over", function(e, data){
            $.info("GAME OVER ~     The winner is " + data.nickname);
        });

        // 刷新房间内各座位的显示内容
        function refreshRoomView(roomModel) {
            $scope.roomModel = roomModel;

            var leftIndex = ((myIndex - 1) >= 0) ? (myIndex - 1) : 2;
            var rightIndex = ((myIndex + 1) <= 2) ? (myIndex + 1) : 0;

            if (roomModel && angular.isArray(roomModel.sites)) {
                $scope.selfInfo = roomModel.sites[myIndex];
                $scope.leftInfo = roomModel.sites[leftIndex];
                $scope.rightInfo = roomModel.sites[rightIndex];
            } else {
                $scope.selfInfo = {};
                $scope.leftInfo = {};
                $scope.rightInfo = {};
            }
            $scope.$apply();        // 触发数据绑定
        }

        // 点击了某张手牌
        $scope.myCardClicked = function (card) {
            card.selected = !card.selected;
        };

        // 玩家举手
        $scope.onHandUp = function () {
            gameService.handUp();
        };

        // 出牌
        $scope.onShowCards = function () {
            var handCards = $scope.selfInfo.hand;
            if (!angular.isArray(handCards) || handCards.length <= 0) return;

            // 找到当前玩家选中的牌
            var selectedCards = [];
            for (var i = 0; i < handCards.length; i++) {
                if (handCards[i].selected) {
                    selectedCards.push(handCards[i]);
                }
            }

            if (selectedCards.length <= 0) {
                $.info("Please choose the card(s) to show...");
                return;
            }

            gameService.showCards(selectedCards);
        };

        // Pass
        $scope.onPass = function () {
            gameService.showCards([{k:"pass"}]);
        };
    });