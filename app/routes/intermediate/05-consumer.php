<?php
/**
 * Routes file for Intermediate Consumer API
 */


/**
 * List and/or Search consumer
 */
Route::get('/app/v1/consumer/search', 'IntermediateAuthController@User_getConsumerListing');
