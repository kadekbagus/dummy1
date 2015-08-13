<?php
/**
 * Routes file for Intermediate Retailer API
 */

/**
 * Create new retailer
 */
Route::post('/app/v1/retailer/new', 'IntermediateAuthController@Retailer_postNewRetailer');

/**
 * Delete retailer
 */
Route::post('/app/v1/retailer/delete', 'IntermediateAuthController@Retailer_postDeleteRetailer');

/**
 * Update retailer
 */
Route::post('/app/v1/retailer/update', 'IntermediateAuthController@Retailer_postUpdateRetailer');

/**
 * List and/or Search retailer
 */
Route::get('/app/v1/retailer/search', 'IntermediateAuthController@Retailer_getSearchRetailer');

/**
 * Retailer city list
 */
Route::get('/app/v1/retailer/city', 'IntermediateAuthController@Retailer_getCityList');

/**
 * List and/or Search retailer by product
 */
Route::get('/app/v1/retailer/search-by-product', 'IntermediateAuthController@Retailer_getSearchRetailerByProduct');

/**
 * List and/or Search retailer by promotion
 */
Route::get('/app/v1/retailer/search-by-promotion', 'IntermediateAuthController@Retailer_getSearchRetailerByPromotion');