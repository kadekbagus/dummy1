<?php
/**
 * Class to represent the personal interests table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class PersonalInterest extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'personal_interests';
    protected $primaryKey = 'personal_interest_id';

}
