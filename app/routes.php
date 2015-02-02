<?php
use OrbitShop\API\v1\Helper\RecursiveFileIterator;

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
$orbitAdditionalClassMap = array(
    __DIR__ . DS . 'controllers' . DS . 'api' . DS . 'v1',
    __DIR__ . DS . 'controllers' . DS . 'intermediate' . DS . 'v1',
    __DIR__ . DS . '..' . DS . 'vendor' . DS . 'eventviva' . DS . 'php-image-resize' . DS . 'src'
);
ClassLoader::addDirectories($orbitAdditionalClassMap);
ClassLoader::register();

/*
|--------------------------------------------------------------------------
| Orbit API Event lists
|--------------------------------------------------------------------------
|
| Search all php files inside the 'events' directory.
|
*/
// Callback which returns only 'php' extension
$onlyPHPExt = function($file, $fullPath)
{
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        return TRUE;
    }

    return FALSE;
};
$orbitEventDir = __DIR__ . DS . 'events' . DS . 'enabled';
$recursiveIterator = RecursiveFileIterator::create($orbitEventDir)
                                          ->setCallbackMatcher($onlyPHPExt);
foreach ($recursiveIterator->get() as $file) {
    require $orbitEventDir . DS . $file;
}
/*
|--------------------------------------------------------------------------
| Orbit API Routes
|--------------------------------------------------------------------------
|
| Search all php files inside the 'routes' the directory.
|
*/
$orbitRouteDir = __DIR__ . DS . 'routes';
$recursiveIterator->setDirectory($orbitRouteDir);
foreach ($recursiveIterator->get() as $file) {
    require $orbitRouteDir . DS . $file;
}
