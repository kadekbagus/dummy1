<?php
/**
 * Routes file for Product Category (Family) related API
 */

/**
 * Create new family
 */
Route::post('/api/v1/family/new', function()
{
    return MerchantTaxAPIController::create()->postNewMerchantTax();
});

/**
 * Delete family
 */
Route::post('/api/v1/family/delete', function()
{
    return MerchantTaxAPIController::create()->postDeleteMerchantTax();
});

/**
 * Update family
 */
Route::post('/api/v1/family/update', function()
{
    return MerchantTaxAPIController::create()->postUpdateMerchantTax();
});

/**
 * List/Search family
 */
Route::get('/api/v1/family/search', function()
{
    return MerchantTaxAPIController::create()->getSearchMerchantTax();
});
