<?php
/**
 * Routes file for Coupon related API
 */

/**
 * Create new coupon
 */
Route::post('/api/v1/coupon/new', function()
{
    return CouponAPIController::create()->postNewCoupon();
});

/**
 * Delete coupon
 */
Route::post('/api/v1/coupon/delete', function()
{
    return CouponAPIController::create()->postDeleteCoupon();
});

/**
 * Update coupon
 */
Route::post('/api/v1/coupon/update', function()
{
    return CouponAPIController::create()->postUpdateCoupon();
});

/**
 * List/Search coupon
 */
Route::get('/api/v1/coupon/search', function()
{
    return CouponAPIController::create()->getSearchCoupon();
});

/**
 * Upload coupon image
 */
Route::post('/api/v1/coupon/upload/image', function()
{
    return UploadAPIController::create()->postUploadCouponImage();
});

/**
 * Delete coupon image
 */
Route::post('/api/v1/coupon/delete/image', function()
{
    return UploadAPIController::create()->postDeleteCouponImage();
});