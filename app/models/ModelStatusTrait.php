<?php
/**
 * Traits for storing common method that used by models which has 'status'
 * column.
 *
 * @author Rio Astamal <me@rioastamal.net>
 *
 * @property string status
 *
 * @method static \Illuminate\Database\Eloquent\Builder active($table = NULL)
 * @method static \Illuminate\Database\Eloquent\Builder blocked($table = NULL)
 * @method static \Illuminate\Database\Eloquent\Builder excludeDeleted($table = NULL)
 * @method static \Illuminate\Database\Eloquent\Builder inactive($table = NULL)
 * @method static \Illuminate\Database\Eloquent\Builder makeActive($table = NULL)
 * @method static \Illuminate\Database\Eloquent\Builder makeBlocked($table = NULL)
 * @method static \Illuminate\Database\Eloquent\Builder pending($table = NULL)
 * @method static \Illuminate\Database\Eloquent\Builder withDeleted($table = NULL)
 */
trait ModelStatusTrait
{
    /**
     * Method to append dot after a table name. Used on every scope.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $table
     * @return string
     */
    protected function appendDot($table=NULL)
    {
        if (! empty($table)) {
            // Append the dot using custom table name
            $table .= '.';
        }

        return $table;
    }

   /**
     * Scope to filter records based on status field. Only return records which
     * had value 'active'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Illuminate\Database\Eloquent\Builder $query
     * @param string $table Table name
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query, $table=NULL)
    {
        return $query->where($this->appendDot($table) . 'status', 'active');
    }

    /**
     * Scope to filter records based on status field. Only return records which
     * had value 'blocked'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param $query Illuminate\Database\Eloquent\Builder
     * @param string $table Table name
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function scopeBlocked($query, $table=NULL)
    {
        return $query->where($this->appendDot($table) . 'status', 'blocked');
    }

    /**
     * Scope to filter records based on status field. Only return records which
     * had value 'pending'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $table Table name
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query, $table=NULL)
    {
        return $query->where($this->appendDot($table) . 'status', 'pending');
    }

    /**
     * Scope to filter records based on status field. Only return records which
     * had value 'inactive'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $table Table name
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function scopeInactive($query, $table=NULL)
    {
        return $query->where($this->appendDot($table) . 'status', 'inactive');
    }

    /**
     * Scope to filter records based on status field. Only return records which
     * had value 'deleted'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $table Table name
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDeleted($query, $table=NULL)
    {
        return $query->where($this->appendDot($table) . 'status', 'deleted');
    }

    /**
     * Scope to filter records based on status field. Only return records which
     * had value 'deleted'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $table Table name
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeDeleted($query, $table=NULL)
    {
        return $query->where($this->appendDot($table) . 'status', '!=', 'deleted');
    }

    /**
     * Scope to change the status of an apikey record to 'active'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $table Table name
     * @return Apikey
     */
    public function scopeMakeActive($query, $table=NULL)
    {
        $this->status = $this->appendDot($table) . 'active';

        return $this;
    }

    /**
     * Scope to change the status of an apikey record to 'blocked'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $table Table name
     * @return Apikey
     */
    public function scopeMakeBlocked($query, $table=NULL)
    {
        $this->status = $this->appendDot($table) . 'blocked';

        return $this;
    }

    /**
     * Override Eloquent delete behaviour by only changing the value of `status`
     * column to 'deleted'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param boolean $force Force hard delete (wipe the record from database)
     * @param string $table Table name
     * @return boolean
     */
    public function delete($force=FALSE, $table=NULL)
    {
        if ($force === TRUE) {
            return parent::delete();
        }

        $this->status = $this->appendDot($table) . 'deleted';

        return $this->save();
    }
}
