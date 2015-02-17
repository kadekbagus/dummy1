<?php
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Routes related with Captive Portal
|
*/
Route::get('/captive', ['as' => 'captive-portal', function()
{
    return IntermediateLoginController::create()->getCaptive();
}]);
