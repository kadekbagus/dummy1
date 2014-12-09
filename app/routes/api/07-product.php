<?php
/**
 * Routes file for Product related API
 */

/**
 * Create new product
 */
Route::post('/api/v1/product/new', function()
{
    return ProductAPIController::create()->postNewProduct();
});

/**
 * Delete product
 */
Route::post('/api/v1/product/delete', function()
{
    return ProductAPIController::create()->postDeleteProduct();
});

/**
 * Update product
 */
Route::post('/api/v1/product/update', function()
{
    return ProductAPIController::create()->postUpdateProduct();
});

/**
 * List/Search product
 */
Route::get('/api/v1/product/search', function()
{
    return ProductAPIController::create()->getSearchProduct();
});

/**
 * Upload Merchant logo
 */
Route::post('/api/v1/product/upload/image', function()
{
    return UploadAPIController::create()->postUploadProductImage();
});
