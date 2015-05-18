<?php

// Get Dashboard top product
Route::get('/api/v1/dashboard/top-product', "DashboardAPIController@getTopProduct");


// Get Dashboard top product family
Route::get('/api/v1/dashboard/top-product-family', "DashboardAPIController@getTopProductFamily");
