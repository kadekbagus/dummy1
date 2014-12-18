/**
 * Created with JetBrains PhpStorm.
 * User: julisman
 * Date: 1/8/14
 * Time: 3:18 PM
 * http://blog.julisman.com
 */
'use strict';

define([

], function () {

var app = angular.module('app', ['ui.bootstrap','LocalStorageModule'], function($interpolateProvider,$httpProvider) {
    $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded';
    $interpolateProvider.startSymbol('<%');
    $interpolateProvider.endSymbol('%>');

});
    app.controller('loginCtrl', ['$scope','serviceAjax','$modal','localStorageService' , function($scope,serviceAjax, $modal, localStorageService) {

        (this.cekLocalStorage = function(){
            if(localStorageService.get('user'))  window.location.assign("pos/dashboard");
        })();
        $scope.login = {};
        $scope.signin = {};
        $scope.signin.alerts = [
            {
                text: "Your username / password is incorrect",
                active: false
            }, {
                text: "Your username is not recognized",
                active: false
            }
        ];
        $scope.signin.alertDismisser = function(index) {
            $scope.signin.alerts[index].active = false;
        };
        $scope.loginFn = function(){
            serviceAjax.posDataToServer('login',$scope.login).then(function(data){
              if(data.code == 0){
                  localStorageService.add('user',data.data);
                  window.location.assign("pos/dashboard");

              }else{
                  $scope.signin.alerts[0].active = true;
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
    app.controller('dashboardCtrl', ['$scope', 'localStorageService','$timeout','serviceAjax', function($scope,localStorageService, $timeout, serviceAjax) {
        $scope.tes = 'ss';

        $scope.logoutfn =  function(){

            serviceAjax.getDataFromServer('logout').then(function(data){
                console.log(data);
                if(data.code == 0){
                    localStorageService.remove('user');
                    window.location.assign("pos");
                }else{
                    $scope.signin.alerts[0].active = true;
                }
            });
            //localStorageService.remove('user');
        };


        $scope.datauser = localStorageService.get('user');

        //time
        var updatetime = function() {
            $scope.datetime = moment().format('DD MMMM YYYY HH:MM:SS');
            $timeout(updatetime, 1000);
        };

        $timeout(updatetime, 1000);

    }]);
    app.baseUrlServer = 'http://localhost:8000/app/v1/pos/';

    return app;
});
