<?php

// Get Dashboard top product
Route::get('/app/v1/dashboard/top-product', "IntermediateAuthController@Dashboard_getTopProduct");


// Get Dashboard top product family
Route::get('/app/v1/dashboard/top-product-family', "IntermediateAuthController@Dashboard_getTopProductFamily");
