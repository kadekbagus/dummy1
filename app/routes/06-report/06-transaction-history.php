<?php
/**
 * Report Printer Purchase History
 */

Route::get('/printer/consumer-transaction-history/product-list', [
    'as'      => 'printer-customer-transaction-history-product-list',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\TransactionHistoryPrinterController@getProductListPrintView'
]);

Route::get('/printer/consumer-transaction-history/receipt-report', [
    'as'      => 'printer-customer-transaction-history-receipt-report',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\TransactionHistoryPrinterController@getReceiptReportPrintView'
]);

Route::get('/printer/consumer-transaction-history/detail-sales-report', [
    'as'      => 'printer-customer-transaction-history-detail-sales-report',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\TransactionHistoryPrinterController@getDetailReportPrintView'
]);
