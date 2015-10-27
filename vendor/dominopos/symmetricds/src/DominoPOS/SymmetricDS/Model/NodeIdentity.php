<?php namespace DominoPOS\SymmetricDS\Model;


/**
 * @property string node_id
 */
class NodeIdentity extends Base {

    protected $table = 'node_identity';

    protected $primaryKey = 'node_id';

    public $timestamps = false;
}
