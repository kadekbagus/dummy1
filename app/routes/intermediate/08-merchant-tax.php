<?php
/**
 * Routes file for Intermediate Merchant Tax API
 */

/**
 * Create new tax
 */
Route::post('/app/v1/tax/new', 'IntermediateAuthController@MerchantTax_postNewMerchantTax');

/**
 * Delete tax
 */
Route::post('/app/v1/tax/delete', 'IntermediateAuthController@MerchantTax_postDeleteMerchantTax');

/**
 * Update tax
 */
Route::post('/app/v1/tax/update', 'IntermediateAuthController@MerchantTax_postUpdateMerchantTax');

/**
 * List and/or Search tax
 */
Route::get('/app/v1/tax/search', 'IntermediateAuthController@MerchantTax_getSearchMerchantTax');
