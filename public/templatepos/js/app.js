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

        $scope.signin.alerts = [{ text: "Maaf, ID atau password yang Anda masukkan salah",active: false} ];
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
        //init
        $scope.cart      = [];
        $scope.product   = [];
        $scope.datauser  = localStorageService.get('user');


        //show modal product detail
        $scope.showdetailFn = function(id){
            $scope.productmodal = $scope.product[id];
            $scope.product[id]['disabled'] = 'disabled';
        };

        //get unix guestid
        ($scope.getguest = function(){
                $scope.guests = moment().unix();
        })();
        //function -+ wish list
        $scope.qaFn = function(id,action){

            if(action == 'p'){
                $scope.cart[id]['qty'] = $scope.cart[id]['qty'] ? $scope.cart[id]['qty'] + 1 : 1;
            }else if(action == 'm'){
                $scope.cart[id]['qty'] = $scope.cart[id]['qty'] ? ($scope.cart[id]['qty'] == 1 ? $scope.cart.splice(id ,1) : $scope.cart[id]['qty'] - 1)  :  $scope.cart.splice(id ,1);
            }else if(action == 'd'){
                $scope.cart.splice(id ,1);
            }else{
                //do something when error
            }
            $scope.countcart();
        };
        // qty change manual
        $scope.qtychangemanualFn = function(){
            $scope.countcart();
        };
        //get product
        $scope.getproduct = function(){
            if(progressJs) progressJs("#loading").start().autoIncrease(4, 500);
            serviceAjax.getDataFromServer('product/search?merchant_id[]=' + $scope.datauser['userdetail']['merchant_id'] + '&take=14').then(function(response){
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
                serviceAjax.getDataFromServer('pos/productsearch?product_name_like=' + newvalue + '&upc_code_like=' +  newvalue + '&product_code_like='+newvalue).then(function (response) {
                    if (response.code == 0 &&  response.message != 'There is no product found that matched your criteria.' &&  response.data.records != null) {
                        for (var i = 0; i < response.data.records.length; i++) {
                            response.data.records[i]['price'] = accounting.formatMoney(response.data.records[i]['price'], "", 0, ",", ".");
                        }
                        $scope.product = response.data.records;
                    } else {
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

        //function count cart
        $scope.countcart = function(){
            if($scope.cart.length > 0){
                $scope.totalitem = 0;
                $scope.subtotal  = 0;
                var tmphargatotal = 0;
                for(var i = 0; i < $scope.cart.length ; i++){
                    if($scope.cart[i]['qty'] > 0){
                        $scope.cart[i]['hargatotal'] =  accounting.formatMoney($scope.cart[i]['qty'] *  accounting.unformat($scope.cart[i]['price']), "", 0, ",", ".");

                        $scope.totalitem += parseInt($scope.cart[i]['qty']);
                        tmphargatotal    += accounting.unformat($scope.cart[i]['hargatotal']);
                        $scope.subtotal   = accounting.formatMoney(tmphargatotal, "", 0, ",", ".");
                    }
                }
                //todo:agung change hardcore for VAT
                var vat  = 10;
                var hvat = parseInt(accounting.unformat($scope.subtotal) * vat / 100);
                $scope.vat        =  accounting.formatMoney(hvat, "", 0, ",", ".");
                $scope.totalpay   =  accounting.formatMoney((hvat + accounting.unformat($scope.subtotal)), "", 0, ",", ".");
            }
        };
        //insert to cart
        $scope.inserttocartFn = function(){
             if($scope.productmodal){
                 $scope.cart.push({
                     product_name : $scope.productmodal['product_name'],
                     qty          : 1,
                     price        : $scope.productmodal['price'],
                     hargatotal   : 0

                 });
                 $scope.countcart();
             }
        };

        //new cart
        $scope.newcartFn = function(act){
            $scope.getguest();
            $scope.cart      = [];
        };
        //delete cart
        $scope.deletecartFn = function(act){
            $scope.cart      = [];
        };
        //checkout
        $scope.checkoutFn = function(act){

        };
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
/*
    app.controller('ProductDetailCtrl', ['$scope','$modalInstance','dataProduct', function($scope,$modalInstance,dataProduct) {

        $scope.productmodal = dataProduct;


         $scope.cancel = function () {
             $modalInstance.dismiss('cancel');
         };
     }]);*/

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
