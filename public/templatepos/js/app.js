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
    //config base url
    app.baseUrlServer = 'http://localhost:8000/app/v1/pos/';

    app.controller('loginCtrl', ['$scope','serviceAjax','localStorageService' , function($scope,serviceAjax,localStorageService) {

        //cek seesion
        (this.cekLocalStorage = function(){
            if(localStorageService.get('user'))  window.location.assign("pos/dashboard");
        })();

        $scope.login  = {};
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
        $scope.loginFn = function(){/*
            if(progressJs) progressJs().start();*/
            if(progressJs)  progressJs().setOptions({overlayMode: true, theme: 'blueOverlay'}).start().autoIncrease(4, 500);
            serviceAjax.posDataToServer('login',$scope.login).then(function(data){
              if(data.code == 0){
                  localStorageService.add('user',data.data);

                  window.location.assign("pos/dashboard");

              }else{
                  $scope.signin.alerts[0].active = true;
              }
                if(progressJs) progressJs().end();
            });
        };
    }]);


    app.controller('dashboardCtrl', ['$scope', 'localStorageService','$timeout','serviceAjax','$modal', function($scope,localStorageService, $timeout, serviceAjax, $modal) {

        $scope.cart      = [];
        $scope.product   = [];

        //dummy for cart list
        for(var i = 0; i <= 7; i ++){
            $scope.cart[i] = {
                'upc'        : '99992827',
                'name'       : 'Tomkins',
                'quantity'     : '',
                'price'      : '',
                'pricetotal' : ''
            }
        };
        //dummy for product
        for(var i = 0; i < 14; i ++){
            $scope.product[i] = {
                'productid'  : '',
                'image'      : '',
                'upc'        : '',
                'price'      : ''
            }
        };
        //show detail when click name
        $scope.showdetailFn = function(){
            var modalInstance;
            modalInstance = $modal.open({
                templateUrl: "productdetail.html",
                controller: 'ModalInstanceCtrl',
                resolve: {
                    dataProduct : function(){
                        return $scope.product;
                    }
                }
            });
            modalInstance.result.then((function(data) {

            }), function() {});
        };




        //function jumlah
        $scope.qaFn = function(id,action){
            if(action == 'p'){
                $scope.cart[id]['quantity'] = $scope.cart[id]['quantity'] ? $scope.cart[id]['quantity'] + 1 : 1;
            }else if(action == 'm'){
                $scope.cart[id]['quantity'] = $scope.cart[id]['quantity'] ? $scope.cart[id]['quantity'] - 1 : 0;
            }else if(action == 'd'){
                $scope.cart.splice(id ,1);
            }else{
                //do something when error
            }
        };
        //logout
        $scope.logoutfn =  function(){
            if(progressJs)  progressJs().setOptions({overlayMode: true, theme: 'blueOverlay'}).start().autoIncrease(4, 500);
            serviceAjax.posDataToServer('logout').then(function(data){
                console.log(data);
                if(data.code == 0){
                    localStorageService.remove('user');
                    window.location.assign("/pos");
                }else{
                    alert('gagal logout');
                }
                if(progressJs) progressJs().end();
            });
            //localStorageService.remove('user');
        };


        $scope.datauser = localStorageService.get('user');

        //timeout scan barcode & date time
        var updatetime = function() {

            //time
            $scope.datetime = moment().format('DD MMMM YYYY hh:mm:ss');
            //scan barcode
            /*
            serviceAjax.posDataToServer('logout').then(function(data){
                console.log(data);
                if(data.code == 0){
                    localStorageService.remove('user');
                    window.location.assign("/pos");
                }else{
                    alert('gagal logout');
                }
            });*/

            $timeout(updatetime, 1000);
        };

        $timeout(updatetime, 1000);

    }]);

    app.controller('ModalInstanceCtrl', ['$scope','$modalInstance','dataProduct', function($scope,$modalInstance,dataProduct) {
        console.log(dataProduct);
         $scope.cancel = function () {
             $modalInstance.dismiss('cancel');
         };
     }]);

    app.directive('numbersOnly', function(){
        return {
            require: 'ngModel',
            link: function(scope, element, attrs, modelCtrl) {
                modelCtrl.$parsers.push(function (inputValue) {
                    // this next if is necessary for when using ng-required on your input.
                    // In such cases, when a letter is typed first, this parser will be called
                    // again, and the 2nd time, the value will be undefined
                    if (inputValue == undefined) return ''
                    var transformedInput = inputValue.replace(/[^0-9+.]/g, '');
                    if (transformedInput!=inputValue) {
                        modelCtrl.$setViewValue(transformedInput);
                        modelCtrl.$render();
                    }

                    return transformedInput;
                });
            }
        };
    });


    return app;
});
