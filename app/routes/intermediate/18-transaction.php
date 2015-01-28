<?php
/**
 * Intermediate route for transaction history
 */

/**
 * List Merchants for particular user which has transactions
 */
Route::get('/app/v1/transaction-history/merchant-list', 'IntermediateAuthController@TransactionHistory_getMerchantList');

/**
 * List Retailers for particular user which has transactions
 */
Route::get('/app/v1/transaction-history/retailer-list', 'IntermediateAuthController@TransactionHistory_getRetailerList');
