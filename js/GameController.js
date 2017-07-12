angular.module("app")

    .controller("GameController", function ($rootScope, $scope, $uibModal, toaster, gameService) {
        $scope.roomList = {};

        // 监听来自websocket的数据
        gameService.setWebSocketListener(function (e) {
            var type = e.type || '';
            var data = e.data;

            switch (type) {
                case 'rooms_model':
                    $scope.roomList = data;
                    break;
                default :
                    $scope.$broadcast(type, data);		// 广播websocket推送到前端的信息
                    break;
            }
        });

        // 建立websocket连接
        gameService.webSocketConnect();

        // 获得玩家昵称
        gameService.getIndentity().then(function (resp) {
            if (resp.data && resp.data.nickname) {
                $rootScope.nickname = resp.data.nickname;
            } else {
                $rootScope.nickname = null;
            }
        });

        // 登录
        $scope.onSignInClicked = function () {
            showLoginModal();
        }

        // 退出
        $scope.onSignOutClicked = function () {
            $.question('Are you sure you want to quit?', function () {
                gameService.signOut().then(function (resp) {
                    $rootScope.nickname = null;
                    toaster.pop('info', 'Bye ~ ~');
                });
            });
        }

        // 显示登录窗口
        function showLoginModal() {
            $uibModal.open({
                templateUrl: 'login_form.html',
                controller: 'LoginFormController',
            }).result.then(function (loginInfo) {
                gameService.signIn(loginInfo).then(function (resp) {
                    $rootScope.nickname = loginInfo.nickname;
                    toaster.pop('info', 'Welcome, ' + loginInfo.nickname);
                });
            });
        };

        // 玩家点选了某个房间
        $scope.onRoomSelect = function (room) {
            // 若未登录, 则显示登录窗口, 要求其登录
            if (!$rootScope.nickname) {
                showLoginModal();
            } else if ($rootScope.roomId != room.roomId){
                // 已登录, 进入房间
                if ($rootScope.roomId != room.roomId) {
                    gameService.enterRoom({roomId: room.roomId});
                }
            }
        }

        $scope.test = function () {
            gameService.sendMessage();
        }
    })


    /**
     * 登录模态窗口控制器
     */
    .controller("LoginFormController", function ($scope, $uibModalInstance, toaster) {

        $scope.nickname = null;
        $scope.password = null;

        $scope.cancel = function () {
            $uibModalInstance.dismiss('cancel');
        };

        $scope.signIn = function () {
            if (!$scope.nickname) {
                toaster.pop('info', 'Ops~ You NEED a nickname!');
                return;
            }

            $uibModalInstance.close({
                nickname: $scope.nickname,
                password: $scope.password
            });
        };
    });



