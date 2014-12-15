<?php
/**
 * Routes file for POS
 */

Route::get('pos/', function()
{
	return View::make('pos.login');
});