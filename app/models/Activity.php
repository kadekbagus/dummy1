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

    const ACTIVITY_REPONSE_OK = 'OK';
    const ACTIVITY_RESPONSE_FAILED = 'Failed';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Set the value of `group`, `ip_address`, `user_agent`, and `location_id`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Activity
     */
    public static function mobileCI()
    {
        $activity = new static();
        $activity->group = 'mobile-ci';
        $activity->ip_address = static::getIPAddress();
        $activity->user_agent = static::getUserAgent();
        $activity->location_id = Config::get('orbit.shop.id');

        return $activity;
    }

    /**
     * Set the value of `group`, `ip_address`, `user_agent`, and `location_id`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Activity
     */
    public static function pos()
    {
        $activity = new static();
        $activity->group = 'pos';
        $activity->ip_address = static::getIPAddress();
        $activity->user_agent = static::getUserAgent();
        $activity->location_id = Config::get('orbit.shop.id');

        return $activity;
    }

    /**
     * Set the value of `group`, `ip_address`, `user_agent`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Activity
     */
    public static function portal()
    {
        $activity = new static();
        $activity->group = 'portal';
        $activity->ip_address = static::getIPAddress();
        $activity->user_agent = static::getUserAgent();

        return $activity;
    }

    /**
     * Set the value of `activity_name`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $activityName
     * @return Activity
     */
    public function setActivityName($activityName)
    {
        $this->activity_name = $activityName;

        return $this;
    }

    /**
     * Set the value of `activity_name_long`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $activityNameLong
     * @return Activity
     */
    public function setActivityNameLong($activityNameLong)
    {
        $this->activity_name_long = $activityNameLong;

        return $this;
    }

    /**
     * Set the value of `activity_type`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @type string $type
     * @return Activity
     */
    public function setActivityType($type)
    {
        $this->activity_type = $type;

        return $this;
    }

    /**
     * Set the value of `notes`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $notes - Notes
     * @return Activity
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * Set the value of `user_id`, `user_email`, and `metadata_user`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string|User $user
     * @return Activity
     */
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

    /**
     * Set the value of `staff_id` and `metadata_staff`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param User $user
     * @return Activity
     */
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
     * Set the value of `location_id`, `location_name`, and `metadata_location`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Retailer|Merchant $location
     * @return Activity
     */
    public function setLocation($location)
    {
        if (is_object($location)) {
            if (TRUE === ($location instanceof Retailer)) {
                $location->parent;
            }

            $this->location_id = $location->merchant_id;
            $this->location_name = $location->name;
            $this->metadata_location = serialize($location->toArray());
        }

        return $this;
    }

    /**
     * Set the value of `object_id`, `object_name`, and `metadata_object`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Object $object
     * @return Activity
     */
    public function setObject($object)
    {
        if (is_object($object)) {
            $this->object_id = $object->getKeyName();
            $this->object_name = get_class($object);

            $this->metadata_object = serialize($object->toArray());
        }

        return $this;
    }

    /**
     * Set the value of 'response_status' field with OK status
     *
     * @author Rio Astamal
     * @return Activity
     */
    public function responseOK()
    {
        $this->response_status = static::ACTIVITY_REPONSE_OK;

        return $this;
    }

    /**
     * Set the value of 'response_status' field with Failed status
     *
     * @author Rio Astamal
     * @return Activity
     */
    public function responseFailed()
    {
        $this->response_status = static::ACTIVITY_RESPONSE_FAILED;

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
     * An activity belongs to an Object (Product)
     */
    public function product()
    {
        return $this->belongsToObject('Product', 'object_id', 'product_id');
    }

    /**
     * An activity could belongs to a ProductVariant
     */
    public function productVariant()
    {
        return $this->belongsToObject('ProductVariant', 'object_id', 'product_variant_id');
    }

    /**
     * An activity could belongs to a Promotion
     */
    public function promotion()
    {
        return $this->belongsToObject('Promotion', 'object_id', 'promotion_id');
    }

    /**
     * An activity could belongs to a Coupon
     */
    public function coupon()
    {
        return $this->belongsToObject('Coupon', 'object_id', 'promotion_id');
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

    /**
     * Override the save method
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return int
     */
    public function save(array $options = array())
    {
        if (App::environment() === 'testing') {
            // Skip saving
            return 1;
        }
        return parent::save($options);
    }

    /**
     * Get IP Address of the request.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    protected static function getIPAddress()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Detect the user agent of the request.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    protected static function getUserAgent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown-UA/?';
    }
}
