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

/**
 * Update Widget
 */
Route::post('/api/v1/widget/update', function()
{
    return WidgetAPIController::create()->postUpdateWidget();
});

