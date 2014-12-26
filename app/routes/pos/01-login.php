<?php
/**
 * Routes file for POS quick & dirty
 */

// cashier login
Route::post('/api/v1/pos/login', function () {
    return POS\CashierAPIController::create()->postLoginCashier();
});

Route::post('/app/v1/pos/login', 'IntermediateLoginController@POS\Cashier_postLoginCashier');


// cashier logout
Route::post('/api/v1/pos/logout', function () {
    return POS\CashierAPIController::create()->postLogoutCashier();
});

Route::post('/app/v1/pos/logout', 'IntermediateLoginController@POS\Cashier_postLogoutCashier');


// scan barcode
Route::post('/api/v1/pos/scanbarcode', function () {
    return POS\CashierAPIController::create()->postScanBarcode();
});

Route::post('/app/v1/pos/scanbarcode', 'IntermediateBaseController@POS\Cashier_postScanBarcode');


// product search
Route::get('/api/v1/pos/productsearch', function () {
    return POS\CashierAPIController::create()->getSearchProductPOS();
});

Route::get('/app/v1/pos/productsearch', 'IntermediateAuthController@POS\Cashier_getSearchProductPOS');


Route::get('/pos', function () {
    if (Auth::check()) {
        return View::make('pos.login');
    } else {
        return View::make('pos.login');
    }
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
    if (Auth::check()) {
        return View::make('pos.dashboard');
    } else {
        echo "anda tidak login";
    }
});

Route::get('/pos/cash', function () {
    if (Auth::check()) {
        return View::make('pos.cash');
    } else {
        echo "anda tidak login";
    }
});
Route::get('/pos/card', function () {
    if (Auth::check()) {
        return View::make('pos.card');
    } else {
        echo "anda tidak login";
    }
});


