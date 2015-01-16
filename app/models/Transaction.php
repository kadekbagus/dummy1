<?php
class Transaction extends Eloquent
{
    /**
    * Transaction model
    *
    * @author kadek <kadek@dominopos.com>
    */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'transactions';

    protected $primaryKey = 'transaction_id';

    public function details()
    {
        return $this->hasMany('TransactionDetail', 'transaction_id', 'transaction_id');
    }

    public function cashier()
    {
        return $this->belongsTo('User', 'cashier_id', 'user_id');
    }

    public function user()
    {
        return $this->belongsTo('User', 'customer_id', 'user_id');
    }
}
