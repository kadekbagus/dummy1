/**
 * Created with JetBrains PhpStorm.
 * User: julisman
 * Date: 1/8/14
 * Time: 3:18 PM
 * http://blog.julisman.com
 */

require.config({
    urlArgs: '_t=' + (+new Date()),
    paths: {
        'text'          : './../vendor/require/require.text'
    }
});

require([
    './app',
    './service'
],function(){
    angular.element(document).ready(function(){
        // bootstrapping angular module
        angular.bootstrap(document, ['app']);
    });
});