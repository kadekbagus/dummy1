<?php
/**
 * Routes file for Activity related API
 */

/**
 * Get list of activities
 */
Route::post('/api/v1/activity/list', function()
{
    return ActivityAPIController::create()->getSearchActivity();
});
