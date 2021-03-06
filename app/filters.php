<?php

/*
|--------------------------------------------------------------------------
| Application & Route Filters
|--------------------------------------------------------------------------
|
| Below you will find the "before" and "after" events for the application
| which may be used to do any work before or after a request into your
| application. Here you may also register your custom route filters.
|
*/

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

App::before(function($request)
{
    //
});


App::after(function($request, $response)
{
    //
});

App::bind('current_retailer', function () {
    $currentRetailer = Retailer::with('parent')->excludeDeleted();

    $currentRetailer->where('merchant_id', Config::get('orbit.shop.id'));

    OrbitInput::get('retailer_id', function ($retailerId)  use ($currentRetailer){
        $currentRetailer->where('merchant_id', $retailerId);
    });

    return $currentRetailer->first();
});

/*
|--------------------------------------------------------------------------
| Authentication Filters
|--------------------------------------------------------------------------
|
| The following filters are used to verify that the user of the current
| session is logged into this application. The "basic" filter easily
| integrates HTTP Basic authentication for quick, simple checking.
|
*/

Route::filter('auth', function()
{
    if (Auth::guest())
    {
        if (Request::ajax())
        {
            return Response::make('Unauthorized', 401);
        }
        else
        {
            return Redirect::guest('login');
        }
    }
});

Route::filter('authCustomer', function()
{

});

Route::filter('auth.basic', function()
{
    return Auth::basic();
});

/*
|--------------------------------------------------------------------------
| Guest Filter
|--------------------------------------------------------------------------
|
| The "guest" filter is the counterpart of the authentication filters as
| it simply checks that the current user is not logged in. A redirect
| response will be issued if they are, which you may freely change.
|
*/

Route::filter('guest', function()
{
    if (Auth::check()) return Redirect::to('/');
});

/*
|--------------------------------------------------------------------------
| CSRF Protection Filter
|--------------------------------------------------------------------------
|
| The CSRF filter is responsible for protecting your application against
| cross-site request forgery attacks. If this special token in a user
| session does not match the one given in this request, we'll bail.
|
*/

Route::filter('csrf', function()
{
    if (Session::token() !== Input::get('_token'))
    {
        throw new Illuminate\Session\TokenMismatchException;
    }
});

/*
|--------------------------------------------------------------------------
| Mobile-CI Filter
|--------------------------------------------------------------------------
|
| The CSRF filter is responsible for protecting your application against
| cross-site request forgery attacks. If this special token in a user
| session does not match the one given in this request, we'll bail.
|
*/
Route::filter('orbit-settings', function()
{
    if (! App::make('orbitSetting')->getSetting('current_retailer')) {
        throw new Exception ('You have to setup current retailer first on Admin Portal.');
    }

    $browserLang = substr(Request::server('HTTP_ACCEPT_LANGUAGE'), 0, 2);

    $currentRetailer = App::make('current_retailer');
    $merchantLang    = null;
    if ($currentRetailer) {
       $merchantLang = $currentRetailer->parent->mobile_default_language;
    }

    if ($merchantLang == 'user') {
        if (! empty($browserLang) AND in_array($browserLang, Config::get('orbit.languages', ['en']))) {
            // Set Browser Lang
            App::setLocale($browserLang);
        } else {
            // Fallback to 'en'
            App::setLocale('en');
        }
    } else {
        // Set Merchant Setting Lang
        if (! empty($merchantLang)) {
            App::setLocale($merchantLang);
        } else {
            // Fallback to 'en'
            App::setLocale('en');
        }
    }
});

Route::filter('enable-cart', function()
{
    $currentRetailer = App::make('current_retailer');
    if ($currentRetailer && $currentRetailer->parent->enable_shopping_cart != 'yes') {
        return Redirect::route('home');
    }
});
