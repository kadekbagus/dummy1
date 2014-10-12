#!/bin/bash
#
echo "-------------------------------------------------------"
echo " Orbit API Base Library Unit Test"
echo "-------------------------------------------------------"
cd vendor/dominopos/orbit-api
composer dump-autoload
phpunit

# Travese back to the original root directory
cd ../../..

echo "-------------------------------------------------------"
echo " Orbit API Application Unit Test"
echo "-------------------------------------------------------"
phpunit && {
  echo "-------------------------------------------------------"
  echo " Deploying Orbit API Application"
  echo "-------------------------------------------------------"
}
