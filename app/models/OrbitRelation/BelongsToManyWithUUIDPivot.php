<?php
namespace OrbitRelation;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Orbit\EncodedUUID;

/**
 * Just like Eloquent's BelongsToMany but this one generates UUIDs for the pivot table.
 *
 * It requires the pivot table's key to be specified.
 *
 * @package OrbitRelation
 */
class BelongsToManyWithUUIDPivot extends BelongsToMany {

    /**
     * @var string the name of the pivot table's key.
     */
    protected $pivotKey;

    public function __construct(Builder $query, Model $parent, $table, $foreignKey, $otherKey, $pivotKey, $relationName = null)
    {
        parent::__construct($query, $parent, $table, $foreignKey, $otherKey,
            $relationName);
        $this->pivotKey = $pivotKey;
    }


    protected function createAttachRecord($id, $timed)
    {
        $record = parent::createAttachRecord($id, $timed);
        $record[$this->pivotKey] = EncodedUUID::make();
        return $record;
    }

}