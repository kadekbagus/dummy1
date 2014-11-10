<?php
/**
 * Routes file for session related API
 */

Route::get('/api/v1/session/check/{token?}', function($token = null)
{
    return SessionAPIController::create()->getCheck($token);
});