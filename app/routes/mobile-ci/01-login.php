<?php

// -------------------- views ------------------------------
Route::get('/customer', array('as' => 'signin', function() 
{
    return View::make('mobile-ci.signin');
}));
Route::get('/customer/signup', function() 
{
    return View::make('mobile-ci.signup', array('email' => ''));
});

Route::post('/customer/signup', array('uses'=>'MobileCI\\MobileCIAPIController@postSignUpView'));

Route::group(array('before'=>'authCustomer'), function()
{
    Route::get('/customer/home', function() 
    {
        return View::make('mobile-ci.home');
    });
});
// -------------------- views ------------------------------


// Route::post('/api/v1/customer/check', function() 
// {
//     return MobileCI\MobileCIAPIController::create()->postCheckEmail();
// });

Route::post('/api/v1/customer/login', function() 
{
    return MobileCI\MobileCIAPIController::create()->postLoginInShop();
});

Route::post('/app/v1/customer/login', 'IntermediateAuthController@MobileCI\MobileCI_postLoginInShop');

Route::get('/api/v1/customer/logout', function() 
{
    return MobileCI\MobileCIAPIController::create()->getLogoutInShop();
});

Route::get('/app/v1/customer/logout', 'IntermediateAuthController@MobileCI\MobileCI_getLogoutInShop');

Route::post('/api/v1/customer/signup', function() 
{
    return MobileCI\MobileCIAPIController::create()->postRegisterUserInShop();
});

Route::post('/app/v1/customer/signup', 'IntermediateAuthController@MobileCI\MobileCI_postRegisterUserInShop');
