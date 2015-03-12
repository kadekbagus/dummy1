#!/bin/bash
#
# @author Rio Astamal <me@rioastamal.net>
# @desc Increment the build number of the application.
#

# Path to the php file which storing the build number
PHP_VERSION_PATH="app/orbit_version.php"

# We are interesting with this line
# --> define('ORBIT_APP_BUILD_NUMBER', XYZ);
# --> define('ORBIT_APP_BUILD_DATE', ABC);
#
# We should replace the 'XYZ' with the BUILD_NUMBER env and ABC with build date.
echo "Bumping build number to ${BUILD_NUMBER}..."
sed -i "s/\(ORBIT_APP_BUILD_NUMBER\x27,\)\s\([0-9]\+\)/\1 $BUILD_NUMBER/" ${PHP_VERSION_PATH}

echo "Bumping build date to ${BUILD_ID}..."
sed -i "s/\(ORBIT_APP_BUILD_DATE\x27,\)\s\(\x27*\x27\));/\1 \x27$BUILD_ID\x27);/" ${PHP_VERSION_PATH}
