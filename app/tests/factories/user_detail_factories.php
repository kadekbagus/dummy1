<?php

$factory('UserDetail', [
    'address_line1' => $faker->address,
    'postal_code'   => $faker->postcode,
    'phone'         => $faker->phoneNumber,
    'user_id'       => 'factory:User'
]);