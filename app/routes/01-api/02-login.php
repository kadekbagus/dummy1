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

/**
 * URL to check the token
 */
Route::get('/api/v1/user/token/check', function()
{
    return LoginAPIController::create()->getRegisterTokenCheck();
});

/**
 * Token List
 */
Route::get('/api/v1/token/list', function()
{
    return TokenAPIController::create()->getSearchToken();
});

/**
 * Login route for user with role 'Super Admin'
 */
Route::post('/api/v1/login/admin', function()
{
    return LoginAPIController::create()->postLoginAdmin();
});

/**
 * Login route for user with role 'Merchant Owner'
 */
Route::post('/api/v1/login/merchant', function()
{
    return LoginAPIController::create()->postLoginMerchant();
});

/**
 * Login route for user with role 'Consumer'
 */
Route::post('/api/v1/login/customer', function()
{
    return LoginAPIController::create()->postLoginCustomer();
});

/**
 * Login route for user with role 'Cashier'
 */
Route::post('/api/v1/login/pos', function()
{
    return LoginAPIController::create()->postLoginCashier();
});

/**
 * Login route for user with role 'Consumer', for login from mobile-ci
 */
Route::post('/api/v1/login/mobile', function()
{
    return LoginAPIController::create()->postLoginMobile();
});
