<?php

// Get Dashboard top product
Route::get('/api/v1/dashboard/top-product', "DashboardAPIController@getTopProduct");


// Get Dashboard top product attribute
Route::get('/api/v1/dashboard/top-product-attribute', "DashboardAPIController@getTopProductAttribute");

// Get Dashboard top product family
Route::get('/api/v1/dashboard/top-product-family', "DashboardAPIController@getTopProductFamily");

// Get Dashboard top widget clicked
Route::get('/api/v1/dashboard/top-widget', "DashboardAPIController@getTopWidgetClick");

// Get Dashboard user login by date
Route::get('/api/v1/dashboard/user-login-by-date', "DashboardAPIController@getUserLoginByDate");

// Get Dashboard user by gender
Route::get('/api/v1/dashboard/user-by-gender', "DashboardAPIController@getUserByGender");

// Get Dashboard user by age
Route::get('/api/v1/dashboard/user-by-age', "DashboardAPIController@getUserByAge");

// Get Dashboard user login time
Route::get('/api/v1/dashboard/user-hourly-login', "DashboardAPIController@getHourlyUserLogin");

// Get Dashboard user login time
Route::get('/api/v1/dashboard/user-connect-time', "DashboardAPIController@getUserConnectTime");
