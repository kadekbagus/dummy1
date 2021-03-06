<?php
/**
 * Widget for represent the structure of widget_retailer table.
 *
 * @author Rio Astamal <me@rioastamal.net?
 */
class WidgetRetailer extends Eloquent
{
    use GeneratedUuidTrait;
    protected $table = 'widgets';
    protected $primaryKey = 'widget_id';
}
