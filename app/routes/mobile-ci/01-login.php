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

Route::post('/customer/signup', array('uses'=>'MobileCI\\MobileCIAPIController@postSignUpView'));

Route::group(array('before'=>'authCustomer'), function()
{
    Route::get('/', function() 
    {
        return View::make('mobile-ci.home');
    });
});
// -------------------- views ------------------------------


Route::post('/api/v1/customer/login', function() 
{
    return MobileCI\MobileCIAPIController::create()->postLoginInShop();
});

Route::post('/app/v1/customer/login', 'IntermediateAuthController@MobileCI_postLoginInShop');
