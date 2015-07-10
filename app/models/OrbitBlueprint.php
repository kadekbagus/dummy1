<?php

/**
 * Custom Blueprint subclass with extra methods for the more uncommon field types.
 */
class OrbitBlueprint extends \Illuminate\Database\Schema\Blueprint
{
    public function binaryString($column, $length = 128)
    {
        return $this->addColumn('binaryString', $column, compact('length'));
    }

    public function binaryUuid($column)
    {
        return $this->binaryString($column, 16); // 128 bits = 16 bytes
    }

    public function encodedUuid($column)
    {
        // 3TJa fUYD bTuF 85Yr 93Ip LV
        return $this->addColumn('char', $column, ['length' => 22, 'character_set' => 'ascii', 'collation' => 'ascii_bin']);
    }
}