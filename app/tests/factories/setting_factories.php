<?php
/*
 | Table: settings
 | Columns:
 | setting_id	bigint(20) UN AI PK
 | setting_name	varchar(100)
 | setting_value	text
 | object_id	bigint(20) UN
 | object_type	varchar(100)
 | modified_by	bigint(20) UN
 | status	varchar(15)
 | created_at	timestamp
 | updated_at	timestamp
*/

$factory('Setting', [
    'setting_name'   => $faker->word,
    'setting_value'  => $faker->words(3),
    'status'         => 'active',
]);