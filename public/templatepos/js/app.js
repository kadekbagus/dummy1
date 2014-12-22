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

var app = angular.module('app', ['ui.bootstrap','ngAnimate','LocalStorageModule'], function($interpolateProvider,$httpProvider) {
    $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded';
    $interpolateProvider.startSymbol('<%');
    $interpolateProvider.endSymbol('%>');

});
    //config base url
   // app.baseUrlServer = 'http://localhost:8000/app/v1/pos/';
    app.baseUrlServer = 'http://192.168.0.109:8000/app/v1/';

    app.controller('loginCtrl', ['$scope','serviceAjax','localStorageService' , function($scope,serviceAjax,localStorageService) {

        //cek seesion
        (this.cekLocalStorage = function(){
            if(localStorageService.get('user'))  window.location.assign("pos/dashboard");
        })();
        //init object
        $scope.login  = {};
        $scope.signin = {};

        $scope.signin.alerts = [{ text: "Maaf, nomor ID atau password yang Anda masukkan tidak cocok",active: false} ];
        $scope.signin.alertDismisser = function(index) {
            $scope.signin.alerts[index].active = false;
        };

        $scope.loginFn = function(){
            $scope.showloader = true;
            if(progressJs) progressJs().start().autoIncrease(4, 500);
            serviceAjax.posDataToServer('pos/login',$scope.login).then(function(data){
              if(data.code == 0){
                  $scope.shownall = false;
                  localStorageService.add('user',data.data);
                  window.location.assign("pos/dashboard");
              }else{
                  $scope.signin.alerts[0].active = true;
              }
                $scope.showloader = false;
                if(progressJs) progressJs().end();
            });
        };
    }]);

    app.controller('dashboardCtrl', ['$scope', 'localStorageService','$timeout','serviceAjax','$modal', function($scope,localStorageService, $timeout, serviceAjax, $modal) {
        //init object
        $scope.cart      = [];
        $scope.product   = [];
        $scope.datauser  = localStorageService.get('user');

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

        //show modal product detail
        $scope.showdetailFn = function(id){
            var modalInstance;
            modalInstance = $modal.open({
                templateUrl: "productdetail.html",
                controller: 'ProductDetailCtrl',
                resolve: {
                    dataProduct : function(){
                        return $scope.product[id];
                    }
                }
            });
            modalInstance.result.then((function(data) {

            }), function() {});
        };

        //get unix guestid
        (this.getguest = function(){
                $scope.guests = moment().unix();
        })();

        //function -+ wish list
        $scope.qaFn = function(id,action){
            if(action == 'p'){
                $scope.cart[id]['quantity'] = $scope.cart[id]['quantity'] ? $scope.cart[id]['quantity'] + 1 : 1;
            }else if(action == 'm'){
                $scope.cart[id]['quantity'] = $scope.cart[id]['quantity'] ? ($scope.cart[id]['quantity'] == 1 ? $scope.cart.splice(id ,1) : $scope.cart[id]['quantity'] - 1)  :  $scope.cart.splice(id ,1);
            }else if(action == 'd'){
                $scope.cart.splice(id ,1);
            }else{
                //do something when error
            }
        };

        //get product
        $scope.getproduct = function(){
            if(progressJs) progressJs("#loading").start().autoIncrease(4, 500);
            serviceAjax.getDataFromServer('product/search?merchant_id[]=' + $scope.datauser['userdetail']['merchant_id'] + '&take=16').then(function(response){
                if(response.code == 0 ){
                    for(var i =0; i <response.data.records.length; i++){
                        response.data.records[i]['price'] = accounting.formatMoney(response.data.records[i]['price'], "", 0, ",", ".");
                    }
                    $scope.product = response.data.records;
                }else{
                    //do something when error
                }

                if(progressJs) progressJs("#loading").end();
            });
        };
        $scope.getproduct();
        //watch search
        $scope.$watch("searchproduct", function(newvalue){
            $scope.productnotfound = false;
            if(newvalue && newvalue.length > 2) {
                if(progressJs) progressJs("#loadingsearch").start().autoIncrease(4, 500);
                serviceAjax.getDataFromServer('product/search?product_name_like=' + newvalue).then(function (response) {
                    if (response.code == 0 &&  response.message != 'There is no product found that matched your criteria.') {
                        for (var i = 0; i < response.data.records.length; i++) {
                            response.data.records[i]['price'] = accounting.formatMoney(response.data.records[i]['price'], "", 0, ",", ".");
                        }
                        $scope.product = response.data.records;
                    } else {
                        //do something when error
                        $scope.productnotfound = true;
                        $scope.product = [];
                    }
                    if(progressJs) progressJs("#loadingsearch").end();
                })
            }else if(newvalue.length == 0){
                $scope.productnotfound = false;
                $scope.getproduct();
            }
        });

        //logout
        $scope.logoutfn =  function(){
            if(progressJs) progressJs().start().autoIncrease(4, 500);
            serviceAjax.posDataToServer('pos/logout').then(function(data){
                if(data.code == 0){
                    localStorageService.remove('user');
                    window.location.assign("/pos");
                }else{
                    alert('gagal logout');
                }
                if(progressJs) progressJs().end();
            });
        };




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

    app.controller('ProductDetailCtrl', ['$scope','$modalInstance','dataProduct', function($scope,$modalInstance,dataProduct) {
        console.log(dataProduct);
        $scope.productmodal = dataProduct;
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
