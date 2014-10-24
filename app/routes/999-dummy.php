<?php
/**
 * A dummy route file
 */
Route::get('/api/v1/dummy/hisname', function()
{
    return DummyAPIController::create()->hisname();
});

Route::get('/api/v1/dummy/hisname/auth', function()
{
    return DummyAPIController::create()->hisnameAuth();
});

Route::post('/api/v1/dummy/myname', function()
{
    return DummyAPIController::create()->myName();
});

Route::post('/api/v1/dummy/myname/auth', function()
{
    return DummyAPIController::create()->myNameAuth();
});
