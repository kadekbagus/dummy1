<?php
/**
 * Routes file for Import data related API
 */

/**
 * Import product data
 */
Route::post('/api/v1/import/product', function()
{
    return ImportAPIController::create()->postImportProduct();
});
