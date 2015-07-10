<?php
/**
 * So we register to listen to the model's "creating" event in the trait's boot method,
 * but the dispatcher is reset during unit testing. The "booted" flag however is not cleared,
 * so the boot method that registers the events will not run again.
 *
 * This is a workaround where the boot method registers itself with a static registry, then
 * when this file is called again (app reset), we boot the trait again so it registers itself
 * with the Laravel dispatcher again.
 *
 * For more info see:
 * https://github.com/laravel/framework/issues/1181#issuecomment-42636346
 */
if (App::environment('testing')) {
    foreach (\Orbit\EncodedUUID::getModelsUsing() as $class_name) {
        $class_name::bootGeneratedUuidTrait();
    }
}