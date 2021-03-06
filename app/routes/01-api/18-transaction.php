<?php
/**
 * Routes file for Transaction related API
 */

/**
 * Get list of merchants for particular user which has transactions
 */
Route::get('/api/v1/consumer-transaction-history/merchant-list', function()
{
    return TransactionHistoryAPIController::create()->getMerchantList();
});

/**
 * Get list of retailers for particular user which has transactions
 */
Route::get('/api/v1/consumer-transaction-history/retailer-list', function()
{
    return TransactionHistoryAPIController::create()->getMerchantList();
});

/**
 * Get list of product for particular user which has transactions
 */
Route::get('/api/v1/consumer-transaction-history/product-list', function()
{
    return TransactionHistoryAPIController::create()->getProductList();
});

/**
 * Get list of receipt
 */
Route::get('/api/v1/consumer-transaction-history/receipt-list', function()
{
    return TransactionHistoryAPIController::create()->getReceiptReport();
});


/**
 * Get list of detail sales
 * TBD: routing
 * Route::get('/api/v1/consumer-transaction-history/detail-sales-report', function()
 * {
 *   return TransactionHistoryAPIController::create()->getDetailSalesReport();
 * });
*/
