<?php
/**
 * An API controller for import data.
 *
 * @author Tian <tian@dominopos.com>
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitUploader\UploaderConfig;
use DominoPOS\OrbitUploader\UploaderMessage;
use DominoPOS\OrbitUploader\Uploader;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;

class ImportAPIController extends ControllerAPI
{
    /**
     * Generic method for saving the uploaded metadata to the Media table on
     * the database.
     *
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
            //$media->media_name_long = sprintf('%s_orig', $object['media_name_id']);
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
        }

        return $result;
    }

    /**
     * Import product data.
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                (required) - Merchant ID
     * @param file|array `products`                   (required) - Product file
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postImportProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.import.postimportproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.import.postimportproduct.after.auth', array($this));

            // Try to check access control list, does this merchant allowed to
            // perform this action
            $user = $this->api->user;

            Event::fire('orbit.import.postimportproduct.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('import_product')) {
                Event::fire('orbit.import.postimportproduct.authz.notallowed', array($this, $user));
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => Lang::get('validation.orbit.actionlist.import_product')));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'merchant owner'];
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.import.postimportproduct.after.authz', array($this, $user));

            // Register custom validation
            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');
            $products = OrbitInput::files('products');

            // validate input data
            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                    'products'      => $products,
                ),
                array(
                    'merchant_id'   => 'required|numeric|orbit.empty.merchant',
                    'products'      => 'required|nomore.than.one',
                ),
                array(
                   'nomore.than.one' => Lang::get('validation.max.array', array('max' => 1))
                )
            );

            Event::fire('orbit.import.postimportproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.import.postimportproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.merchant');

            // Delete old coupon image
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'merchant')
                              ->where('media_name_id', 'import_products');

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

            // Load the orbit configuration for import product
            $importProductConfig = Config::get('orbit.import.product.main');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-products_[ARRAY_NO].csv
            $renameFile = function($uploader, &$file, $dir) use ($merchant, $importProductConfig)
            {
                $merchant_id = $merchant->merchant_id;
                $file['new']->name = sprintf('%s-%s', $merchant_id, $importProductConfig['name']);
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($importProductConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.import.postimportproduct.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($products);

            // Save the files metadata
            $object = array(
                'id'                => $merchant->merchant_id,
                'name'              => 'merchant',
                'media_name_id'     => 'import_products',
                'modified_by'       => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.import.postimportproduct.after.save', array($this, $merchant, $uploader));

            // Commit the changes
            $this->commit();

            Event::fire('orbit.import.postimportproduct.after.commit', array($this, $merchant, $uploader));

            /*
             *
             * start importing products data procedures
             *
             */

            /* product csv format:
            Array No.   Column Name         Data Type
            0           Default SKU         string
            1           Product Name        string
            2           Short Description   string
            3           Long Description    string
            4           Default Price       decimal
            5           Tax1 Name           string
            6           Tax2 Name           string
            7           Default Barcode     string
            8           Family1 Name        string
            9           Family2 Name        string
            10          Family3 Name        string
            11          Family4 Name        string
            12          Family5 Name        string
            13          Variant1 Name       string
            14          Variant2 Name       string
            15          Variant3 Name       string
            16          Variant4 Name       string
            17          Variant5 Name       string
            18          Variant1 Value      string
            19          Variant2 Value      string
            20          Variant3 Value      string
            21          Variant4 Value      string
            22          Variant5 Value      string
            23          Variant SKU         string
            24          Variant Barcode     string
            25          Variant Price       decimal
            */

            // get csv file
            $file = $importProductConfig['path'] . DIRECTORY_SEPARATOR . $mediaList[0]['original']['file_name'];

            // get chunk size config
            $chunkSize = $importProductConfig['chunk_size'];

            // get error log max config
            $errorLogMax = $importProductConfig['error_log_max'];

            // error log
            $errorLog = array();

            // row counter for user error message row number
            $rowCounter = 0;

            // for condition if default_sku need to be checked for uniqueness in each row
            $previous_row_default_sku = '';

            // start validation
            Excel::filter('chunk')->load($file)->chunk($chunkSize, function($rows) use ($errorLogMax, &$errorLog, &$rowCounter, &$previous_row_default_sku)
            {
                foreach($rows as $row)
                {
                    // increase row counter by 1
                    $rowCounter++;

echo($rowCounter . '<br />');
var_dump($row->toArray());

                    // validate product
                    $default_sku = $row['default_sku']; // product_code
                    $product_name = $row['product_name'];
                    // $short_desc = $row[2];
                    // $long_desc = $row[3];
                    // $default_sku = $row[0];
                    // $default_sku = $row[0];
                    // $default_sku = $row[0];
                    // $default_sku = $row[0];
                    // $default_sku = $row[0];
                    // $default_sku = $row[0];
                    // $default_sku = $row[0];
                    // $default_sku = $row[0];
                    // $default_sku = $row[0];

//dd($default_sku);
                    // validation rule
                    $validator = Validator::make(
                        array(
                            'default_sku'           => $default_sku,
                            'product_name'          => $product_name,
                            // 'status'                => $status,
                            // 'category_id1'          => $category_id1,
                            // 'category_id2'          => $category_id2,
                            // 'category_id3'          => $category_id3,
                            // 'category_id4'          => $category_id4,
                            // 'category_id5'          => $category_id5,
                            // 'short_description'     => $short_description,
                            // 'price'                 => $price,
                            // 'merchant_tax_1'        => $merchant_tax_id1,
                        ),
                        array(
                            'default_sku'           => "required|orbit.exists.product.sku_code:{$previous_row_default_sku},{$default_sku}",
                            'product_name'          => 'required',
                            // 'status'                => 'required|orbit.empty.product_status',
                            // 'category_id1'          => 'numeric|orbit.empty.category_id1',
                            // 'category_id2'          => 'numeric|orbit.empty.category_id2',
                            // 'category_id3'          => 'numeric|orbit.empty.category_id3',
                            // 'category_id4'          => 'numeric|orbit.empty.category_id4',
                            // 'category_id5'          => 'numeric|orbit.empty.category_id5',
                            // 'short_description'     => 'required',
                            // 'price'                 => 'required',
                            // 'merchant_tax_1'        => 'required',
                        ),
                        array(
                            // Duplicate SKU error message
                            'orbit.exists.product.sku_code' => Lang::get('validation.orbit.exists.product.sku_code', [
                                'sku' => $default_sku
                            ])
                        )
                    );

                    // Run the validation
                    if ($validator->fails()) {
                        foreach($validator->messages()->all() as $msg)
                        {
                            // log error message to array
                            $errorMessage = array(
                                'row'       => $rowCounter,
                                'message'   => $msg
                            );
                            $errorLog[] = $errorMessage;

                            // if total error reach max error, then throw exception
                            if (count($errorLog) === $errorLogMax) {
                                $this->response->data = $errorLog; // !!!
                                OrbitShopAPI::throwInvalidArgument('error');
                            }
                        }
var_dump($errorLog);
                    }

                    // validate product variant (combination)
                    $variant1_value = $row['variant1_value'];
                    // $variant1_value = $row[18];
                    // $variant1_value = $row[18];
                    // $variant1_value = $row[18];

                    // set to current default_sku
                    $previous_row_default_sku = $default_sku;
                };
echo "CHUNKED". '<br />';

            });
dd('a');
            // start creating data
            Excel::filter('chunk')->load($file)->chunk($chunkSize, function($rows)
            {
                $this->beginTransaction();

                foreach($rows as $row)
                {
var_dump($row->toArray());
                };
echo "a";

                $this->commit();
            });

            //$this->response->data = $mediaList;
            //$this->response->message = Lang::get('statuses.orbit.uploaded.coupon.main');

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.import.postimportproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.import.postimportproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
//$this->response->data = null;         !!!
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.import.postimportproduct.query.error', array($this, $e));

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
        } catch (Exception $e) {
            Event::fire('orbit.import.postimportproduct.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.import.postimportproduct.before.render', array($this, $output));

        return $output;
    }

    protected function registerCustomValidation()
    {
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

        // Check the products, we are allowed array of images but not more that one
        Validator::extend('nomore.than.one', function ($attribute, $value, $parameters) {
            if (is_array($value['name']) && count($value['name']) > 1) {
                return FALSE;
            }

            return TRUE;
        });

        // Check product_code (SKU), it should not exists
        Validator::extend('orbit.exists.product.sku_code', function ($attribute, $value, $parameters) {
            $previous_row_default_sku = $parameters[0];
            $default_sku = $parameters[1];
//echo ($previous_row_default_sku.'==='.$default_sku.'<br />');
            // check if default_sku need to be checked for uniqueness in each row
            if ($previous_row_default_sku !== $default_sku) {
                $merchant = App::make('orbit.empty.merchant');
//echo('checking sku');
                // Check also the UPC on product variant
                $productVariant = ProductVariant::excludeDeleted()
                                                ->where('merchant_id', $merchant->merchant_id)
                                                ->where('sku', $value)
                                                ->first();

                if (! empty($productVariant)) {
                    return FALSE;
                }

                $product = Product::excludeDeleted()
                                  ->where('product_code', $value)
                                  ->where('merchant_id', $merchant->merchant_id)
                                  ->first();

                if (! empty($product)) {
                    return FALSE;
                }

                App::instance('orbit.exists.product.sku_code', $product);
            }
//echo('NOT checking sku');
            return TRUE;
        });

    }

}
