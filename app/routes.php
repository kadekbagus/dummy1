<?php

/*
|--------------------------------------------------------------------------
| Orbit API Roites
|--------------------------------------------------------------------------
|
| Search all php files inside the 'routes' the directory.
|
*/
$directory = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'routes';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
$it->rewind();
while ($it->valid()) {
    if (! $it->isDot()) {
        // Only for php files
        if ($it->getExtension() === 'php') {
            $fullpath = $it->key();
            require $fullpath;
        }
    }
    $it->next();
}

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::get('/', function()
{
    return View::make('hello');
});
