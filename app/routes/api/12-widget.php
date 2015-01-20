<?php
/**
 * Routes file for Widget related API
 */

/**
 * Create New Widget
 */
Route::post('/api/v1/widget/new', function()
{
    return WidgetAPIController::create()->postNewWidget();
});

