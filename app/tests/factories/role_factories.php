<?php
/*
 | Table: roles
 | Columns:
 | role_id int(10) UN AI PK
 | role_name   varchar(30)
 | role_order  int(10) UN
 | modified_by bigint(20) UN
 | created_at  timestamp
 | updated_at  timestamp
 */
$factory('Role', [
    'role_name' => 'Guest',
    'role_order' => 1
]);

$factory('Role', 'role_super_admin', [
    'role_name'  => 'Super Admin',
    'role_order' => 1
]);

$factory('Role', 'role_admin', [
    'role_name'  => 'Admin',
    'role_order' => 1
]);

$factory('Role', 'role_guest', [
    'role_name'  => 'Guest',
    'role_order' => 1
]);


$factory('Role', 'role_cashier', [
    'role_name'  => 'Cashier',
    'role_order' => 1
]);

$factory('Role', 'role_consumer', [
    'role_name'  => 'Consumer',
    'role_order' => 1
]);