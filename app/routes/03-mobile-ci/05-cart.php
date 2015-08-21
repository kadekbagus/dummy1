<?php

Route::group(
    array('before' => array('orbit-settings', 'enable-cart')),
    function () {

        // recognize me
        Route::get(
            '/customer/me',
            function () {

                return MobileCI\AccountController::create()->getMeView();
            }
        );

        // track save receipt click activity
        Route::post(
            '/app/v1/customer/savereceiptclickactivity',
            array(
                'as' => 'click-save-receipt-activity',
                function () {

                    return MobileCI\ActivityController::create()->postClickSaveReceiptActivity();
                })
        );

        // track checkout button click activity
        Route::post(
            '/app/v1/customer/checkoutclickactivity',
            array(
                'as' => 'click-checkout-activity',
                function () {

                    return MobileCI\ActivityController::create()->postClickCheckoutActivity();
                })
        );

        // send ticket to email
        Route::post(
            '/app/v1/customer/sendticket',
            array(
                'as' => 'send-ticket',
                function () {

                    return MobileCI\AccountController::create()->postSendTicket();
                })
        );

        // add product based coupon to cart
        Route::post(
            '/app/v1/customer/addcouponproducttocart',
            function () {

                return MobileCI\TransactionController::create()->postAddProductCouponToCart();
            }
        );

        // save transaction and show ticket
        Route::post(
            '/customer/savetransaction',
            function () {

                return MobileCI\TransactionController::create()->postSaveTransaction();
            }
        );

        // reset cart
        Route::post(
            '/app/v1/customer/resetcart',
            array(
                'as' => 'reset-cart',
                function () {

                    return MobileCI\TransactionController::create()->postResetCart();
                })
        );

        Route::get(
            '/customer/payment',
            function () {

                return MobileCI\TransactionController::create()->getPaymentView();
            }
        );

        Route::get(
            '/customer/paypalpayment',
            function () {

                return MobileCI\TransactionController::create()->getPaypalPaymentView();
            }
        );

        Route::get(
            '/customer/cart',
            function () {

                return MobileCI\TransactionController::create()->getCartView();
            }
        );

        Route::get(
            '/customer/transfer',
            function () {

                return MobileCI\TransactionController::create()->getTransferCartView();
            }
        );

        Route::get(
            '/customer/thankyou',
            function () {

                return MobileCI\HomeController::create()->getThankYouView();
            }
        );

        // add to cart
        Route::post(
            '/app/v1/customer/addtocart',
            function () {

                return MobileCI\TransactionController::create()->postAddToCart();
            }
        );

        // update cart
        Route::post(
            '/app/v1/customer/updatecart',
            function () {

                return MobileCI\TransactionController::create()->postUpdateCart();
            }
        );

        // delete from cart
        Route::post(
            '/app/v1/customer/deletecart',
            function () {

                return MobileCI\TransactionController::create()->postDeleteFromCart();
            }
        );

        // cart product pop up
        Route::post(
            '/app/v1/customer/cartproductpopup',
            function () {

                return MobileCI\WidgetController::create()->postCartProductPopup();
            }
        );

        // cart cart-based-promo pop up
        Route::post(
            '/app/v1/customer/cartpromopopup',
            function () {

                return MobileCI\WidgetController::create()->postCartPromoPopup();
            }
        );

        // cart cart-based-coupon pop up
        Route::post(
            '/app/v1/customer/cartcouponpopup',
            function () {

                return MobileCI\WidgetController::create()->postCartCouponPopup();
            }
        );

        // catalogue product-based-coupon pop up
        Route::post(
            '/app/v1/customer/productcouponpopup',
            function () {

                return MobileCI\WidgetController::create()->postProductCouponPopup();
            }
        );

        // cart product-based-coupon pop up
        Route::post(
            '/app/v1/customer/cartproductcouponpopup',
            function () {

                return MobileCI\WidgetController::create()->postCartProductCouponPopup();
            }
        );

        // delete coupon from cart
        Route::post(
            '/app/v1/customer/deletecouponcart',
            function () {

                return MobileCI\TransactionController::create()->postDeleteCouponFromCart();
            }
        );

        // add cart based coupon to cart
        Route::post(
            '/app/v1/customer/addcouponcarttocart',
            function () {

                return MobileCI\TransactionController::create()->postAddCouponCartToCart();
            }
        );

        // add cart based coupon to cart
        Route::post(
            '/app/v1/customer/closecart',
            function () {

                return MobileCI\TransactionController::create()->postCloseCart();
            }
        );
    }
);
