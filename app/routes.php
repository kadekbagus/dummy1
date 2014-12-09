<?php
if (! defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

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
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($event_dir));
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
| Orbit API Routes
|--------------------------------------------------------------------------
|
| Search all php files inside the 'routes' the directory.
|
*/
$route_dir = __DIR__ . DS . 'routes';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($route_dir));
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
