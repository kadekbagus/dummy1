<?php

Route::get('/customer', function()
{
    return MobileCI\MobileCIAPIController::create()->getSignInView();
});

Route::get('/customer/signup', function()
{
    return MobileCI\MobileCIAPIController::create()->getSignUpView();
});

Route::post('/customer/signup', function()
{
    return MobileCI\MobileCIAPIController::create()->postSignUpView();
});

Route::get('/customer/home', function()
{
    return MobileCI\MobileCIAPIController::create()->getHomeView();
});

Route::get('/customer/cart', function()
{
    return MobileCI\MobileCIAPIController::create()->getCartView();
});

Route::get('/customer/catalogue', function()
{
    return MobileCI\MobileCIAPIController::create()->getCatalogueView();
});

Route::get('/customer/product', function()
{
    return MobileCI\MobileCIAPIController::create()->getProductView();
});

Route::get('/customer/transfer', function()
{
    return MobileCI\MobileCIAPIController::create()->getTransferCartView();
});

Route::get('/customer/payment', function()
{
    return MobileCI\MobileCIAPIController::create()->getPaymentView();
});

Route::get('/customer/thankyou', function()
{
    return MobileCI\MobileCIAPIController::create()->getThankYouView();
});

Route::get('/customer/welcome', function()
{
    return MobileCI\MobileCIAPIController::create()->getWelcomeView();
});

Route::get('/customer/search', function()
{
    return MobileCI\MobileCIAPIController::create()->getSearchProduct();
});

Route::get('/customer/promotion', function()
{
    return MobileCI\MobileCIAPIController::create()->getSearchPromotion();
});

Route::get('/customer/promotions', function()
{
    return MobileCI\MobileCIAPIController::create()->getPromotionList();
});

Route::get('/customer/coupon', function()
{
    return MobileCI\MobileCIAPIController::create()->getSearchCoupon();
});

Route::get('/customer/coupons', function()
{
    return MobileCI\MobileCIAPIController::create()->getCouponList();
});

Route::get('/customer/activation', function()
{
    return MobileCI\MobileCIAPIController::create()->getActivationView();
});

Route::post('/app/v1/customer/login', 'IntermediateLoginController@postLoginMobileCI');

Route::get('/customer/logout', 'IntermediateLoginController@getLogoutMobileCI');

// get product listing for families
Route::get('/app/v1/customer/products', function()
{
    return MobileCI\MobileCIAPIController::create()->getProductList();
});

// add to cart
Route::post('/app/v1/customer/addtocart', function()
{
    return MobileCI\MobileCIAPIController::create()->postAddToCart();
});

// update cart
Route::post('/app/v1/customer/updatecart', function()
{
    return MobileCI\MobileCIAPIController::create()->postUpdateCart();
});

// delete from cart
Route::post('/app/v1/customer/deletecart', function()
{
    return MobileCI\MobileCIAPIController::create()->postDeleteFromCart();
});

// cart product pop up
Route::post('/app/v1/customer/cartproductpopup', function()
{
    return MobileCI\MobileCIAPIController::create()->postCartProductPopup();
});

// cart cart-based-promo pop up
Route::post('/app/v1/customer/cartpromopopup', function()
{
    return MobileCI\MobileCIAPIController::create()->postCartPromoPopup();
});

// cart cart-based-coupon pop up
Route::post('/app/v1/customer/cartcouponpopup', function()
{
    return MobileCI\MobileCIAPIController::create()->postCartCouponPopup();
});

// catalogue product-based-coupon pop up
Route::post('/app/v1/customer/productcouponpopup', function()
{
    return MobileCI\MobileCIAPIController::create()->postProductCouponPopup();
});

// cart product-based-coupon pop up
Route::post('/app/v1/customer/cartproductcouponpopup', function()
{
    return MobileCI\MobileCIAPIController::create()->postCartProductCouponPopup();
});

// delete coupon from cart
Route::post('/app/v1/customer/deletecouponcart', function()
{
    return MobileCI\MobileCIAPIController::create()->postDeleteCouponFromCart();
});

// add cart based coupon to cart
Route::post('/app/v1/customer/addcouponcarttocart', function()
{
    return MobileCI\MobileCIAPIController::create()->postAddCouponCartToCart();
});

// add cart based coupon to cart
Route::post('/app/v1/customer/closecart', function()
{
    return MobileCI\MobileCIAPIController::create()->postCloseCart();
});

// family page
Route::get('/customer/category', function()
{
    return MobileCI\MobileCIAPIController::create()->getCategory();
});

// add product based coupon to cart
Route::post('/app/v1/customer/addcouponproducttocart', function()
{
    return MobileCI\MobileCIAPIController::create()->postAddProductCouponToCart();
});