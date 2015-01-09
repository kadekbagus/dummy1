<?php
/**
 * Routes file for Intermediate Coupon API
 */

/**
 * Create new coupon
 */
Route::post('/app/v1/coupon/new', 'IntermediateAuthController@Coupon_postNewCoupon');

/**
 * Delete coupon
 */
Route::post('/app/v1/coupon/delete', 'IntermediateAuthController@Coupon_postDeleteCoupon');

/**
 * Update coupon
 */
Route::post('/app/v1/coupon/update', 'IntermediateAuthController@Coupon_postUpdateCoupon');

/**
 * List and/or Search coupon
 */
Route::get('/app/v1/coupon/search', 'IntermediateAuthController@Coupon_getSearchCoupon');
