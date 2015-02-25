<?php
/**
 * Routes file for POS quick & dirty
 */
Route::group(array('before' => 'orbit-settings'), function()
{
    // cashier login
    Route::post('/api/v1/pos/logincashier', function () {
        return POS\CashierAPIController::create()->postLoginCashier();
    });

    Route::post('/app/v1/pos/logincashier', 'IntermediateLoginController@POS\Cashier_postLoginCashier');


    // cashier logout
    Route::post('/api/v1/pos/logoutcashier', function () {
        return POS\CashierAPIController::create()->postLogoutCashier();
    });

    Route::post('/app/v1/pos/logoutcashier', 'IntermediateLoginController@POS\Cashier_postLogoutCashier');


    // scan barcode
    Route::post('/api/v1/pos/scanbarcode', function () {
        return POS\CashierAPIController::create()->postScanBarcode();
    });

    Route::post('/app/v1/pos/scanbarcode', 'IntermediateAuthController@POS\Cashier_postScanBarcode');


    // product search with variant
    Route::get('/api/v1/pos/productsearchvar', function () {
        return POS\CashierAPIController::create()->getSearchProductPOSwithVariant();
    });

    Route::get('/app/v1/pos/productsearchvar', 'IntermediateAuthController@POS\Cashier_getSearchProductPOSwithVariant');


    // product search
    Route::get('/api/v1/pos/productsearch', function () {
        return POS\CashierAPIController::create()->getSearchProductPOS();
    });

    Route::get('/app/v1/pos/productsearch', 'IntermediateAuthController@POS\Cashier_getSearchProductPOS');


    // save transaction
    Route::post('/api/v1/pos/savetransaction', function () {
        return POS\CashierAPIController::create()->postSaveTransaction();
    });

    Route::post('/app/v1/pos/savetransaction', 'IntermediateAuthController@POS\Cashier_postSaveTransaction');


    // print ticket
    Route::post('/api/v1/pos/ticketprint', function () {
        return POS\CashierAPIController::create()->postPrintTicket();
    });

    Route::post('/app/v1/pos/ticketprint', 'IntermediateAuthController@POS\Cashier_postPrintTicket');


    // card payment
    Route::post('/api/v1/pos/cardpayment', function () {
        return POS\CashierAPIController::create()->postCardPayment();
    });

    Route::post('/app/v1/pos/cardpayment', 'IntermediateAuthController@POS\Cashier_postCardPayment');


    // cash drawer
    Route::post('/api/v1/pos/cashdrawer', function () {
        return POS\CashierAPIController::create()->postCashDrawer();
    });

    Route::post('/app/v1/pos/cashdrawer', 'IntermediateAuthController@POS\Cashier_postCashDrawer');


    // scan cart
    Route::post('/api/v1/pos/scancart', function () {
        return POS\CashierAPIController::create()->postScanCart();
    });

    Route::post('/app/v1/pos/scancart', 'IntermediateAuthController@POS\Cashier_postScanCart');


    // customer display
    Route::post('/api/v1/pos/customerdisplay', function () {
        return POS\CashierAPIController::create()->postCustomerDisplay();
    });

    Route::post('/app/v1/pos/customerdisplay', 'IntermediateAuthController@POS\Cashier_postCustomerDisplay');


    // product detail
    Route::post('/api/v1/pos/productdetail', function () {
        return POS\CashierAPIController::create()->postProductDetail();
    });

    Route::post('/app/v1/pos/productdetail', 'IntermediateAuthController@POS\Cashier_postProductDetail');


    // cart based promotion
    Route::post('/api/v1/pos/cartbasedpromotion', function () {
        return POS\CashierAPIController::create()->postCartBasedPromotion();
    });

    Route::post('/app/v1/pos/cartbasedpromotion', 'IntermediateAuthController@POS\Cashier_postCartBasedPromotion');


    Route::get('/pos', function () {
        return Redirect::to('/pos/signin');
    });

    Route::get('/pos/signin', function () {
        return View::make('pos.login');
    });

    Route::get('/pos/home', function () {
        if (Auth::check()) {
            echo "anda login <br/>";
            $user_id = Auth::user()->user_id;
            $username = Auth::user()->username;
            $email = Auth::user()->user_email;
            echo "user id ".$user_id."<br/>";
            echo "username ".$username."<br/>";
            echo "email ".$email."<br/>";
        } else {
            echo "anda tidak login";
        }
    });

    Route::get('/pos/dashboard', function () {
         return View::make('pos.dashboard');
    });
});
