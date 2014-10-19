<?php
/**
 * Traits for storing common method that used by models which has 'status'
 * column.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
trait ModelStatusTrait
{
   /**
     * Scope to filter records based on status field. Only return records which
     * had value 'active'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeActive($query)
    {
        return $query->whereStatus('active');
    }

    /**
     * Scope to filter records based on status field. Only return records which
     * had value 'blocked'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeBlocked($query)
    {
        return $query->whereStatus('blocked');
    }

    /**
     * Scope to filter records based on status field. Only return records which
     * had value 'deleted'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeWithDeleted($query)
    {
        return $query->whereStatus('deleted');
    }

    /**
     * Scope to filter records based on status field. Only return records which
     * had value 'deleted'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeExcludeDeleted($query)
    {
        return $query->where('status', '!=', 'deleted');
    }

    /**
     * Scope to change the status of an apikey record to 'active'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Apikey
     */
    public function scopeMakeActive()
    {
        $this->status = 'active';

        return $this;
    }

    /**
     * Scope to change the status of an apikey record to 'blocked'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Apikey
     */
    public function scopeMakeBlocked()
    {
        $this->status = 'blocked';

        return $this;
    }

    /**
     * Override Eloquent delete behaviour by only changing the value of `status`
     * columnt to 'deleted'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function delete()
    {
        $this->status = 'deleted';

        return $this->save();
    }
}
