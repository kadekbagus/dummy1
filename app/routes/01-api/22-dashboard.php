<?php

// Get Dashboard top product
Route::get('/api/v1/dashboard/top-product', "DashboardAPIController@getTopProduct");


// Get Dashboard top product family
Route::get('/api/v1/dashboard/top-product-attribute', "DashboardAPIController@getTopProductAttribute");

// Get Dashboard top product family
Route::get('/api/v1/dashboard/top-product-family', "DashboardAPIController@getTopProductFamily");

// Get Dashboard top product family
Route::get('/api/v1/dashboard/top-widget', "DashboardAPIController@getTopWidgetClick");

// Get Dashboard top product family
Route::get('/api/v1/dashboard/user-login-by-date', "DashboardAPIController@getUserLoginByDate");
