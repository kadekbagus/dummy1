<?php
/**
 * Routes file for Intermediate Widget API
 */

/**
 * Create New Widget
 */
Route::post('/app/v1/widget/new', 'IntermediateAuthController@Widget_postNewWidget');
