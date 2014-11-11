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

Route::options('/api/v1/login', function()
{
    return LoginAPIController::create()->postLogin();
});

Route::post('/api/v1/logout', function()
{
    return LoginAPIController::create()->postLogout();
});

Route::options('/api/v1/logout', function()
{
    return LoginAPIController::create()->postLogout();
});
