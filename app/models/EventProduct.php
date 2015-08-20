<?php

class EventProduct extends Eloquent
{
    /**
     * EventProduct Model
     *
     * @author kadek <kadek@dominopos.com>
     */

    protected $table = 'event_product';

    protected $primaryKey = 'event_product_id';

    public function event()
    {
        return $this->belongsTo('Event', 'event_id', 'event_id');
    }

    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'product_id');
    }
}
