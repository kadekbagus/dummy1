<?php

Route::group(
    array('before' => 'orbit-settings'),
    function () {

        Route::get(
            '/customer/promotion',
            function () {

                return MobileCI\PromotionController::create()->getSearchPromotion();
            }
        );

        Route::get(
            '/customer/promotions',
            function () {

                return MobileCI\PromotionController::create()->getPromotionList();
            }
        );

        Route::get(
            '/customer/coupon',
            function () {

                return MobileCI\CouponController::create()->getSearchCoupon();
            }
        );

        Route::get(
            '/customer/coupons',
            function () {

                return MobileCI\CouponController::create()->getCouponList();
            }
        );
    }
);
