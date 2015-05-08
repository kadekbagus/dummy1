<?php
/**
 * Report Printer Purchase History
 */

Route::get('/printer/consumer-transaction-history/product-list', 'Report\PurchaseHistoryPrinterController@getProductListPrintView');
