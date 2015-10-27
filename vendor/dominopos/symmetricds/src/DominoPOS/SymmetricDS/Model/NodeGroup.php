<?php namespace DominoPOS\SymmetricDS\Model;


/**
 * Class NodeGroup
 * @property string node_group_id
 * @package DominoPOS\SymmetricDS
 *
 */
class NodeGroup extends Base {
    protected $table = 'node_group';
    protected $primaryKey = 'node_group_id';

    /**
     * @var static
     */
    private static $cloud;

    /**
     * @var static
     */
    private static $merchant;

    /**
     * @var static
     */
    private static $retailer;

    const CLOUD    = 'cloud';
    const MERCHANT = 'merchant';
    const RETAILER = 'retailer';

    public static function seedDefaults()
    {
        static::$cloud    = static::findOrCreate(static::CLOUD);
        static::$merchant = static::findOrCreate(static::MERCHANT);
        static::$retailer = static::findOrCreate(static::RETAILER);

        if( ! in_array(static::MERCHANT, static::$cloud->nodeLink()->getRelatedIds()))
        {
            static::$cloud->nodeLink()->attach(static::$merchant, ['data_event_action' => 'W']);
        }

        if( ! in_array(static::CLOUD, static::$merchant->nodeLink()->getRelatedIds()))
        {
            static::$merchant->nodeLink()->attach(static::$cloud, ['data_event_action' => 'P']);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function nodeLink()
    {
        return $this->belongsToMany(
            'DominoPOS\\SymmetricDS\\Model\\NodeGroup',
            'node_group_link',
            'source_node_group_id',
            'target_node_group_id',
            'links'
        )->withTimestamps()->withPivot(['data_event_action']);
    }

    /**
     * @return Base
     */
    public static function getCloud()
    {
        return self::$cloud;
    }

    /**
     * @return Base
     */
    public static function getMerchant()
    {
        return self::$merchant;
    }

    /**
     * @return Base
     */
    public static function getRetailer()
    {
        return self::$retailer;
    }


    private static function findOrCreate($id)
    {
        $obj = static::find($id);
        if(is_null($obj)) {
            $obj = new static;
            $obj->node_group_id = $id;
            $obj->save();
        }

        return $obj;
    }

}
