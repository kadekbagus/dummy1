<?php

// -------------------- views ------------------------------
// Route::get('/customer', 'IntermediateAuthController@MobileCI\MobileCI_getSignInView');

Route::get('/customer', function()
{
    return MobileCI\MobileCIAPIController::create()->getSignInView();
});

// Route::get('/customer/signup', 'IntermediateAuthController@MobileCI\MobileCI_getSignUpView');

Route::get('/customer/signup', function()
{
    return MobileCI\MobileCIAPIController::create()->getSignUpView();
});

// transfer email value from login page to signup page
// Route::post('/customer/signup', 'IntermediateAuthController@MobileCI\MobileCI_postSignUpView');

Route::post('/customer/signup', function()
{
    return MobileCI\MobileCIAPIController::create()->postSignUpView();
});

Route::get('/customer/home', 'IntermediateAuthController@MobileCI\MobileCI_getHomeView');

Route::get('/customer/cart', 'IntermediateAuthController@MobileCI\MobileCI_getCartView');

Route::get('/customer/catalogue', 'IntermediateAuthController@MobileCI\MobileCI_getCatalogueView');

Route::get('/customer/product', 'IntermediateAuthController@MobileCI\MobileCI_getProductView');

Route::get('/customer/transfer', 'IntermediateAuthController@MobileCI\MobileCI_getTransferCartView');

Route::get('/customer/payment', 'IntermediateAuthController@MobileCI\MobileCI_getPaymentView');

Route::get('/customer/thankyou', 'IntermediateAuthController@MobileCI\MobileCI_getThankYouView');

Route::get('/customer/welcome', 'IntermediateAuthController@MobileCI\MobileCI_getWelcomeView');

Route::get('/customer/search', 'IntermediateAuthController@MobileCI\MobileCI_getSearchProduct');

// -------------------- end views ------------------------------

Route::get('/customer/activation', 'IntermediateAuthController@MobileCI\MobileCI_getActivationView');

Route::post('/api/v1/customer/login', function() 
{
    return MobileCI\MobileCIAPIController::create()->postLoginInShop();
});

Route::post('/app/v1/customer/login', 'IntermediateLoginController@MobileCI\MobileCI_postLoginInShop');

Route::get('/api/v1/customer/logout', function() 
{
    return MobileCI\MobileCIAPIController::create()->getLogoutInShop();
});

Route::get('/customer/logout', 'IntermediateLoginController@MobileCI\MobileCI_getLogoutInShop');

// get product listing for families
Route::get('/app/v1/customer/products', 'IntermediateAuthController@MobileCI\MobileCI_getProductList');

Route::get('/api/v1/customer/products', function() 
{
    return MobileCI\MobileCIAPIController::create()->getProductList();
});
