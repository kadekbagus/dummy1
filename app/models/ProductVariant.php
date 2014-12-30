<?php
/**
 * Class to represent product_variants table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class ProductVariant extends Eloquent
{
    protected $primaryKey = 'product_variant_id';
    protected $table = 'product_variants';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Product variant belongs to a merchant.
     */
    public function merchant()
    {
        $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    /**
     * Product variant belongs to a retailer (shop).
     */
    public function retailer()
    {
        $this->belongsTo('Retailer', 'retailer_id', 'merchant_id');
    }

    /**
     * Product variant belongs to a product.
     */
    public function product()
    {
        $this->belongsTo('Product', 'product_id', 'product_id');
    }

    /**
     * Product variant atribute belongs to a product attribute value.
     */
    public function attributeValue1()
    {
        $this->belongsTo('ProductAttributeValue', 'product_attribute_value_id1', 'product_attribute_value_id');
    }
    public function attributeValue2()
    {
        $this->belongsTo('ProductAttributeValue', 'product_attribute_value_id2', 'product_attribute_value_id');
    }
    public function attributeValue3()
    {
        $this->belongsTo('ProductAttributeValue', 'product_attribute_value_id3', 'product_attribute_value_id');
    }
    public function attributeValue4()
    {
        $this->belongsTo('ProductAttributeValue', 'product_attribute_value_id4', 'product_attribute_value_id');
    }
    public function attributeValue5()
    {
        $this->belongsTo('ProductAttributeValue', 'product_attribute_value_id5', 'product_attribute_value_id');
    }

    /**
     * The one who create this attribute value.
     */
    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    /**
     * The one who edit this attribute value.
     */
    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }
}
