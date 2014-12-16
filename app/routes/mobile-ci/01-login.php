<?php

// -------------------- views ------------------------------
Route::get('/customer', function() 
{
    return View::make('mobile-ci.signin');
});
// -------------------- views ------------------------------


Route::post('/api/v1/customer/login', function() 
{
    return LoginAPIController::create()->postLoginInShop();
});

Route::post('/app/v1/customer/login', 'IntermediateAuthController@Login_postLoginInShop');
