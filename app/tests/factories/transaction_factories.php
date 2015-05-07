<?php
// Transactions Factory

$factory('Transaction', function ($faker) {
    return [
        'cashier_id'  => 'factory:User',
        'customer_id' => 'factory:User',
        'merchant_id' => 'factory:Merchant',
        'retailer_id' => 'factory:Merchant',
        'status'      => 'paid'
    ];
});

$factory('TransactionDetail', function ($faker) {
    return [
        'transaction_id' => 'factory:Transaction',
        'product_id' => 'factory:Product'
    ];
});
