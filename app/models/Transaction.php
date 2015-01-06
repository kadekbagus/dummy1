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

}