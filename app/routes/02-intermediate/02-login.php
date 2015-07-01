<?php
/**
 * Routes file for login related API.
 * This URL which should get called by the Frontend.
 */

/**
 * Login and logout user
 */
Route::post('/app/v1/login', 'IntermediateLoginController@postLogin');
Route::get('/app/v1/logout', 'IntermediateLoginController@getLogout');
Route::get('/app/v1/session', 'IntermediateLoginController@getSession');

Route::post('/app/v1/login/admin', 'IntermediateLoginController@postLoginAdmin');
Route::post('/app/v1/login/merchant', 'IntermediateLoginController@postLoginMerchant');
Route::post('/app/v1/login/customer', 'IntermediateLoginController@postLoginCustomer');
Route::post('/app/v1/login/pos', array('before' => 'orbit-settings', 'uses' => 'IntermediateLoginController@postLoginCashier'));
Route::post('/app/v1/login/mobile', array('before' => 'orbit-settings', 'uses' => 'IntermediateLoginController@postLoginMobile'));

/**
 * Customer registration
 */
Route::post('/app/v1/user/register/mobile', 'IntermediateLoginController@Login_postRegisterUserInShop');

/**
 * Check the token
 */
Route::post('/app/v1/user/token/check', 'IntermediateLoginController@postRegisterTokenCheck');

/**
 * Alias for check token
 */
Route::post('/app/v1/user/activate', 'IntermediateLoginController@postRegisterTokenCheck');

/**
 * Token List
 */
Route::get('/app/v1/token/list', 'IntermediateLoginController@getTokenList');
