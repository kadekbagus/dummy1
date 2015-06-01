<?php

  //  Route::get('/printer/promotion/list', 'Report\PromotionPrinterController@getPromotionPrintView');

Route::get('/printer/promotion/list', [
    'as'        => 'printer-promotion-list',
    'before'    => 'orbit-settings',
    'uses'      => 'Report\PromotionPrinterController@getPromotionPrintView'
]);