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

// Get Dashboard user connect time
Route::get('/api/v1/dashboard/user-connect-time', "DashboardAPIController@getUserConnectTime");

// Get Dashboard user customer last visit
Route::get('/api/v1/dashboard/customer-last-visit', "DashboardAPIController@getUserLastVisit");

// Get Dashboard user customer merchant summary
Route::get('/api/v1/dashboard/customer-merchant-summary', "DashboardAPIController@getUserMerchantSummary");
