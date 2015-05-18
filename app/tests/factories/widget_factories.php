<?php

$factory('Widget', [
    'widget_type' => $faker->randomElement(['new_product', 'catalogue', 'promotion', 'coupon']),
    'merchant_id' => 'factory:Merchant',
    'widget_slogan' => $faker->words(5),
    'status' => 'active'
]);
