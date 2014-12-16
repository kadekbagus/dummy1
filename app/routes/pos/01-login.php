<?php
/**
 * Routes file for POS quick & dirty
 */

Route::post('/api/v1/pos/login', function () {
    return LoginAPIController::create()->postLoginCashier();
});

Route::post('/app/v1/pos/login', 'IntermediateLoginController@Login_postLoginCashier');

Route::get('/pos', function () {
    if (Auth::check()) {

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

Route::get('/pos/logout', function () {
    Auth::logout();
});
