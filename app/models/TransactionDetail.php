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

    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'product_id');
    }

    public function productVariant()
    {
        return $this->belongsTo('ProductVariant', 'product_variant_id', 'product_variant_id');
    }

    /**
     * Simple join with transaction table
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTransactionJoin($builder)
    {
        $tablePrefix = DB::getTablePrefix();
        $builder->select("transaction_details.*")
            ->leftJoin("transactions", function ($join) {
                $join->on("transactions.transaction_id", "=", 'transaction_details.transaction_id');
                $join->where('transactions.status', '=', DB::raw('paid'));
            })
            ->leftJoin("merchants as {$tablePrefix}merchant", function ($join) {
                $join->on("transactions.merchant_id", "=", "merchant.merchant_id");
            })
            ->leftJoin("merchants as {$tablePrefix}retailer", function ($join) {
                $join->on("transactions.retailer_id", "=", "retailer.merchant_id");
            })
            ->leftJoin("products", function ($join) {
                $join->on("transaction_details.product_id", "=", "products.product_id");
            });

        return $builder;
    }

    /**
     * Simple join with transaction table
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeDeletedTransaction($builder)
    {
        return $builder->where('transactions.status', '!=', 'deleted');
    }

    /**
     * Get transactions details which has particular product attribute value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @param array|int $ids - List of value ids
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAttributeValueIds($builder, $ids=array())
    {
        $prefix = DB::getTablePrefix();
        return $builder->select('products.product_name',
                              DB::raw('transpose_variant.product_attribute_id,
                              transpose_variant.product_attribute_name,
                              transpose_variant.value_id product_attribute_value_id,
                              transpose_variant.attribute_value_name'),
                              'transaction_details.*')
                     ->join('products', 'products.product_id', '=', 'transaction_details.product_id')
                     ->join('product_variants', 'product_variants.product_variant_id', '=', 'transaction_details.product_variant_id')
                     ->join('transactions', 'transactions.transaction_id', '=', 'transaction_details.transaction_id')
                     ->join(DB::raw("(
                            select vr.product_variant_id, vr.product_id, vr.value_id, vr.number,
                            pav.value attribute_value_name, par.product_attribute_name, par.product_attribute_id
                            from
                            (
                                select product_id, product_variant_id, product_attribute_value_id1 as value_id, '1' as number
                                from orbit_shop.orb_product_variants
                                union all
                                select product_id, product_variant_id, product_attribute_value_id2 as value_id, '2' as number
                                from orbit_shop.orb_product_variants
                                union all
                                select product_id, product_variant_id, product_attribute_value_id3 as value_id, '3' as number
                                from orbit_shop.orb_product_variants
                                union all
                                select product_id, product_variant_id, product_attribute_value_id4 as value_id, '4' as number
                                from orbit_shop.orb_product_variants
                                union all
                                select product_id, product_variant_id, product_attribute_value_id5 as value_id, '5' as number
                                from orbit_shop.orb_product_variants
                            ) as vr
                            join orb_product_attribute_values pav on pav.product_attribute_value_id=vr.value_id
                            join orb_product_attributes par on par.product_attribute_id=pav.product_attribute_id
                            where vr.value_id is not null
                    ) transpose_variant"), DB::raw('`transpose_variant`.`product_variant_id`'), '=', 'product_variants.product_variant_id')
                    ->whereIn(DB::raw('`transpose_variant`.`value_id`'), $ids);
    }

    /**
     * Get Transactions Detail Report Based
     * @param \Illuminate\Database\Eloquent\Builder $builder
     */
    public function scopeDetailSalesReport($builder)
    {
        $tablePrefix  = DB::getTablePrefix();
        $transactions = $builder->select(
                'transactions.transaction_id',
                'transaction_details.product_code as product_sku',
                'transaction_details.product_id',
                'transaction_details.product_name',
                'transaction_details.quantity',
                DB::raw("ifnull({$tablePrefix}transaction_details.variant_price ,{$tablePrefix}transaction_details.price) as price"),
                'transactions.payment_method',
                DB::raw("(
                        case payment_method
                           when 'cash' then 'Cash'
                           when 'online_payment' then 'Online Payment'
                           when 'paypal' then 'Paypal'
                           when 'card' then 'Card'
                           else payment_method
                        end
                    ) as payment_type"),
                'transaction_details.created_at',
                DB::raw("sum(ifnull({$tablePrefix}tax.total_tax, 0)) as total_tax"),
                DB::raw("(quantity * (ifnull({$tablePrefix}transaction_details.variant_price ,{$tablePrefix}transaction_details.price) + sum(ifnull({$tablePrefix}tax.total_tax, 0)))) as sub_total"),
                'cashier.user_firstname as cashier_user_firstname',
                'cashier.user_lastname as cashier_user_lastname',
                DB::raw("concat({$tablePrefix}cashier.user_firstname, ' ', {$tablePrefix}cashier.user_lastname) as cashier_user_fullname"),
                'customer.user_firstname as customer_user_firstname',
                'customer.user_lastname as customer_user_lastname',
                'customer.user_email as customer_user_email'
            )
            ->join("transactions", function ($join) {
                $join->on("transactions.transaction_id", '=', "transaction_details.transaction_id");
            })
            ->leftJoin("transaction_detail_taxes as {$tablePrefix}tax", function ($join) {
                $join->on("transaction_details.transaction_detail_id", '=', 'tax.transaction_detail_id');
            })
            ->leftJoin("users as {$tablePrefix}customer", function ($join) {
                $join->on('customer.user_id', '=', 'transactions.customer_id');
            })
            ->leftJoin("users as {$tablePrefix}cashier", function ($join) {
                $join->on('cashier.user_id', '=', 'transactions.cashier_id');
            });

        return $transactions;
    }
}
