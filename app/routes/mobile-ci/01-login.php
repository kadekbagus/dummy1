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

// transfer email value from login page to signup page
Route::post('/customer/signup', array('uses'=>'MobileCI\\MobileCIAPIController@postSignUpView'));

Route::get('/customer/home', 'IntermediateAuthController@MobileCI\MobileCI_getHomeView');

Route::get('/customer/cart', function() {
  return View::make('mobile-ci.cart', array('page_title'=>'MY SHOPPING CART'));
});

Route::get('/customer/catalogue', function() {
  return View::make('mobile-ci.catalogue', array('page_title'=>'CATALOGUE'));
});

Route::get('/customer/product/{id}', function() {
  return View::make('mobile-ci.product', array('page_title'=>'CATALOGUE'));
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

Route::post('/app/v1/customer/login', 'IntermediateLoginController@MobileCI\MobileCI_postLoginInShop');

Route::get('/api/v1/customer/logout', function() 
{
    return MobileCI\MobileCIAPIController::create()->getLogoutInShop();
});

Route::get('/app/v1/customer/logout', 'IntermediateLoginController@MobileCI\MobileCI_getLogoutInShop');

Route::post('/api/v1/customer/signup', function() 
{
    return MobileCI\MobileCIAPIController::create()->postRegisterUserInShop();
});

Route::post('/app/v1/customer/signup', 'IntermediateAuthController@MobileCI\MobileCI_postRegisterUserInShop');
