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

/**
 * Customer registration
 */
Route::post('/app/v1/user/register/mobile', 'IntermediateLoginController@Login_postRegisterUserInShop');

/**
 * Check the token
 */
Route::get('/app/v1/user/token/check', 'IntermediateLoginController@getRegisterTokenCheck');

/**
 * Alias for check token
 */
Route::get('/app/v1/user/activate', 'IntermediateLoginController@getRegisterTokenCheck');
