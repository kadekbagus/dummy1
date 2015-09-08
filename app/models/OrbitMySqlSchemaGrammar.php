<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;

class OrbitMySqlSchemaGrammar extends \Illuminate\Database\Schema\Grammars\MySqlGrammar
{

    /**
     * Constructs and adds some modifiers handled here.
     */
    function __construct()
    {
        // must be added in this order
        $this->modifiers[] = "CharacterSet";
        $this->modifiers[] = "Collation";
    }

    /**
     * Returns DB type for binary string of a certain length.
     * @param Fluent $column
     * @return string
     */
    protected function typeBinaryString(Fluent $column)
    {
        return "binary({$column->length})";
    }

    /**
     * Get the SQL for specifiying a column's character set.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $column
     * @return string|null
     */
    protected function modifyCharacterSet(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, ['char', 'varchar', 'text', 'enum', 'set']) && $column->characterSet) {
            return " CHARACTER SET {$column->characterSet} ";
        }
    }

    /**
     * Get the SQL for specifiying a column's character set.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $column
     * @return string|null
     */
    protected function modifyCollation(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, ['char', 'varchar', 'text', 'enum', 'set']) && $column->collation) {
            return " COLLATE {$column->collation} ";
        }
    }
}