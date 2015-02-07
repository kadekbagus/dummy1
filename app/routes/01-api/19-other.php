<?php
/**
 * Get country list
 */
Route::get('/api/v1/country/list', function()
{
    return CountryAPIController::create()->getSearchCountry();
});
