<?php
class Token extends Eloquent
{
    /**
    * Token Model
    *
    * @author Tian <tian@dominopos.com>
    */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'tokens';
    
    protected $primaryKey = 'token_id';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

}
