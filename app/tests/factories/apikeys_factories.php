<?php
/*
 | Table: apikeys
 | Columns:
 | apikey_id   bigint(20) UN AI PK
 | api_key varchar(100)
 | api_secret_key  varchar(255)
 | user_id bigint(20) UN
 | status  varchar(15)
 | created_at  timestamp
 | updated_at  timestamp
 */
$factory('Apikey', [
    'api_key'        => $faker->lexify('??????'),
    'api_secret_key' => $faker->lexify('??????????'),
    'status'         => 'active',
    'user_id'        => 'factory:User'
]);
