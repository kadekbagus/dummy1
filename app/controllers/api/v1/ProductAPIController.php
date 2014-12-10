<?php

/**
 * An API controller for managing products.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class ProductAPIController extends ControllerAPI
{

    /**
     * POST - Update Product
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`             (required) - ID of the merchant
     * @param string     `product_code`            (optional) - Product code
     * @param string     `upc_code`                (optional) - Product UPC code
     * @param string     `product_name`            (optional) - Product name
     * @param string     `image`                   (optional) - Product image
     * @param string     `short_description`       (optional) - Product short description
     * @param string     `long_description`        (optional) - Product long description
     * @param string     `is_featured`             (optional) - is featured
     * @param string     `new_from`                (optional) - new from
     * @param string     `new_until`               (optional) - new until
     * @param string     `in_store_localization`   (optional) - in store localization
     * @param string     `post_sales_url`          (optional) - post sales url
     * @param decimal    `price`                   (optional) - Price of the product
     * @param string     `merchant_tax_id1`        (optional) - Tax 1
     * @param string     `merchant_tax_id2`        (optional) - Tax 2
     * @param string     `status`                  (optional) - Status
     * @param integer    `created_by`              (optional) - ID of the creator
     * @param integer    `modified_by`             (optional) - Modify by
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateProduct()
    {
        try {
            $httpCode=200;

            Event::fire('orbit.product.postupdateproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postupdateproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postupdateproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_product')) {
                Event::fire('orbit.product.postupdateproduct.authz.notallowed', array($this, $user));
                $updateProductLang = Lang::get('validation.orbit.actionlist.update_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateProductLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postupdateproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $product_id = OrbitInput::post('product_id');
            $merchant_id = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'product_id'        => $product_id,
                    'merchant_id'       => $merchant_id,
                ),
                array(
                    'product_id'        => 'required|numeric|orbit.empty.product',
                    'merchant_id'       => 'numeric|orbit.empty.merchant',
                )
            );

            Event::fire('orbit.product.postupdateproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postupdateproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedproduct = Product::excludeDeleted()->where('product_id', $product_id)->first();

            OrbitInput::post('product_code', function($product_code) use ($updatedproduct) {
                $updatedproduct->product_code = $product_code;
            });

            OrbitInput::post('upc_code', function($upc_code) use ($updatedproduct) {
                $updatedproduct->upc_code = $upc_code;
            });

            OrbitInput::post('product_name', function($product_name) use ($updatedproduct) {
                $updatedproduct->product_name = $product_name;
            });

            OrbitInput::post('image', function($image) use ($updatedproduct) {
                $updatedproduct->image = $image;
            });

            OrbitInput::post('short_description', function($short_description) use ($updatedproduct) {
                $updatedproduct->short_description = $short_description;
            });

            OrbitInput::post('long_description', function($long_description) use ($updatedproduct) {
                $updatedproduct->long_description = $long_description;
            });

            OrbitInput::post('is_featured', function($is_featured) use ($updatedproduct) {
                $updatedproduct->is_featured = $is_featured;
            });

            OrbitInput::post('new_from', function($new_from) use ($updatedproduct) {
                $updatedproduct->new_from = $new_from;
            });

            OrbitInput::post('new_until', function($new_until) use ($updatedproduct) {
                $updatedproduct->new_until = $new_until;
            });

            OrbitInput::post('in_store_localization', function($in_store_localization) use ($updatedproduct) {
                $updatedproduct->in_store_localization = $in_store_localization;
            });

            OrbitInput::post('post_sales_url', function($post_sales_url) use ($updatedproduct) {
                $updatedproduct->post_sales_url = $post_sales_url;
            });

            OrbitInput::post('price', function($price) use ($updatedproduct) {
                $updatedproduct->price = $price;
            });

            OrbitInput::post('merchant_tax_id1', function($merchant_tax_id1) use ($updatedproduct) {
                $updatedproduct->merchant_tax_id1 = $merchant_tax_id1;
            });

            OrbitInput::post('merchant_tax_id2', function($merchant_tax_id2) use ($updatedproduct) {
                $updatedproduct->merchant_tax_id2 = $merchant_tax_id2;
            });

            OrbitInput::post('status', function($status) use ($updatedproduct) {
                $updatedproduct->status = $status;
            });

            OrbitInput::post('created_by', function($created_by) use ($updatedproduct) {
                $updatedproduct->created_by = $created_by;
            });

            $updatedproduct->modified_by = $this->api->user->user_id;

            Event::fire('orbit.product.postupdateproduct.before.save', array($this, $updatedproduct));

            $updatedproduct->save();

            Event::fire('orbit.product.postupdateproduct.after.save', array($this, $updatedproduct));
            $this->response->data = $updatedproduct;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postupdateproduct.after.commit', array($this, $updatedproduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postupdateproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postupdateproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postupdateproduct.query.error', array($this, $e));

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
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.product.postupdateproduct.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);

    }

    /**
     * GET - Search Product
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `product_id`               (optional) - ID of the product
     * @param string     `product_code`             (optional)
     * @param string     `product_name`             (optional)
     * @param string     `short_description`        (optional)
     * @param string     `long_description`         (optional)
     * @param string     `product_name_like`        (optional)
     * @param string     `short_description_like`   (optional)
     * @param string     `long_description_like`    (optional)
     * @param integer    `merchant_id`              (optional)
     * @param integer    `status`                   (optional)
     * @return Illuminate\Support\Facades\Response
     */

    public function getSearchProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.getsearchproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.getsearchproduct.after.auth', array($this));

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.getsearchproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.product.getsearchproduct.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.getsearchproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,product_id,product_name,product_code,product_price,product_tax_code,product_short_description,product_long_description,product_is_new,product_new_until,product_retailer_id,product_merchant_id,product_status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );

            Event::fire('orbit.product.getsearchproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.product.getsearchproduct.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $products = Product::excludeDeleted()->allowedForUser($user);

            // Filter product by Ids
            OrbitInput::get('product_id', function ($productIds) use ($products) {
                $products->whereIn('products.product_id', $productIds);
            });

            // Filter product by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($products) {
                $products->whereIn('products.merchant_id', $merchantIds);
            });

            // Filter product by product code
            OrbitInput::get('product_code', function ($product_code) use ($products) {
                $products->whereIn('products.product_code', $product_code);
            });

            // Filter product by name
            OrbitInput::get('product_name', function ($name) use ($products) {
                $products->whereIn('products.product_name', $name);
            });

            // Filter product by name pattern
            OrbitInput::get('product_name_like_like', function ($name) use ($products) {
                $products->where('products.product_name', 'like', "%$name%");
            });

            // Filter product by short description
            OrbitInput::get('short_description', function ($short_description) use ($products) {
                $products->whereIn('products.short_description', $short_description);
            });

            // Filter product by short description pattern
            OrbitInput::get('short_description_like', function ($short_description) use ($products) {
                $products->where('products.short_description', 'like', "%$short_description%");
            });

            // Filter product by long description
            OrbitInput::get('long_description', function ($long_description) use ($products) {
                $products->whereIn('products.long_description', $long_description);
            });

            // Filter product by long description pattern
            OrbitInput::get('long_description_like', function ($long_description) use ($products) {
                $products->where('products.long_description', 'like', "%$long_description%");
            });

            // Filter product by status
            OrbitInput::get('status', function ($status) use ($products) {
                $products->whereIn('products.status', $status);
            });

            $_products = clone $products;

            // Get the take args
            $take = $maxRecord;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;
            });
            $products->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $products) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $products->skip($skip);

            // Default sort by
            $sortBy = 'products.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'           => 'products.created_at',
                    'product_id'                => 'products.product_id',
                    'product_name'              => 'products.product_name',
                    'product_code'              => 'products.product_code',
                    'product_price'             => 'products.price',
                    'product_tax_code'          => 'products.tax_code',
                    'product_short_description' => 'products.short_description',
                    'product_long_description'  => 'products.long_description',
                    'product_is_new'            => 'products.is_new',
                    'product_new_until'         => 'products.new_until',
                    'product_merchant_id'       => 'products.merchant_id',
                    'product_status'            => 'products.status',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $products->orderBy($sortBy, $sortMode);

            $totalRec = $_products->count();
            $listOfRec = $products->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.getsearchproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.getsearchproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.product.getsearchproduct.query.error', array($this, $e));

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
            Event::fire('orbit.product.getsearchproduct.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        $output = $this->render($httpCode);
        Event::fire('orbit.product.getsearchproduct.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Add new product
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`             (required) - ID of the merchant
     * @param string     `product_code`            (optional) - Product code
     * @param string     `upc_code`                (optional) - Merchant description
     * @param string     `product_name`            (optional) - Product name
     * @param string     `image`                   (optional) - Product image
     * @param string     `short_description`       (optional) - Product short description
     * @param string     `long_description`        (optional) - Product long description
     * @param string     `is_featured`             (optional) - is featured
     * @param string     `new_from`                (optional) - new from
     * @param string     `new_until`               (optional) - new until
     * @param string     `in_store_localization`   (optional) - in store localization
     * @param string     `post_sales_url`          (optional) - post sales url
     * @param decimal    `price`                   (optional) - Price of the product
     * @param string     `merchant_tax_id1`        (optional) - Tax 1
     * @param string     `merchant_tax_id2`        (optional) - Tax 2
     * @param string     `status`                  (optional) - Status
     * @param integer    `created_by`              (optional) - ID of the creator
     * @param integer    `modified_by`             (optional) - Modify by
     * @param file       `images`                  (optional) - Product Image
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postnewproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postnewproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postnewproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_product')) {
                Event::fire('orbit.merchant.postnewproduct.authz.notallowed', array($this, $user));
                $createProductLang = Lang::get('validation.orbit.actionlist.new_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createProductLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postnewproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');
            $product_code = OrbitInput::post('product_code');
            $upc_code = OrbitInput::post('upc_code');
            $product_name = OrbitInput::post('product_name');
            $image = OrbitInput::post('image');
            $short_description = OrbitInput::post('short_description');
            $long_description = OrbitInput::post('long_description');
            $is_featured = OrbitInput::post('is_featured');
            $new_from = OrbitInput::post('new_from');
            $new_until = OrbitInput::post('new_until');
            $in_store_localization = OrbitInput::post('in_store_localization');
            $post_sales_url = OrbitInput::post('post_sales_url');
            $price = OrbitInput::post('price');
            $merchant_tax_id1 = OrbitInput::post('merchant_tax_id1');
            $merchant_tax_id2 = OrbitInput::post('merchant_tax_id2');
            $status = OrbitInput::post('status');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                    'product_name'  => $product_name,
                    'status'        => $status,
                ),
                array(
                    'merchant_id'   => 'required|numeric',
                    'product_name'  => 'required',
                    'status'        => 'required',
                )
            );

            Event::fire('orbit.product.postnewproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            foreach ($retailer_ids as $retailer_id_check) {
                $validator = Validator::make(
                    array(
                        'retailer_id'   => $retailer_id_check,

                    ),
                    array(
                        'retailer_id'   => 'orbit.empty.retailer',
                    )
                );

                Event::fire('orbit.product.postnewproduct.before.retailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.product.postnewproduct.after.retailervalidation', array($this, $validator));
            }

            Event::fire('orbit.product.postnewproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $newproduct = new Product();
            $newproduct->merchant_id = $merchant_id;
            $newproduct->product_code = $product_code;
            $newproduct->upc_code = $upc_code;
            $newproduct->product_name = $product_name;
            $newproduct->image = $image;
            $newproduct->short_description = $short_description;
            $newproduct->long_description = $long_description;
            $newproduct->is_featured = $is_featured;
            $newproduct->new_from = $new_from;
            $newproduct->new_until = $new_until;
            $newproduct->in_store_localization = $in_store_localization;
            $newproduct->post_sales_url = $post_sales_url;
            $newproduct->price = $price;
            $newproduct->merchant_tax_id1 = $merchant_tax_id1;
            $newproduct->merchant_tax_id2 = $merchant_tax_id2;
            $newproduct->status = $status;
            $newproduct->created_by = $this->api->user->user_id;
            $newproduct->modified_by = $this->api->user->user_id;

            Event::fire('orbit.product.postnewproduct.before.save', array($this, $newproduct));

            $newproduct->save();

            $productretailers = array();

            foreach ($retailer_ids as $retailer_id) {
                $productretailer = new ProductRetailer();
                $productretailer->retailer_id = $retailer_id;
                $productretailer->product_id = $newproduct->product_id;
                $productretailer->save();
                $productretailers[] = $productretailer;
            }

            $newproduct->setRelation('retailers', $productretailers);
            $newproduct->retailers = $productretailers;

            Event::fire('orbit.product.postnewproduct.after.save', array($this, $newproduct));
            $this->response->data = $newproduct;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postnewproduct.after.commit', array($this, $newproduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postnewproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postnewproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postnewproduct.query.error', array($this, $e));

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
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.product.postnewproduct.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * POST - Delete Product
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `product_id`                  (required) - ID of the product
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postdeleteproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postdeleteproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postdeleteproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_product')) {
                Event::fire('orbit.product.postdeleteproduct.authz.notallowed', array($this, $user));
                $deleteProductLang = Lang::get('validation.orbit.actionlist.delete_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteProductLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postdeleteproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $product_id = OrbitInput::post('product_id');

            $validator = Validator::make(
                array(
                    'product_id' => $product_id,
                ),
                array(
                    'product_id' => 'required|numeric|orbit.empty.product',
                )
            );

            Event::fire('orbit.product.postdeleteproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postdeleteproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deleteproduct = Product::excludeDeleted()->where('product_id', $product_id)->first();
            $deleteproduct->status = 'deleted';
            $deleteproduct->modified_by = $this->api->user->user_id;

            Event::fire('orbit.product.postdeleteproduct.before.save', array($this, $deleteproduct));

            // get product-retailer for the product
            $deleteproductretailers = ProductRetailer::where('product_id', $deleteproduct->product_id)->get();

            foreach ($deleteproductretailers as $deleteproductretailer) {
                $deleteproductretailer->delete();
            }

            $deleteproduct->save();

            Event::fire('orbit.product.postdeleteproduct.after.save', array($this, $deleteproduct));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.product');

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postdeleteproduct.after.commit', array($this, $deleteproduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postdeleteproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postdeleteproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postdeleteproduct.query.error', array($this, $e));

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
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.product.postdeleteproduct.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.product.postdeleteproduct.before.render', array($this, $output));

        return $output;
    }

    protected function registerCustomValidation()
    {
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

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Merchant::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check the existance of retailer id
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) {
            $retailer = Retailer::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.empty.retailer', $retailer);

            return TRUE;
        });
    }
}
