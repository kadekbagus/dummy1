<?php namespace Orbit;

/**
 * Helper to create controller builder export
 * Class Builder
 * @package Orbit
 *
 * @author Yudi Rahono <yudi.rahono@dominopos.com>
 */
class Builder {
    /**
     * Complete Builder ready to get from database
     * @var \Illuminate\Database\Eloquent\Builder $builder
     */
    private $builder;

    /**
     * Unsorted Builder used to count and another sub query purposes
     * @var \Illuminate\Database\Eloquent\Builder $unsorted
     */
    private $unsorted;

    public static function create()
    {
        return new static();
    }

    public function setBuilder($builder)
    {
        $this->builder = $builder;

        return $this;
    }

    public function setUnsorted($unsorted)
    {
        $this->unsorted = $unsorted;

        return $this;
    }

    public function getBuilder()
    {
        return $this->builder;
    }

    public function getUnsorted()
    {
        return $this->unsorted;
    }
}
