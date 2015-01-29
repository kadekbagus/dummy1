<?php
if (! defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
use OrbitShop\API\v1\Helper\RecursiveFileIterator;

/*
|--------------------------------------------------------------------------
| Orbit API Class Map
|--------------------------------------------------------------------------
|
| Add additional class map that are not covered by composer autoloader.
| This class map might change very often so it is best to put it here
| instead.
|
*/
$orbit_additional_classmap = array(
    __DIR__ . DS . 'controllers' . DS . 'api' . DS . 'v1',
    __DIR__ . DS . 'controllers' . DS . 'intermediate' . DS . 'v1',
    __DIR__ . DS . '..' . DS . 'vendor' . DS . 'eventviva' . DS . 'php-image-resize' . DS . 'src'
);
ClassLoader::addDirectories($orbit_additional_classmap);
ClassLoader::register();

/*
|--------------------------------------------------------------------------
| Orbit API Event lists
|--------------------------------------------------------------------------
|
| Search all php files inside the 'events' directory.
|
*/
$event_dir = __DIR__ . DS . 'events' . DS . 'enabled';
$route_iterator = new RecursiveFileIterator($event_dir);
foreach ($route_iterator as $item) {
    $file = new SplFileInfo($item);
    if (! $file->isDir()) {
        if ($file->getExtension() === 'php') {
            require $item;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Orbit API Routes
|--------------------------------------------------------------------------
|
| Search all php files inside the 'routes' the directory.
|
*/
$route_dir = __DIR__ . DS . 'routes';
$route_iterator = new RecursiveFileIterator($route_dir);
foreach ($route_iterator as $item) {
    $file = new SplFileInfo($item);
    if (! $file->isDir()) {
        if ($file->getExtension() === 'php') {
            require $item;
        }
    }
}
