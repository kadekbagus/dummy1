<?php
/**
 * Routes file for Merchant related API
 */

/**
 * Create new merchant
 */
Route::post('/api/v1/merchant/new', function()
{
    return MerchantAPIController::create()->postNewMerchant();
});

/**
 * Delete merchant
 */
Route::post('/api/v1/merchant/delete', function()
{
    return MerchantAPIController::create()->postDeleteMerchant();
});

/**
 * Update merchant
 */
Route::post('/api/v1/merchant/update', function()
{
    return MerchantAPIController::create()->postUpdateMerchant();
});

/**
 * List/Search merchant
 */
Route::get('/api/v1/merchant/search', function()
{
    return MerchantAPIController::create()->getSearchMerchant();
});
