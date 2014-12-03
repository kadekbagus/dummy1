<?php
/**
 * Routes file for Merchant Tax related API
 */

/**
 * Create new tax
 */
Route::post('/api/v1/tax/new', function()
{
    return MerchantTaxAPIController::create()->postNewMerchantTax();
});

/**
 * Delete tax
 */
Route::post('/api/v1/tax/delete', function()
{
    return MerchantTaxAPIController::create()->postDeleteMerchantTax();
});

/**
 * Update tax
 */
Route::post('/api/v1/tax/update', function()
{
    return MerchantTaxAPIController::create()->postUpdateMerchantTax();
});

/**
 * List/Search prtaxoduct
 */
Route::get('/api/v1/tax/search', function()
{
    return MerchantTaxAPIController::create()->getSearchMerchantTax();
});
