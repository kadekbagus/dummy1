<?php

// -------------------- views ------------------------------
Route::get('/customer', function() 
{
    return View::make('mobile-ci.signin');
});
Route::get('/customer/signup', function() 
{
    return View::make('mobile-ci.signup');
});

Route::post('/customer/signup', array('uses'=>'MobileCI\\MobileCIController@postSignUpView'));

Route::get('/customer/home', array('uses'=>'DummyAPIController@IamOK'));
// -------------------- views ------------------------------


Route::post('/api/v1/customer/login', function() 
{
    return LoginAPIController::create()->postLoginInShop();
});

Route::post('/app/v1/customer/login', 'IntermediateAuthController@Login_postLoginInShop');
