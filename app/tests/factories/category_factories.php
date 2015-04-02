<?php
/*
 | Table: categories
 | Columns:
 | category_id int(10) UN AI PK
 | merchant_id int(10) UN
 | category_name   varchar(100)
 | category_level  int(10) UN
 | category_order  int(10) UN
 | description varchar(2000)
 | status  varchar(15)
 | created_by  bigint(20) UN
 | modified_by bigint(20) UN
 | created_at  timestamp
 | updated_at  timestamp
 */

$factory('Category', [
    'merchant_id' => 'factory:Merchant',
    'created_by'  => 'factory:User',
    'modified_by' => 'factory:User',
    'category_name' => $faker->word
]);
