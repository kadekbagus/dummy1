<?php
/**
 * Routes file for login related API
 */

/**
 * Login user
 */
Route::post('/api/v1/login', function()
{
    return LoginAPIController::create()->postLogin();
});

Route::post('/api/v1/logout', function()
{
    return LoginAPIController::create()->postLogout();
});

/**
 * Customer registration
 */
Route::post('/api/v1/user/register/mobile', function()
{
    return LoginAPIController::create()->postRegisterUserInShop();
});