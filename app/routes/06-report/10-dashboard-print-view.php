<?php
Route::get('/printer/dashboard/top-product', [
    'as'      => 'printer-dashboard-top-product',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\DashboardPrinterController@getTopProductPrintView'
]);

Route::get('/printer/dashboard/top-product-family', [
    'as'      => 'printer-dashboard-top-product-family',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\DashboardPrinterController@getTopProductFamilyPrintView'
]);

Route::get('/printer/dashboard/top-widget-click', [
    'as'      => 'printer-dashboard-detail-top-widget-click',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\DashboardPrinterController@getTopWidgetClickPrintView'
]);

Route::get('/printer/dashboard/user-login-by-date', [
    'as'      => 'printer-dashboard-detail-user-login-by-date',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\DashboardPrinterController@getUserLoginByDatePrintView'
]);

Route::get('/printer/dashboard/user-by-gender', [
    'as'      => 'printer-dashboard-detail-user-by-gender',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\DashboardPrinterController@getUserByGenderPrintView'
]);

Route::get('/printer/dashboard/user-by-age', [
    'as'      => 'printer-dashboard-detail-user-by-age',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\DashboardPrinterController@getUserByAgePrintView'
]);

Route::get('/printer/dashboard/user-hourly-login', [
    'as'      => 'printer-dashboard-detail-user-hourly-login',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\DashboardPrinterController@getHourlyUserLoginPrintView'
]);

Route::get('/printer/dashboard/user-connect-time', [
    'as'      => 'printer-dashboard-detail-user-connect-time',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\DashboardPrinterController@getUserConnectTimePrintView'
]);


