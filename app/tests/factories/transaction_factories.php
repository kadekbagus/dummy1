<?php
// Transactions Factory
use Laracasts\TestDummy\Factory;

$factory('Transaction', function ($faker) {
    return [
        'cashier_id'  => 'factory:User',
        'transaction_code' => $faker->bothify('TRX-??-####'),
        'payment_method'  => $faker->word,
        'customer_id' => 'factory:User',
        'merchant_id' => 'factory:Merchant',
        'retailer_id' => 'factory:Merchant',
        'status'      => 'paid'
    ];
});

$factory('TransactionDetail', function (\Faker\Generator $faker) {
    $product = Factory::create('Product');

    return [
        'transaction_id' => 'factory:Transaction',
        'product_id'   =>  $product->product_id,
        'product_name' => $product->product_name,
        'product_code' => $product->product_code,
        'upc'          => $product->upc_code,
        'price'        => $product->price,
        'quantity'     => $faker->numberBetween(1, 10)
    ];
});


/** @var $faker \Faker\Generator */
$factory('TransactionDetailTax', function ($faker) {
    return [
        'tax_name' => $faker->word
    ];
});
