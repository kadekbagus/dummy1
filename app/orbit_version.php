<?php
/**
 * File which holds the version of Orbit Application.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */

/**
 * Main constant storing app version.
 *
 * Version number are formed from X.Y, where:
 *   X: Major version
 *   Y: Minor version
 */
if (! defined('ORBIT_APP_VERSION')) {
    define('ORBIT_APP_VERSION', '0.12-dev');
}

/**
 * Main constant storing application build number. This number should be
 * generated by the build system (continuous integration) such as Jenkins.
 */
if (! defined('ORBIT_APP_BUILD_NUMBER')) {
    define('ORBIT_APP_BUILD_NUMBER', 623);
}

/**
 * Constant storing codename.
 */
if (! defined('ORBIT_APP_CODENAME')) {
    define('ORBIT_APP_CODENAME', 'Sputnik');
}

/**
 * Constant storing the release date, ISO 8601.
 */
if (! defined('ORBIT_APP_RELEASE_DATE')) {
    define('ORBIT_APP_RELEASE_DATE', '2015-03-25 13-50-00');
}

/**
 * Constanct storing the build date, ISO 8601
 */
if (! defined('ORBIT_APP_BUILD_DATE')) {
    define('ORBIT_APP_BUILD_DATE', '2015-03-12_15-44-34');
}
