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
