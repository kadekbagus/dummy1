/**
 * @author agung@dominopos.com
 */

"use strict";
define([

], function () {

var app = angular.module('app', ['ui.bootstrap'], function($interpolateProvider,$httpProvider) {
    $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded';
    $interpolateProvider.startSymbol('<%');
    $interpolateProvider.endSymbol('%>');

});
    app.controller('loginCtrl', ['$scope','serviceAjax','$modal', function($scope,serviceAjax, $modal) {
        $scope.login = {};

        $scope.loginFn = function(){
            serviceAjax.posDataToServer('login',$scope.login).then(function(data){
              if(data.code == 0){
                  window.location.assign("pos/dashboard")
              }else{
                  alert(data.message);
              }
            });
        };


     /*   $scope.open = function (size) {
            var modalInstance;
            modalInstance = $modal.open({
                templateUrl: "changePassword.html",
                controller: 'ModalInstanceCtrl',
                resolve: {
                    changePssword : function(){
                        return $scope.merchant;
                    }
                }
            });
            modalInstance.result.then((function(data) {

            }), function() {});
        };*/



    }]);

    /*app.controller('ModalInstanceCtrl', ['$scope','$modalInstance', function($scope,$modalInstance) {
        $scope.cancel = function () {
            $modalInstance.dismiss('cancel');
        };
    }]);*/
    app.baseUrlServer = 'http://localhost:8000/app/v1/pos/';

    return app;
});
