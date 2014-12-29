/**
 * Created with JetBrains PhpStorm.
 * User: julisman
 * Date: 1/12/14
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

    app.controller('layoutCtrl', ['$scope','serviceAjax','localStorageService' ,'$timeout', function($scope,serviceAjax,localStorageService,$timeout) {
        $scope.datauser  = localStorageService.get('user');
        var updatetime = function() {
            $scope.datetime = moment().format('DD MMMM YYYY hh:mm:ss');
            $timeout(updatetime, 1000);
        };
        $timeout(updatetime, 1000);
    }]);

    app.controller('loginCtrl', ['$scope','serviceAjax','localStorageService' , function($scope,serviceAjax,localStorageService) {

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
            serviceAjax.posDataToServer('/pos/login',$scope.login).then(function(data){
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

    app.controller('dashboardCtrl', ['$scope', 'localStorageService','$timeout','serviceAjax','$modal','$http', function($scope,localStorageService, $timeout, serviceAjax, $modal, $http) {
        //init
        $scope.cart      = [];
        $scope.product   = [];
        $scope.productidenabled = [];
        //show modal product detail
        $scope.showdetailFn = function(id,act){
            $scope.productmodal        = $scope.product[id];
            $scope.productmodal['idx'] = id;
            $scope.hiddenbtn = false;
            if(act) $scope.hiddenbtn = true;
        };
        //get unix guestid
        ($scope.getguest = function(){
                $scope.guests = moment().unix();
        })();
        //function -+ wish list
        $scope.qaFn = function(id,action){
            var fndelete = function(){
                if($scope.product[$scope.cart[id]['idx']]) $scope.product[$scope.cart[id]['idx']]['disabled'] = false;
                $scope.adddelenadis($scope.cart[id]['product_id'],'del');
                $scope.cart.splice(id ,1);
            };
            if(action == 'p'){
                $scope.cart[id]['qty'] = $scope.cart[id]['qty'] ? parseInt($scope.cart[id]['qty']) + 1 : 1;
            }else if(action == 'm'){
                if($scope.cart[id]['qty'] == 1){
                    fndelete();
                }else{
                    $scope.cart[id]['qty'] = $scope.cart[id]['qty'] - 1;
                }
            }else if(action == 'd'){
                fndelete();
                if($scope.cart.length == 0) $scope.cart = [];
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
            serviceAjax.getDataFromServer('/product/search?merchant_id[]=' + $scope.datauser['userdetail']['merchant_id'] + '&take=14').then(function(response){
                if(response.code == 0 ){
                    for(var i =0; i <response.data.records.length; i++){
                       response.data.records[i]['price'] = accounting.formatMoney(response.data.records[i]['price'], "", 0, ",", ".");
                    }
                    $scope.product = response.data.records;
                    $scope.enadis();
                }else{
                    //do something when error
                }
                if(progressJs) progressJs("#loading").end();
            });
        };
        //watch search
        $scope.$watch("searchproduct", function(newvalue){
            $scope.productnotfound = false;
            if(newvalue){
                if(newvalue && newvalue.length > 2) {
                    if(progressJs) progressJs("#loadingsearch").start().autoIncrease(4, 500);
                    serviceAjax.getDataFromServer('/pos/productsearch?product_name_like=' + newvalue + '&upc_code_like=' +  newvalue + '&product_code_like='+newvalue +'merchant_id[]=' + $scope.datauser['userdetail']['merchant_id']).then(function (response) {
                        if (response.code == 0 &&  response.message != 'There is no product found that matched your criteria.' &&  response.data.records != null) {
                            for (var i = 0; i < response.data.records.length; i++) {
                                response.data.records[i]['price'] = accounting.formatMoney(response.data.records[i]['price'], "", 0, ",", ".");
                            }
                            $scope.product = response.data.records;
                            $scope.enadis();
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
            }else{
                $scope.productnotfound = false;
                $scope.getproduct();
            }
        });
        //function count cart
        $scope.countcart = function(){
            if($scope.cart.length > 0){
                $scope.cart.totalitem = 0;
                $scope.cart.subtotal  = 0;
                var tmphargatotal = 0;
                for(var i = 0; i < $scope.cart.length ; i++){
                    if($scope.cart[i]['qty'] > 0){
                        $scope.cart[i]['hargatotal'] =  accounting.formatMoney($scope.cart[i]['qty'] *  accounting.unformat($scope.cart[i]['price']), "", 0, ",", ".");

                        $scope.cart.totalitem += parseInt($scope.cart[i]['qty']);
                        tmphargatotal    += accounting.unformat($scope.cart[i]['hargatotal']);
                        $scope.cart.subtotal   = accounting.formatMoney(tmphargatotal, "", 0, ",", ".");
                    }
                }
                //todo:agung change hardcore for VAT
                var vat  = 10;
                var hvat = parseInt(accounting.unformat($scope.cart.subtotal) * vat / 100);
                $scope.cart.vat        =  accounting.formatMoney(hvat, "", 0, ",", ".");
                $scope.cart.totalpay   =  accounting.formatMoney((hvat + accounting.unformat($scope.cart.subtotal)), "", 0, ",", ".");
            }
        };
        //insert to cart
        $scope.inserttocartFn = function(){
             if($scope.productmodal){
                 $scope.adddelenadis($scope.productmodal['product_id'],'add');
                 if($scope.checkcart($scope.productmodal['product_id'])){
                     $scope.cart.push({
                         product_name : $scope.productmodal['product_name'],
                         qty          : 1,
                         price        : $scope.productmodal['price'],
                         idx          : $scope.productmodal['idx'],
                         upc_code     : $scope.productmodal['upc_code'],
                         product_id   : $scope.productmodal['product_id'],
                         hargatotal   : 0
                     });
                 }
                 $scope.countcart();
             }
        };
        //enabled disabled
        $scope.enadis = function(){
            for(var i = 0; i < $scope.product.length;i++){
                for(var a = 0; a < $scope.productidenabled.length; a++){
                    if($scope.product[i]['product_id'] == $scope.productidenabled[a] ){
                        $scope.product[i]['disabled'] = true;
                    }
                }
            }
        };
        //delete array product id enable
        $scope.adddelenadis = function(id,act){
            var check = false;
            for(var a = 0; a < $scope.productidenabled.length; a++){
                if(id == $scope.productidenabled[a] ){
                    if(act == 'del') {
                        $scope.productidenabled.splice(a ,1);
                        $scope.getproduct();
                    }
                    if(act == 'add') check = true;
                }
            }
            if(act == 'add' && !check) {
                $scope.productidenabled.push(id);
                $scope.enadis();
            }
        };
        //checkcart
        $scope.checkcart = function(id){
            var check = true;
            for(var i = 0; i < $scope.cart.length; i++){
                if($scope.cart[i]['product_id'] == id){
                    $scope.cart[i]['qty']++;
                    check = false;
                }
            }
            return check;
        };
        //new cart
        $scope.newcartFn = function(act){
            $scope.productidenabled = [];
            $scope.cart             = [];
            $scope.getguest();
            $scope.getproduct();
        };
        //delete cart
        $scope.deletecartFn = function(act){
            $scope.productidenabled = [];
            $scope.cart             = [];
            $scope.getproduct();
        };
        //checkout
        $scope.checkoutFn = function(act){
            if(act) $scope.action = act == 't' ? 'cash' : 'card';
        };
        //watch amount on page cash
        $scope.$watch("cart.amount", function(newvalue,oldvalue){
            $scope.changetf = false;
            if(newvalue) {
                $scope.cart['change'] = 0;
                $scope.messagepay     = '';
                oldvalue = accounting.unformat(oldvalue);
                newvalue = accounting.unformat(newvalue);
                if(oldvalue != newvalue) $scope.cart['amount'] = accounting.formatMoney(newvalue, "", 0, ",", ".");
            }
        });
        $scope.getChange = function(clickEvent){
            if(clickEvent.keyCode == '13'){
                $scope.change = accounting.unformat($scope.cart['amount']) - accounting.unformat($scope.cart['totalpay']);
                $scope.changetf = $scope.change > 0 ? true:false;
                $scope.messagepay = $scope.changetf ? 'Nominal tunai melebihi total bayar, ada kembalian!' : 'Nominal tunai lebih kecil dari total bayar!';
               $scope.cart['change'] =    accounting.formatMoney($scope.change, "", 0, ",", ".");
            }
        };
        //go to main
        $scope.gotomain = function(){
            angular.element("#myModalcheckout").modal('hide');
            $scope.action = 'main';
        };
        //chose terminal payment debit/redit
        $scope.choseTerminalFn = function(id){
            $scope.gesekkartu = true;
        };

        //scan product only run on linux
        ($scope.scanproduct = function(){
            serviceAjax.posDataToServer('/pos/scanbarcode').then(function(response){
                    if(response.code == 0){
                        $scope.productmodal        = response['data'];
                        $scope.inserttocartFn();
                        $scope.scanproduct();
                    }else if(response.code == 13){
                        /*angular.element("#ProductNotFound").modal();
                        $scope.scanproduct();*/
                    }
            });
        })();

        //logout
        $scope.logoutfn =  function(){
            if(progressJs) progressJs().start().autoIncrease(4, 500);
            serviceAjax.posDataToServer('/pos/logout').then(function(data){
                if(data.code == 0){
                    localStorageService.remove('user');
                    window.location.assign("/pos");
                }else{
                    alert('gagal logout');
                }
                if(progressJs) progressJs().end();
            });
        };
    }]);

    app.controller('cashCtrl', ['$scope','serviceAjax','localStorageService' , function($scope,serviceAjax,localStorageService) {


    }]);

    app.controller('cardCtrl', ['$scope','serviceAjax','localStorageService' , function($scope,serviceAjax,localStorageService) {
        $scope.datauser  = localStorageService.get('user');
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
