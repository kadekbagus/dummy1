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
use OrbitShop\API\v1\Helper\Generator;

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
            // Load the orbit configuration for import product
            $importProductConfig = Config::get('orbit.import.product.main');

            // set max_execution_time
            set_time_limit($importProductConfig['max_execution_time']);

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
                    'merchant_id'   => 'required|orbit.empty.merchant',
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

            // column index number
            $columnIndex = array();
            $columnIndex['default_sku'] = 0; // product_code
            $columnIndex['product_name'] = 1;
            $columnIndex['short_description'] = 2;
            $columnIndex['long_description'] = 3;
            $columnIndex['default_price'] = 4;
            $columnIndex['tax1_name'] = 5;
            $columnIndex['tax2_name'] = 6;
            $columnIndex['default_barcode'] = 7;// upc_code
            $columnIndex['family1_name'] = 8;
            $columnIndex['family2_name'] = 9;
            $columnIndex['family3_name'] = 10;
            $columnIndex['family4_name'] = 11;
            $columnIndex['family5_name'] = 12;
            $columnIndex['variant1_name'] = 13;
            $columnIndex['variant2_name'] = 14;
            $columnIndex['variant3_name'] = 15;
            $columnIndex['variant4_name'] = 16;
            $columnIndex['variant5_name'] = 17;
            $columnIndex['variant1_value'] = 18;
            $columnIndex['variant2_value'] = 19;
            $columnIndex['variant3_value'] = 20;
            $columnIndex['variant4_value'] = 21;
            $columnIndex['variant5_value'] = 22;
            $columnIndex['variant_sku'] = 23;
            $columnIndex['variant_barcode'] = 24;
            $columnIndex['variant_price'] = 25;

            // get csv file
            $file = $importProductConfig['path'] . DIRECTORY_SEPARATOR . $mediaList[0]['original']['file_name'];

            // first line total column
            $totalColumn = $importProductConfig['total_column'];

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

            // to save current product data. Only use first row product data, like variant name.
            $currentProduct = array();

            // validate if merchant have retailers, at least should have one retailer
            $retailer_ids = $merchant->getMyRetailerIds();
            if (count($retailer_ids) === 0) {
                // log error message to array
                $errorMessage = array(
                    'row'       => 1,
                    'message'   => 'Merchant should at least have one retailer.'
                );
                $errorLog[] = $errorMessage;
                $this->response->data = $errorLog;
                OrbitShopAPI::throwInvalidArgument('error');
            }

            // start validation
            Excel::filter('chunk')->load($file)->chunk($chunkSize, function($rows) use ($columnIndex, $totalColumn, $errorLogMax, &$errorLog, &$rowCounter, &$previous_row_default_sku, &$currentProduct)
            {
                foreach($rows as $row)
                {
                    // increase row counter by 1
                    $rowCounter++;

                    // validate total column
                    if (count($row) != $totalColumn) {
                        // log error message to array
                        $errorMessage = array(
                            'row'       => $rowCounter,
                            'message'   => 'Each line should have ' . $totalColumn . ' columns.'
                        );
                        $errorLog[] = $errorMessage;
                        $this->response->data = $errorLog;
                        OrbitShopAPI::throwInvalidArgument('error');
                    }

                    // validate product
                    $default_sku = trim($row[$columnIndex['default_sku']]); // product_code
                    $product_name = trim($row[$columnIndex['product_name']]);
                    $short_description = trim($row[$columnIndex['short_description']]);
                    $long_description = trim($row[$columnIndex['long_description']]);
                    $default_price = (float)$row[$columnIndex['default_price']];
                    $tax1_name = trim($row[$columnIndex['tax1_name']]);
                    $tax2_name = trim($row[$columnIndex['tax2_name']]);
                    $default_barcode = trim($row[$columnIndex['default_barcode']]); // upc_code
                    $family1_name = trim($row[$columnIndex['family1_name']]);
                    $family2_name = trim($row[$columnIndex['family2_name']]);
                    $family3_name = trim($row[$columnIndex['family3_name']]);
                    $family4_name = trim($row[$columnIndex['family4_name']]);
                    $family5_name = trim($row[$columnIndex['family5_name']]);

                    // only take variant name from the first row of the product
                    if ($previous_row_default_sku !== $default_sku) {
                        $currentProduct['variant1_name'] = trim($row[$columnIndex['variant1_name']]);
                        $currentProduct['variant2_name'] = trim($row[$columnIndex['variant2_name']]);
                        $currentProduct['variant3_name'] = trim($row[$columnIndex['variant3_name']]);
                        $currentProduct['variant4_name'] = trim($row[$columnIndex['variant4_name']]);
                        $currentProduct['variant5_name'] = trim($row[$columnIndex['variant5_name']]);
                    }

                    $variant1_name = $currentProduct['variant1_name'];
                    $variant2_name = $currentProduct['variant2_name'];
                    $variant3_name = $currentProduct['variant3_name'];
                    $variant4_name = $currentProduct['variant4_name'];
                    $variant5_name = $currentProduct['variant5_name'];

                    // validate product variant (combination)
                    $variant1_value = trim($row[$columnIndex['variant1_value']]);
                    $variant2_value = trim($row[$columnIndex['variant2_value']]);
                    $variant3_value = trim($row[$columnIndex['variant3_value']]);
                    $variant4_value = trim($row[$columnIndex['variant4_value']]);
                    $variant5_value = trim($row[$columnIndex['variant5_value']]);
                    $variant_sku = trim($row[$columnIndex['variant_sku']]);
                    $variant_barcode = trim($row[$columnIndex['variant_barcode']]);
                    $variant_price = (float)$row[$columnIndex['variant_price']];

                    // validation rule
                    $validator = Validator::make(
                        array(
                            'default_sku'           => $default_sku,
                            'product_name'          => $product_name,
                            'short_description'     => $short_description,
                            'default_price'         => $default_price,
                            'tax1_name'             => $tax1_name,
                            'tax2_name'             => $tax2_name,
                            'default_barcode'       => $default_barcode,
                            'family1_name'          => $family1_name,
                            'family2_name'          => $family2_name,
                            'family3_name'          => $family3_name,
                            'family4_name'          => $family4_name,
                            'family5_name'          => $family5_name,
                            'variant1_name'         => $variant1_name,
                            'variant2_name'         => $variant2_name,
                            'variant3_name'         => $variant3_name,
                            'variant4_name'         => $variant4_name,
                            'variant5_name'         => $variant5_name,
                            'variant_name_unique'   => 1,
                            'variant1_value'        => $variant1_value,
                            'variant2_value'        => $variant2_value,
                            'variant3_value'        => $variant3_value,
                            'variant4_value'        => $variant4_value,
                            'variant5_value'        => $variant5_value,
                            'variant_sku'           => $variant_sku,
                            'variant_barcode'       => $variant_barcode,
                            'variant_price'         => $variant_price,
                        ),
                        array(
                            'default_sku'           => "required|orbit.exists.product.sku_code:{$previous_row_default_sku}",
                            'product_name'          => 'required',
                            'short_description'     => 'required',
                            'default_price'         => 'required|numeric',
                            'tax1_name'             => 'required|orbit.empty.tax1_name',
                            'tax2_name'             => 'orbit.empty.tax2_name',
                            'default_barcode'       => 'numeric|orbit.exists.product.upc_code',
                            'family1_name'          => 'orbit.empty.family_name:1',
                            'family2_name'          => 'orbit.empty.family_name:2',
                            'family3_name'          => 'orbit.empty.family_name:3',
                            'family4_name'          => 'orbit.empty.family_name:4',
                            'family5_name'          => 'orbit.empty.family_name:5',
                            'variant1_name'         => 'orbit.empty.variant_name',
                            'variant2_name'         => 'orbit.empty.variant_name',
                            'variant3_name'         => 'orbit.empty.variant_name',
                            'variant4_name'         => 'orbit.empty.variant_name',
                            'variant5_name'         => 'orbit.empty.variant_name',
                            'variant_name_unique'   => 'all_variant_name_column_unique:'.$variant1_name.','.$variant2_name.','.$variant3_name.','.$variant4_name.','.$variant5_name,
                            'variant1_value'        => 'orbit.empty.variant_value_name:'.$variant1_name,
                            'variant2_value'        => 'orbit.empty.variant_value_name:'.$variant2_name,
                            'variant3_value'        => 'orbit.empty.variant_value_name:'.$variant3_name,
                            'variant4_value'        => 'orbit.empty.variant_value_name:'.$variant4_name,
                            'variant5_value'        => 'orbit.empty.variant_value_name:'.$variant5_name,
                            'variant_sku'           => 'orbit.exists.product.sku_code',
                            'variant_barcode'       => 'numeric|orbit.exists.product.upc_code',
                            'variant_price'         => 'numeric',
                        ),
                        array(
                            // Duplicate SKU error message
                            'default_sku.orbit.exists.product.sku_code' => Lang::get('validation.orbit.exists.product.sku_code', [
                                'sku' => $default_sku
                            ]),
                            // Duplicate UPC error message
                            'default_barcode.orbit.exists.product.upc_code' => Lang::get('validation.orbit.exists.product.upc_code', [
                                'upc' => $default_barcode
                            ]),
                            'family1_name.required' => Lang::get('validation.orbit.req.family_required', [
                                'message_1' => '1',
                                'message_2' => '2, 3, 4, 5'
                            ]),
                            'family2_name.required' => Lang::get('validation.orbit.req.family_required', [
                                'message_1' => '2',
                                'message_2' => '3, 4, 5'
                            ]),
                            'family3_name.required' => Lang::get('validation.orbit.req.family_required', [
                                'message_1' => '3',
                                'message_2' => '4, 5'
                            ]),
                            'family4_name.required' => Lang::get('validation.orbit.req.family_required', [
                                'message_1' => '4',
                                'message_2' => '5'
                            ]),
                            'family1_name.orbit.empty.family_name' => Lang::get('validation.orbit.empty.family_name', [
                                'family_level' => 1
                            ]),
                            'family2_name.orbit.empty.family_name' => Lang::get('validation.orbit.empty.family_name', [
                                'family_level' => 2
                            ]),
                            'family3_name.orbit.empty.family_name' => Lang::get('validation.orbit.empty.family_name', [
                                'family_level' => 3
                            ]),
                            'family4_name.orbit.empty.family_name' => Lang::get('validation.orbit.empty.family_name', [
                                'family_level' => 4
                            ]),
                            'family5_name.orbit.empty.family_name' => Lang::get('validation.orbit.empty.family_name', [
                                'family_level' => 5
                            ]),
                            'variant1_name.required' => Lang::get('validation.orbit.req.variant_required', [
                                'message_1' => '1',
                                'message_2' => '2, 3, 4, 5'
                            ]),
                            'variant2_name.required' => Lang::get('validation.orbit.req.variant_required', [
                                'message_1' => '2',
                                'message_2' => '3, 4, 5'
                            ]),
                            'variant3_name.required' => Lang::get('validation.orbit.req.variant_required', [
                                'message_1' => '3',
                                'message_2' => '4, 5'
                            ]),
                            'variant4_name.required' => Lang::get('validation.orbit.req.variant_required', [
                                'message_1' => '4',
                                'message_2' => '5'
                            ]),
                            'variant1_name.orbit.empty.variant_name' => Lang::get('validation.orbit.empty.variant_name', [
                                'number' => 1
                            ]),
                            'variant2_name.orbit.empty.variant_name' => Lang::get('validation.orbit.empty.variant_name', [
                                'number' => 2
                            ]),
                            'variant3_name.orbit.empty.variant_name' => Lang::get('validation.orbit.empty.variant_name', [
                                'number' => 3
                            ]),
                            'variant4_name.orbit.empty.variant_name' => Lang::get('validation.orbit.empty.variant_name', [
                                'number' => 4
                            ]),
                            'variant5_name.orbit.empty.variant_name' => Lang::get('validation.orbit.empty.variant_name', [
                                'number' => 5
                            ]),
                            'all_variant_name_column_unique' => 'All variant name columns should be unique.',
                            'variant1_value.orbit.empty.variant_value_name' => Lang::get('validation.orbit.empty.variant_value_name', ['number' => 1]),
                            'variant2_value.orbit.empty.variant_value_name' => Lang::get('validation.orbit.empty.variant_value_name', ['number' => 2]),
                            'variant3_value.orbit.empty.variant_value_name' => Lang::get('validation.orbit.empty.variant_value_name', ['number' => 3]),
                            'variant4_value.orbit.empty.variant_value_name' => Lang::get('validation.orbit.empty.variant_value_name', ['number' => 4]),
                            'variant5_value.orbit.empty.variant_value_name' => Lang::get('validation.orbit.empty.variant_value_name', ['number' => 5]),
                            'variant_sku.orbit.exists.product.sku_code' => Lang::get('validation.orbit.exists.product.sku_code', [
                                'sku' => $variant_sku
                            ]),
                            'variant_barcode.orbit.exists.product.upc_code' => Lang::get('validation.orbit.exists.product.upc_code', [
                                'upc' => $variant_barcode
                            ]),
                        )
                    );

                    // family x is required if other family name specified
                    for($i = 1; $i <= 5; $i++) {
                        $validator->sometimes("family{$i}_name", 'required', function($input) use ($i) {
                            for($j = $i + 1; $j <= 5; $j++) {
                                if ($input["family{$j}_name"] !== '') {
                                    return true;
                                }
                            }

                            return false;
                        });
                    }

                    // variant x is required if other variant name specified
                    for($i = 1; $i <= 5; $i++) {
                        $validator->sometimes("variant{$i}_name", 'required', function($input) use ($i) {
                            for($j = $i + 1; $j <= 5; $j++) {
                                if ($input["variant{$j}_name"] !== '') {
                                    return true;
                                }
                            }

                            return false;
                        });
                    }

                    // variant value x is required if current product variant name x is specified
                    for($i = 1; $i <= 5; $i++) {
                        $validator->sometimes("variant{$i}_value", 'required', function($input) use ($i) {
                            if ($input["variant{$i}_name"] !== '') {
                                return true;
                            }

                            return false;
                        });
                    }

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
                                $this->response->data = $errorLog;
                                OrbitShopAPI::throwInvalidArgument('error');
                            }
                        }
                    }

                    // set to current default_sku
                    $previous_row_default_sku = $default_sku;
                };

            });

            // if have error, then throw exception
            if (count($errorLog) <> 0) {
                $this->response->data = $errorLog;
                OrbitShopAPI::throwInvalidArgument('error');
            }

            // start creating data
            $errorLog = array();
            $rowCounter = 0;
            $previous_row_default_sku = '';

            $this->beginTransaction();

            Excel::filter('chunk')->load($file)->chunk($chunkSize, function($rows) use ($merchant, $retailer_ids, $columnIndex, $errorLogMax, &$errorLog, &$rowCounter, &$previous_row_default_sku)
            {
                // increase row counter by 1
                $rowCounter++;

                foreach($rows as $row)
                {
                    $default_sku = trim($row[$columnIndex['default_sku']]);

                    $newproduct = null;

                    // insert product
                    if ($previous_row_default_sku !== $default_sku) {
                        // validation rule
                        $validator = Validator::make(
                            array(
                                'default_sku'          => $default_sku,
                            ),
                            array(
                                'default_sku'          => 'orbit.exists.product.sku_code',
                            ),
                            array(
                                // Duplicate SKU error message
                                'default_sku.orbit.exists.product.sku_code' => Lang::get('validation.orbit.exists.product.sku_code', [
                                    'sku' => $default_sku
                                ]),
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
                                    $this->response->data = $errorLog;
                                    OrbitShopAPI::throwInvalidArgument('error');
                                }
                            }
                        }

                        $newproduct = new Product();
                        $newproduct->merchant_id = $merchant->merchant_id;
                        $newproduct->product_code = $default_sku;
                        $newproduct->product_name = trim($row[$columnIndex['product_name']]);
                        $newproduct->short_description = trim($row[$columnIndex['short_description']]);
                        $newproduct->long_description = trim($row[$columnIndex['long_description']]);
                        $newproduct->price = (float)$row[$columnIndex['default_price']];
                        $newproduct->upc_code = trim($row[$columnIndex['default_barcode']]);
                        $newproduct->new_from = 0;
                        $newproduct->new_until = 0;

                        // tax1
                        $tax1_name = trim($row[$columnIndex['tax1_name']]);
                        if ($tax1_name !== '') {
                            $tax1 = MerchantTax::excludeDeleted()
                                               ->where('merchant_id', $merchant->merchant_id)
                                               ->where('tax_name', $tax1_name)
                                               ->where('tax_type', 'government')
                                               ->first();
                            $newproduct->merchant_tax_id1 = $tax1->merchant_tax_id;
                        }

                        // tax2
                        $tax2_name = trim($row[$columnIndex['tax2_name']]);
                        if ($tax2_name !== '') {
                            $tax2 = MerchantTax::excludeDeleted()
                                               ->where('merchant_id', $merchant->merchant_id)
                                               ->where('tax_name', $tax2_name)
                                               ->where(function ($query) {
                                                    $query->where('tax_type', 'service')
                                                          ->orWhere('tax_type', 'luxury');
                                               })
                                               ->first();
                            $newproduct->merchant_tax_id2 = $tax2->merchant_tax_id;;
                        }

                        // family1
                        $family1_name = trim($row[$columnIndex['family1_name']]);
                        if ($family1_name !== '') {
                            $family1 = Category::excludeDeleted()
                                               ->where('merchant_id', $merchant->merchant_id)
                                               ->where('category_level', 1)
                                               ->where('category_name', $family1_name)
                                               ->first();
                            $newproduct->category_id1 = $family1->category_id;
                        }

                        // family2
                        $family2_name = trim($row[$columnIndex['family2_name']]);
                        if ($family2_name !== '') {
                            $family2 = Category::excludeDeleted()
                                               ->where('merchant_id', $merchant->merchant_id)
                                               ->where('category_level', 2)
                                               ->where('category_name', $family2_name)
                                               ->first();
                            $newproduct->category_id2 = $family2->category_id;
                        }

                        // family3
                        $family3_name = trim($row[$columnIndex['family3_name']]);
                        if ($family3_name !== '') {
                            $family3 = Category::excludeDeleted()
                                               ->where('merchant_id', $merchant->merchant_id)
                                               ->where('category_level', 3)
                                               ->where('category_name', $family3_name)
                                               ->first();
                            $newproduct->category_id3 = $family3->category_id;
                        }

                        // family4
                        $family4_name = trim($row[$columnIndex['family4_name']]);
                        if ($family4_name !== '') {
                            $family4 = Category::excludeDeleted()
                                               ->where('merchant_id', $merchant->merchant_id)
                                               ->where('category_level', 4)
                                               ->where('category_name', $family4_name)
                                               ->first();
                            $newproduct->category_id4 = $family4->category_id;
                        }

                        // family5
                        $family5_name = trim($row[$columnIndex['family5_name']]);
                        if ($family5_name !== '') {
                            $family5 = Category::excludeDeleted()
                                               ->where('merchant_id', $merchant->merchant_id)
                                               ->where('category_level', 5)
                                               ->where('category_name', $family5_name)
                                               ->first();
                            $newproduct->category_id5 = $family5->category_id;
                        }

                        // variant1
                        $variant1_name = trim($row[$columnIndex['variant1_name']]);
                        if ($variant1_name !== '') {
                            $variant1 = ProductAttribute::excludeDeleted()
                                                        ->where('merchant_id', $merchant->merchant_id)
                                                        ->where('product_attribute_name', $variant1_name)
                                                        ->first();
                            $newproduct->attribute_id1 = $variant1->product_attribute_id;
                        }

                        // variant2
                        $variant2_name = trim($row[$columnIndex['variant2_name']]);
                        if ($variant2_name !== '') {
                            $variant2 = ProductAttribute::excludeDeleted()
                                                        ->where('merchant_id', $merchant->merchant_id)
                                                        ->where('product_attribute_name', $variant2_name)
                                                        ->first();
                            $newproduct->attribute_id2 = $variant2->product_attribute_id;
                        }

                        // variant3
                        $variant3_name = trim($row[$columnIndex['variant3_name']]);
                        if ($variant3_name !== '') {
                            $variant3 = ProductAttribute::excludeDeleted()
                                                        ->where('merchant_id', $merchant->merchant_id)
                                                        ->where('product_attribute_name', $variant3_name)
                                                        ->first();
                            $newproduct->attribute_id3 = $variant3->product_attribute_id;
                        }

                        // variant4
                        $variant4_name = trim($row[$columnIndex['variant4_name']]);
                        if ($variant4_name !== '') {
                            $variant4 = ProductAttribute::excludeDeleted()
                                                        ->where('merchant_id', $merchant->merchant_id)
                                                        ->where('product_attribute_name', $variant4_name)
                                                        ->first();
                            $newproduct->attribute_id4 = $variant4->product_attribute_id;
                        }

                        // variant5
                        $variant5_name = trim($row[$columnIndex['variant5_name']]);
                        if ($variant5_name !== '') {
                            $variant5 = ProductAttribute::excludeDeleted()
                                                        ->where('merchant_id', $merchant->merchant_id)
                                                        ->where('product_attribute_name', $variant5_name)
                                                        ->first();
                            $newproduct->attribute_id5 = $variant5->product_attribute_id;
                        }

                        $newproduct->is_featured = 'n';
                        $newproduct->is_all_retailer = 'Y';
                        $newproduct->status = 'active';
                        $newproduct->created_by = $this->api->user->user_id;
                        $newproduct->save();

                        // create default variant
                        ProductVariant::createDefaultVariant($newproduct);
                    } else {
                        $newproduct = Product::excludeDeleted()
                                             ->where('merchant_id', $merchant->merchant_id)
                                             ->where('product_code', $default_sku)
                                             ->first();
                    }

                    // validation rule before creating product variant
                    $hasVariant = FALSE;
                    for ($i = 1; $i <= 5; $i++) {
                        ${"variant{$i}_value"} = $value = trim($row[$columnIndex["variant{$i}_value"]]);
                        if (!empty($value)) {
                            $hasVariant = TRUE;
                        }
                    }
                    if ($hasVariant): //has variant
                        $validator = Validator::make(
                            array(
                                'variant_value_unique_fields' => $newproduct->product_id,
                            ),
                            array(
                                'variant_value_unique_fields' => [['variant_value_unique', $variant1_value, $variant2_value, $variant3_value, $variant4_value, $variant5_value]],
                            ),
                            array(
                                'variant_value_unique' => Lang::get('validation.orbit.exists.product.variant_value_unique'),
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
                                    $this->response->data = $errorLog;
                                    OrbitShopAPI::throwInvalidArgument('error');
                                }
                            }
                        } else {
                            // create variant
                            $product_variant = new ProductVariant();
                            $product_variant->merchant_id = $merchant->merchant_id;
                            $product_variant->product_id = $newproduct->product_id;

                            // variant_value
                            for ($i = 1; $i <= 5; $i++) {
                                if (${"variant{$i}_value"} !== '') {
                                    $attributeValue = ProductAttributeValue::excludeDeleted()
                                                                           ->where('value', ${"variant{$i}_value"})
                                                                           ->where('product_attribute_id', $newproduct->{'attribute_id' . $i})
                                                                           ->first();
                                    $product_variant->{'product_attribute_value_id' . $i} = $attributeValue->product_attribute_value_id;
                                }
                            }

                            $product_variant->sku = trim($row[$columnIndex['variant_sku']]);
                            $product_variant->upc = trim($row[$columnIndex['variant_barcode']]);
                            $product_variant->price = (float)$row[$columnIndex['variant_price']];

                            $product_variant->default_variant = 'no';
                            $product_variant->status = 'active';
                            $product_variant->created_by = $this->api->user->user_id;
                            $product_variant->save();
                        }
                    endif; // has variant

                    // set to current default_sku
                    $previous_row_default_sku = $default_sku;
                };

            });

            // if have error, then throw exception
            if (count($errorLog) <> 0) {
                $this->response->data = $errorLog;
                OrbitShopAPI::throwInvalidArgument('error');
            }

            $this->commit();

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
            //$this->response->data = null;
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

    /**
     * Import product image.
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                (required) - Merchant ID
     * @param file|array `image-zip`                  (required) - Images file in zip format
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postImportProductImage()
    {
        try {
            // Load the orbit configuration for import product
            $importProductConfig = Config::get('orbit.import.product.image-zip');

            // set max_execution_time
            set_time_limit($importProductConfig['max_execution_time']);

            //ini_set('post_max_size', '10M');
            //ini_set('upload_max_filesize', '10M');

            $httpCode = 200;

            Event::fire('orbit.import.postimportproductimage.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.import.postimportproductimage.after.auth', array($this));

            // Try to check access control list, does this merchant allowed to
            // perform this action
            $user = $this->api->user;

            Event::fire('orbit.import.postimportproductimage.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('import_product')) {
                Event::fire('orbit.import.postimportproductimage.authz.notallowed', array($this, $user));
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

            Event::fire('orbit.import.postimportproductimage.after.authz', array($this, $user));

            // Register custom validation
            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');
            $products = OrbitInput::files('image-zip');

            // validate input data
            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                    'image'         => $products,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.merchant',
                    'image'         => 'required|nomore.than.one',
                ),
                array(
                   'nomore.than.one' => Lang::get('validation.max.array', array('max' => 1))
                )
            );

            Event::fire('orbit.import.postimportproductimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.import.postimportproductimage.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.merchant');

            // Delete old coupon image
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'merchant')
                              ->where('media_name_id', 'import_product_image_zip');

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
            // [MERCHANT_ID]-products_[ARRAY_NO].csv
            $renameFile = function($uploader, &$file, $dir) use ($merchant, $importProductConfig)
            {
                $merchant_id = $merchant->merchant_id;
                $file['new']->name = sprintf('%s-%s', $merchant_id, $importProductConfig['filename']);
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($importProductConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.import.postimportproductimage.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($products);

            // Save the files metadata
            $object = array(
                'id'                => $merchant->merchant_id,
                'name'              => 'merchant',
                'media_name_id'     => 'import_product_image_zip',
                'modified_by'       => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.import.postimportproductimage.after.save', array($this, $merchant, $uploader));

            // Commit the changes
            $this->commit();

            Event::fire('orbit.import.postimportproductimage.after.commit', array($this, $merchant, $uploader));

            /*
             *
             * start importing product image zip
             *
             */

            // get zip file
            $file = $importProductConfig['path'] . DIRECTORY_SEPARATOR . $mediaList[0]['original']['file_name'];

            // get error log max config
            $errorLogMax = $importProductConfig['error_log_max'];

            // extract to folder
            $extractToFolder = sprintf($importProductConfig['extract_to_folder'], $merchant->merchant_id);
            if (! file_exists($extractToFolder)) {
                mkdir($extractToFolder, 0777, true);
            };

            // error log
            $errorLog = array();

            // row counter for user error message row number
            $rowCounter = 0;

            // error flag
            $errorFlag = false;

            $zip = new ZipArchive;
            if ($zip->open($file) === TRUE) {
                $zip->extractTo($extractToFolder);

                for($i = 0; $i < $zip->numFiles; $i++) {
                    $basename = $zip->getNameIndex($i);
                    $fileinfo = pathinfo($basename);
                    $tmpFile =  $extractToFolder . DIRECTORY_SEPARATOR . $fileinfo['basename'];
                    $sku = $fileinfo['filename'];

                    $product = Product::excludeDeleted()
                                      ->where('merchant_id', $merchant_id)
                                      ->where('product_code', $sku)
                                      ->first();

                    if (! empty($product)) {
                        $_POST['product_id'] = $product->product_id;

                        $_FILES = array();
                        $_FILES['images']['tmp_name'][0] = $tmpFile;
                        $_FILES['images']['type'][0] = 'image/jpeg';
                        $_FILES['images']['name'][0] = basename($_FILES['images']['tmp_name'][0]);
                        $_FILES['images']['error'][0] = 0;
                        $_FILES['images']['size'][0] = filesize($tmpFile);

                        $secretKey = $this->api->user->apikey->api_secret_key;
                        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

                        Config::set('memory:from_import_image', TRUE);
                        $api = UploadAPIController::create('raw')->postUploadProductImage();

                        // if file size is too big
                        if (($api->code === 1) && ($api->status === 'error')) {
                            $errorFlag = true;
                        }
                    } else {
                        // if SKU is not found
                        $errorFlag = true;
                    }
                }

                $zip->close();
            } else {
                OrbitShopAPI::throwInvalidArgument('Failed to read zip file.');
            }

            // if have error then throw exception
            if ($errorFlag === true) {
                OrbitShopAPI::throwInvalidArgument('Some files do not match the requirement, either the file size is too big or the SKU is not found.');
            }

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.import.postimportproductimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.import.postimportproductimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.import.postimportproductimage.query.error', array($this, $e));

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
            Event::fire('orbit.import.postimportproductimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.import.postimportproductimage.before.render', array($this, $output));

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
            $sku = $value;

            if (! empty($parameters)) {
                $previous_row_default_sku = $parameters[0];

                // check if default_sku need to be checked for uniqueness in each row
                if ($previous_row_default_sku === $sku) {
                    return TRUE;
                }
            }

            $merchant = App::make('orbit.empty.merchant');

            // Check also the UPC on product variant
            $productVariant = ProductVariant::excludeDeleted()
                                            ->where('merchant_id', $merchant->merchant_id)
                                            ->where('sku', $sku)
                                            ->first();

            if (! empty($productVariant)) {
                return FALSE;
            }

            $product = Product::excludeDeleted()
                              ->where('merchant_id', $merchant->merchant_id)
                              ->where('product_code', $sku)
                              ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.exists.product.sku_code', $product);

            return TRUE;
        });

        // Check upc_code (barcode), it should not exists
        Validator::extend('orbit.exists.product.upc_code', function ($attribute, $value, $parameters) {
            $merchant = App::make('orbit.empty.merchant');
            $upc_code = $value;

            // Check upc_code on product variant
            $productVariant = ProductVariant::excludeDeleted()
                                            ->where('merchant_id', $merchant->merchant_id)
                                            ->where('upc', $upc_code)
                                            ->first();

            if (! empty($productVariant)) {
                return FALSE;
            }

            // Check upc_code on product
            $product = Product::excludeDeleted()
                              ->where('merchant_id', $merchant->merchant_id)
                              ->where('upc_code', $upc_code)
                              ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.exists.product.upc_code', $product);

            return TRUE;
        });

        // Check the existance of tax1_name
        Validator::extend('orbit.empty.tax1_name', function ($attribute, $value, $parameters) {
            $merchant = App::make('orbit.empty.merchant');

            $tax = MerchantTax::excludeDeleted()
                              ->where('merchant_id', $merchant->merchant_id)
                              ->where('tax_name', $value)
                              ->where('tax_type', 'government')
                              ->first();

            if (empty($tax)) {
                return FALSE;
            }

            App::instance('orbit.empty.tax1_name', $tax);

            return TRUE;
        });

        // Check the existance of tax2_name
        Validator::extend('orbit.empty.tax2_name', function ($attribute, $value, $parameters) {
            $merchant = App::make('orbit.empty.merchant');

            $tax = MerchantTax::excludeDeleted()
                              ->where('merchant_id', $merchant->merchant_id)
                              ->where('tax_name', $value)
                              ->where(function ($query) {
                                  $query->where('tax_type', 'service')
                                        ->orWhere('tax_type', 'luxury');
                              })
                              ->first();

            if (empty($tax)) {
                return FALSE;
            }

            App::instance('orbit.empty.tax2_name', $tax);

            return TRUE;
        });

        // Check the existance of product family name
        Validator::extend('orbit.empty.family_name', function ($attribute, $value, $parameters) {
            $merchant = App::make('orbit.empty.merchant');

            // if filter by family level
            if (empty($parameters)) {
                $family = Category::excludeDeleted()
                                  ->where('merchant_id', $merchant->merchant_id)
                                  ->where('category_name', $value)
                                  ->first();
            } else {
                $family_level = $parameters[0];
                $family = Category::excludeDeleted()
                                  ->where('merchant_id', $merchant->merchant_id)
                                  ->where('category_name', $value)
                                  ->where('category_level', $family_level)
                                  ->first();
            }

            if (empty($family)) {
                return FALSE;
            }

            App::instance('orbit.empty.family_name', $family);

            return TRUE;
        });

        // Check the existance of product variant name
        Validator::extend('orbit.empty.variant_name', function ($attribute, $value, $parameters) {
            $merchant = App::make('orbit.empty.merchant');

            $variant = ProductAttribute::excludeDeleted()
                                       ->where('merchant_id', $merchant->merchant_id)
                                       ->where('product_attribute_name', $value)
                                       ->first();

            if (empty($variant)) {
                return FALSE;
            }

            App::instance('orbit.empty.variant_name', $variant);

            return TRUE;
        });

        // all product variant name should unique
        Validator::extend('all_variant_name_column_unique', function ($attribute, $value, $parameters) {
            // remove empty string
            $a = array_filter($parameters);

            // remove unique string
            $b = array_unique($a);

            if (count($a) !== count($b)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of product variant value name
        Validator::extend('orbit.empty.variant_value_name', function ($attribute, $value, $parameters) {
            $merchant = App::make('orbit.empty.merchant');
            $variant_name = $parameters[0];

            $variantValue = ProductAttributeValue::excludeDeleted()
                                                 ->whereHas('attribute', function ($q) use ($merchant, $variant_name) {
                                                      $q->excludeDeleted()
                                                        ->where('merchant_id', $merchant->merchant_id)
                                                        ->where('product_attribute_name', $variant_name);
                                                 })
                                                 ->where('value', $value)
                                                 ->first();

            if (empty($variantValue)) {
                return FALSE;
            }

            App::instance('orbit.empty.variant_value_name', $variantValue);

            return TRUE;
        });

        // validasi check all product variant value should unique for the related product
        Validator::extend('variant_value_unique', function ($attribute, $value, $parameters) {
            $merchant = App::make('orbit.empty.merchant');
            $product_id = $value;
            $variant_value = $parameters;

            $productVariant = ProductVariant::excludeDeleted()
                                            ->where('merchant_id', $merchant->merchant_id)
                                            ->where('product_id', $product_id);

            for ($i = 1; $i <= count($variant_value); $i++) {
                $variant_value[$i-1] = trim($variant_value[$i-1]);

                if ($variant_value[$i-1] === '') {
                    $productVariant->whereNull("product_attribute_value_id{$i}");
                } else {
                    $productVariant->whereHas("attributeValue{$i}", function ($q) use ($variant_value, $i) {
                        $q->where('value', $variant_value[$i-1]);
                    });
                }
            }

            $productVariant = $productVariant->first();

            if (! empty($productVariant)) {
                return FALSE;
            }

            return TRUE;
        });

    }

}
