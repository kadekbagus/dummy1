<?php

// Get Dashboard top product
Route::get('/app/v1/dashboard/top-product', "IntermediateAuthController@Dashboard_getTopProduct");


// Get Dashboard top product attribute
Route::get('/app/v1/dashboard/top-product-attribute', "IntermediateAuthController@Dashboard_getTopProductAttribute");


// Get Dashboard top product family
Route::get('/app/v1/dashboard/top-product-family', "IntermediateAuthController@Dashboard_getTopProductFamily");

// Get Dashboard top widget click
Route::get('/app/v1/dashboard/top-widget', "IntermediateAuthController@Dashboard_getTopWidgetClick");

// Get Dashboard user login by date
Route::get('/app/v1/dashboard/user-login-by-date', "IntermediateAuthController@Dashboard_getUserLoginByDate");

// Get Dashboard user by gender
Route::get('/app/v1/dashboard/user-by-gender', "IntermediateAuthController@Dashboard_getUserByGender");

// Get Dashboard user by age
Route::get('/app/v1/dashboard/user-by-age', "IntermediateAuthController@Dashboard_getUserByAge");
