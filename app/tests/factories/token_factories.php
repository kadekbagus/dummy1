<?php
$factory('Token', 'token_reset_password', [
    'token_name'  => 'reset_password',
    'token_value' => $faker->sha1,
    'expire'      => $faker->dateTimeBetween('+2 day', '+7 day'),
    'status'      => 'active',
]);

$factory('Token', 'token_user_registration_mobile', [
    'token_name'  => 'user_registration_mobile',
    'token_value' => $faker->sha1,
    'expire'      => $faker->dateTimeBetween('+2 day', '+7 day'),
    'status'      => 'active',
]);

