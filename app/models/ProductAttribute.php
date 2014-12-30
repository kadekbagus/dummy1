<?php
/**
 * Class to represent the product_attributes table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class ProductAttribute extends Eloquent
{
    protected $primaryKey = 'product_attribute_id';
    protected $table = 'product_attributes';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;


    /**
     * Product attributes has many product attribute values.
     */
    public function values()
    {
        return $this->hasMany('ProductAttributeValue');
    }

    /**
     * The one who create this attribute.
     */
    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    /**
     * The one who edit this attribute.
     */
    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }
}
