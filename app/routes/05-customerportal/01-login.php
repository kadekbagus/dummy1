<?php
/**
 * Routes file for Counsumer Portal quick & dirty
 */

// cashier login
Route::post('/api/v1/customerportal/login', function () {
    return Customerportal\CustomerportalAPIController::create()->postLoginInPortal();
});

Route::post('/app/v1/customerportal/login', 'IntermediateLoginController@Customerportal\Customerportal_postLoginInPortal');


// cashier logout
Route::post('/api/v1/pos/logout', function () {
    return POS\CashierAPIController::create()->postLogoutPortal();
});

Route::post('/app/v1/pos/logout', 'IntermediateLoginController@Customerportal\Customerportal_postLogoutPortal');


// customer resend activation
Route::post('/api/v1/customerportal/resend-activation-email', 'Customerportal\CustomerportalAPIController@postResendActivationEmail');

// customer reset password
Route::post('/api/v1/customerportal/request-password-reset', 'Customerportal\CustomerportalAPIController@postRequestPasswordReset');
Route::post('/api/v1/customerportal/reset-password', 'Customerportal\CustomerportalAPIController@postResetPassword');


