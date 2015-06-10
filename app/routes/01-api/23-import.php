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

/**
 * Import product image
 */
Route::post('/api/v1/import/image/product', function()
{
    return ImportAPIController::create()->postImportProductImage();
});
