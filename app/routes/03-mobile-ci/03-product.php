<?php

Route::group(
    array('before' => 'orbit-settings'),
    function () {

        // get product listing for families
        Route::get(
            '/app/v1/customer/products',
            function () {

                return MobileCI\ProductController::create()->getProductList();
            }
        );

        Route::get(
            '/customer/product',
            function () {

                return MobileCI\ProductController::create()->getProductView();
            }
        );

        Route::get(
            '/customer/search',
            function () {

                return MobileCI\ProductController::create()->getSearchProduct();
            }
        );

    }
);
