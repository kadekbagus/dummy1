<?php

Route::group(
    array('before' => 'orbit-settings'),
    function () {

        Route::get(
            '/customer',
            function () {

                return MobileCI\AccountController::create()->getSignInView();
            }
        );

        Route::get(
            '/customer/signup',
            function () {

                return MobileCI\AccountController::create()->getSignUpView();
            }
        );

        Route::post(
            '/customer/signup',
            function () {

                return MobileCI\AccountController::create()->postSignUpView();
            }
        );

        Route::get(
            '/customer/home',
            array(
            'as' => 'home',
            function () {

                return MobileCI\HomeController::create()->getHomeView();
            })
        );

        Route::get(
            '/customer/productscan',
            function () {

                return MobileCI\ProductController::create()->getProductScanView();
            }
        );

        Route::post(
            '/api/v1/customer/scan',
            function () {

                return UploadAPIController::create()->postUploadUPCBarcode();
            }
        );

        Route::post('/app/v1/customer/scan', 'IntermediateAuthController@Upload_postUploadUPCBarcode');

        Route::get(
            '/customer/welcome',
            function () {

                return MobileCI\HomeController::create()->getWelcomeView();
            }
        );

        Route::get(
            '/customer/activation',
            function () {

                return MobileCI\AccountController::create()->getActivationView();
            }
        );

        Route::post('/app/v1/customer/login', 'IntermediateLoginController@postLoginMobileCI');

        Route::get('/customer/logout', 'IntermediateLoginController@getLogoutMobileCI');

        // track event popup click activity
        Route::post(
            '/app/v1/customer/eventpopupactivity',
            function () {

                return MobileCI\WidgetController::create()->postEventPopUpActivity();
            }
        );

        // track event popup display activity
        Route::post(
            '/app/v1/customer/displayeventpopupactivity',
            array(
            'as' => 'display-event-popup-activity',
            function () {

                return MobileCI\ActivityController::create()->postDisplayEventPopUpActivity();
            })
        );

        // track widget click activity
        Route::post(
            '/app/v1/customer/widgetclickactivity',
            array(
            'as' => 'click-widget-activity',
            function () {

                return MobileCI\ActivityController::create()->postClickWidgetActivity();
            })
        );
    }
);
