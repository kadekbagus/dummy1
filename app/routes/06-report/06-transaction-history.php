<?php
/**
 * Report Printer Purchase History
 */

Route::get('/printer/consumer-transaction-history/product-list', 'Report\TransactionHistoryPrinterController@getProductListPrintView');

Route::get('/printer/consumer-transaction-history/receipt-report', 'Report\TransactionHistoryPrinterController@getReceiptReportPrintView');

Route::get('/printer/consumer-transaction-history/detail-sales-report', 'Report\TransactionHistoryPrinterController@getDetailReportPrintView');
