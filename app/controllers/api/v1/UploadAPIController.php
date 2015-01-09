<?php
/**
 * An API controller for managing file uploads.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitUploader\UploaderConfig;
use DominoPOS\OrbitUploader\UploaderMessage;
use DominoPOS\OrbitUploader\Uploader;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use \Exception;

class UploadAPIController extends ControllerAPI
{
    /**
     * From what part of the code this API are called from.
     *
     * @var string
     */
    protected $calledFrom = 'default';

    /**
     * Generic method for saving the uploaded metadata to the Media table on
     * the database.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    public function saveMetadata($object, $metadata)
    {
        $result = array();

        foreach ($metadata as $i=>$file) {
            // Save original file meta data into Media table
            $media = new Media();
            $media->object_id = $object['id'];
            $media->object_name = $object['name'];
            $media->media_name_id = $object['media_name_id'];
            $media->media_name_long = sprintf('%s_orig', $object['media_name_id']);
            $media->file_name = $file['file_name'];
            $media->file_extension = $file['file_ext'];
            $media->file_size = $file['file_size'];
            $media->mime_type = $file['mime_type'];
            $media->path = $file['path'];
            $media->realpath = $file['realpath'];
            $media->metadata = NULL;
            $media->modified_by = $object['modified_by'];
            $media->save();
            $result[] = $media;

            // Save the cropped, resized and scaled if any
            foreach (array('resized', 'cropped', 'scaled') as $variant) {
                // Save each profile
                foreach ($file[$variant] as $profile=>$finfo) {
                    $media = new Media();
                    $media->object_id = $object['id'];
                    $media->object_name = $object['name'];
                    $media->media_name_id = $object['media_name_id'];
                    $media->media_name_long = sprintf('%s_%s_%s', $object['media_name_id'], $variant, $profile);
                    $media->file_name = $finfo['file_name'];
                    $media->file_extension = $file['file_ext'];
                    $media->file_size = $finfo['file_size'];
                    $media->mime_type = $file['mime_type'];
                    $media->path = $finfo['path'];
                    $media->realpath = $finfo['realpath'];
                    $media->metadata = NULL;
                    $media->modified_by = $object['modified_by'];
                    $media->save();
                    $result[] = $media;
                }
            }
        }

        return $result;
    }

    /**
     * Upload logo for Merchant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadMerchantLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadmerchantlogo.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadmerchantlogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadmerchantlogo.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('edit_merchant')) {
                    Event::fire('orbit.upload.postuploadmerchantlogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadmerchantlogo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $images = OrbitInput::files('images');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    'images'      => $images,
                ),
                array(
                    'merchant_id'   => 'required|numeric|orbit.empty.merchant',
                    'images'        => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadmerchantlogo.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadmerchantlogo.after.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->beginTransaction();
            }

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.merchant');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'merchant')
                              ->where('media_name_id', 'merchant_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($merchant)
            {
                $merchant_id = $merchant->merchant_id;
                $slug = Str::slug($merchant->name);
                $file['new']->name = sprintf('%s-%s', $merchant_id, $slug);
            };

            // Load the orbit configuration for merchant upload logo
            $uploadLogoConfig = Config::get('orbit.upload.merchant.logo');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadmerchantlogo.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => 'merchant',
                'media_name_id' => 'merchant_logo',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $merchant->logo = $uploaded[0]['path'];
                $merchant->save();
            }

            Event::fire('orbit.upload.postuploadmerchantlogo.after.save', array($this, $merchant, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.merchant.logo');

            // Commit the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadmerchantlogo.after.commit', array($this, $merchant, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadmerchantlogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadmerchantlogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadmerchantlogo.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadmerchantlogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadmerchantlogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete logo for a merchant.
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                  (required) - ID of the merchant
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMerchantLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletemerchantlogo.before.auth', array($this));

            if (! $this->calledFrom('merchant.new, merchant.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletemerchantlogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletemerchantlogo.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_merchant')) {
                    Event::fire('orbit.upload.postdeletemerchantlogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postdeletemerchantlogo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                ),
                array(
                    'merchant_id'   => 'required|numeric|orbit.empty.merchant',
                )
            );

            Event::fire('orbit.upload.postdeletemerchantlogo.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletemerchantlogo.after.validation', array($this, $validator));

            if (! $this->calledFrom('merchant.new,merchant.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.merchant');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'merchant')
                              ->where('media_name_id', 'merchant_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletemerchantlogo.before.save', array($this, $merchant));

            // Update the `logo` field which store the original path of the logo
            // This is temporary since right now the business rules actually
            // only allows one logo per merchant
            $merchant->logo = NULL;
            $merchant->save();

            Event::fire('orbit.upload.postdeletemerchantlogo.after.save', array($this, $merchant));

            $this->response->data = $merchant;
            $this->response->message = Lang::get('statuses.orbit.uploaded.merchant.delete_logo');

            if (! $this->calledFrom('merchant.new,merchant.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletemerchantlogo.after.commit', array($this, $merchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletemerchantlogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('merchant.new,merchant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletemerchantlogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('merchant.new,merchant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletemerchantlogo.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            if (! $this->calledFrom('merchant.new,merchant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletemerchantlogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('merchant.new, merchant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletemerchantlogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload photo for a product.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `product_id`                  (required) - ID of the product
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadProductImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadproductimage.before.auth', array($this));

            if (! $this->calledFrom('product.new, product.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadproductimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadproductimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('edit_product')) {
                    Event::fire('orbit.upload.postuploadproductimage.authz.notallowed', array($this, $user));
                    $editProductLang = Lang::get('validation.orbit.actionlist.update_product');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editProductLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadproductimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $product_id = OrbitInput::post('product_id');
            $images = OrbitInput::files('images');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'product_id'    => $product_id,
                    'images'        => $images,
                ),
                array(
                    'product_id'   => 'required|numeric|orbit.empty.product',
                    'images'       => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadproductimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadproductimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('product.new,product.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $product = App::make('orbit.empty.product');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $product->product_id)
                              ->where('object_name', 'product')
                              ->where('media_name_id', 'product_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Callback to rename the file, we will format it as follow
            // [PRODUCT_ID]-[PRODUCT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($product)
            {
                $product_id = $product->product_id;
                $slug = Str::slug($product->product_name);
                $file['new']->name = sprintf('%s-%s', $product_id, $slug);
            };

            // Load the orbit configuration for product upload
            $uploadProductConfig = Config::get('orbit.upload.product.main');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadProductConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadproductimage.before.save', array($this, $product, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Save the files metadata
            $object = array(
                'id'            => $product->product_id,
                'name'          => 'product',
                'media_name_id' => 'product_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $product->image = $uploaded[0]['path'];
                $product->save();
            }

            Event::fire('orbit.upload.postuploadproductimage.after.save', array($this, $product, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.product.main');

            if (! $this->calledFrom('product.new,product.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadproductimage.after.commit', array($this, $product, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadproductimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadproductimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadproductimage.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadproductimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('product.new, product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadproductimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete photo for a product.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `product_id`                  (required) - ID of the product
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteProductImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeleteproductimage.before.auth', array($this));

            if (! $this->calledFrom('product.new, product.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeleteproductimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeleteproductimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_product')) {
                    Event::fire('orbit.upload.postdeleteproductimage.authz.notallowed', array($this, $user));
                    $editProductLang = Lang::get('validation.orbit.actionlist.update_product');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editProductLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postdeleteproductimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $product_id = OrbitInput::post('product_id');

            $validator = Validator::make(
                array(
                    'product_id'    => $product_id,
                ),
                array(
                    'product_id'   => 'required|numeric|orbit.empty.product',
                )
            );

            Event::fire('orbit.upload.postdeleteproductimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeleteproductimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('product.new,product.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $product = App::make('orbit.empty.product');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $product->product_id)
                              ->where('object_name', 'product')
                              ->where('media_name_id', 'product_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeleteproductimage.before.save', array($this, $product));

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            $product->image = NULL;
            $product->save();

            Event::fire('orbit.upload.postdeleteproductimage.after.save', array($this, $product));

            $this->response->data = $product;
            $this->response->message = Lang::get('statuses.orbit.uploaded.product.delete_image');

            if (! $this->calledFrom('product.new,product.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeleteproductimage.after.commit', array($this, $product));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeleteproductimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeleteproductimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeleteproductimage.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeleteproductimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('product.new, product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeleteproductimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload photo for a promotion.
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                (required) - ID of the promotion
     * @param file|array `images`                      (required) - Promotion images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadPromotionImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadpromotionimage.before.auth', array($this));

            if (! $this->calledFrom('promotion.new, promotion.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadpromotionimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadpromotionimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_promotion')) {
                    Event::fire('orbit.upload.postuploadpromotionimage.authz.notallowed', array($this, $user));
                    $editPromotionLang = Lang::get('validation.orbit.actionlist.update_promotion');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editPromotionLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadpromotionimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $promotion_id = OrbitInput::post('promotion_id');
            $images = OrbitInput::files('images');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'promotion_id'  => $promotion_id,
                    'images'        => $images,
                ),
                array(
                    'promotion_id'  => 'required|numeric|orbit.empty.promotion',
                    'images'        => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadpromotionimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadpromotionimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $promotion = App::make('orbit.empty.promotion');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $promotion->promotion_id)
                              ->where('object_name', 'promotion')
                              ->where('media_name_id', 'promotion_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Callback to rename the file, we will format it as follow
            // [PRODUCT_ID]-[PRODUCT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($promotion)
            {
                $promotion_id = $promotion->promotion_id;
                $slug = Str::slug($promotion->promotion_name);
                $file['new']->name = sprintf('%s-%s', $promotion_id, $slug);
            };

            // Load the orbit configuration for promotion upload
            $uploadPromotionConfig = Config::get('orbit.upload.promotion.main');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadPromotionConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadpromotionimage.before.save', array($this, $promotion, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Save the files metadata
            $object = array(
                'id'            => $promotion->promotion_id,
                'name'          => 'promotion',
                'media_name_id' => 'promotion_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per promotion
            if (isset($uploaded[0])) {
                $promotion->image = $uploaded[0]['path'];
                $promotion->save();
            }

            Event::fire('orbit.upload.postuploadpromotionimage.after.save', array($this, $promotion, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.promotion.main');

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadpromotionimage.after.commit', array($this, $promotion, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadpromotionimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadpromotionimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadpromotionimage.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadpromotionimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('promotion.new, promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadpromotionimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete photo for a promotion.
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                  (required) - ID of the promotion
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeletePromotionImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletepromotionimage.before.auth', array($this));

            if (! $this->calledFrom('promotion.new, promotion.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletepromotionimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletepromotionimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_promotion')) {
                    Event::fire('orbit.upload.postdeletepromotionimage.authz.notallowed', array($this, $user));
                    $editPromotionLang = Lang::get('validation.orbit.actionlist.update_promotion');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editPromotionLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postdeletepromotionimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $promotion_id = OrbitInput::post('promotion_id');

            $validator = Validator::make(
                array(
                    'promotion_id'    => $promotion_id,
                ),
                array(
                    'promotion_id'   => 'required|numeric|orbit.empty.promotion',
                )
            );

            Event::fire('orbit.upload.postdeletepromotionimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletepromotionimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $promotion = App::make('orbit.empty.promotion');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $promotion->promotion_id)
                              ->where('object_name', 'promotion')
                              ->where('media_name_id', 'promotion_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletepromotionimage.before.save', array($this, $promotion));

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per promotion
            $promotion->image = NULL;
            $promotion->save();

            Event::fire('orbit.upload.postdeletepromotionimage.after.save', array($this, $promotion));

            $this->response->data = $promotion;
            $this->response->message = Lang::get('statuses.orbit.uploaded.promotion.delete_image');

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletepromotionimage.after.commit', array($this, $promotion));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletepromotionimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletepromotionimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletepromotionimage.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletepromotionimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('promotion.new, promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletepromotionimage.before.render', array($this, $output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        if ($this->calledFrom('default')) {
            // Check the existance of merchant id
            $user = $this->api->user;
            Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) use ($user) {
                $merchant = Merchant::excludeDeleted()
                            ->allowedForUser($user)
                            ->where('merchant_id', $value)
                            ->first();

                if (empty($merchant)) {
                    return FALSE;
                }

                App::instance('orbit.empty.merchant', $merchant);

                return TRUE;
            });
        }

        if ($this->calledFrom('default')) {
            // Check the existance of product id
            Validator::extend('orbit.empty.product', function ($attribute, $value, $parameters) {
                $product = Product::excludeDeleted()
                            ->where('product_id', $value)
                            ->first();

                if (empty($product)) {
                    return FALSE;
                }

                App::instance('orbit.empty.product', $product);

                return TRUE;
            });
        }

        if ($this->calledFrom('default')) {
            // Check the existance of promotion id
            Validator::extend('orbit.empty.promotion', function ($attribute, $value, $parameters) {
                $promotion = Promotion::excludeDeleted()
                            ->where('promotion_id', $value)
                            ->first();

                if (empty($promotion)) {
                    return FALSE;
                }

                App::instance('orbit.empty.promotion', $promotion);

                return TRUE;
            });
        }

        // Check the images, we are allowed array of images but not more that one
        Validator::extend('nomore.than.one', function ($attribute, $value, $parameters) {
            if (is_array($value['name']) && count($value['name']) > 1) {
                return FALSE;
            }

            return TRUE;
        });
    }

    public function calledFrom($list)
    {
        if (! is_array($list))
        {
            $list = explode(',', (string)$list);
            $list = array_map('trim', $list);
        }

        return in_array($this->calledFrom, $list);
    }

    /**
     * Set the called from value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $from The source of the caller
     * @return UploadAPIController
     */
    public function setCalledFrom($from)
    {
        $this->calledFrom = $from;

        return $this;
    }
}
