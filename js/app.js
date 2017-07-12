angular.module("app", [
    'ngAnimate',
    'ui.bootstrap',
    'toaster'
])

    .config(['$httpProvider', function ($httpProvider) {
        $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded;charset=utf-8';
        var param = function (obj) {
            var query = '', name, value, fullSubName, subName, subValue, innerObj, i;
            for (name in obj) {
                value = obj[name];
                if (value instanceof Array) {
                    for (i = 0; i < value.length; ++i) {
                        subValue = value[i];
                        fullSubName = name + '[' + i + ']';
                        innerObj = {};
                        innerObj[fullSubName] = subValue;
                        query += param(innerObj) + '&';
                    }
                } else if (value instanceof Object) {
                    for (subName in value) {
                        subValue = value[subName];
                        fullSubName = name + '[' + subName + ']';
                        innerObj = {};
                        innerObj[fullSubName] = subValue;
                        query += param(innerObj) + '&';
                    }
                } else if (value !== undefined && value !== null) {
                    query += encodeURIComponent(name) + '=' + encodeURIComponent(value) + '&';
                }
            }
            return query.length ? query.substr(0, query.length - 1) : query;
        };

        // Override $http service's default transformRequest
        $httpProvider.defaults.transformRequest = [function (data) {
            return angular.isObject(data) && String(data) !== '[object File]' ? param(data) : data;
        }];

        /**
         * 注入http请求拦截器, 处理一般错误信息
         */
        $httpProvider.interceptors.push(function ($q) {
            return {
                'response': function (resp) {
                    if (resp.data != null && resp.data.code != null) {
                        if (resp.data.code == 0) {
                            return resp;
                        } else {
                            if (resp.data.message) {
                                $.fail(resp.data.message);
                                resp.errorHandled = true;
                            }
                            return $q.reject(resp);
                        }
                    }
                    return resp;
                },
                'responseError': function (rejection) {
                    switch (rejection.status) {
                        case 404:
                            $.fail('请求的资源不存在');
                            break;
                        case 500:
                            $.fail('服务器内部故障');
                            break;
                    }
                    return $q.reject(rejection);
                }
            };
        });
    }]);

var isDebug = true;