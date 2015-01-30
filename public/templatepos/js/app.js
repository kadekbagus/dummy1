/**
 * Created with JetBrains PhpStorm.
 * User: julisman
 * Date: 1/12/14
 * Time: 3:18 PM
 * http://blog.julisman.com
 */
'use strict';

define([
    'config'
], function (config) {

var app = angular.module('app', ['ui.bootstrap','ngAnimate','LocalStorageModule'], function($interpolateProvider,$httpProvider) {
    $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded';
    $interpolateProvider.startSymbol('<%');
    $interpolateProvider.endSymbol('%>');

});
    app.controller('layoutCtrl', ['$scope','serviceAjax','localStorageService' ,'$timeout', function($scope,serviceAjax,localStorageService,$timeout) {
        $scope.datauser  = localStorageService.get('user');
        var updatetime = function() {
            $scope.datetime = moment().format('DD MMMM YYYY HH:mm:ss');
            $timeout(updatetime, 1000);
        };
        $timeout(updatetime, 1000);
    }]);

    app.controller('loginCtrl', ['$scope','serviceAjax','localStorageService', function($scope,serviceAjax,localStorageService) {
        //check session
        serviceAjax.getDataFromServer('/session',$scope.login).then(function(data) {
            if (data.code != 0 && !$scope.datauser) {
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
            }else{
                window.location.assign("dashboard");
            }
        });

    }]);

    app.controller('dashboardCtrl', ['$scope', 'localStorageService','$timeout','serviceAjax','$modal','$http', '$anchorScroll','$location', function($scope,localStorageService, $timeout, serviceAjax, $modal, $http,$anchorScroll,$location) {
        //init
        $scope.cart             = [];
        $scope.product          = [];
        $scope.productidenabled = [];
        $scope.configs          = config;
        $scope.datadisplay      = {};
        $scope.manualscancart   = '';
        //check session
        serviceAjax.getDataFromServer('/session',$scope.login).then(function(data){
            if(data.code != 0 && !$scope.datauser){
                window.location.assign("signin");
            }else{
                //show modal product detail
                $scope.showdetailFn = function(id,act,idcart){
                    //set loading
                    $scope.loadproductdetail = true;
                    //init product modal
                    $scope.productmodal        = $scope.product[id];
                    $scope.productmodal['idx'] = id;
                    //hide btn when action view
                    $scope.hiddenbtn = false;
                    if(act){
                        $scope.hiddenbtn = true;
                        $scope.productmodal.price = $scope.cart[idcart]['price'];
                        $scope.productmodal.beforepromoprice = $scope.cart[idcart]['beforepromoprice'];
                    }
                    //reset data promotion
                    $scope.datapromotion = [];
                    $scope.variantstmp = '';
                    $scope.showprice = false;

                    $scope.getpromotion($scope.productmodal['product_id']);
                };
                //canceler request
                $scope.cancelRequestService = function(){
                    serviceAjax.cancelRequest();
                };
                //get unix guestid
                ($scope.getguest = function(){
                    $scope.guests = 'Guest-'+moment().format('DD-MM-YYYY-HH-mm-ss');
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
                //get product
                $scope.getproduct = function(){
                    serviceAjax.getDataFromServer('/pos/productsearch?take=12').then(function(response){
                        if(response.code == 0 ){
                            if(response.data.records.length > 0)for(var i =0; i <response.data.records.length; i++){
                                response.data.records[i]['price'] = accounting.formatMoney(response.data.records[i]['price'], "", 0, ",", ".");
                            }
                            $scope.product = response.data.records;
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
                            serviceAjax.getDataFromServer('/pos/productsearch?product_name_like=' + newvalue + '&upc_code_like=' +  newvalue + '&product_code_like='+newvalue).then(function (response) {
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
                //get product based promotion
                $scope.getpromotion = function(productid){
                   if(productid) serviceAjax.posDataToServer('/pos/productdetail', {product_id :productid}).then(function (response) {
                        if (response.code == 0 ) {
                            $scope.productdetail = response.data;
                            $scope.datapromotion = response.data.promo;
                            if($scope.datapromotion.length)for(var i = 0; i < $scope.datapromotion.length;i++){
                                $scope.datapromotion[i]['oridiscount_value'] = $scope.datapromotion[i]['discount_value'];
                                $scope.datapromotion[i]['discount_value']    = $scope.datapromotion[i]['rule_type'] == 'product_discount_by_percentage' ?  $scope.datapromotion[i]['discount_value'] * 100 + ' %' : accounting.formatMoney($scope.datapromotion[i]['discount_value'], "", 0, ",", ".");
                                $scope.datapromotion[i]['new_from']          = moment($scope.datapromotion[i]['new_from']).isValid() ? moment($scope.datapromotion[i]['new_from']).format('DD MMMM YYYY')  : '';
                                $scope.datapromotion[i]['new_until']         = moment($scope.datapromotion[i]['new_until']).isValid() ? moment($scope.datapromotion[i]['new_from']).format('DD MMMM YYYY') : '';
                            }

                            $scope.dataattrvalue1 = [];
                            $scope.dataattrvalue2 = [];
                            $scope.dataattrvalue3 = [];
                            $scope.dataattrvalue4 = [];
                            $scope.dataattrvalue5 = [];
                            $scope.tmpattr = [];
                            $scope.chooseattr = [];
                            //TODO: agung :Refactor this, and try with dfferent data
                            if($scope.productdetail.attributes)for(var a=0; a < $scope.productdetail.attributes.length;a++){

                                $scope.dataattrvalue1[a] = angular.copy($scope.productdetail.attributes[a]);
                                $scope.dataattrvalue2[a] = angular.copy($scope.productdetail.attributes[a]);
                                $scope.dataattrvalue3[a] = angular.copy($scope.productdetail.attributes[a]);
                                $scope.dataattrvalue4[a] = angular.copy($scope.productdetail.attributes[a]);
                                $scope.dataattrvalue5[a] = angular.copy($scope.productdetail.attributes[a]);
                                $scope.tmpattr[a] = angular.copy($scope.productdetail.attributes[a]);
                            }

                            for(var i = 0; i < $scope.dataattrvalue1.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue1.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue1[i]['attr_val_id1'] == $scope.dataattrvalue1[a]['attr_val_id1']){
                                            $scope.dataattrvalue1.splice(i,1);
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue2.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue2.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue2[i]['attr_val_id2'] == $scope.dataattrvalue2[a]['attr_val_id2']){
                                            $scope.dataattrvalue2.splice(i,1);
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue3.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue3.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue3[i]['attr_val_id3'] == $scope.dataattrvalue3[a]['attr_val_id3']){
                                            $scope.dataattrvalue3.splice(i,1);
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue4.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue4.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue4[i]['attr_val_id4'] == $scope.dataattrvalue4[a]['attr_val_id4']){
                                            $scope.dataattrvalue4.splice(i,1);
                                       }
                                   }
                                }
                            }
                            for(var i = 0; i < $scope.dataattrvalue5.length;i++){
                                for(var a = 0; a < $scope.dataattrvalue5.length;a++){
                                   if(i != a) {
                                       if($scope.dataattrvalue5[i]['attr_val_id5'] == $scope.dataattrvalue5[a]['attr_val_id5']){
                                            $scope.dataattrvalue5.splice(i,1);
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
                        }else{
                            //do smoething
                        }
                    })
                };
                //get  cart based promotion
                ($scope.getcartpromotion = function(){
                    serviceAjax.posDataToServer('/pos/cartbasedpromotion').then(function (response) {
                        console.log(response);
                        if (response.code == 0 ) {
                        }
                    })
                })();
                // when choose last the attribute
                $scope.changeattr = function(id,idx){

                    for(var i = id+1; i < $scope.chooseattr.length; i++ ){
                       $scope.chooseattr[i] = '';
                    }
                    if(id +1 == $scope.countattr){
                        $scope.variantstmp = $scope.tmpattr[idx];
                        $scope.productmodal.upc_code = $scope.tmpattr[idx]['upc'];

                        if($scope.datapromotion.length) {
                            var diskon = 0;
                            $scope.productmodal.beforepromoprice    = accounting.formatMoney($scope.tmpattr[idx]['price'], "", 0, ",", ".");
                            if($scope.datapromotion.length)for(var i = 0; i < $scope.datapromotion.length;i++){
                               diskon += $scope.datapromotion[i]['rule_type'] == 'product_discount_by_percentage' ?  $scope.datapromotion[i]['oridiscount_value'] * $scope.tmpattr[idx]['price'] : $scope.datapromotion[i]['oridiscount_value'];
                            }
                            var diskons = $scope.tmpattr[idx]['price'] - diskon;
                            $scope.productmodal.price =  diskons < 0 ?  0 :accounting.formatMoney(diskons, "", 0, ",", ".");
                            $scope.showprice = true;
                        }else{
                            $scope.productmodal.price    = accounting.formatMoney($scope.tmpattr[idx]['price'], "", 0, ",", ".");
                            $scope.productmodal.beforepromoprice = 0;
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
                $scope.inserttocartFn = function(bool){
                    if($scope.productmodal){
                        //customer display
                        if(!bool)$scope.customerdispaly($scope.productmodal['product_name'], accounting.formatMoney($scope.productmodal['price'], "", 0, ",", "."));
                        $location.hash('bottom');
                        $anchorScroll();
                        $scope.searchproduct    = '';
                        $scope.adddelenadis($scope.productmodal['product_id'],'add');
                        if($scope.checkcart($scope.productmodal)){
                            $scope.cart.push({
                                product_name      : $scope.productmodal['product_name'],
                                variants          : $scope.variantstmp,
                                qty               : 1,
                                price             : $scope.productmodal['price'],
                                idx               : $scope.productmodal['idx'],
                                upc_code          : $scope.productmodal['upc_code'],
                                product_code      : $scope.productmodal['product_code'],
                                product_id        : $scope.productmodal['product_id'],
                                ispromo           : $scope.datapromotion.length ? true : false,
                                beforepromoprice  : $scope.productmodal['beforepromoprice'],
                                attribute_id1     : $scope.productmodal['attribute_id1'],
                                attribute_id2     : $scope.productmodal['attribute_id2'],
                                attribute_id3     : $scope.productmodal['attribute_id3'],
                                attribute_id4     : $scope.productmodal['attribute_id4'],
                                attribute_id5     : $scope.productmodal['attribute_id5'],
                                category_id1      : $scope.productmodal['category_id1'],
                                category_id2      : $scope.productmodal['category_id2'],
                                category_id3      : $scope.productmodal['category_id3'],
                                category_id4      : $scope.productmodal['category_id4'],
                                category_id5      : $scope.productmodal['category_id5'],
                                hargatotal        : 0
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
                $scope.checkcart = function(product){
                    var check = true;
                    for(var i = 0; i < $scope.cart.length; i++){
                        if($scope.cart[i]['variants'] == ''){
                            if($scope.cart[i]['product_id'] == product['product_id']){
                                $scope.cart[i]['qty']++;
                                check = false;
                                break;
                            }
                        }else{
                            if($scope.cart[i]['variants']['product_variant_id'] == $scope.variantstmp['product_variant_id']){
                                $scope.cart[i]['qty']++;
                                check = false;
                                break;
                            }
                        }
                    }
                    return check;
                };
                //delete cart && new cart
                $scope.newdeletecartFn = function(act){
                    $scope.successscant     = false;
                    $scope.productidenabled = [];
                    $scope.cart             = [];
                    $scope.searchproduct    = '';
                    $scope.getproduct();
                    if(act) $scope.getguest();
                    $scope.customerdispaly('Welcome to ',$scope.datauser['merchants'][0]['name'].substr(0,20));
                };
                //checkout
                $scope.checkoutFn = function(act,term){
                    switch(act){
                        case 't':
                            $scope.action  = 'cash';
                            $scope.cheader = 'PEMBAYARAN TUNAI';

                            $scope.isvirtual = true;
                            /* event.preventDefault();
                             $timeout(function(){
                             angular.element('#tenderedcash').focus();
                             },500);*/
                            //customer display
                            $scope.customerdispaly('TOTAL',$scope.cart.totalpay);
                            break;
                        case 'k':
                            $scope.cardfile = false;
                            //terminal 1
                            $scope.action = 'card';
                            $scope.cheader = 'PEMBAYARAN KARTU DEBIT/KREDIT';
                            //case success
                            /*serviceAjax.posDataToServer('/pos/cardpayment ',{amount : accounting.unformat($scope.cart.totalpay)}).then(function(response){
                             if(response.code == 0){
                             //todo:agung: make sure return result
                             $scope.savetransactions();
                             }else{
                             //do something
                             $scope.cheader = 'TRANSAKSI GAGAL';
                             }
                             });*/
                            //case fail
                            var failcard = function() {
                                $scope.cheader = 'TRANSAKSI GAGAL';
                                $scope.cardfile = true;
                                $scope.headrcard = term ? term :$scope.headrcard;
                            };
                            $timeout(failcard, 4000);
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
                    $scope.sendcart = {
                        total_item     : accounting.unformat($scope.cart.totalitem),
                        subtotal       : accounting.unformat($scope.cart.subtotal),
                        vat            : accounting.unformat($scope.cart.vat),
                        total_to_pay   : accounting.unformat($scope.cart.totalpay),
                        tendered       : accounting.unformat($scope.cart.amount),
                        change         : accounting.unformat($scope.cart.change),
                        merchant_id    : $scope.datauser['userdetail']['merchant_id'],
                        customer_id    : $scope.cart.user_id,
                        guest          : $scope.guests,
                        cashier_id     : $scope.datauser['user_id'],
                        payment_method : $scope.action,
                        cart           : $scope.cart
                    };
                    serviceAjax.posDataToServer('/pos/savetransaction',$scope.sendcart).then(function(response){
                        if(response.code == 0){
                            $scope.action = 'done';
                            $scope.cheader = 'TRANSAKSI BERHASIL';
                            $scope.transaction_id = response.data.transaction_id;
                            //customer display
                            $scope.customerdispaly('Change '+$scope.cart.change,'Thank You');

                            $scope.ticketprint();
                        }else{
                            //do something
                            $scope.cheader = 'TRANSAKSI GAGAL';
                        }
                    });
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
                        oldvalue = accounting.unformat(oldvalue);
                        newvalue = accounting.unformat(newvalue);
                        if(oldvalue != newvalue){
                            $scope.changetf = false;
                            $scope.cart['amount'] = accounting.formatMoney(newvalue, "", 0, ",", ".");
                            $scope.change = accounting.unformat($scope.cart['amount']) - accounting.unformat($scope.cart['totalpay']);
                            $scope.changetf = $scope.change >= 0 ? true:false;
                            $scope.cart['change'] =  $scope.change > 0 ?   accounting.formatMoney($scope.change, "", 0, ",", ".") : 0;
                        }
                    }
                });
                //go to main
                $scope.gotomain = function(){
                    $scope.resetpayment();
                    $scope.cheader = 'PILIH CARA PEMBAYARAN';
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
                            $scope.productmodal      = response['data'];
                            $scope.inserttocartFn();
                            $scope.scanproduct();
                        }else if(response.code == 13){
                             // angular.element("#ProductNotFound").modal();
                             // $scope.scanproduct();
                        }
                    });
                })();
                //binding keypad scant
                $scope.keypadscantFn = function(idx){
                    if(idx == 'c'){
                        $scope.manualscancart    = '';
                        $scope.isvirtualscancart = false;
                    }else if(idx =='d'){
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
                    if(!bool) $scope.cart[$scope.indexactiveqty]['qty'] = $scope.cart[$scope.indexactiveqty]['qty'] == 0 ? 1 : $scope.cart[$scope.indexactiveqty]['qty'];
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

                        }else {
                            //do something
                        }
                    });
                };
                //init customer display
                $scope.customerdispaly('Welcome to ',$scope.datauser['userdetail']['merchant']['name'].substr(0,20));
                //scan cart automatic and manually
                $scope.scancartFn = function(bool){
                    $scope.cancelRequestService();
                    var data = {
                        barcode : bool ?  $scope.manualscancart : ''
                    };
                    console.log(data);
                    serviceAjax.posDataToServer('/pos/scancart',data).then(function(response){
                            if(response.code == 0 ){
                                var name = response.data.users.user_firstname+' '+response.data.users.user_lastname;
                                $scope.successscant = true;
                                $scope.guests       = name;
                                $scope.cart.user_id = response.data.users.user_id;
                                for(var i = 0; i < response.data.details.length; i++){
                                    $scope.productmodal        = response.data.details[i]['product'];
                                    $scope.productmodal['idx'] = i;
                                    angular.element("#modalscancart").modal('hide');
                                    $scope.inserttocartFn(true);
                                    if(bool)  $scope.virtualFn(false);
                                    $scope.customerdispaly('Welcome',name.substr(0,20));  
                                }
                                 $scope.scanproduct();
                                 $scope.errorscancart = '';
                                 $scope.manualscancart = '';
                            }else{
                                //do something when error
                                $scope.errorscancart  = 'Maaf, keranjang belanja tidak ditemukan. Silakan coba lagi.';
                                $scope.manualscancart = '';
                                $scope.scancartFn();

                            }
                    });


                };
            }
        });

        //cancel cart
        $scope.cancelCart = function(){
            $scope.scanproduct();
            $scope.errorscancart  = '';
            $scope.manualscancart = '';
            $scope.isvirtualscancart = false;
        };

        //logout
        $scope.logoutfn =  function(){
            if(progressJs) progressJs().start().autoIncrease(4, 500);
            serviceAjax.getDataFromServer('/logout').then(function(response){
                if(response.code == 0){
                    localStorageService.remove('user');
                    window.location.assign("signin");
                }else{
                    alert('gagal logout');
                }
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
