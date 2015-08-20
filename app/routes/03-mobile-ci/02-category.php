<?php

Route::group(
    array('before' => 'orbit-settings'),
    function () {

        Route::get(
            '/customer/catalogue',
            function () {

                return MobileCI\CategoryController::create()->getCatalogueView();
            }
        );

        // family page
        Route::get(
            '/customer/category',
            function () {

                return MobileCI\CategoryController::create()->getCategory();
            }
        );

        // family page
        Route::get(
            '/customer/event',
            function () {

                return MobileCI\CategoryController::create()->getEventDetailView();
            }
        );
    }
);
