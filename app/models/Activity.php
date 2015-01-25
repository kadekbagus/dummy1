<?php
/**
 * Class for represent the activities table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitRelation\BelongsTo as BelongsToObject;

class Activity extends Eloquent
{
    protected $primaryKey = 'activity_id';
    protected $table = 'activities';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    public static function mobileCI()
    {
        $activity = new static();
        $activity->group = 'mobile-ci';
        $activity->ip_address = $_SERVER['REMOTE_ADDR'];
        $activity->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $activity->location_id = Config::get('orbit.shop.id');

        return $activity;
    }

    public static function pos()
    {
        $activity = new static();
        $activity->group = 'pos';
        $activity->ip_address = $_SERVER['REMOTE_ADDR'];
        $activity->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $activity->location_id = Config::get('orbit.shop.id');

        return $activity;
    }

    public static function portal()
    {
        $activity = new static();
        $activity->group = 'portal';
        $activity->ip_address = $_SERVER['REMOTE_ADDR'];
        $activity->user_agent = $_SERVER['HTTP_USER_AGENT'];

        return $activity;
    }

    public function setUser($user='guest')
    {
        if (is_object($user)) {
            $this->user_id = $user->user_id;
            $this->user_email = $user->user_email;
            $this->role_id = $user->role->role_id;
            $this->role = $user->role->role_name;

            $this->metadata_user = serialize($user->toArray());
        }

        if ($user === 'guest') {
            $this->user_id = 0;
            $this->user_email = 'guest';
            $this->role_id = 0;
            $this->role = 'Guest';
        }

        return $this;
    }

    public function setStaff($user)
    {
        if (is_object($user)) {
            $user->employee;

            $this->staff_id = $user->user_id;
            $this->metadata_staff = serialize($user->toArray());
        }

        return $this;
    }

    /**
     * Activity belongs to a User
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    /**
     * Activity belongs to a Location (retailer/shop)
     */
    public function retailer()
    {
        return $this->belongsTo('Retailer', 'location_id', 'merchant_id');
    }

    /**
     * Activity belongs to a Staff
     */
    public function staff()
    {
        return $this->belongsTo('User', 'staff_id', 'user_id');
    }

    /**
     * An activity belongs to an Object
     */
    public function product()
    {
        return $this->belongsToObject('Product', 'object_id', 'product_id');
    }

	/**
	 * Define an inverse one-to-one or many relationship.
     *
     * @author Rio Astamal <me@rioastamal.net>
	 *
	 * @param  string  $related
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @param  string  $relation
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function belongsToObject($related, $foreignKey = null, $otherKey = null, $relation = null)
	{
		// If no relation name was given, we will use this debug backtrace to extract
		// the calling method's name and use that as the relationship name as most
		// of the time this will be what we desire to use for the relationships.
		if (is_null($relation))
		{
			list(, $caller) = debug_backtrace(false);

			$relation = $caller['function'];
		}

		// If no foreign key was supplied, we can use a backtrace to guess the proper
		// foreign key name by using the name of the relationship function, which
		// when combined with an "_id" should conventionally match the columns.
		if (is_null($foreignKey))
		{
			$foreignKey = snake_case($relation).'_id';
		}

		$instance = new $related;

		// Once we have the foreign key names, we'll just create a new Eloquent query
		// for the related models and returns the relationship instance which will
		// actually be responsible for retrieving and hydrating every relations.
		$query = $instance->newQuery();

		$otherKey = $otherKey ?: $instance->getKeyName();

		return new BelongsToObject($query, $this, $foreignKey, $otherKey, $relation);
	}
}
