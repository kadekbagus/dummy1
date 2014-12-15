<?php

Route::get('/customer', function() 
{
    return View::make('mobile-ci.signin');
});

Route::get('/api/v1/customer/login', function() 
{
    return LoginAPIController::create()->postLoginInShop();
});