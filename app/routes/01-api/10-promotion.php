<?php
/**
 * Routes file for Promotion related API
 */

/**
 * Create new promotion
 */
Route::post('/api/v1/promotion/new', function()
{
    return PromotionAPIController::create()->postNewPromotion();
});

/**
 * Delete promotion
 */
Route::post('/api/v1/promotion/delete', function()
{
    return PromotionAPIController::create()->postDeletePromotion();
});

/**
 * Update promotion
 */
Route::post('/api/v1/promotion/update', function()
{
    return PromotionAPIController::create()->postUpdatePromotion();
});

/**
 * List/Search promotion
 */
Route::get('/api/v1/promotion/search', function()
{
    return PromotionAPIController::create()->getSearchPromotion();
});

/**
 * Upload promotion image
 */
Route::post('/api/v1/promotion/upload/image', function()
{
    return UploadAPIController::create()->postUploadPromotionImage();
});

/**
 * Delete promotion image
 */
Route::post('/api/v1/promotion/delete/image', function()
{
    return UploadAPIController::create()->postDeletePromotionImage();
});