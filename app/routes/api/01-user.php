<?php
/**
 * Routes file for user related API
 */

/**
 * Create new user
 */
Route::post('/api/v1/user/new', function()
{
    return UserAPIController::create()->postNewUser();
});

/**
 * Delete user
 */
Route::post('/api/v1/user/delete', function()
{
    return UserAPIController::create()->postDeleteUser();
});

/**
 * Update user
 */
Route::post('/api/v1/user/update', function()
{
    return UserAPIController::create()->postUpdateUser();
});
