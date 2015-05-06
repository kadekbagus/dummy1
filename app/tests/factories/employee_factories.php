<?php
/*
 | Table: employees
 | Columns:
 | employee_id	bigint(20) UN AI PK
 | user_id	bigint(20) UN
 | employee_id_char	varchar(50)
 | position	varchar(50)
 | status	varchar(15)
 | created_at	timestamp
 | updated_at	timestamp
 */

$factory('Employee', [
    'user_id' => 'factory:User',
    'employee_id_char' => $faker->bothify('########'),
    'status' => 'active'
]);