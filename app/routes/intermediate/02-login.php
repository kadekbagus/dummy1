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
