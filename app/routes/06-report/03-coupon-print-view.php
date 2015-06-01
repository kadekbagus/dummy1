<?php

//    Route::get('/printer/coupon/list', 'Report\CouponPrinterController@getCouponPrintView');

Route::get('/printer/coupon/list', [
    'as'        => 'printer-coupon-list',
    'before'    => 'orbit-settings',
    'uses'      => 'Report\CouponPrinterController@getCouponPrintView'
]);