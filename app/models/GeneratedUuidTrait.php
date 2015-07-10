<?php
use Illuminate\Database\Eloquent\Builder;

trait GeneratedUuidTrait {

    protected function insertAndSetId(Builder $query, $attributes)
    {
        $uuid = \Orbit\EncodedUUID::make();
        $key_name = $this->getKeyName();
        $attributes[$key_name] = $uuid;
        $query->insert($attributes);
        $this->attributes[$key_name] = $uuid;
    }

}