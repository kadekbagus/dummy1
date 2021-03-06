<?php
/**
 * Get country list
 */
Route::get('/app/v1/country/list', 'IntermediateLoginController@Country_getSearchCountry');

/**
 * Shutdown box
 */
Route::post('/app/v1/box-control/shutdown', 'IntermediateAuthController@Shutdown_postShutdownBox');

/**
 * Reboot box
 */
Route::post('/app/v1/box-control/reboot', 'IntermediateAuthController@Shutdown_postRebootBox');

/**
 * Lippo Mall PWU - Network checkout test
 */
Route::get('/app/v1/captive/user-out', 'DummyAPIController@getUserOutOfNetwork');

/**
 * Get current retailer
 */
Route::get('/app/v1/current-retailer', ['before' => 'orbit-settings', 'uses' => 'IntermediateLoginController@Retailer_getCurrentRetailer']);