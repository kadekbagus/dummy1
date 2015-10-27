<?php namespace DominoPOS\SymmetricDS\Model;

use Illuminate\Database\Eloquent\Model;

class Base extends Model {
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'last_update_time';

    protected $connection = 'symmetric';
    public $incrementing = false;

    public function save(array $options = array())
    {
        $this->exists = !is_null($this->find($this->getKey()));
        return parent::save($options);
    }
}
