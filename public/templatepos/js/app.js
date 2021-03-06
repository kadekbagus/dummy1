/**
 * Created with JetBrains PhpStorm.
 * User: julisman and ahmad
 * Date: 1/12/14
 * Time: 3:18 PM
 * http://blog.julisman.com
 */
'use strict';

define([
    'config',
    'i18n/id',
    'i18n/en',
    'version'
], function (config,id,en, version) {

var app = angular.module('app', ['ui.bootstrap','ngAnimate','LocalStorageModule','ngTouch'], function($interpolateProvider,$httpProvider) {
    $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded';
    $interpolateProvider.startSymbol('<%');
    $interpolateProvider.endSymbol('%>');

});
app.controller('layoutCtrl', ['$scope','serviceAjax','localStorageService' ,'$timeout', '$location', function($scope,serviceAjax,localStorageService,$timeout, $location) {
    $scope.datauser  = localStorageService.get('user');

    $scope.pathPublic = config.baseUrlServerPublic + '/mobile-ci/';

    serviceAjax.getDataFromServer('/current-retailer?with[]=merchant').then(function(response) {
        if(response.code == 0){
            var url = window.location.pathname.split('/');
            if (response.data.merchant.enable_shopping_cart === 'no' && url[url.length - 1] !== 'access-forbidden') {
                window.location.assign('access-forbidden');
            };
        }
    });

    var updatetime = function() {
        $scope.datetime = moment().format('DD MMMM YYYY HH:mm:ss');
        $timeout(updatetime, 1000);
    };
    $timeout(updatetime, 1000);
}]);

app.controller('loginCtrl', ['$scope','serviceAjax','localStorageService', function($scope,serviceAjax,localStorageService) {
    $scope.infomerchant = [];
    $scope.versions     = {};
    $scope.language     = en;
    serviceAjax.getDataFromServer('/pos/getmerchantinfo').then(function(response) {
        if(response.code == 0){
            $scope.language = response.data.pos_language == 'id' ? id : en;
            localStorageService.set('currency',response.data.currency)
            localStorageService.set('currency_symbol',response.data.currency_symbol)
        }
    });
    serviceAjax.getDataFromServerPublicUrl('/app/orbit-version').then(function(response) {
        if(response.code == 0){
            $scope.versions.adminBuildDate    = "";
            $scope.versions.apiVersion        = 'v'+response.data.version;
            $scope.versions.strings           = 'Orbit '+$scope.versions.apiVersion;
            localStorageService.add('version',$scope.versions.strings);
        }
    });
    if(localStorageService.get('user')){
        window.location.assign("dashboard");
    }
    $scope.login  = {};
    $scope.signin = {};
    $scope.signin.alerts = [{ text: $scope.language.loginerror,active: false} ];
    $scope.signin.alertDismisser = function(index) {
        $scope.signin.alerts[index].active = false;
    };
    $scope.loginFn = function(){
        $scope.showloader = true;
        if(progressJs) progressJs().start().autoIncrease(4, 500);
        serviceAjax.posDataToServer('/pos/logincashier',$scope.login).then(function(data){
            if(data.code == 0){
                $scope.shownall = false;
                localStorageService.add('user',data.data);
                window.location.assign("dashboard");
            }else{
                $scope.signin.alerts[0].active = true;
            }
            $scope.showloader = false;
            if(progressJs) progressJs().end();
        });
    };

}]);

app.controller('Error404Ctrl', ['$scope','serviceAjax','localStorageService' ,'$timeout', '$location', function($scope,serviceAjax,localStorageService,$timeout, $location) {
    serviceAjax.getDataFromServer('/current-retailer?with[]=merchant').then(function(response) {
        if(response.code == 0){
            if (response.data.merchant.enable_shopping_cart === 'yes') {
                window.location.assign('signin');
            };
        }
    });
}]);

app.filter('getById', function() {
    return function(input, id) {
        var i=0, len=input.length;
        for (; i<len; i++) {
            if (+input[i].id == +id) {
                return input[i];
            }
        }
        return null;
    }
});

    app.controller('dashboardCtrl', ['$scope', 'localStorageService','$timeout','serviceAjax','$modal','$http', '$anchorScroll','$location', '$filter', function($scope,localStorageService, $timeout, serviceAjax, $modal, $http,$anchorScroll,$location,$filter) {
        //init
        $scope.cart               = [];
        $scope.product            = [];
        $scope.productidenabled   = [];
        $scope.configs            = config;
        $scope.datadisplay        = {};
        $scope.manualscancart     = '';
        $scope.holdbtn            = true;
        $scope.versions           = localStorageService.get('version');
        $scope.lock               = false;
        $scope.terminalstatus     = true;
        //get currency from local storage
        $scope.precision          = localStorageService.get('currency') == 'IDR' ? 0 : 2;
        $scope.currency           = localStorageService.get('currency') == 'IDR' ? 'IDR' : 'USD';
        $scope.currency_symbol    = localStorageService.get('currency_symbol') == 'Rp' ? 'Rp' : '$';

        if(!$scope.datauser){
             window.location.assign("signin");
        }else{
             $scope.language           = $scope.datauser['merchant']['pos_language'] == 'id' ? id : en;
             $scope.cheader            = $scope.language.pilihcarapembayaran;
             $scope.gesek              = $scope.language.gesekkartusekarang;
             $scope.vat_included       = $scope.datauser['merchant']['vat_included'];
             //when click product in list
             $scope.showdetailFn = function(id,act,attr1){
                    //show modal product detail
                    if(attr1 != null) angular.element('#myModal').modal('show');

                    $scope.loadproductdetail = true;
                    $scope.hiddenbtn         = false;
                    $scope.showprice         = false;
                    $scope.variantstmp       = '';
                    $scope.getpromotion(id,act,attr1);
                    $scope.hiddenbtn = act ? true : false;
                };
             //canceler request
             $scope.cancelRequestService = function(){
                    serviceAjax.cancelRequest();
                };
             //get unix guestid
             ($scope.getguest = function(){
                    $scope.guests = 'Guest-'+moment().format('DD-MM-YYYY-HH-mm-ss');
                })();

             //check terminal status
             $scope.getterminal = function(){
                    serviceAjax.getDataFromServer('/pos/terminalstatus').then(function(response){
                        if(response.code == 13 && response.message == 'Terminal not found' ){
                          $scope.terminalstatus = false;
                        }else{
                          $scope.terminalstatus = true;
                        }
                    });

              };
             //function -+ cart qty list
             $scope.qaFn = function(id,action){
                    var fndelete = function(){
                         //set activity when product delete from the cart
                        var user_id = $scope.cart.user_id ? $scope.cart.user_id : 0;
                        $scope.activity('activity-delete-product',{customer_id : user_id ,product_id : $scope.cart[id]['product_id'] });

                        if($scope.product[$scope.cart[id]['idx']]) $scope.product[$scope.cart[id]['idx']]['disabled'] = false;
                        $scope.adddelenadis($scope.cart[id]['product_id'],'del');
                        $scope.cart.splice(id ,1);
                        $scope.tmpsubtotal = '';
                        $scope.applycartpromotion   = [];
                        $scope.applycartcoupon      = [];

                    };

                    if(action == 'p'){//add
                        $scope.cart[id]['qty'] = $scope.cart[id]['qty'] ? parseInt($scope.cart[id]['qty']) + 1 : 1;
                        $scope.watchqtypromotion(id);
                    }else if(action == 'm'){//minus
                        if($scope.cart[id]['qty'] == 1){
                            fndelete();
                        }else{
                            $scope.cart[id]['qty'] = $scope.cart[id]['qty'] - 1;
                            $scope.watchqtypromotion(id);
                        }
                    }else if(action == 'd'){//delete
                        fndelete();
                        if($scope.cart.length == 0) $scope.cart = [];
                    }else{
                        //do something when error
                    }
                    $scope.countcart();
                };
             //watch qty promotion
             $scope.watchqtypromotion = function(id){
                    if($scope.cart[id]['promotion'].length){
                        for(var i = 0; i < $scope.cart[id]['promotion'].length;i++){
                            $scope.cart[id]['promotion'][i]['afterpromotionprice'] = accounting.formatMoney($scope.cart[id]['promotion'][i]['tmpafterpromotionprice'] * $scope.cart[id]['qty'], "", $scope.precision, ",", ".");
                        }
                    }
                };
             //get product
             $scope.getproduct = function(){
                    serviceAjax.getDataFromServer('/pos/quickproduct?take=12').then(function(response){
                        if(response.code == 0 ){
                            if(response.data.records.length > 0)for(var i =0; i <response.data.records.length; i++){
                                response.data.records[i]['product']['price'] = accounting.formatMoney(response.data.records[i]['product']['price'], "", $scope.precision, ",", ".");
                                $scope.product[i] = response.data.records[i]['product'];
                            }
                            //$scope.product = response.data.records;
                            $scope.enadis();
                        }else if(response.code == 13){
                            $scope.logoutfn();
                        }else{
                            //do something when error
                        }
                    });
                };
             //watch search
             $scope.$watch("searchproduct", function(newvalue){
                    $scope.productnotfound = false;
                    if(newvalue){
                        if(newvalue && newvalue.length > 2) {
                            if(progressJs) progressJs("#loadingsearch").start().autoIncrease(4, 500);
                            serviceAjax.getDataFromServer('/pos/productsearch?keyword=' + newvalue).then(function (response) {
                                if (response.code == 0 &&  response.message != 'There is no product found that matched your criteria.' &&  response.data.records != null) {
                                    for (var i = 0; i < response.data.records.length; i++) {
                                        response.data.records[i]['price'] = accounting.formatMoney(response.data.records[i]['price'], "", $scope.precision, ",", ".");
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
             //get promotion base scanproduct
              $scope.getpromotionScanproduct = function(product,act,attr1){
                   $scope.datapromotion = [];
                    serviceAjax.posDataToServer('/pos/productdetail', {product_id :product.product_id}).then(function (response) {
                        if (response.code == 0 ) {
                            $scope.productmodal = response.data.product;
                            $scope.productmodal['price']    = product.price;
                            $scope.productmodal['upc_code'] = product.upc_code;

                            $scope.productdetail = response.data;
                            $scope.datapromotion = response.data.promo;
                            var discount    = 0;
                            var tmpdiscount = 0;
                            if($scope.datapromotion.length)for(var i = 0; i < $scope.datapromotion.length;i++){
                                $scope.datapromotion[i]['oridiscount_value'] = $scope.datapromotion[i]['discount_value'];
                                $scope.datapromotion[i]['discount_value']    = $scope.datapromotion[i]['rule_type'] == 'product_discount_by_percentage' ?  $scope.datapromotion[i]['discount_value'] * 100 + ' %' : accounting.formatMoney($scope.datapromotion[i]['discount_value'], "", $scope.precision, ",", ".");
                                $scope.datapromotion[i]['begin_date']        = moment($scope.datapromotion[i]['begin_date']).isValid() ? moment($scope.datapromotion[i]['begin_date']).format('DD MMMM YYYY')  : '';
                                $scope.datapromotion[i]['end_date']          = moment($scope.datapromotion[i]['end_date']).isValid() ? moment($scope.datapromotion[i]['end_date']).format('DD MMMM YYYY') : '';

                                if($scope.datapromotion[i]['rule_type'] == 'product_discount_by_percentage'){
                                    tmpdiscount = $scope.datapromotion[i]['oridiscount_value'] * $scope.productmodal['price'];
                                }else if($scope.datapromotion[i]['rule_type'] == 'product_discount_by_value'){
                                    tmpdiscount = accounting.unformat($scope.datapromotion[i]['discount_value']);
                                }else if($scope.datapromotion[i]['rule_type'] == 'new_product_price'){
                                    tmpdiscount =  $scope.productmodal['price'] - accounting.unformat($scope.datapromotion[i]['discount_value']) + 0;
                                }else{
                                    tmpdiscount = 0;
                                }

                               // if(act){
                                 //   tmpdiscount = $scope.datapromotion[i]['rule_type'] == 'product_discount_by_percentage' ?  $scope.datapromotion[i]['oridiscount_value'] * $scope.productmodal['price'] : accounting.unformat($scope.datapromotion[i]['discount_value']);
                                    $scope.datapromotion[i]['afterpromotionprice']    = accounting.formatMoney(tmpdiscount, "", $scope.precision, ",", ".");
                                    $scope.datapromotion[i]['tmpafterpromotionprice'] = tmpdiscount;
                                    discount += tmpdiscount;
                               // }
                            }
                           // if(act){
                                var discounts = accounting.unformat($scope.productmodal['price']) - discount;
                                $scope.productmodal.afterpromotionprice =  discounts < 0 ?  0 :accounting.formatMoney(discounts, "", $scope.precision, ",", ".");
                           // }
                            $scope.productmodal['price'] = accounting.formatMoney($scope.productmodal['price'], "", $scope.precision, ",", ".");

                            $scope.dataattrvalue1 = [];
                            $scope.dataattrvalue2 = [];
                            $scope.dataattrvalue3 = [];
                            $scope.dataattrvalue4 = [];
                            $scope.dataattrvalue5 = [];
                            $scope.tmpattr = [];
                            $scope.chooseattr = [];
                            //TODO: agung :Refactor this, and try with dfferent data
                            var b = 0;
                            if($scope.productdetail.attributes.length)for(var a=0; a < $scope.productdetail.attributes.length;a++){
                                if($scope.productdetail.attributes[a]['attr1'] != null){
                                     $scope.dataattrvalue1[b] = angular.copy($scope.productdetail.attributes[a]);
                                     $scope.dataattrvalue2[b] = angular.copy($scope.productdetail.attributes[a]);
                                     $scope.dataattrvalue3[b] = angular.copy($scope.productdetail.attributes[a]);
                                     $scope.dataattrvalue4[b] = angular.copy($scope.productdetail.attributes[a]);
                                     $scope.dataattrvalue5[b] = angular.copy($scope.productdetail.attributes[a]);
                                     $scope.tmpattr[b] = angular.copy($scope.productdetail.attributes[a]);
                                     b++;
                                }

                            }

                            for(var i = 0; i < $scope.dataattrvalue1.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue1.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue1[i]['attr_val_id1'] == $scope.dataattrvalue1[a]['attr_val_id1']){
                                            $scope.dataattrvalue1.splice(i,1);
                                            i=0;a=0;
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue2.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue2.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue2[i]['attr_val_id1'] == $scope.dataattrvalue2[a]['attr_val_id1'] && $scope.dataattrvalue2[i]['attr_val_id2'] == $scope.dataattrvalue2[a]['attr_val_id2']){
                                            $scope.dataattrvalue2.splice(i,1);
                                            i=0;a=0;
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue3.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue3.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue3[i]['attr_val_id1'] == $scope.dataattrvalue3[a]['attr_val_id1'] && $scope.dataattrvalue3[i]['attr_val_id2'] == $scope.dataattrvalue3[a]['attr_val_id2'] && $scope.dataattrvalue3[i]['attr_val_id3'] == $scope.dataattrvalue3[a]['attr_val_id3']){
                                            $scope.dataattrvalue3.splice(i,1);
                                            i=0;a=0;
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue4.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue4.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue4[i]['attr_val_id1'] == $scope.dataattrvalue4[a]['attr_val_id1'] && $scope.dataattrvalue4[i]['attr_val_id2'] == $scope.dataattrvalue4[a]['attr_val_id2'] && $scope.dataattrvalue4[i]['attr_val_id3'] == $scope.dataattrvalue4[a]['attr_val_id3'] && $scope.dataattrvalue4[i]['attr_val_id4'] == $scope.dataattrvalue4[a]['attr_val_id4']){
                                            $scope.dataattrvalue4.splice(i,1);
                                            i=0;a=0;
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue5.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue5.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue5[i]['attr_val_id1'] == $scope.dataattrvalue5[a]['attr_val_id1'] && $scope.dataattrvalue5[i]['attr_val_id2'] == $scope.dataattrvalue5[a]['attr_val_id2'] && $scope.dataattrvalue5[i]['attr_val_id3'] == $scope.dataattrvalue5[a]['attr_val_id3'] && $scope.dataattrvalue5[i]['attr_val_id4'] == $scope.dataattrvalue5[a]['attr_val_id4'] && $scope.dataattrvalue5[i]['attr_val_id5'] == $scope.dataattrvalue5[a]['attr_val_id5']){
                                            $scope.dataattrvalue5.splice(i,1);
                                            i=0;a=0;
                                       }
                                   }
                                }
                            }

                            $scope.countattr = 0;
                            if($scope.productdetail.product.attribute1) $scope.countattr++;
                            if($scope.productdetail.product.attribute2) $scope.countattr++;
                            if($scope.productdetail.product.attribute3) $scope.countattr++;
                            if($scope.productdetail.product.attribute4) $scope.countattr++;
                            if($scope.productdetail.product.attribute5) $scope.countattr++;
                            $scope.loadproductdetail = false;
                            if(attr1 == null ) $scope.inserttocartFn();
                        }else if(response.code == 13) {
                            $scope.logoutfn();
                        }else{
                            //do smoething
                        }
                    })
              };
             //get product based promotion TODO:
             $scope.getpromotion = function(productid,act,attr1){
                    $scope.datapromotion = [];
                   if(productid) serviceAjax.posDataToServer('/pos/productdetail', {product_id :productid}).then(function (response) {
                        if (response.code == 0 ) {
                            $scope.productmodal = response.data.product;

                            $scope.productdetail = response.data;
                            $scope.datapromotion = response.data.promo;
                            var discount    = 0;
                            var tmpdiscount = 0;
                            if($scope.datapromotion.length)for(var i = 0; i < $scope.datapromotion.length;i++){
                                $scope.datapromotion[i]['oridiscount_value'] = $scope.datapromotion[i]['discount_value'];
                                $scope.datapromotion[i]['discount_value']    = $scope.datapromotion[i]['rule_type'] == 'product_discount_by_percentage' ?  $scope.datapromotion[i]['discount_value'] * 100 + ' %' : accounting.formatMoney($scope.datapromotion[i]['discount_value'], "", $scope.precision, ",", ".");
                                $scope.datapromotion[i]['begin_date']        = moment($scope.datapromotion[i]['begin_date']).isValid() ? moment($scope.datapromotion[i]['begin_date']).format('DD MMMM YYYY')  : '';
                                $scope.datapromotion[i]['end_date']          = moment($scope.datapromotion[i]['end_date']).isValid() ? moment($scope.datapromotion[i]['end_date']).format('DD MMMM YYYY') : '';

                                if($scope.datapromotion[i]['rule_type'] == 'product_discount_by_percentage'){
                                    tmpdiscount = $scope.datapromotion[i]['oridiscount_value'] * $scope.productmodal['price'];
                                }else if($scope.datapromotion[i]['rule_type'] == 'product_discount_by_value'){
                                    tmpdiscount = accounting.unformat($scope.datapromotion[i]['discount_value']);
                                }else if($scope.datapromotion[i]['rule_type'] == 'new_product_price'){
                                    tmpdiscount =  $scope.productmodal['price'] - accounting.unformat($scope.datapromotion[i]['discount_value']) + 0;
                                }else{
                                    tmpdiscount = 0;
                                }

                               // if(act){
                                 //   tmpdiscount = $scope.datapromotion[i]['rule_type'] == 'product_discount_by_percentage' ?  $scope.datapromotion[i]['oridiscount_value'] * $scope.productmodal['price'] : accounting.unformat($scope.datapromotion[i]['discount_value']);
                                    $scope.datapromotion[i]['afterpromotionprice']    = accounting.formatMoney(tmpdiscount, "", $scope.precision, ",", ".");
                                    $scope.datapromotion[i]['tmpafterpromotionprice'] = tmpdiscount;
                                    discount += tmpdiscount;
                               // }
                            }
                           // if(act){
                                var discounts = accounting.unformat($scope.productmodal['price']) - discount;
                                $scope.productmodal.afterpromotionprice =  discounts < 0 ?  0 :accounting.formatMoney(discounts, "", $scope.precision, ",", ".");
                           // }
                            $scope.productmodal['price'] = accounting.formatMoney($scope.productmodal['price'], "", $scope.precision, ",", ".");

                            $scope.dataattrvalue1 = [];
                            $scope.dataattrvalue2 = [];
                            $scope.dataattrvalue3 = [];
                            $scope.dataattrvalue4 = [];
                            $scope.dataattrvalue5 = [];
                            $scope.tmpattr = [];
                            $scope.chooseattr = [];
                            //TODO: agung :Refactor this, and try with dfferent data
                            var b = 0;
                            if($scope.productdetail.attributes.length)for(var a=0; a < $scope.productdetail.attributes.length;a++){
                                if($scope.productdetail.attributes[a]['attr1'] != null){
                                    $scope.dataattrvalue1[b] = angular.copy($scope.productdetail.attributes[a]);
                                    $scope.dataattrvalue2[b] = angular.copy($scope.productdetail.attributes[a]);
                                    $scope.dataattrvalue3[b] = angular.copy($scope.productdetail.attributes[a]);
                                    $scope.dataattrvalue4[b] = angular.copy($scope.productdetail.attributes[a]);
                                    $scope.dataattrvalue5[b] = angular.copy($scope.productdetail.attributes[a]);
                                    $scope.tmpattr[b] = angular.copy($scope.productdetail.attributes[a]);
                                    b++
                                }
                            }

                            for(var i = 0; i < $scope.dataattrvalue1.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue1.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue1[i]['attr_val_id1'] == $scope.dataattrvalue1[a]['attr_val_id1']){
                                            $scope.dataattrvalue1.splice(i,1);
                                            i=0;a=0;
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue2.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue2.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue2[i]['attr_val_id1'] == $scope.dataattrvalue2[a]['attr_val_id1'] && $scope.dataattrvalue2[i]['attr_val_id2'] == $scope.dataattrvalue2[a]['attr_val_id2']){
                                            $scope.dataattrvalue2.splice(i,1);
                                            i=0;a=0;
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue3.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue3.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue3[i]['attr_val_id1'] == $scope.dataattrvalue3[a]['attr_val_id1'] && $scope.dataattrvalue3[i]['attr_val_id2'] == $scope.dataattrvalue3[a]['attr_val_id2'] && $scope.dataattrvalue3[i]['attr_val_id3'] == $scope.dataattrvalue3[a]['attr_val_id3']){
                                            $scope.dataattrvalue3.splice(i,1);
                                            i=0;a=0;
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue4.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue4.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue4[i]['attr_val_id1'] == $scope.dataattrvalue4[a]['attr_val_id1'] && $scope.dataattrvalue4[i]['attr_val_id2'] == $scope.dataattrvalue4[a]['attr_val_id2'] && $scope.dataattrvalue4[i]['attr_val_id3'] == $scope.dataattrvalue4[a]['attr_val_id3'] && $scope.dataattrvalue4[i]['attr_val_id4'] == $scope.dataattrvalue4[a]['attr_val_id4']){
                                            $scope.dataattrvalue4.splice(i,1);
                                            i=0;a=0;
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue5.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue5.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue5[i]['attr_val_id1'] == $scope.dataattrvalue5[a]['attr_val_id1'] && $scope.dataattrvalue5[i]['attr_val_id2'] == $scope.dataattrvalue5[a]['attr_val_id2'] && $scope.dataattrvalue5[i]['attr_val_id3'] == $scope.dataattrvalue5[a]['attr_val_id3'] && $scope.dataattrvalue5[i]['attr_val_id4'] == $scope.dataattrvalue5[a]['attr_val_id4'] && $scope.dataattrvalue5[i]['attr_val_id5'] == $scope.dataattrvalue5[a]['attr_val_id5']){
                                            $scope.dataattrvalue5.splice(i,1);
                                            i=0;a=0;
                                       }
                                   }
                                }
                            }

                            $scope.countattr = 0;
                            if($scope.productdetail.product.attribute1) $scope.countattr++;
                            if($scope.productdetail.product.attribute2) $scope.countattr++;
                            if($scope.productdetail.product.attribute3) $scope.countattr++;
                            if($scope.productdetail.product.attribute4) $scope.countattr++;
                            if($scope.productdetail.product.attribute5) $scope.countattr++;
                            $scope.loadproductdetail = false;
                            if(attr1 == null && !$scope.hiddenbtn) $scope.inserttocartFn();
                        }else if(response.code == 13) {
                            $scope.logoutfn();
                        }else{
                            //do smoething
                        }
                    })
                };
             //get  cart based promotion
             ($scope.getcartpromotion = function(){
                    serviceAjax.posDataToServer('/pos/cartbasedpromotion').then(function (response) {
                        if (response.code == 0 ) {
                            $scope.cartpromotions = response.data;
                        }
                    })
                })();
             // when choose last the attribute
             $scope.changeattr = function(id,idx){
                    $scope.showprice = false;
                    for(var i = id+1; i < $scope.chooseattr.length; i++ ){
                       $scope.chooseattr[i] = '';
                    }
                    if(id +1 == $scope.countattr){
                      //  $scope.chooseattr[id] = true;
                        $scope.variantstmp = $scope.tmpattr[idx];
                        $scope.productmodal.upc_code = $scope.tmpattr[idx]['upc'];
                        $scope.showprice = true;
                        if($scope.datapromotion.length) {
                            var discount    = 0;
                            var tmpdiscount = 0;
                            for(var i = 0; i < $scope.datapromotion.length;i++){
                                if($scope.datapromotion[i]['rule_type'] == 'product_discount_by_percentage'){
                                    tmpdiscount = $scope.datapromotion[i]['oridiscount_value'] * $scope.tmpattr[idx]['price'];
                                }else if($scope.datapromotion[i]['rule_type'] == 'product_discount_by_value'){
                                    tmpdiscount = accounting.unformat($scope.datapromotion[i]['discount_value']);
                                }else if($scope.datapromotion[i]['rule_type'] == 'new_product_price'){
                                    tmpdiscount =  $scope.tmpattr[idx]['price'] - accounting.unformat($scope.datapromotion[i]['discount_value']) + 0;
                                }else{
                                    tmpdiscount = 0;
                                }
                              //  tmpdiscount = $scope.datapromotion[i]['rule_type'] == 'product_discount_by_percentage' ?  $scope.datapromotion[i]['oridiscount_value'] * $scope.tmpattr[idx]['price'] : accounting.unformat($scope.datapromotion[i]['discount_value']);
                                $scope.datapromotion[i]['afterpromotionprice']    = accounting.formatMoney(tmpdiscount, "", $scope.precision, ",", ".");
                                $scope.datapromotion[i]['tmpafterpromotionprice'] = tmpdiscount;
                                tmpdiscount = tmpdiscount > 0 ? tmpdiscount : 0;
                                discount += tmpdiscount;
                            }
                            var discounts = $scope.tmpattr[idx]['price'] - discount;
                            $scope.productmodal.afterpromotionprice =  discounts < 0 ?  0 :accounting.formatMoney(discounts, "", $scope.precision, ",", ".");
                            $scope.productmodal.price =  accounting.formatMoney($scope.tmpattr[idx]['price'], "", $scope.precision, ",", ".");
                        }else{
                            $scope.productmodal.price    = accounting.formatMoney($scope.tmpattr[idx]['price'], "", $scope.precision, ",", ".");
                            $scope.productmodal.afterpromotionprice = 0;
                        }
                    }

                };
             //reset search
             $scope.resetsearch = function(){
                    $scope.searchproduct = '';
                };

             //function count cart
             $scope.countcart = function(){
                    if($scope.cart.length > 0){

                        $scope.cart.totalitem  = 0;
                        $scope.cart.subtotal   = 0;
                        $scope.cart.vat        = 0;
                        $scope.naspletan  = 0;   // service
                        $scope.nasbedag = 0;     // vat
                        $scope.nakeleng = 0;    // luxury
                        $scope.taxes = [];
                        var tmphargatotal      = 0;
                        var tmpvattotal        = 0;
                        var tmphargatotalwotax = 0;
                        var tmphargatotaltax   = 0;
                        var taxpromo=0;
                        var nasplet = 0;
                        var bedag = 0;
                        var taxes = [];
                        var taxObj = {};

                        for(var i = 0; i < $scope.cart.length ; i++){
                            if($scope.cart[i]['qty'] > 0){
                                var vat             = 0;
                                var tmpvat          = 0;
                                var service         = 0;
                                var tmpservice      = 0;
                                var pricewotax      = 0;
                                var pricetax        = 0;

                                $scope.cart[i]['hargatotal'] =  accounting.formatMoney($scope.cart[i]['qty'] * accounting.unformat($scope.cart[i]['price']), "", $scope.precision, ",", ".");
                                var temp_price = $scope.cart[i]['hargatotal'];

                                $scope.cart.totalitem += parseInt($scope.cart[i]['qty']);
                                var promotionprice = 0;
                                var couponprice    = 0;
                                if($scope.vat_included == 'yes'){
                                    if($scope.cart[i]['product_details']['tax2'] != null){
                                        if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'service'){
                                            tmpvat     = accounting.unformat($scope.cart[i]['hargatotal']) / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));
                                            vat        = accounting.unformat($scope.cart[i]['hargatotal']) - tmpvat;
                                            tmpservice = tmpvat / (1 + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                            service    = tmpvat - tmpservice;
                                            pricewotax = accounting.unformat($scope.cart[i]['hargatotal']) / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']) + (parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value'])));
                                            //nasplet    += tmpservice;

                                            var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                            if(taxonarray){
                                                taxonarray.total += service;
                                                taxonarray.tmptotal += service;
                                            } else {
                                                var tax = {};
                                                tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                tax.total = service;
                                                tax.tmptotal = service;
                                                taxes.push(tax);
                                            }
                                            var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                            if(taxonarray){
                                                taxonarray.total += vat;
                                                taxonarray.tmptotal += vat;
                                            } else {
                                                var tax = {};
                                                tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                tax.total = vat;
                                                tax.tmptotal = vat;
                                                taxes.push(tax);
                                            }
                                        }else if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'luxury'){
                                            tmpvat     = accounting.unformat($scope.cart[i]['hargatotal']) / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                            vat        = tmpvat * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                            service    = tmpvat * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']);
                                            pricewotax = accounting.unformat($scope.cart[i]['hargatotal']) / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));

                                            var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                            if(taxonarray){
                                                taxonarray.total += service;
                                                taxonarray.tmptotal += service;
                                            } else {
                                                var tax = {};
                                                tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                tax.total = service;
                                                tax.tmptotal = service;
                                                taxes.push(tax);
                                            }
                                            var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                            if(taxonarray){
                                                taxonarray.total += vat;
                                                taxonarray.tmptotal += vat;
                                            } else {
                                                var tax = {};
                                                tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                tax.total = vat;
                                                tax.tmptotal = vat;
                                                taxes.push(tax);
                                            }
                                        }
                                    } else {
                                        vat        = accounting.unformat($scope.cart[i]['hargatotal']) / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value'])) * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                        pricetax   = vat;
                                        pricewotax = accounting.unformat($scope.cart[i]['hargatotal']) / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));

                                        var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                        if(taxonarray){
                                            taxonarray.total += vat;
                                            taxonarray.tmptotal += vat;
                                        } else {
                                            var tax = {};
                                            tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                            tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                            tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                            tax.total = vat;
                                            tax.tmptotal = vat;
                                            taxes.push(tax);
                                        }
                                    }

                                    tmphargatotalwotax += pricewotax;
                                    tmpvattotal += (vat + service);
                                    nasplet += service;

                                    //promotion
                                    var promo_tmpvat = 0, promo_vat = 0, promo_tmpservice = 0, promo_service = 0;
                                    if($scope.cart[i]['promotion']){
                                        for(var a = 0; a < $scope.cart[i]['promotion'].length;a++){
                                            if(temp_price < accounting.unformat($scope.cart[i]['promotion'][a]['afterpromotionprice'])) {
                                                var discountpromo = temp_price;
                                                promotionprice  += temp_price;
                                                var promo = temp_price;
                                                $scope.cart[i]['promotion'][a]['afterpromotionprice'] = accounting.formatMoney(discountpromo, "", $scope.precision, ",", ".");
                                            } else {
                                                var discountpromo = accounting.unformat($scope.cart[i]['promotion'][a]['afterpromotionprice']);
                                                promotionprice  += accounting.unformat($scope.cart[i]['promotion'][a]['afterpromotionprice']);
                                                var promo = accounting.unformat($scope.cart[i]['promotion'][a]['afterpromotionprice']);
                                            }
                                            temp_price = temp_price - discountpromo;
                                            var promo_valuewotax;
                                            if($scope.cart[i]['product_details']['tax2'] != null){
                                                if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'service'){
                                                    promo_tmpvat     = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));
                                                    promo_vat        = discountpromo - promo_tmpvat;
                                                    promo_tmpservice = promo_tmpvat / (1 + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                                    promo_service    = promo_tmpvat - promo_tmpservice;
                                                    promo_valuewotax = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']) + (parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value'])));

                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_service;
                                                        taxonarray.tmptotal -= promo_service;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                        tax.total = promo_service;
                                                        tax.tmptotal = promo_service;
                                                        taxes.push(tax);
                                                    }
                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_vat;
                                                        taxonarray.tmptotal -= promo_vat;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                        tax.total = promo_vat;
                                                        tax.tmptotal = promo_vat;
                                                        taxes.push(tax);
                                                    }
                                                    //nasplet    += promo_tmpservice;
                                                }else if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'luxury'){
                                                    promo_tmpvat     = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                                    promo_vat        = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                                    promo_service    = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']);
                                                    promo_valuewotax = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));

                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_service;
                                                        taxonarray.tmptotal -= promo_service;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                        tax.total = promo_service;
                                                        tax.tmptotal = promo_service;
                                                        taxes.push(tax);
                                                    }
                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_vat;
                                                        taxonarray.tmptotal -= promo_vat;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                        tax.total = promo_vat;
                                                        tax.tmptotal = promo_vat;
                                                        taxes.push(tax);
                                                    }
                                                }
                                            } else {
                                                promo_tmpvat     = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));
                                                promo_vat        = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                                promo_valuewotax = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));

                                                var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                if(taxonarray){
                                                    taxonarray.total -= promo_vat;
                                                    taxonarray.tmptotal -= promo_vat;
                                                } else {
                                                    var tax = {};
                                                    tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                    tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                    tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                    tax.total = promo_vat;
                                                    tax.tmptotal = promo_vat;
                                                    taxes.push(tax);
                                                }
                                            }
                                            tmphargatotalwotax -= promo_valuewotax;
                                            tmpvattotal -= (promo_vat + promo_service);
                                            nasplet -= promo_service;
                                        }
                                    }
                                    if($scope.cart[i]['coupon']){
                                        for(var b= 0; b < $scope.cart[i]['coupon'].length;b++){
                                            // console.log(accounting.unformat($scope.cart[i]['coupon'][b]['aftercouponprice']));
                                            if(temp_price < accounting.unformat($scope.cart[i]['coupon'][b]['aftercouponprice'])) {
                                                var discountpromo = temp_price;
                                                couponprice  += temp_price;
                                                var promo = temp_price;
                                                $scope.cart[i]['coupon'][b]['aftercouponprice'] = accounting.formatMoney(discountpromo, "", $scope.precision, ",", ".");
                                            } else {
                                                var discountpromo = accounting.unformat($scope.cart[i]['coupon'][b]['aftercouponprice']);
                                                couponprice  += accounting.unformat($scope.cart[i]['coupon'][b]['aftercouponprice']);
                                                var promo = accounting.unformat($scope.cart[i]['coupon'][b]['aftercouponprice']);
                                            }
                                            temp_price = temp_price - discountpromo;
                                            // console.log($scope.cart[i]['coupon'][b]['aftercouponprice'])
                                            var promo_valuewotax;
                                             if($scope.cart[i]['product_details']['tax2'] != null){
                                                if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'service'){
                                                    promo_tmpvat     = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));
                                                    promo_vat        = discountpromo - promo_tmpvat;
                                                    promo_tmpservice = promo_tmpvat / (1 + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                                    promo_service    = promo_tmpvat - promo_tmpservice;
                                                    promo_valuewotax = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']) + (parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value'])));

                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_service;
                                                        taxonarray.tmptotal -= promo_service;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                        tax.total = promo_service;
                                                        tax.tmptotal = promo_service;
                                                        taxes.push(tax);
                                                    }
                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_vat;
                                                        taxonarray.tmptotal -= promo_vat;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                        tax.total = promo_vat;
                                                        tax.tmptotal = promo_vat;
                                                        taxes.push(tax);
                                                    }
                                                    //nasplet    += promo_tmpservice;
                                                }else if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'luxury'){
                                                    promo_tmpvat     = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                                    promo_vat        = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                                    promo_service    = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']);
                                                    promo_valuewotax = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_service;
                                                        taxonarray.tmptotal -= promo_service;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                        tax.total = promo_service;
                                                        tax.tmptotal = promo_service;
                                                        taxes.push(tax);
                                                    }
                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_vat;
                                                        taxonarray.tmptotal -= promo_vat;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                        tax.total = promo_vat;
                                                        tax.tmptotal = promo_vat;
                                                        taxes.push(tax);
                                                    }
                                                }
                                            } else {
                                                promo_tmpvat     = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));
                                                promo_vat        = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                                promo_valuewotax = discountpromo / (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));

                                                var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                if(taxonarray){
                                                    taxonarray.total -= promo_vat;
                                                    taxonarray.tmptotal -= promo_vat;
                                                } else {
                                                    var tax = {};
                                                    tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                    tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                    tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                    tax.total = promo_vat;
                                                    tax.tmptotal = promo_vat;
                                                    taxes.push(tax);
                                                }
                                            }
                                            tmphargatotalwotax -= promo_valuewotax;
                                            tmpvattotal -= (promo_vat + promo_service);
                                            nasplet -= promo_service;
                                        }
                                    }
                                    // console.log(tmpvattotal);
                                } else {
                                    if($scope.cart[i]['product_details']['tax2'] != null){
                                        if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'service'){
                                            tmpservice     = accounting.unformat($scope.cart[i]['hargatotal']) * (1 + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                            service        = tmpservice - accounting.unformat($scope.cart[i]['hargatotal']);
                                            tmpvat         = tmpservice * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));
                                            vat            = tmpvat - tmpservice;
                                            pricetax       = accounting.unformat($scope.cart[i]['hargatotal']) * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']) + (parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value'])));

                                            var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                            if(taxonarray){
                                                taxonarray.total += service;
                                                taxonarray.tmptotal += service;
                                            } else {
                                                var tax = {};
                                                tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                tax.total = service;
                                                tax.tmptotal = service;
                                                taxes.push(tax);
                                            }
                                            var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                            if(taxonarray){
                                                taxonarray.total += vat;
                                                taxonarray.tmptotal += vat;
                                            } else {
                                                var tax = {};
                                                tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                tax.total = vat;
                                                tax.tmptotal = vat;
                                                taxes.push(tax);
                                            }
                                            //nasplet    += tmpservice;
                                        }else if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'luxury'){
                                            vat        = accounting.unformat($scope.cart[i]['hargatotal']) * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                            service    = accounting.unformat($scope.cart[i]['hargatotal']) * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']);
                                            pricetax   = accounting.unformat($scope.cart[i]['hargatotal']) * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));

                                            var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                            if(taxonarray){
                                                taxonarray.total += service;
                                                taxonarray.tmptotal += service;
                                            } else {
                                                var tax = {};
                                                tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                tax.total = service;
                                                tax.tmptotal = service;
                                                taxes.push(tax);
                                            }
                                            var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                            if(taxonarray){
                                                taxonarray.total += vat;
                                                taxonarray.tmptotal += vat;
                                            } else {
                                                var tax = {};
                                                tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                tax.total = vat;
                                                tax.tmptotal = vat;
                                                taxes.push(tax);
                                            }
                                        }
                                    } else {
                                        vat        = accounting.unformat($scope.cart[i]['hargatotal']) * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                        pricetax   = vat;

                                        var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                        if(taxonarray){
                                            taxonarray.total += vat;
                                            taxonarray.tmptotal += vat;
                                        } else {
                                            var tax = {};
                                            tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                            tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                            tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                            tax.total = vat;
                                            tax.tmptotal = vat;
                                            taxes.push(tax);
                                        }
                                    }

                                    tmphargatotaltax += pricetax;
                                    tmpvattotal += (vat + service);
                                    nasplet += service;
                                    taxpromo += pricetax;
                                    var promo_tmpvat = 0, promo_vat = 0, promo_tmpservice = 0, promo_service = 0;
                                    if($scope.cart[i]['promotion']){
                                        for(var a = 0; a < $scope.cart[i]['promotion'].length;a++){
                                            if(temp_price < accounting.unformat($scope.cart[i]['promotion'][a]['afterpromotionprice'])) {
                                                var discountpromo = temp_price;
                                                promotionprice  += temp_price;
                                                var promo = temp_price;
                                            } else {
                                                var discountpromo = accounting.unformat($scope.cart[i]['promotion'][a]['afterpromotionprice']);
                                                promotionprice  += accounting.unformat($scope.cart[i]['promotion'][a]['afterpromotionprice']);
                                                var promo = accounting.unformat($scope.cart[i]['promotion'][a]['afterpromotionprice']);
                                            }
                                            temp_price = temp_price - discountpromo;
                                            var promo_valuetax;
                                            if($scope.cart[i]['product_details']['tax2'] != null){
                                                if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'service'){
                                                    promo_tmpservice     = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                                    promo_service        = promo_tmpservice - discountpromo;
                                                    promo_tmpvat = promo_tmpservice * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));
                                                    promo_vat    = promo_tmpvat - promo_tmpservice;
                                                    promo_valuetax = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']) + (parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value'])));

                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_service;
                                                        taxonarray.tmptotal -= promo_service;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                        tax.total = promo_service;
                                                        tax.tmptotal = promo_service;
                                                        taxes.push(tax);
                                                    }
                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_vat;
                                                        taxonarray.tmptotal -= promo_vat;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                        tax.total = promo_vat;
                                                        tax.tmptotal = promo_vat;
                                                        taxes.push(tax);
                                                    }
                                                    //nasplet    += promo_tmpservice;
                                                }else if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'luxury'){
                                                    promo_tmpservice     = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                                    promo_service        = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                                    promo_service    = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']);
                                                    promo_valuetax = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));

                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_service;
                                                        taxonarray.tmptotal -= promo_service;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                        tax.total = promo_service;
                                                        tax.tmptotal = promo_service;
                                                        taxes.push(tax);
                                                    }
                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_vat;
                                                        taxonarray.tmptotal -= promo_vat;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                        tax.total = promo_vat;
                                                        tax.tmptotal = promo_vat;
                                                        taxes.push(tax);
                                                    }
                                                }
                                            } else {
                                                promo_tmpvat     = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));
                                                promo_vat        = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                                promo_valuewotax = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));

                                                var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                if(taxonarray){
                                                    taxonarray.total -= promo_vat;
                                                    taxonarray.tmptotal -= promo_vat;
                                                } else {
                                                    var tax = {};
                                                    tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                    tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                    tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                    tax.total = promo_vat;
                                                    tax.tmptotal = promo_vat;
                                                    taxes.push(tax);
                                                }
                                            }
                                            tmphargatotaltax -= promo_valuetax;
                                            tmpvattotal -= (promo_vat + promo_service);
                                            nasplet += promo_service;
                                        }
                                    }

                                    if($scope.cart[i]['coupon']){
                                        for(var b= 0; b < $scope.cart[i]['coupon'].length;b++){
                                            if(temp_price < accounting.unformat($scope.cart[i]['coupon'][b]['aftercouponprice'])) {
                                                var discountpromo = temp_price;
                                                couponprice  += temp_price;
                                                var promo = temp_price;
                                            } else {
                                                var discountpromo = accounting.unformat($scope.cart[i]['coupon'][b]['aftercouponprice']);
                                                couponprice  += accounting.unformat($scope.cart[i]['coupon'][b]['aftercouponprice']);
                                                var promo = accounting.unformat($scope.cart[i]['coupon'][b]['aftercouponprice']);
                                            }
                                            temp_price = temp_price - discountpromo;
                                            var promo_valuetax;
                                            if($scope.cart[i]['product_details']['tax2'] != null){
                                                if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'service'){
                                                    promo_tmpservice     = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                                    promo_service        = promo_tmpservice - discountpromo;
                                                    promo_tmpvat = promo_tmpservice * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));
                                                    promo_vat    = promo_tmpvat - promo_tmpservice;
                                                    promo_valuetax = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']) + (parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value'])));

                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_service;
                                                        taxonarray.tmptotal -= promo_service;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                        tax.total = promo_service;
                                                        tax.tmptotal = promo_service;
                                                        taxes.push(tax);
                                                    }
                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_vat;
                                                        taxonarray.tmptotal -= promo_vat;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                        tax.total = promo_vat;
                                                        tax.tmptotal = promo_vat;
                                                        taxes.push(tax);
                                                    }
                                                    //nasplet    += promo_tmpservice;
                                                }else if($scope.cart[i]['product_details']['tax2']['tax_type'] == 'luxury'){
                                                    promo_tmpservice     = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));
                                                    promo_service        = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                                    promo_service    = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']);
                                                    promo_valuetax = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']) + parseFloat($scope.cart[i]['product_details']['tax2']['tax_value']));

                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax2']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_service;
                                                        taxonarray.tmptotal -= promo_service;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax2']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax2']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax2']['tax_value'];
                                                        tax.total = promo_service;
                                                        tax.tmptotal = promo_service;
                                                        taxes.push(tax);
                                                    }
                                                    var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                    if(taxonarray){
                                                        taxonarray.total -= promo_vat;
                                                        taxonarray.tmptotal -= promo_vat;
                                                    } else {
                                                        var tax = {};
                                                        tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                        tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                        tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                        tax.total = promo_vat;
                                                        tax.tmptotal = promo_vat;
                                                        taxes.push(tax);
                                                    }
                                                }
                                            } else {
                                                promo_tmpvat     = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));
                                                promo_vat        = promo_tmpvat * parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']);
                                                promo_valuewotax = discountpromo * (1 + parseFloat($scope.cart[i]['product_details']['tax1']['tax_value']));

                                                var taxonarray = $filter('getById')(taxes, $scope.cart[i]['product_details']['tax1']['merchant_tax_id']);

                                                if(taxonarray){
                                                    taxonarray.total -= promo_vat;
                                                    taxonarray.tmptotal -= promo_vat;
                                                } else {
                                                    var tax = {};
                                                    tax.id = $scope.cart[i]['product_details']['tax1']['merchant_tax_id'];
                                                    tax.name = $scope.cart[i]['product_details']['tax1']['tax_name'];
                                                    tax.value = $scope.cart[i]['product_details']['tax1']['tax_value'];
                                                    tax.total = promo_vat;
                                                    tax.tmptotal = promo_vat;
                                                    taxes.push(tax);
                                                }
                                            }

                                            tmphargatotaltax -= promo_valuetax;
                                            tmpvattotal -= (promo_vat + promo_service);
                                            nasplet += promo_service;
                                        }
                                    }
                                }

                                promotionprice = promotionprice > 0 ? promotionprice : 0;
                                couponprice    = couponprice > 0 ? couponprice : 0;
                                tmphargatotal    += accounting.unformat($scope.cart[i]['hargatotal']) - promotionprice - couponprice;
                                // console.log(couponprice);
                                $scope.cart.subtotal   = accounting.formatMoney(tmphargatotal, "", $scope.precision, ",", ".");
                                $scope.tmpsubtotal     = $scope.cart.subtotal;
                                $scope.naspletan  = accounting.formatMoney(nasplet, "", $scope.precision, ",", ".");
                            }
                        }

                        var tmpcartsubtotalpromotion   = accounting.unformat($scope.cart.subtotal);
                        var temp_subtotal = tmpcartsubtotalpromotion;
                        var tmpvattotalbeforecartbased = tmpvattotal;


                        var cart_tax_factor = 0;
                        if($scope.vat_included == 'yes'){
                            if(tmphargatotalwotax !== 0){
                                cart_tax_factor = tmpvattotal / tmphargatotalwotax;
                            }
                        } else {
                            if (tmphargatotal !== 0){
                                cart_tax_factor = tmpvattotal / tmphargatotal;
                            }
                        }
                        //check cart based promo
                        var promotioncartbase          = 0;
                        var taxpromotioncartbase       = 0;
                        var taxcouponcartbase       = 0;
                        if($scope.cartpromotions){
                            $scope.applycartpromotion      = [];
                            $scope.tmpvallues = '';
                            var cart_promo_tmpvat, cart_promo_tmpvat_wo_tax, cart_promo_tmpvat_tax;
                            for(var j = 0; j < $scope.cartpromotions.length;j++){
                                if (tmpcartsubtotalpromotion >= accounting.unformat($scope.cartpromotions[j]['promotionrule']['rule_value'])){
                                    var promotion = $scope.cartpromotions[j]['promotionrule']['rule_type'] == 'cart_discount_by_percentage' ? $scope.cartpromotions[j]['promotionrule']['discount_value'] *  tmpcartsubtotalpromotion : accounting.unformat($scope.cartpromotions[j]['promotionrule']['discount_value']);

                                    if(temp_subtotal < promotion) {
                                        promotion = temp_subtotal;
                                    }

                                    promotioncartbase  += promotion;

                                    temp_subtotal = temp_subtotal - promotion;

                                    $scope.tmpvallues  = promotion;
                                    $scope.applycartpromotion.push(angular.copy($scope.cartpromotions[j]));
                                    var idxapply = $scope.applycartpromotion.length -1;
                                    if($scope.applycartpromotion[idxapply]['promotionrule']['rule_type'] == 'cart_discount_by_percentage'){
                                        $scope.applycartpromotion[idxapply]['promotionrule']['discount']       = $scope.applycartpromotion[idxapply]['promotionrule']['discount_value'] * 100 +' %';
                                        $scope.applycartpromotion[idxapply]['promotionrule']['discount_value'] = '- '+ accounting.formatMoney($scope.tmpvallues, "", $scope.precision, ",", ".");
                                    }else{
                                        $scope.applycartpromotion[idxapply]['promotionrule']['discount_value'] = '- '+ accounting.formatMoney($scope.applycartpromotion[idxapply]['promotionrule']['discount_value'], "", $scope.precision, ",", ".");
                                    }
                                    if($scope.vat_included == 'yes'){
                                        cart_promo_tmpvat_wo_tax = promotion / (1 + cart_tax_factor);
                                        cart_promo_tmpvat = promotion - cart_promo_tmpvat_wo_tax;
                                        taxpromotioncartbase += cart_promo_tmpvat;
                                        for(var xx = 0; xx < taxes.length; xx++){
                                            // console.log(taxes[xx].total);

                                            var tax_reduction = (taxes[xx].tmptotal / tmpvattotalbeforecartbased) * cart_promo_tmpvat;
                                            taxes[xx].total -= tax_reduction;
                                            // console.log(cart_promo_tmpvat);
                                        }
                                    } else {
                                        cart_promo_tmpvat_tax = promotion * (1 + cart_tax_factor);
                                        // cart_promo_tmpvat = cart_promo_tmpvat_tax - promotion;
                                        cart_promo_tmpvat = promotion / tmphargatotal * tmpvattotalbeforecartbased;
                                        taxpromotioncartbase += cart_promo_tmpvat;
                                        for(var xx = 0; xx < taxes.length; xx++){
                                            var tax_reduction = (taxes[xx].tmptotal / tmpvattotalbeforecartbased) * cart_promo_tmpvat;
                                            taxes[xx].total -= tax_reduction;
                                        }
                                    }
                                }
                            }
                            tmpvattotal -= taxpromotioncartbase;
                        };
                        //check coupon
                        var couponcartbase          = 0;
                        if($scope.cartcoupon){
                            $scope.applycartcoupon      = [];
                            $scope.tmpvallues = '';
                            var cart_promo_tmpvat, cart_promo_tmpvat_wo_tax, cart_promo_tmpvat_tax;
                            for(var m = 0; m < $scope.cartcoupon.length; m++){
                                var coupon = $scope.cartcoupon[m]['issuedcoupon']['rule_type'] == 'cart_discount_by_percentage' ?  $scope.cartcoupon[m]['issuedcoupon']['discount_value'] *  tmpcartsubtotalpromotion : accounting.unformat($scope.cartcoupon[m]['issuedcoupon']['discount_value']);

                                if(temp_subtotal < coupon) {
                                    coupon = temp_subtotal;
                                }

                                couponcartbase += coupon;

                                temp_subtotal = temp_subtotal - coupon;

                                $scope.tmpvalluescoupon  = coupon;
                                $scope.applycartcoupon.push(angular.copy($scope.cartcoupon[m]));
                                var idxapply = $scope.applycartcoupon.length -1;
                                if($scope.applycartcoupon[idxapply]['issuedcoupon']['rule_type'] == 'cart_discount_by_percentage'){
                                    $scope.applycartcoupon[idxapply]['issuedcoupon']['discount']       = $scope.applycartcoupon[idxapply]['issuedcoupon']['discount_value'] * 100 +' %';
                                    $scope.applycartcoupon[idxapply]['issuedcoupon']['discount_value'] = '- '+ accounting.formatMoney($scope.tmpvalluescoupon, "", $scope.precision, ",", ".");
                                }else{
                                    $scope.applycartcoupon[idxapply]['issuedcoupon']['discount_value'] = '- '+ accounting.formatMoney($scope.applycartcoupon[idxapply]['issuedcoupon']['discount_value'], "", $scope.precision, ",", ".");
                                }
                                if($scope.vat_included == 'yes'){
                                    cart_promo_tmpvat_wo_tax = coupon / (1 + cart_tax_factor);
                                    cart_promo_tmpvat = coupon - cart_promo_tmpvat_wo_tax;
                                    taxcouponcartbase += cart_promo_tmpvat;
                                    for(var xx = 0; xx < taxes.length; xx++){
                                        var tax_reduction = (taxes[xx].tmptotal / tmpvattotalbeforecartbased) * cart_promo_tmpvat;
                                        taxes[xx].total -= tax_reduction;
                                    }
                                } else {
                                    cart_promo_tmpvat_tax = coupon * (1 + cart_tax_factor);
                                    // cart_promo_tmpvat = cart_promo_tmpvat_tax - coupon;
                                    cart_promo_tmpvat = coupon / tmphargatotal * tmpvattotalbeforecartbased;
                                    taxcouponcartbase += cart_promo_tmpvat;
                                    for(var xx = 0; xx < taxes.length; xx++){
                                        var tax_reduction = (taxes[xx].tmptotal / tmpvattotalbeforecartbased) * cart_promo_tmpvat;
                                        taxes[xx].total -= tax_reduction;
                                    }
                                }
                            }
                            tmpvattotal -= taxcouponcartbase;
                        }

                        if($scope.vat_included == 'yes'){
                             $scope.cart.totalpay = accounting.formatMoney(tmpcartsubtotalpromotion - promotioncartbase - couponcartbase, "", $scope.precision, ",", ".");
                             $scope.cart.subtotal = $scope.cart.totalpay;
                             $scope.cart.vat      = accounting.formatMoney(tmpvattotal, "", 2, ",", ".");
                             $scope.taxes = taxes;
                        } else {
                             $scope.cart.totalpay = accounting.formatMoney(tmpcartsubtotalpromotion - promotioncartbase - couponcartbase + parseInt(tmpvattotal), "", $scope.precision, ",", ".");
                             $scope.cart.subtotal = accounting.formatMoney(tmpcartsubtotalpromotion - promotioncartbase - couponcartbase, "", $scope.precision, ",", ".");
                             $scope.cart.vat      = accounting.formatMoney(tmpvattotal, "", 2, ",", ".");
                             $scope.taxes = taxes;
                        }
                        console.log(taxes);
                    }
                };
             //insert to cart
             $scope.inserttocartFn = function(bool){
                    if($scope.productmodal){
                        if(!bool)$scope.customerdispaly($scope.productmodal['product_name'], accounting.formatMoney($scope.productmodal['price'], "", $scope.precision, ",", "."));
                        //set activity when product add to cart
                        var user_id = $scope.cart.user_id ? $scope.cart.user_id : 0;
                        $scope.activity('activity-add-product',{customer_id : user_id ,product_id : $scope.productmodal['product_id'] });

                        $scope.searchproduct    = '';
                        $scope.adddelenadis($scope.productmodal['product_id'],'add');
                        if($scope.checkcart($scope.productmodal)){
                            $scope.cart.push({
                                cart_id                : angular.copy($scope.productmodal['cart_id']),
                                product_name           : angular.copy($scope.productmodal['product_name']),
                                variants               : angular.copy($scope.variantstmp),
                                promotion              : angular.copy($scope.datapromotion),
                                attributes             : angular.copy($scope.productmodal['attributes']),
                                coupon                 : angular.copy($scope.productmodal['coupon_for_this_product']),
                                cartsummary            : angular.copy($scope.productmodal['cartsummary']),
                                qty                    : $scope.productmodal['qty'] ? angular.copy($scope.productmodal['qty']) : 1,
                                price                  : angular.copy($scope.productmodal['price']),
                                idx                    : angular.copy($scope.productmodal['idx']),
                                upc_code               : angular.copy($scope.productmodal['upc_code']),
                                product_code           : angular.copy($scope.productmodal['product_code']),
                                product_id             : angular.copy($scope.productmodal['product_id']),
                                ispromo                : $scope.datapromotion.length ? true : false,
                                afterpromotionprice    : angular.copy($scope.productmodal['afterpromotionprice']),
                                attribute_id1          : angular.copy($scope.productmodal['attribute_id1']),
                                attribute_id2          : angular.copy($scope.productmodal['attribute_id2']),
                                attribute_id3          : angular.copy($scope.productmodal['attribute_id3']),
                                attribute_id4          : angular.copy($scope.productmodal['attribute_id4']),
                                attribute_id5          : angular.copy($scope.productmodal['attribute_id5']),
                                category_id1           : angular.copy($scope.productmodal['category_id1']),
                                category_id2           : angular.copy($scope.productmodal['category_id2']),
                                category_id3           : angular.copy($scope.productmodal['category_id3']),
                                category_id4           : angular.copy($scope.productmodal['category_id4']),
                                category_id5           : angular.copy($scope.productmodal['category_id5']),
                                product_details        : angular.copy($scope.productmodal),
                                hargatotal             : 0,
                                currency               : $scope.currency
                            });
                            $timeout(function(){
                                $location.hash('bottom');
                                $anchorScroll();
                            },500);
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
             $scope.checkcart = function(product){
                    var check = true;
                    for(var i = 0; i < $scope.cart.length; i++){
                        if($scope.cart[i]['variants'] == '' || $scope.cart[i]['variants'] == undefined || product['variants'].length == 1){
                            if($scope.cart[i]['product_id'] == product['product_id']){
                                $scope.cart[i]['qty']++;
                                if($scope.cart[i]['promotion']){
                                    for(var a = 0;a < $scope.cart[i]['promotion'].length;a++){
                                        $scope.cart[i]['promotion'][a]['afterpromotionprice'] = accounting.formatMoney((accounting.unformat($scope.cart[i]['promotion'][a]['afterpromotionprice']) /  ($scope.cart[i]['qty'] -1))* $scope.cart[i]['qty'], "", $scope.precision, ",", ".");
                                    }
                                }
                                check = false;
                                break;
                            }
                        }else{
                            if($scope.cart[i]['variants']['product_variant_id'] == $scope.variantstmp['product_variant_id']){
                                $scope.cart[i]['qty']++;
                                if($scope.cart[i]['promotion']){
                                    for(var a = 0;a < $scope.cart[i]['promotion'].length;a++){
                                        $scope.cart[i]['promotion'][a]['afterpromotionprice'] =  accounting.formatMoney((accounting.unformat($scope.cart[i]['promotion'][a]['afterpromotionprice']) / ($scope.cart[i]['qty'] -1)) * $scope.cart[i]['qty'], "", $scope.precision, ",", ".");
                                    }
                                }
                                check = false;
                                break;
                            }
                        }
                    }
                    return check;
                };
             //delete cart && new cart
             $scope.newdeletecartFn = function(act){

                 if($scope.cart.cart_id){
                     serviceAjax.posDataToServer('/pos/deletecart',{cart_id  : $scope.cart.cart_id}).then(function(response){
                         if(response.code == 0){

                         }
                     });
                 }

                 $scope.successscant         = false;
                 $scope.productidenabled     = [];
                 $scope.cart                 = [];
                 $scope.searchproduct        = '';
                 $scope.applycartpromotion   = [];
                 $scope.applycartcoupon      = [];
                 $scope.tmpsubtotal          = '';
                 $scope.getproduct();
                 if(act) $scope.getguest();
                 $scope.customerdispaly('Welcome to ',$scope.datauser['merchant']['name'].substr(0,20));
                 //set activity when clear cart
                 var user_id = $scope.cart.user_id ? $scope.cart.user_id : 0;
                 $scope.activity('activity-clear',{customer_id : user_id  });
             };
              //when user click checkout
             $scope.showCh = function(){
                    //set activity when cart checkout
                    $scope.getterminal();
                    var user_id = $scope.cart.user_id ? $scope.cart.user_id : 0;
                    $scope.activity('activity-checkout',{customer_id : user_id});
              };
             //checkout
             $scope.checkoutFn = function(act,term){

                    $scope.cardfile  = true;
                    switch(act){
                        case 't':
                            $scope.action  = 'cash';
                            $scope.cheader = $scope.language.pembayarantunai;
                            $scope.isvirtual = true;
                            //customer display
                            $scope.customerdispaly('TOTAL',$scope.cart.totalpay);
                            break;
                        case 'k':
                            $scope.cardfile  = false;
                            $scope.headrcard = term;

                            //terminal 1
                            $scope.action = 'card';
                            $scope.cheader = $scope.language.pembayarankartu;
                            $scope.hasaccepted = false;
                            //case success
                            serviceAjax.posDataToServer('/pos/cardpayment ',{amount : accounting.unformat($scope.cart.totalpay)}).then(function(response){
                                if(response.code == 0){
                                    $scope.savetransactions();
                                    $scope.hasaccepted = true;
                                }else{
                                    $scope.cheader  = $scope.language.transaksigagal;
                                    $scope.cardfile = true;
                                    $scope.holdbtn  = true;
                                }
                                $scope.holdbtn = false;
                                //wait driver until 50 seconds
                                $timeout(function(){
                                    $scope.holdbtn = true;
                                 },50000);
                             });

                            break;
                        case 'd' :
                            //done
                            $scope.gotomain();
                            $scope.newdeletecartFn(true);
                            break;
                        case 'c' :
                            //continue
                            $scope.savetransactions();
                            break;
                    }
                };
             //save transaction
             $scope.savetransactions = function(){
                    $scope.lock = true;
                    $scope.sendcart = {
                        total_item     : accounting.unformat($scope.cart.totalitem),
                        subtotal       : accounting.unformat($scope.cart.subtotal),
                        vat            : accounting.unformat($scope.cart.vat),
                        total_to_pay   : accounting.unformat($scope.cart.totalpay),
                        tendered       : accounting.unformat($scope.cart.amount),
                        change         : accounting.unformat($scope.cart.change),
                        merchant_id    : $scope.datauser['merchant']['merchant_id'],
                        customer_id    : $scope.cart.user_id,
                        guest          : $scope.guests,
                        cashier_id     : $scope.datauser['user']['user_id'],
                        payment_method : $scope.action,
                        cart           : $scope.cart,
                        cart_promotion : $scope.applycartpromotion,
                        cart_coupon    : $scope.applycartcoupon,
                        taxes          : $scope.taxes,
                        currency       : $scope.currency
                    };
                    serviceAjax.posDataToServer('/pos/savetransaction',$scope.sendcart).then(function(response){
                        if(response.code == 0){
                            $scope.action = 'done';
                            $scope.cheader = $scope.language.transaksiberhasil;
                            $scope.transaction_id = response.data.transaction_id;
                            //customer display
                            $scope.customerdispaly('Change '+$scope.cart.change,'Thank You');
                            //print ticket
                            $scope.ticketprint();
                        }else{
                            //do something
                            $scope.cheader = $scope.language.transaksigagal;
                        }
                         $scope.lock = false;
                    });
                };
             //sendticket
             $scope.sendticket = function(){
                    if($scope.transaction_id){
                        serviceAjax.posDataToServer('/pos/sendticketemail',{transaction_id : $scope.transaction_id}).then(function(response){
                            if(response.code == 0){
                                  $scope.messagesendemail = $scope.language.emailberhasil;
                            }else{
                                  $scope.messagesendemail = $scope.language.emailgagal;
                            }
                        });
                    }else{
                        $scope.messagesendemail = $scope.language.emailgagal;
                    }
                    $timeout(function(){
                        $scope.messagesendemail = '';
                    }, 3000);
             };
             //Ticket Print
             $scope.ticketprint = function(){
                    if($scope.transaction_id){
                        serviceAjax.posDataToServer('/pos/ticketprint',{transaction_id : $scope.transaction_id}).then(function(response){
                            if(response.code == 0){

                            }else{
                                //do something
                            }
                        });
                    }else{
                        //do something
                    }
                };
             //watch amount on page cash
             $scope.$watch("cart.amount", function(newvalue,oldvalue){
                    if(newvalue) {
                        var check     = newvalue.split('.');
                        var precision = $scope.precision == 0 ? 0 : (check.length > 1 ? 2 : 0);
                        oldvalue = accounting.unformat(oldvalue);
                        newvalue = accounting.unformat(newvalue);
                        if(oldvalue != newvalue){
                            $scope.changetf = false;
                            $scope.cart['amount'] =   check.length > 1 ? $scope.cart['amount'] : accounting.formatMoney(newvalue, "", precision, ",", ".");
                            $scope.change = accounting.unformat($scope.cart['amount']) - accounting.unformat($scope.cart['totalpay']);
                            $scope.changetf = $scope.change >= 0 ? true:false;
                            $scope.cart['change'] =  $scope.change > 0 ?   accounting.formatMoney($scope.change, "", $scope.precision, ",", ".") : 0;
                        }
                    }
                });
             //go to main
             $scope.gotomain = function(){
                    $scope.resetpayment();
                    $scope.cheader = $scope.language.pilihcarapembayaran;
                    $scope.action  = 'main';
                };
             //reset payment
             $scope.resetpayment  = function(){
                    $scope.change         = 0;
                    $scope.cart['amount'] = '';
                    $scope.cart['change'] = '';
                    $scope.changetf       = false;
                };
             //scan product only run on linux
             ($scope.scanproduct = function(){
                    serviceAjax.posDataToServer('/pos/scanbarcode').then(function(response){
                        if(response.code == 0){
                            $scope.productmodal      = [];
                            $scope.productmodal      = response['data'];
                            $scope.showprice         = false;
                            $scope.hiddenbtn         = false;
                            var check = 1;
                            $scope.variantstmp ={};
                            if($scope.productmodal['variants'].length > 1){
                                angular.element('#myModal').modal('show');

                            }else if($scope.productmodal['variants'].length == 1){
                                $scope.productmodal['price']    =  $scope.productmodal['variants'][0]['price'];
                                $scope.productmodal['upc_code'] =  $scope.productmodal['variants'][0]['upc'];

                                $scope.variantstmp['product_variant_id']    =  $scope.productmodal['variants'][0]['product_variant_id'];
                                $scope.variantstmp['value1']                =  $scope.productmodal['variants'][0]['attribute_value1'] != null ? $scope.productmodal['variants'][0]['attribute_value1']['value'] :'';
                                $scope.variantstmp['value2']                =  $scope.productmodal['variants'][0]['attribute_value2'] != null ? $scope.productmodal['variants'][0]['attribute_value2']['value'] :'';
                                $scope.variantstmp['value3']                =  $scope.productmodal['variants'][0]['attribute_value3'] != null ? $scope.productmodal['variants'][0]['attribute_value3']['value'] :'';
                                $scope.variantstmp['value4']                =  $scope.productmodal['variants'][0]['attribute_value4'] != null ? $scope.productmodal['variants'][0]['attribute_value4']['value'] :'';
                                $scope.variantstmp['value5']                =  $scope.productmodal['variants'][0]['attribute_value5'] != null ? $scope.productmodal['variants'][0]['attribute_value5']['value'] :'';
                                check = null;
                            }

                            $scope.getpromotionScanproduct($scope.productmodal,false,check);

                            $scope.scanproduct();
                        }else if(response.code == 13){
                            if(response.message != 'Scanner not found'){
                                if(response.message=='You have to login to view this page.'){

                                }else{
                                    angular.element("#ProductNotFound").modal();
                                    $scope.scanproduct();
                                }
                            }else if(response.message == 'Scanner not found'){
                                //danger harcoded check type by message
                                if(response.message != 'Scanner not found'){
                                    angular.element("#ProductNotFound").modal();
                                }else if(response.message == 'Scanner not found'){
                                    //wait 2 second and 4 second after 10 request if scanner not found
                                    $scope.counter = $scope.resetback ? 4 : 2;
                                    $scope.timer   = $scope.counter *1000;
                                    $scope.counttimeout = $scope.counttimeout || 0;
                                    $timeout(function(){
                                        $scope.scanproduct();
                                        $scope.counttimeout++;
                                        if($scope.counttimeout == 10){
                                            $scope.resetback = true;
                                        }
                                    },$scope.timer);
                                }
                            }
                           /* $scope.cancelRequestService();
                            $scope.scanproduct();*/
                        }else if(response.message == 'shell error'){
                            // Delay 2 seconds
                            setTimeout(function() {
                                $scope.scanproduct();
                            }, 2000);
                        }
                    });
                })();
             //binding keypad scant
             $scope.keypadscantFn = function(idx){
                    if(idx == 'c'){
                        $scope.manualscancart    = '';
                        $scope.isvirtualscancart = false;
                    }else if(idx =='d'){
                        $scope.cart = [];
                        $scope.tmpsubtotal = '';
                        $scope.scancartFn(true);
                    }else if(idx == 'r'){
                        $scope.manualscancart =  $scope.manualscancart != '' ? $scope.manualscancart.substring(0, $scope.manualscancart.length-1) : '';
                    }else{
                        $scope.manualscancart =  $scope.manualscancart+idx;
                    }
                };
             //binding keypad cash
             $scope.keypadFn = function(idx){
                    if(idx == 'c'){
                        $scope.cart.amount = '';
                        $scope.cart.change = '';
                    }else if(idx =='d'){
                        $scope.virtualFn(false);
                    }else if(idx == 'r'){
                        $scope.cart.amount = $scope.cart.amount.length == 1 ? 0 : $scope.cart.amount != '' ? $scope.cart.amount.substring(0, $scope.cart.amount.length-1) : 0;
                    }else{
                        $scope.cart.amount =  $scope.cart.amount == 0 ? idx : $scope.cart.amount+idx;
                    }
                };
             //binding keypad qty
             $scope.keypaqtydFn = function(idx){
                    if(idx == 'c'){
                        $scope.cart[$scope.indexactiveqty]['qty'] = 0;
                    }else if(idx =='d'){
                        $scope.virtualqtyFn(false);
                    }else if(idx == 'r'){
                        $scope.cart[$scope.indexactiveqty]['qty'] = $scope.cart[$scope.indexactiveqty]['qty'].length == 1 ? 0 :$scope.cart[$scope.indexactiveqty]['qty'] != '' ? $scope.cart[$scope.indexactiveqty]['qty'].substring(0, $scope.cart[$scope.indexactiveqty]['qty'].length-1) : 0;
                    }else{
                        if($scope.isqty) {
                            //overwrite
                            $scope.cart[$scope.indexactiveqty]['qty'] = '';
                            $scope.isqty = false;
                        }
                        $scope.cart[$scope.indexactiveqty]['qty'] = $scope.cart[$scope.indexactiveqty]['qty'] == 0 ? idx : $scope.cart[$scope.indexactiveqty]['qty']+idx;
                        $scope.watchqtypromotion($scope.indexactiveqty);
                    }
                    $scope.countcart();
                };
             //show virtual
             $scope.virtualFn = function(bool){
                    $scope.isvirtual = bool;
                };
             //show virtual qty
             $scope.virtualqtyFn = function(bool,idx){
                    $scope.isvirtualqty = bool;
                    if(!bool) {
                        $scope.cart[$scope.indexactiveqty]['qty'] = $scope.cart[$scope.indexactiveqty]['qty'] == 0 ? 1 : $scope.cart[$scope.indexactiveqty]['qty'];
                    }
                    $scope.indexactiveqty = idx;
                    $scope.isqty  = true;
                    $scope.countcart();
                };
             //show virtual scant cart manual
             $scope.virtualscancartFn = function(bool){
                    $scope.isvirtualscancart = bool;
                    $scope.btnsearch = true;
                };
             //customer display
             $scope.customerdispaly = function(line1,line2){
                    $scope.datadisplay.line1 = line1.substr(0,20);
                    $scope.datadisplay.line2 = line2;
                    serviceAjax.posDataToServer('/pos/customerdisplay',$scope.datadisplay).then(function(response){
                        if(response.code == 0){
                             //do something
                        }else {
                            //do something
                        }
                    });
                };
             //init customer display
             $scope.customerdispaly('Welcome to ',$scope.datauser['merchant']['name'].substr(0,20));
             //scan cart automatic and manually
             $scope.scancartFn = function(bool){
                   $scope.cancelRequestService();
                   $scope.getcartpromotion();
                    $scope.cartcoupon = [];
                    var data = {
                        barcode : bool ?  $scope.manualscancart : ''
                    };
                     serviceAjax.posDataToServer('/pos/scancart',data).then(function(response){
                            if(response.code == 0 ){
                                var name = response.data.cart.users.user_firstname == null ? response.data.cart.users.user_email : response.data.cart.users.user_firstname+' '+response.data.cart.users.user_lastname;
                                $scope.successscant = true;
                                $scope.guests       = name;
                                $scope.cart.user_id = response.data.cart.users.user_id;
                                $scope.cart.cart_id = response.data.cart.cart_id;
                                //cart coupon
                                $scope.cartcoupon =  response.data.cartsummary['used_cart_coupons'];
                                for(var i = 0; i < response.data.cartdetails.length; i++){
                                    $scope.productmodal                = response.data.cartdetails[i]['product'];
                                    $scope.productmodal['qty']         = response.data.cartdetails[i]['quantity'];
                                    $scope.productmodal['attributes']  = response.data.cartdetails[i]['attributes'];
                                    $scope.productmodal['cartsummary'] = response.data.cartsummary;
                                    $scope.variantstmp                 = response.data.cartdetails[i]['variant'];
                                    $scope.productmodal['variants']    = response.data.cartdetails[i]['variant'];
                                    $scope.productmodal['tax1'] = response.data.cartdetails[i]['tax1'];
                                    $scope.productmodal['tax2'] = response.data.cartdetails[i]['tax2'];
                                    $scope.productmodal['idx']         = i;

                                    //promotion
                                    $scope.datapromotion  = response.data.cartdetails[i]['promo_for_this_product'];
                                    var price             = response.data.cartdetails[i]['variant']['price'];
                                    var discount    = 0;
                                    var tmpdiscount = 0;
                                    var tmpprice = price * response.data.cartdetails[i]['quantity'];
                                    if($scope.datapromotion) {
                                        for(var a =0; a < $scope.datapromotion.length; a++){
                                            if($scope.datapromotion[a]) {
                                                $scope.datapromotion[a]['oridiscount_value'] = $scope.datapromotion[a]['discount_value'];
                                                $scope.datapromotion[a]['discount_value']    = $scope.datapromotion[a]['rule_type'] == 'product_discount_by_percentage' ? $scope.datapromotion[a]['discount_value'] * 100 + ' %' : accounting.formatMoney($scope.datapromotion[a]['discount_value'], "", $scope.precision, ",", ".");

                                                if($scope.datapromotion[a]['rule_type'] == 'product_discount_by_percentage'){
                                                    tmpdiscount = $scope.datapromotion[a]['oridiscount_value'] * (price * response.data.cartdetails[i]['quantity']);
                                                }else if($scope.datapromotion[a]['rule_type'] == 'product_discount_by_value'){
                                                    tmpdiscount = accounting.unformat($scope.datapromotion[a]['discount_value']) * response.data.cartdetails[i]['quantity'];
                                                }else if($scope.datapromotion[a]['rule_type'] == 'new_product_price'){
                                                    tmpdiscount = (price * response.data.cartdetails[i]['quantity']) - (accounting.unformat($scope.datapromotion[a]['discount_value']) * response.data.cartdetails[i]['quantity']);
                                                }
                                                if (tmpprice < tmpdiscount) {
                                                    tmpdiscount = tmpprice;
                                                }

                                                tmpprice = tmpprice - tmpdiscount;
                                                //  tmpdiscount = $scope.datapromotion[a]['rule_type'] == 'product_discount_by_percentage' ?  $scope.datapromotion[a]['oridiscount_value'] * (price * response.data.cartdetails[i]['quantity']) : accounting.unformat($scope.datapromotion[a]['promotion_detail']['discount_value']) * response.data.cartdetails[i]['quantity'];
                                                $scope.datapromotion[a]['afterpromotionprice']    = accounting.formatMoney(tmpdiscount, "", $scope.precision, ",", ".");
                                                $scope.datapromotion[a]['tmpafterpromotionprice'] = tmpdiscount / response.data.cartdetails[i]['quantity'];
                                                // tmpdiscount = tmpdiscount > 0 ? tmpdiscount : 0;
                                                discount += tmpdiscount;
                                            }
                                        }
                                        var discounts = price - discount;
                                        $scope.productmodal.afterpromotionprice =  discounts < 0 ?  0 :accounting.formatMoney(discounts, "", $scope.precision, ",", ".");
                                        $scope.productmodal.price =  accounting.formatMoney(price, "", $scope.precision, ",", ".");
                                    }else{
                                        $scope.productmodal.price    = accounting.formatMoney(price, "", $scope.precision, ",", ".");
                                        $scope.productmodal.afterpromotionprice = 0;
                                    }

                                    //coupon
                                    $scope.productmodal['coupon_for_this_product']  = response.data.cartdetails[i]['coupon_for_this_product'];
                                    if($scope.productmodal['coupon_for_this_product']){
                                        for(var b =0;b < $scope.productmodal['coupon_for_this_product'].length; b++){
                                            $scope.productmodal['coupon_for_this_product'][b]['oridiscount_value'] = $scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value'];
                                            $scope.productmodal['coupon_for_this_product'][b]['discount_value']    = $scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['rule_type'] == 'product_discount_by_percentage' ? $scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value'] * 100 + ' %' : accounting.formatMoney($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value'], "", $scope.precision, ",", ".");

                                            if($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['rule_type'] == 'product_discount_by_percentage'){
                                                tmpdiscount = $scope.productmodal['coupon_for_this_product'][b]['oridiscount_value'] * price;
                                            }else if($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['rule_type'] == 'product_discount_by_value'){
                                                tmpdiscount = accounting.unformat($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value']);
                                            }else if($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['rule_type'] == 'new_product_price'){
                                                tmpdiscount = price - accounting.unformat($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value']);
                                            }else{
                                                tmpdiscount = 0;
                                            }
                                            if (tmpprice < tmpdiscount) {
                                                tmpdiscount = tmpprice;
                                            }

                                            tmpprice = tmpprice - tmpdiscount;
                                            //  tmpdiscount = $scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['rule_type'] == 'product_discount_by_percentage' ?  $scope.productmodal['coupon_for_this_product'][b]['oridiscount_value'] * price : accounting.unformat($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value']);
                                            $scope.productmodal['coupon_for_this_product'][b]['aftercouponprice']    = accounting.formatMoney(tmpdiscount, "", $scope.precision, ",", ".");
                                            $scope.productmodal['coupon_for_this_product'][b]['tmpafterpromotionprice'] = tmpdiscount;
                                            // tmpdiscount = tmpdiscount > 0 ? tmpdiscount : 0;
                                            discount += tmpdiscount;
                                        }
                                    }
                                    $scope.productmodal['cart_id']     = response.data.cart.cart_id;
                                    $scope.inserttocartFn(true);
                                }

                                angular.element("#modalscancart").modal('hide');
                                if(bool)  $scope.virtualFn(false);
                                $scope.customerdispaly('Welcome',name.substr(0,20));
                                $scope.scanproduct();
                                $scope.errorscancart = '';
                                $scope.manualscancart = '';
                            }else if(response.code == 13 ){
                                 //do something when error
                                $scope.errorscancart  = $scope.language.errorscancart;
                                $scope.manualscancart = '';

                                if(response.message == 'You have to login to view this page.'){
                                    $scope.logoutfn();
                                }

                                if(response.message == 'Scanner not found'){
                                    $scope.errorscancart  = $scope.language.scannertidakditemukan;
                                }

                                if(response.message != 'Scanner not found') $scope.scancartFn();
                            } else{
                                //do something when error
                                $scope.errorscancart  = $scope.language.errorscancart;
                                $scope.manualscancart = '';
                                $scope.scancartFn();
                            }
                    });
                };
            //check if cart.status == 'cashier'
            ($scope.getCart = function(){
                serviceAjax.getDataFromServer('/pos/getcartcashier').then(function(response) {

                    if (response.code == 0) {
                         var name = response.data.cart.users.user_firstname == null ? response.data.cart.users.user_email : response.data.cart.users.user_firstname+' '+response.data.cart.users.user_lastname;
                $scope.successscant = true;
                $scope.guests       = name;
                $scope.cart.user_id = response.data.cart.users.user_id;
                $scope.cart.cart_id = response.data.cart.cart_id;
                //cart coupon
                $scope.cartcoupon =  response.data.cartsummary['used_cart_coupons'];

                for(var i = 0; i < response.data.cartdetails.length; i++){
                    $scope.productmodal                = response.data.cartdetails[i]['product'];
                    $scope.productmodal['qty']         = response.data.cartdetails[i]['quantity'];
                    $scope.productmodal['attributes']  = response.data.cartdetails[i]['attributes'];
                    $scope.productmodal['cartsummary'] = response.data.cartsummary;
                    $scope.variantstmp                 = response.data.cartdetails[i]['variant'];
                    $scope.productmodal['variants']    = response.data.cartdetails[i]['variant'];
                    $scope.productmodal['tax1'] = response.data.cartdetails[i]['tax1'];
                    $scope.productmodal['tax2'] = response.data.cartdetails[i]['tax2'];
                    $scope.productmodal['idx']         = i;

                    //promotion
                    $scope.datapromotion  = response.data.cartdetails[i]['promo_for_this_product'];
                    var price             = response.data.cartdetails[i]['variant']['price'];
                    var discount    = 0;
                    var tmpdiscount = 0;
                    var tmpprice = price * response.data.cartdetails[i]['quantity'];
                     console.log($scope.datapromotion);
                    if($scope.datapromotion) {
                        for(var a =0; a < $scope.datapromotion.length; a++){
                            if($scope.datapromotion[a]){
                                $scope.datapromotion[a]['oridiscount_value'] = $scope.datapromotion[a]['discount_value'];
                                $scope.datapromotion[a]['discount_value']    = $scope.datapromotion[a]['rule_type'] == 'product_discount_by_percentage' ? $scope.datapromotion[a]['discount_value'] * 100 + ' %' : accounting.formatMoney($scope.datapromotion[a]['discount_value'], "", $scope.precision, ",", ".");

                                if($scope.datapromotion[a]['rule_type'] == 'product_discount_by_percentage'){
                                    tmpdiscount = $scope.datapromotion[a]['oridiscount_value'] * (price * response.data.cartdetails[i]['quantity']);
                                }else if($scope.datapromotion[a]['rule_type'] == 'product_discount_by_value'){
                                    tmpdiscount = accounting.unformat($scope.datapromotion[a]['discount_value']) * response.data.cartdetails[i]['quantity'];
                                }else if($scope.datapromotion[a]['rule_type'] == 'new_product_price'){
                                    tmpdiscount = (price * response.data.cartdetails[i]['quantity']) - (accounting.unformat($scope.datapromotion[a]['discount_value']) * response.data.cartdetails[i]['quantity']);
                                }
                                if (tmpprice < tmpdiscount) {
                                    tmpdiscount = tmpprice;
                                }
                                console.log(tmpprice);
                                tmpprice = tmpprice - tmpdiscount;
                                //  tmpdiscount = $scope.datapromotion[a]['rule_type'] == 'product_discount_by_percentage' ?  $scope.datapromotion[a]['oridiscount_value'] * (price * response.data.cartdetails[i]['quantity']) : accounting.unformat($scope.datapromotion[a]['promotion_detail']['discount_value']) * response.data.cartdetails[i]['quantity'];
                                $scope.datapromotion[a]['afterpromotionprice']    = accounting.formatMoney(tmpdiscount, "", $scope.precision, ",", ".");
                                $scope.datapromotion[a]['tmpafterpromotionprice'] = tmpdiscount / response.data.cartdetails[i]['quantity'];
                                // tmpdiscount = tmpdiscount > 0 ? tmpdiscount : 0;
                                discount += tmpdiscount;
                            }
                        }
                        var discounts = price - discount;
                        $scope.productmodal.afterpromotionprice =  discounts < 0 ?  0 :accounting.formatMoney(discounts, "", $scope.precision, ",", ".");
                        $scope.productmodal.price =  accounting.formatMoney(price, "", $scope.precision, ",", ".");
                    }else{
                        $scope.productmodal.price    = accounting.formatMoney(price, "", $scope.precision, ",", ".");
                        $scope.productmodal.afterpromotionprice = 0;
                    }

                    //coupon
                    $scope.productmodal['coupon_for_this_product']  = response.data.cartdetails[i]['coupon_for_this_product'];
                    if($scope.productmodal['coupon_for_this_product']){
                        for(var b =0;b < $scope.productmodal['coupon_for_this_product'].length; b++){
                            $scope.productmodal['coupon_for_this_product'][b]['oridiscount_value'] = $scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value'];
                            $scope.productmodal['coupon_for_this_product'][b]['discount_value']    = $scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['rule_type'] == 'product_discount_by_percentage' ? $scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value'] * 100 + ' %' : accounting.formatMoney($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value'], "", $scope.precision, ",", ".");

                            if($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['rule_type'] == 'product_discount_by_percentage'){
                                tmpdiscount = $scope.productmodal['coupon_for_this_product'][b]['oridiscount_value'] * price;
                            }else if($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['rule_type'] == 'product_discount_by_value'){
                                tmpdiscount = accounting.unformat($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value']);
                            }else if($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['rule_type'] == 'new_product_price'){
                                tmpdiscount = price - accounting.unformat($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value']);
                            }else{
                                tmpdiscount = 0;
                            }
                            if (tmpprice < tmpdiscount) {
                                tmpdiscount = tmpprice;
                            }

                            tmpprice = tmpprice - tmpdiscount;

                            //  tmpdiscount = $scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['rule_type'] == 'product_discount_by_percentage' ?  $scope.productmodal['coupon_for_this_product'][b]['oridiscount_value'] * price : accounting.unformat($scope.productmodal['coupon_for_this_product'][b]['issuedcoupon']['discount_value']);
                            $scope.productmodal['coupon_for_this_product'][b]['aftercouponprice']    = accounting.formatMoney(tmpdiscount, "", $scope.precision, ",", ".");
                            $scope.productmodal['coupon_for_this_product'][b]['tmpafterpromotionprice'] = tmpdiscount;
                            // tmpdiscount = tmpdiscount > 0 ? tmpdiscount : 0;
                            discount += tmpdiscount;
                        }
                    }

                    $scope.productmodal['cart_id']     = response.data.cart.cart_id;
                    $scope.inserttocartFn(true);
                }

                angular.element("#modalscancart").modal('hide');
                $scope.customerdispaly('Welcome',name.substr(0,20));

                $scope.errorscancart = '';
                $scope.manualscancart = '';

                    }
                });
            })();

        }


        //activity
        $scope.activity = function(act, data){
            serviceAjax.posDataToServer('/pos/'+act,data).then(function(response){
                if(response.code == 0){

                }else{
                    console.log('failed insert activity!');
                }

            });
        };
        //cancel cart
        $scope.cancelCart = function(){
            $scope.cancelRequestService();
            $scope.scanproduct();
            $scope.errorscancart  = '';
            $scope.manualscancart = '';
            $scope.isvirtualscancart = false;
        };

        //logout
        $scope.logoutfn =  function(){
            if(progressJs) progressJs().start().autoIncrease(4, 500);
            serviceAjax.getDataFromServer('/logout?_orbit_logout_from=pos').then(function(response){
                localStorageService.remove('user');
                window.location.assign("signin");
                // if(response.code == 0){

                // }else{
                //     //alert('gagal logout');
                // }
                if(progressJs) progressJs().end();
            });
        };
    }]);

    app.directive('numbersOnly', function(){
        return {
            require: 'ngModel',
            link: function(scope, element, attrs, modelCtrl) {
                modelCtrl.$parsers.push(function (inputValue) {
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
