<?php
/**
 * Intermediate route for transaction history
 */

/**
 * List Merchants for particular user which has transactions
 */
Route::get('/app/v1/consumer-transaction-history/merchant-list', 'IntermediateAuthController@TransactionHistory_getMerchantList');

/**
 * List Retailers for particular user which has transactions
 */
Route::get('/app/v1/consumer-transaction-history/retailer-list', 'IntermediateAuthController@TransactionHistory_getRetailerList');

/**
 * List Products for particular user which has transactions
 */
Route::get('/app/v1/consumer-transaction-history/product-list', 'IntermediateAuthController@TransactionHistory_getProductList');

/**
 * Get list of receipt
 */
Route::get('/app/v1/consumer-transaction-history/receipt-list', 'IntermediateAuthController@TransactionHistory_getReceiptReport');

/**
 * Get list of detail sales
 */
Route::get('/app/v1/consumer-transaction-history/detail-sales-report', 'IntermediateAuthController@TransactionHistory_getDetailSalesReport');

