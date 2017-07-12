/*
 * 对 jquery-confirm 的扩展
 * 
 * @requires jquery-confirm
 * 
 * 向 jQuery 注入几个常用的几个弹出消息框方法
 * 
 * @author Bailey
 */

if (typeof Jconfirm === 'undefined') { throw new Error('requires jquery-confirm'); }

if (typeof $.fail != 'undefined' || typeof $.warn != 'undefined' || typeof $.info != 'undefined' || typeof $.question != 'undefined') { 
    throw new Error('自定义方法冲突!'); 
}

(function($) {
    "use strict";

    /*
     * 弹出一般信息提示框
     */
    $.info = function(message, title) {
        $.alert({
            title: title || 'Info',
            icon: 'glyphicon glyphicon-info-sign',
            autoClose: 'confirm|4000',
            content: message
        });
    };

    /*
     * 弹出错误提示消息框
     */
    $.fail = function(message, title) {
        $.alert({
            title: title || 'Error',
            icon: 'glyphicon glyphicon-exclamation-sign',
            content: message
        });
    };

    /*
     * 弹出警告信息提示框
     */
    $.warn = function(message, title) {
        $.alert({
            title: title || 'Warning',
            icon: 'glyphicon glyphicon-warning-sign',
            content: message
        });
    };

    $.question = function(message, confirmCallback, cancelCallback) {
        $.confirm({
            title: 'Confirm',
            icon: 'glyphicon glyphicon-question-sign',
            content: message,
            confirmButton : 'Yes',
            cancelButton : 'No',
            confirm: function() {
                if (confirmCallback) confirmCallback();
            },
            cancel: function() {
                if (cancelCallback) cancelCallback();
            }
        });
    };

    /*
     * 弹出错误提示消息框
     */
    $.debugInfo = function(message) {
        if (!isDebug) return;
        $.alert({
            title: 'Debuger',
            icon: 'glyphicon glyphicon-exclamation-sign',
            columnClass: 'col-md-6',
            backgroundDismiss: true,
            content: message
        });
    };

})(jQuery);