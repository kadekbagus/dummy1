<?php
/**
 * Routes file for user related API
 */
Route::post('/api/v1/user/new', function()
{
    return UserAPIController::create()->postNewUser();
});
