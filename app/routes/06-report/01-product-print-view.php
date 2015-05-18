<?php

/**
 * Export and Printer route for product.
 */
Route::get('/printer/product/list', [
    'as'        => 'printer-product-list',
    'before'    => 'orbit-settings',
    'uses'      => 'Report\ProductPrinterController@getProductPrintView'
]);
