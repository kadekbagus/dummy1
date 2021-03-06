<?php
/**
 * Routes file for Intermediate Import Product API
 */

/**
 * Import product data
 */
Route::post('/app/v1/import/product', 'IntermediateAuthController@Import_postImportProduct');

/**
 * Import product image
 */
Route::post('/app/v1/import/image/product', 'IntermediateAuthController@Import_postImportProductImage');
