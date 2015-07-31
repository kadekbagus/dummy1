<?php

trait GeneratedUuidTrait {

    public static function bootGeneratedUuidTrait()
    {
//        $my_class = get_called_class();
//        EncodedUUID::registerUseInModel($my_class);
        // on creating the model (before saving),
        // generate a UUID and ensure it does not
        // overwrite our generated ID with last_insert_id()
        static::creating(function($model) {
            // did not find a better place to put this...
            $model->setIncrementing(false);

            $key = $model->getKeyName();
            $model->setAttribute($key, \OrbitShop\API\V2\ObjectID::make());
        });
    }

    public static function getBootedStatus()
    {
        $class = get_called_class();

        return isset(static::$booted[$class]);
    }

}
