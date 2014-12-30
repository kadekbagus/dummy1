<?php
class TransactionDetail extends Eloquent
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

    protected $table = 'transaction_details';

    protected $primaryKey = 'transaction_detail_id';

    public function transaction()
    {
        return $this->belongsTo('Transaction', 'transaction_id', 'transaction_id');
    }

}