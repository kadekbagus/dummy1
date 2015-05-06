<?php
/**
 * Routes file for Role API
 */

/**
 * Get list of Role
 */
Route::get('/api/v1/role/list', function()
{
    return RoleAPIController::create()->getSearchRole();
});
