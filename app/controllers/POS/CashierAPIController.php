<?php namespace POS;

/**
 * An API controller for managing Cashier QUICK AND DIRTY
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use \View;
use \User;
use \UserDetail;
use \Token;
use \Role;
use \Lang;
use \Apikey;
use \Validator;
use \Product;
use \CartCoupon;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Config;
use \ProductAttribute;
use \DB;
use Carbon\Carbon as Carbon;
use \Hash;
use Activity;
use \CartDetail;
use \Promotion;
use \Coupon;

class CashierAPIController extends ControllerAPI
{

    /**
     * POST - Login cashier in shop
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `username`  (required) - username of the cashier
     * @param string    `password`  (required) - password of the cashier
     * @return Illuminate\Support\Facades\Response
     */
    public function postLoginCashier()
    {
        $activity = Activity::pos()->setActivityType('login');
        try {
            $username = OrbitInput::post('username');
            $password = OrbitInput::post('password');

            $role = Role::where('role_name', 'cashier')->first();
            if (empty($role)) {
                $message = Lang::get('validation.orbit.empty.employee.role',
                    array('role' => 'Cashier')
                );
                ACL::throwAccessForbidden($message);
            }

            $user = User::with('apikey', 'userdetail.merchant', 'role')
                        ->active()
                        ->where('username', $username)
                        ->where('user_role_id', $role->role_id)
                        ->first();

            if (is_object($user)) {
                if (! Hash::check($password, $user->user_password)) {
                    $message = \Lang::get('validation.orbit.access.loginfailed');
                    ACL::throwAccessForbidden($message);
                } else {
                    // Start the orbit session
                    $data = array(
                        'logged_in' => TRUE,
                        'user_id'   => $user->user_id,
                    );
                    $config = new SessionConfig(Config::get('orbit.session'));
                    $session = new Session($config);
                    $session->enableForceNew()->start($data);

                    // Successfull login
                    $activity->setUser($user)
                             ->setActivityName('login_ok')
                             ->setActivityNameLong('Login OK')
                             ->responseOK();
                }
            } else {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            $user->setHidden(array('user_password', 'apikey'));

            $this->response->data = $user;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        $activity->save();
        return $this->render();
    }

    /**
     * POST - Logout cashier
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postLogoutCashier()
    {
        try {
            $config = new SessionConfig(Config::get('orbit.session'));
            $session = new Session($config);

            $session->start(array(), 'no-session-creation');
            $session->destroy();
        } catch (Exception $e) {
        }

        return \Redirect::to('/pos');
    }



    /**
     * POST - Scan Barcode
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postScanBarcode()
    {
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            $driver = Config::get('orbit.devices.barcode.path');
            $params = Config::get('orbit.devices.barcode.params');
            $cmd = 'sudo '.$driver.' '.$params;
            $barcode = shell_exec($cmd);

            $barcode = trim($barcode);
            $product = Product::where('upc_code', $barcode)
                    ->active()
                    ->first();

            if (! is_object($product)) {
                $message = \Lang::get('validation.orbit.empty.upc_code');
                ACL::throwAccessForbidden($message);
            }

            $this->response->data = $product;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }

    /**
     * GET - Search Product for POS
     *
     * @author kadek <kadek@dominopos.com>
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
    public function getSearchProductPOS()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

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

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get the maximum record
            $maxRecord = (int) \Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $products = Product::with('retailers')->excludeDeleted();

            // Filter product by Ids
            OrbitInput::get('product_id', function ($productIds) use ($products) {
                $products->whereIn('products.product_id', $productIds);
            });

            // Filter product by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($products) {
                $products->whereIn('products.merchant_id', $merchantIds);
            });

            // Filter product by name
            OrbitInput::get('product_name', function ($name) use ($products) {
                $products->whereIn('products.product_name', $name);
            });

            // Filter product by name pattern
            OrbitInput::get('product_name_like', function ($name) use ($products) {
                $products->where('products.product_name', 'like', "%$name%");
            });

            // Filter product by product code
            OrbitInput::get('product_code', function ($product_code) use ($products) {
                $products->whereIn('products.product_code', $product_code);
            });

            // Filter product by product code pattern
            OrbitInput::get('product_code_like', function ($product_code) use ($products) {
                $products->orwhere('products.product_code', 'like', "%$product_code%");
            });

            // Filter product by upc code
            OrbitInput::get('upc_code', function ($upc_code) use ($products) {
                $products->whereIn('products.upc_code', $upc_code);
            });

            // Filter product by upc code pattern
            OrbitInput::get('upc_code_like', function ($upc_code) use ($products) {
                $products->orwhere('products.upc_code', 'like', "%$upc_code%");
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

            $data = new \stdClass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = \Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (\Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = \Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        $this->response->code = 0;
        $this->response->status = 'succes';
        $this->response->message = 'succes';
        $httpCode =200;
        $output = $this->render($httpCode);
        return $output;
    }


    /**
     * GET - Search Product for POS with Variant
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `product_variant_id`                 (optional)
     * @param integer    `product_id`                         (optional)
     * @param decimal    `price`                              (optional)
     * @param string     `upc`                                (optional)
     * @param string     `sku`                                (optional)
     * @param integer    `stock`                              (optional)
     * @param integer    `product_attribute_value_id1`        (optional)
     * @param integer    `product_attribute_value_id2`        (optional)
     * @param integer    `product_attribute_value_id3`        (optional)
     * @param integer    `product_attribute_value_id4`        (optional)
     * @param integer    `product_attribute_value_id5`        (optional)
     * @param integer    `merchant_id`                        (optional)
     * @param integer    `retailer_id`                        (optional)
     * @param integer    `created_by`                         (optional)
     * @param integer    `modified_by`                        (optional)
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchProductPOSwithVariant()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,product_id,product_sku,product_price,product_retailer_id,product_merchant_id,product_status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get the maximum record
            $maxRecord = (int) \Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $products = \ProductVariant::with('product',
                             'attributeValue1.attribute', 'attributeValue2.attribute',
                             'attributeValue3.attribute', 'attributeValue4.attribute',
                             'attributeValue5.attribute')->excludeDeleted();

            // Filter product variant by product variant id
            OrbitInput::get('product_variant_id', function ($productIds) use ($products) {
                $products->whereIn('product_variants.product_variant_id', $productIds);
            });

            // Filter product variant by product id
            OrbitInput::get('product_id', function ($productIds) use ($products) {
                $products->whereIn('product_variants.product_id', $productIds);
            });

            // Filter product variant by price
            OrbitInput::get('price', function ($price) use ($products) {
                $products->whereIn('product_variants.price', $price);
            });

            // Filter product variant by upc
            OrbitInput::get('upc', function ($upc) use ($products) {
                $products->whereIn('product_variants.upc', $upc);
            });

            // Filter product variant by sku
            OrbitInput::get('sku', function ($sku) use ($products) {
                $products->whereIn('product_variants.sku', $sku);
            });

            // Filter product variant by stock
            OrbitInput::get('sku', function ($stock) use ($products) {
                $products->whereIn('product_variants.sku', $stock);
            });

            // Filter product variant by product_attribute_value_id1
            OrbitInput::get('product_attribute_value_id1', function ($product_attribute_value_id1) use ($products) {
                $products->whereIn('product_variants.product_attribute_value_id1', $product_attribute_value_id1);
            });

            // Filter product variant by product_attribute_value_id2
            OrbitInput::get('product_attribute_value_id2', function ($product_attribute_value_id2) use ($products) {
                $products->whereIn('product_variants.product_attribute_value_id2', $product_attribute_value_id2);
            });

            // Filter product variant by product_attribute_value_id3
            OrbitInput::get('product_attribute_value_id3', function ($product_attribute_value_id3) use ($products) {
                $products->whereIn('product_variants.product_attribute_value_id3', $product_attribute_value_id3);
            });

            // Filter product variant by product_attribute_value_id4
            OrbitInput::get('product_attribute_value_id4', function ($product_attribute_value_id4) use ($products) {
                $products->whereIn('product_variants.product_attribute_value_id4', $product_attribute_value_id4);
            });

            // Filter product variant by product_attribute_value_id5
            OrbitInput::get('product_attribute_value_id5', function ($product_attribute_value_id5) use ($products) {
                $products->whereIn('product_variants.product_attribute_value_id5', $product_attribute_value_id5);
            });

            // Filter product variant by merchant_id
            OrbitInput::get('merchant_id', function ($merchant_id) use ($products) {
                $products->whereIn('product_variants.merchant_id', $merchant_id);
            });

            // Filter product variant by retailer_id
            OrbitInput::get('retailer_id', function ($retailer_id) use ($products) {
                $products->whereIn('product_variants.retailer_id', $retailer_id);
            });

            // Filter product variant by created by
            OrbitInput::get('created_by', function ($created_by) use ($products) {
                $products->whereIn('product_variants.created_by', $created_by);
            });

            // Filter product variant by modified by
            OrbitInput::get('modified_by', function ($modified_by) use ($products) {
                $products->whereIn('product_variants.modified_by', $modified_by);
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
            $sortBy = 'product_variants.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'           => 'product_variants.created_at',
                    'product_id'                => 'product_variants.product_id',
                    'product_sku'               => 'product_variants.sku',
                    'product_price'             => 'product_variants.price',
                    'product_merchant_id'       => 'product_variants.merchant_id',
                    'product_retailer_id'       => 'product_variants.retailer_id',
                    'product_status'            => 'product_variants.status',
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

            $data = new \stdClass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = \Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (\Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = \Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        $this->response->code = 0;
        $this->response->status = 'succes';
        $this->response->message = 'succes';
        $httpCode =200;
        $output = $this->render($httpCode);
        return $output;
    }



    /**
     * POST - Save The Transaction
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Agung <agung@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postSaveTransaction()
    {
        try {
            $retailer = $this->getRetailerInfo();
            $total_item     = trim(OrbitInput::post('total_item'));
            $subtotal       = trim(OrbitInput::post('subtotal'));
            $vat            = trim(OrbitInput::post('vat'));
            $total_to_pay   = trim(OrbitInput::post('total_to_pay'));
            $tendered       = trim(OrbitInput::post('tendered'));
            $change         = trim(OrbitInput::post('change'));
            $merchant_id    = trim(OrbitInput::post('merchant_id'));
            $cashier_id     = trim(OrbitInput::post('cashier_id'));
            $customer_id    = trim(OrbitInput::post('customer_id'));
            $payment_method = trim(OrbitInput::post('payment_method'));
            $cart           = OrbitInput::post('cart'); //data of array

            // Begin database transaction
            $this->beginTransaction();

            //insert to table transcations
            $transaction = new \Transaction();
            $transaction->total_item     = $total_item;
            $transaction->subtotal       = $subtotal;
            $transaction->vat            = $vat;
            $transaction->total_to_pay   = $total_to_pay;
            $transaction->tendered       = $tendered;
            $transaction->change         = $change;
            $transaction->merchant_id    = $merchant_id;
            $transaction->cashier_id     = $cashier_id;
            $transaction->customer_id    = $customer_id;
            $transaction->payment_method = $payment_method;
            $transaction->status         = 'paid';

            $transaction->save();

            //insert to table transaction_details
            foreach($cart as $k => $v){
                $transactionDetails = new \TransactionDetail();
                $transactionDetails->transaction_id              = $transaction->transaction_id;
                $transactionDetails->product_id                  = $v['product_id'];
                $transactionDetails->product_name                = $v['product_name'];
                $transactionDetails->product_code                = $v['product_code'];
                $transactionDetails->quantity                    = $v['qty'];
                $transactionDetails->upc                         = $v['upc_code'];
                $transactionDetails->price                       = str_replace( ',', '', $v['price'] );
                // $transactionDetails->variant_price               = $v['variant_price'];
                // $transactionDetails->variant_upc                 = $v['variant_upc'];
                // $transactionDetails->variant_sku                 = $v['variant_sku'];
                // $transactionDetails->variant_stock               = $v['variant_stock'];
                // $transactionDetails->product_attribute_value_id1 = $v['product_attribute_value_id1'];
                // $transactionDetails->product_attribute_value_id2 = $v['product_attribute_value_id2'];
                // $transactionDetails->product_attribute_value_id3 = $v['product_attribute_value_id3'];
                // $transactionDetails->product_attribute_value_id4 = $v['product_attribute_value_id4'];
                // $transactionDetails->product_attribute_value_id5 = $v['product_attribute_value_id5'];
                // $transactionDetails->product_attribute_value1    = $v['product_attribute_value1'];
                // $transactionDetails->product_attribute_value2    = $v['product_attribute_value2'];
                // $transactionDetails->product_attribute_value3    = $v['product_attribute_value3'];
                // $transactionDetails->product_attribute_value4    = $v['product_attribute_value4'];
                // $transactionDetails->product_attribute_value5    = $v['product_attribute_value5'];
                // $transactionDetails->merchant_tax_id1            = $v['merchant_tax_id1'];
                // $transactionDetails->merchant_tax_id2            = $v['merchant_tax_id2'];
                // $transactionDetails->attribute_id1               = $v['attribute_id1'];
                // $transactionDetails->attribute_id2               = $v['attribute_id2'];
                // $transactionDetails->attribute_id3               = $v['attribute_id3'];
                // $transactionDetails->attribute_id4               = $v['attribute_id4'];
                // $transactionDetails->attribute_id5               = $v['attribute_id5'];
                // $transactionDetails->product_attribute_name1     = $v['product_attribute_name1'];
                // $transactionDetails->product_attribute_name2     = $v['product_attribute_name2'];
                // $transactionDetails->product_attribute_name3     = $v['product_attribute_name3'];
                // $transactionDetails->product_attribute_name4     = $v['product_attribute_name4'];
                // $transactionDetails->product_attribute_name5     = $v['product_attribute_name5'];
                $transactionDetails->save();
            }

            // issue product based coupons (if any)
            if($customer_id!=0 ||$customer_id!=NULL){
                foreach($cart as $k => $v){
                    $product_id = $v['product_id'];
                    $coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                    inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                    inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
                    inner join ' . DB::getTablePrefix() . 'products prod on
                    (
                        (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
                        OR
                        (
                            (pr.discount_object_type="family") AND 
                            ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
                            ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                            ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                            ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                            ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                        )
                    )
                    WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND prod.product_id = :productid '), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product_id));
                    if($coupons!=NULL){
                        foreach($coupons as $k => $v){
                            $issue_coupon = new \IssueCoupon();
                            $issue_coupon->promotion_id = $v['promotion_id'];
                            $issue_coupon->issued_coupon_code = '';
                            $issue_coupon->user_id = $customer_id;
                            $issue_coupon->expired_date = Carbon::now()->addDays($v['coupon_validity_in_days']);
                            $issue_coupon->issued_date = Carbon::now();
                            $issue_coupon->issuer_retailer_id = Config::get('orbit.shop.id');
                            $issue_coupon->status = 'active';
                            $issue_coupon->save();
                            $issue_coupon->issued_coupon_code = $this->ISSUE_COUPON_INCREMENT+$issue_coupon->issue_coupon_id;
                            $issue_coupon->save(); 
                        }  
                    }
                }
            }



            // issue cart based coupons (if any)
            if($customer_id!=0 ||$customer_id!=NULL){
                $coupons = Coupon::with('couponrule')->excludeDeleted()
                ->where('merchant_id',$retailer->parent_id)
                ->where('promotion_type','cart')->get()->toArray();
                
                if(is_array($coupons)){
                    foreach($coupons as $kupon){
                        if($total_to_pay >= $kupon['couponrule']['rule_value']){
                            $issue_coupon = new \IssueCoupon();
                            $issue_coupon->promotion_id = $kupon['promotion_id'];
                            $issue_coupon->issued_coupon_code = '';
                            $issue_coupon->user_id = $customer_id;
                            $issue_coupon->expired_date = Carbon::now()->addDays($kupon['coupon_validity_in_days']);
                            $issue_coupon->issued_date = Carbon::now();
                            $issue_coupon->issuer_retailer_id = Config::get('orbit.shop.id');
                            $issue_coupon->status = 'active';
                            $issue_coupon->save();
                            $issue_coupon->issued_coupon_code = $this->ISSUE_COUPON_INCREMENT+$issue_coupon->issue_coupon_id;
                            $issue_coupon->save();
                        }
                    }
                }
            }

            //only payment cash
            if($payment_method == 'cash') self::postCashDrawer();

            $this->response->data = $transaction;
            $this->commit();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }


    /**
     * POST - Print Ticket
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postPrintTicket()
    {
        try {
            $retailer = $this->getRetailerInfo();
            $transaction_id = trim(OrbitInput::post('transaction_id'));

            $transaction = \Transaction::with('details', 'cashier', 'user')->where('transaction_id',$transaction_id)->first();

            if (! is_object($transaction)) {
                $message = \Lang::get('validation.orbit.empty.transaction');
                ACL::throwAccessForbidden($message);
            }

            $this->response->data = $transaction;

            foreach ($transaction['details'] as $key => $value) {
               if($key==0){
                $product = $this->productListFormat(substr($value['product_name'], 0,25), $value['price'], $value['quantity'], $value['product_code']);
               }
               else {
                $product .= $this->productListFormat(substr($value['product_name'], 0,25), $value['price'], $value['quantity'], $value['product_code']);
               }
            }

            $payment = $transaction['payment_method'];
            if($payment=='cash'){$payment='Cash';}
            if($payment=='card'){$payment='Card';}

            $date  =  $transaction['created_at']->timezone('Asia/Jakarta')->format('d M Y H:i:s');

            if($transaction['user']==NULL){
                $customer = "Guest";
            }else{
                $customer = $transaction['user']->user_email;
            }

            $cashier = $transaction['cashier']->user_firstname." ".$transaction['cashier']->user_lastname;
            $bill_no = $transaction['transaction_id'];

            // ticket header
            $ticket_header = $retailer->parent->ticket_header;
            $ticket_header_line = explode("\n", $ticket_header);
            foreach ($ticket_header_line as $line => $value) {
                if($line==0){
                    $head  = $this->just40CharMid($value);
                }else{
                    $head .= $this->just40CharMid($value);
                }
            }
            $head .= '----------------------------------------'." \n";

            $head .= 'Date : '.$date." \n";
            $head .= 'Bill No  : '.$bill_no." \n";
            $head .= 'Cashier : '.$cashier." \n";
            $head .= 'Customer : '.$customer." \n";
            $head .= " \n";
            $head .= '----------------------------------------'." \n";

            $pay   = '----------------------------------------'." \n";
            $pay  .= $this->leftAndRight('SUB TOTAL', number_format($transaction['subtotal'], 2));
            $pay  .= $this->leftAndRight('VAT (10%)', number_format($transaction['vat'], 2));
            $pay  .= $this->leftAndRight('TOTAL', number_format($transaction['total_to_pay'], 2));
            $pay  .= " \n";
            $pay  .= $this->leftAndRight('Payment Method', $payment);
            if($payment=='Cash'){
                $pay  .= $this->leftAndRight('Tendered', number_format($transaction['tendered'], 2));
                $pay  .= $this->leftAndRight('Change', number_format($transaction['change'], 2));
            }
            if($payment=="Card"){
                $pay  .= $this->leftAndRight('Total Paid', number_format($transaction['total_to_pay'], 2));
            }

            $footer  = " \n";
            $footer .= " \n";
            $footer .= " \n";

            // ticket footer
            $ticket_footer = $retailer->parent->ticket_footer;
            $ticket_footer_line = explode("\n", $ticket_footer);
            foreach ($ticket_footer_line as $line => $value) {
                $footer .= $this->just40CharMid($value);
            }

            $footer .= " \n";
            $footer .= " \n";
            $footer .= " \n";
            $footer .= $this->just40CharMid('Powered by DominoPos');
            $footer .= $this->just40CharMid('www.dominopos.com');
            $footer .= '----------------------------------------'." \n";
            $footer .= " \n";
            $footer .= " \n";
            $footer .= " \n";
            $footer .= " \n";
            $footer .= " \n";

            $file = storage_path()."/views/receipt.txt";
            $write = $head.$product.$pay.$footer;

            $fp = fopen($file, 'w');
            fwrite($fp, $write);
            fclose($fp);

            $print = "cat ".storage_path()."/views/receipt.txt > ".Config::get('orbit.devices.printer.params');
            $cut = Config::get('orbit.devices.cutpaper.path');

            shell_exec($print);

            shell_exec($cut);

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }


    /**
     * POST - Card Payment
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postCardPayment()
    {
        try {

            // Check the device exist or not
            if(!file_exists(Config::get('orbit.devices.edc.params')))
            {
                $message = 'Payment Terminal not found'; 
                ACL::throwAccessForbidden($message);
            }

            // Check the driver exist or not
            if(!file_exists(Config::get('orbit.devices.edc.path')))
            {
                $message = 'EDC driver not found'; 
                ACL::throwAccessForbidden($message);
            }

            $amount = trim(OrbitInput::post('amount'));

            $validator = Validator::make(
                array(
                    'amount' => $amount,
                ),
                array(
                    'amount' => 'required|numeric',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $driver = Config::get('orbit.devices.edc.path');
            $params = Config::get('orbit.devices.edc.params');
            $cmd = 'sudo '.$driver.' --device '.$params.' --amounts '.$amount;
            $card = shell_exec($cmd);

            $card = trim($card);

            if($card=='Failed'){
                $message = 'Payment Failed';
                ACL::throwAccessForbidden($message);
            }

            $this->response->data = $card;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }

    /**
     * POST - Cash Drawer
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postCashDrawer()
    {
        try {
            $driver = Config::get('orbit.devices.cashdrawer.path');
            $cmd = 'sudo '.$driver;
            $drawer = shell_exec($cmd);

            $this->response->data = $drawer;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }


    /**
     * POST - Scan Cart
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postScanCart()
    {
        try {
            $barcode = OrbitInput::post('barcode');

            $retailer = $this->getRetailerInfo();
            if(empty($barcode)){
                $driver = Config::get('orbit.devices.barcode.path');
                $params = Config::get('orbit.devices.barcode.params');
                $cmd = 'sudo '.$driver.' '.$params;
                $barcode = shell_exec($cmd);
            }

            $barcode = trim($barcode);
            // $cart = \Cart::with('details.product', 'users')->where('cart_code', $barcode)
            //         ->excludeDeleted()
            //         ->first();

            $cart = \Cart::where('status', 'active')->where('cart_code', $barcode)->first();
            // dd($cart);
            if (! is_object($cart)) {
                $message = \Lang::get('validation.orbit.empty.upc_code');
                ACL::throwAccessForbidden($message);
            }
            
            $user = $cart->users;         

            $cartdetails = CartDetail::with(array('product' => function($q) {
                $q->where('products.status','active');
            }, 'variant' => function($q) {
                $q->where('product_variants.status','active');
            }), 'tax1', 'tax2')->excludeDeleted()->where('status', 'active')->where('cart_id', $cart->cart_id)->get();
            $cartdata = new \stdclass();
            $cartdata->cart = $cart;
            $cartdata->cartdetails = $cartdetails;

            $promo_products = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N" AND p.merchant_id = :merchantid
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id AND prr.retailer_id = :retailerid
                inner join ' . DB::getTablePrefix() . 'products prod on 
                (
                    (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.discount_object_type="family") AND 
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id));
            
            $used_product_coupons = CartCoupon::with(array('cartdetail' => function($q) 
            {
                $q->join('product_variants', 'cart_details.product_variant_id', '=', 'product_variants.product_variant_id');
            }, 'issuedcoupon' => function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id)
                ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');
            }))->whereHas('issuedcoupon', function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id);
            })->whereHas('cartdetail', function($q)
            {
                $q->where('cart_coupons.object_type', '=', 'cart_detail');
            })->get();
            // dd($used_product_coupons);

            $promo_carts = Promotion::with('promotionrule')->excludeDeleted()->where('is_coupon', 'N')->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('retailers', function($q) use ($retailer)
            {
                $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
            })
            ->where(function($q) 
            {
                $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(function($qr)
                {
                    $qr->where('begin_date', '<=', Carbon::now())->where('is_permanent', '=', 'Y');
                });
            })->get();

            $used_cart_coupons = CartCoupon::with(array('cart', 'issuedcoupon' => function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id)
                ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');
            }))
            ->whereHas('cart', function($q) use($cartdata)
            {
                $q->where('cart_coupons.object_type', '=', 'cart')
                ->where('cart_coupons.object_id', '=', $cartdata->cart->cart_id);
            })
            ->where('cart_coupons.object_type', '=', 'cart')->get();

            $subtotal = 0;
            $subtotal_wo_tax = 0;
            $vat = 0;
            $total = 0;
            
            $vat_included = $retailer->parent->vat_included;

            if($vat_included === 'yes') {
                foreach($cartdata->cartdetails as $cartdetail) {
                    $attributes = array();
                    $product_vat_value = 0;
                    $original_price = $cartdetail->variant->price;
                    $original_ammount = $original_price * $cartdetail->quantity;
                    $ammount_after_promo = $original_ammount;
                    $product_price_wo_tax = $original_price;

                    if(!is_null($cartdetail->tax1)) {
                        $product_vat_value =  $product_vat_value + $cartdetail->tax1->tax_value;
                    }
                    if(!is_null($cartdetail->tax2)) {
                        $product_vat_value =  $product_vat_value + $cartdetail->tax2->tax_value;
                    }

                    $product_price_wo_tax = $original_price / (1 + $product_vat_value);
                    $product_vat = ($original_price - $product_price_wo_tax) * $cartdetail->quantity;
                    $vat = $vat + $product_vat;
                    $product_price_wo_tax = $product_price_wo_tax * $cartdetail->quantity;
                    $subtotal = $subtotal + $original_ammount;
                    $subtotal_wo_tax = $subtotal_wo_tax + $product_price_wo_tax;

                    $promo_filters = array_filter($promo_products, function($v) use ($cartdetail) { return $v->product_id == $cartdetail->product_id; });
                    foreach($promo_filters as $promo_filter) {
                        if($promo_filter->rule_type == 'product_discount_by_percentage') {
                            $discount = $promo_filter->discount_value * $original_price;
                            $promo_filter->discount_str = $promo_filter->discount_value * 100;
                        } elseif($promo_filter->rule_type == 'product_discount_by_value') {
                            $discount = $promo_filter->discount_value;
                            $promo_filter->discount_str = $promo_filter->discount_value;
                        }
                        $promo_filter->discount = $discount * $cartdetail->quantity;
                        $ammount_after_promo = $ammount_after_promo - $promo_filter->discount;

                        $promo_wo_tax = $discount / (1 + $product_vat_value);
                        $promo_vat = ($discount - $promo_wo_tax) * $cartdetail->quantity;
                        $vat = $vat - $promo_vat;
                        $promo_wo_tax = $promo_wo_tax * $cartdetail->quantity;
                        $subtotal = $subtotal - $promo_filter->discount;
                        $subtotal_wo_tax = $subtotal_wo_tax - $promo_wo_tax;
                    }
                    $cartdetail->promo_for_this_product = $promo_filters;

                    $coupon_filter = array();
                    foreach($used_product_coupons as $used_product_coupon) {
                        if($used_product_coupon->cartdetail->product_id == $cartdetail->product->product_id) {
                            if($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_percentage') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value * $original_price;
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value * 100;
                            } elseif($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_value') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                            }
                            $used_product_coupon->discount = $discount;
                            $ammount_after_promo = $ammount_after_promo - $discount;
                            $coupon_filter[] = $used_product_coupon;

                            $coupon_wo_tax = $discount / (1 + $product_vat_value);
                            $coupon_vat = ($discount - $coupon_wo_tax);
                            $vat = $vat - $coupon_vat;
                            $subtotal = $subtotal - $discount;
                            $subtotal_wo_tax = $subtotal_wo_tax - $coupon_wo_tax;
                        }
                    }
                    $cartdetail->coupon_for_this_product = $coupon_filter;
                    
                    $cartdetail->original_price = $original_price;
                    $cartdetail->original_ammount = $original_ammount;
                    $cartdetail->ammount_after_promo = $ammount_after_promo;

                    if($cartdetail->attributeValue1['value']) {
                        $attributes[] = $cartdetail->attributeValue1['value'];
                    }
                    if($cartdetail->attributeValue2['value']) {
                        $attributes[] = $cartdetail->attributeValue2['value'];
                    }
                    if($cartdetail->attributeValue3['value']) {
                        $attributes[] = $cartdetail->attributeValue3['value'];
                    }
                    if($cartdetail->attributeValue4['value']) {
                        $attributes[] = $cartdetail->attributeValue4['value'];
                    }
                    if($cartdetail->attributeValue5['value']) {
                        $attributes[] = $cartdetail->attributeValue5['value'];
                    }
                    $cartdetail->attributes = $attributes;
                }
                if(count($cartdata->cartdetails) > 0) {
                    $cart_vat = $vat / $subtotal_wo_tax;
                }

                $cartdiscounts = 0;
                $acquired_promo_carts = array();
                $discount_cart_promo = 0;
                $discount_cart_promo_wo_tax = 0;
                $discount_cart_coupon = 0;
                $cart_promo_taxes = 0;
                $subtotal_before_cart_promo = $subtotal;

                if(!empty($promo_carts)) {
                    foreach($promo_carts as $promo_cart){
                        if($subtotal >= $promo_cart->promotionrule->rule_value){
                            if($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') {
                                $discount = $subtotal * $promo_cart->promotionrule->discount_value;
                                $promo_cart->disc_val_str = '-'.($promo_cart->promotionrule->discount_value * 100).'%';
                                $promo_cart->disc_val = '-'.($subtotal * $promo_cart->promotionrule->discount_value);
                            } elseif ($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') {
                                $discount = $promo_cart->promotionrule->discount_value;
                                $promo_cart->disc_val_str = '-'.$promo_cart->promotionrule->discount_value + 0;
                                $promo_cart->disc_val = '-'.$promo_cart->promotionrule->discount_value + 0;
                            }

                            $cart_promo_wo_tax = $discount / (1 + $cart_vat);
                            $cart_promo_tax = $discount - $cart_promo_wo_tax;
                            $cart_promo_taxes = $cart_promo_taxes + $cart_promo_tax;
                            $discount_cart_promo = $discount_cart_promo + $discount;
                            $discount_cart_promo_wo_tax = $discount_cart_promo_wo_tax + $cart_promo_wo_tax;
                            $acquired_promo_carts[] = $promo_cart;
                        }
                    }
                    
                }

                $coupon_carts = Coupon::join('promotion_rules', function($q) use($subtotal)
                {
                    $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.discount_object_type', '=', 'cash_rebate')->where('promotion_rules.coupon_redeem_rule_value', '<=', $subtotal);
                })->excludeDeleted()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function($q) use ($retailer)
                {
                    $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                })
                ->whereHas('issuedcoupons',function($q) use($user)
                {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
                })->with(array('issuedcoupons' => function($q) use($user)
                {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
                }))
                ->get();

                $available_coupon_carts = array();
                $cart_discount_by_percentage_counter = 0;
                $discount_cart_coupon = 0;
                $discount_cart_coupon_wo_tax = 0;
                $total_cart_coupon_discount = 0;
                $cart_coupon_taxes = 0;
                $acquired_coupon_carts = array();
                if(!empty($used_cart_coupons)) {
                    foreach($used_cart_coupons as $used_cart_coupon) {
                        if(!empty($used_cart_coupon->issuedcoupon->coupon_redeem_rule_value)) {
                            if($subtotal >= $used_cart_coupon->issuedcoupon->coupon_redeem_rule_value) {
                                if($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                                    $used_cart_coupon->disc_val_str = '-'.($used_cart_coupon->issuedcoupon->discount_value * 100).'%';
                                    $used_cart_coupon->disc_val = '-'.($used_cart_coupon->issuedcoupon->discount_value * $subtotal);
                                    $discount = $subtotal * $used_cart_coupon->issuedcoupon->discount_value;
                                    $cart_discount_by_percentage_counter++;
                                } elseif($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
                                    $used_cart_coupon->disc_val_str = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                    $used_cart_coupon->disc_val = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                    $discount = $used_cart_coupon->issuedcoupon->discount_value;
                                }
                                $cart_coupon_wo_tax = $discount / (1 + $cart_vat);
                                $cart_coupon_tax = $discount - $cart_coupon_wo_tax;
                                $cart_coupon_taxes = $cart_coupon_taxes + $cart_coupon_tax;
                                $discount_cart_coupon = $discount_cart_coupon + $discount;
                                $discount_cart_coupon_wo_tax = $discount_cart_coupon_wo_tax + $cart_coupon_wo_tax;

                                $total_cart_coupon_discount = $total_cart_coupon_discount + $discount;
                                $acquired_coupon_carts[] = $used_cart_coupon;
                            } else {
                                $this->beginTransaction();
                                $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $used_cart_coupon->issued_coupon_id)->first();
                                $issuedcoupon->makeActive();
                                $issuedcoupon->save();
                                $used_cart_coupon->delete(TRUE);
                                $this->commit();
                            }
                        }
                    }
                }

                if(!empty($coupon_carts)) {
                    foreach($coupon_carts as $coupon_cart) {
                        if($subtotal >= $coupon_cart->coupon_redeem_rule_value){
                            if($coupon_cart->rule_type == 'cart_discount_by_percentage') {
                                if($cart_discount_by_percentage_counter == 0) { // prevent more than one cart_discount_by_percentage
                                    $discount = $subtotal * $coupon_cart->discount_value;
                                    $cartdiscounts = $cartdiscounts + $discount;
                                    $coupon_cart->disc_val_str = '-'.($coupon_cart->discount_value * 100).'%';
                                    $coupon_cart->disc_val = '-'.($subtotal * $coupon_cart->discount_value);
                                    $available_coupon_carts[] = $coupon_cart;
                                    $cart_discount_by_percentage_counter++;
                                }
                            } elseif ($coupon_cart->rule_type == 'cart_discount_by_value') {
                                $discount = $coupon_cart->discount_value;
                                $cartdiscounts = $cartdiscounts + $discount;
                                $coupon_cart->disc_val_str = '-'.$coupon_cart->discount_value + 0;
                                $coupon_cart->disc_val = '-'.$coupon_cart->discount_value + 0;
                                $available_coupon_carts[] = $coupon_cart;
                            }
                        } else {
                            $coupon_cart->disc_val = $coupon_cart->rule_value;
                        }
                    }
                }

                $subtotal = $subtotal - $discount_cart_promo - $discount_cart_coupon;
                $subtotal_wo_tax = $subtotal_wo_tax - $discount_cart_promo_wo_tax - $discount_cart_coupon_wo_tax;
                $vat = $vat - $cart_promo_taxes - $cart_coupon_taxes;

                $cartsummary = new \stdclass();
                $cartsummary->vat = $vat;
                $cartsummary->total_to_pay = $subtotal;
                $cartsummary->subtotal_wo_tax = $subtotal_wo_tax;
                $cartsummary->acquired_promo_carts = $acquired_promo_carts;
                $cartsummary->used_cart_coupons = $acquired_coupon_carts;
                $cartsummary->available_coupon_carts = $available_coupon_carts;
                $cartsummary->subtotal_before_cart_promo = $subtotal_before_cart_promo;
                $cartdata->cartsummary = $cartsummary;
                // $cartdata->attributes = $attributes;
            } else {

            }

            $this->response->data = $cartdata;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }

    /**
     * POST - Customer display
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postCustomerDisplay()
    {
        try {
            $line1 = trim(OrbitInput::post('line1'));
            $line2 = OrbitInput::post('line2');

            $validator = Validator::make(
                array(
                    'line1' => $line1,
                ),
                array(
                    'line1' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $driver = Config::get('orbit.devices.cdisplay.path');
            $params = Config::get('orbit.devices.cdisplay.params');

            $cmd = 'screen '.$params;
            $screen = shell_exec($cmd);

            if(strlen($line1)<20)
            {
                $line1_length = strlen($line1);
                $fill = 40 - $line1_length;
                $line1 = str_pad($line1, $fill, "\ ");
            }

            if(strlen($line2)<20)
            {
                $line2_length = strlen($line2);
                $fill = 36 - $line2_length;
                $line2 = str_pad($line2, $fill, "\ ");
            }

            $cmd = 'sudo '.$driver.' '.$line1.$line2;
            $display = shell_exec($cmd);

            $this->response->data = $screen;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }


    /**
     * POST - Product Detail with variant and promotion
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postProductDetail()
    {
        try {
            $product_id = trim(OrbitInput::post('product_id'));

            $validator = Validator::make(
            array(
                'product_id' => $product_id,
            ),
            array(
                'product_id' => 'required|numeric',
            )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $retailer = \Retailer::with('parent')->where('merchant_id', Config::get('orbit.shop.id'))->first();

            $product = Product::with('variants', 'attribute1', 'attribute2', 'attribute3', 'attribute4', 'attribute5')->whereHas('retailers', function($query) use ($retailer) {
                       $query->where('retailer_id', $retailer->merchant_id);
                      })->excludeDeleted()->where('product_id', $product_id)->first();

            if (! is_object($product)) {
                $message = \Lang::get('validation.orbit.empty.product');
                ACL::throwAccessForbidden($message);
            }

            $promo_products = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N" AND p.merchant_id = :merchantid
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id AND prr.retailer_id = :retailerid
                inner join ' . DB::getTablePrefix() . 'products prod on
                (
                    (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id)
                    OR
                    (
                        (pr.discount_object_type="family") AND
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )
                WHERE prod.product_id = :productid'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product->product_id));

            $attributes = DB::select(DB::raw('SELECT v.upc, v.sku, v.product_variant_id, av1.value as value1, av1.product_attribute_value_id as attr_val_id1, av2.product_attribute_value_id as attr_val_id2, av3.product_attribute_value_id as attr_val_id3, av4.product_attribute_value_id as attr_val_id4, av5.product_attribute_value_id as attr_val_id5, av2.value as value2, av3.value as value3, av4.value as value4, av5.value as value5, v.price, pa1.product_attribute_name as attr1, pa2.product_attribute_name as attr2, pa3.product_attribute_name as attr3, pa4.product_attribute_name as attr4, pa5.product_attribute_name as attr5 FROM ' . DB::getTablePrefix() . 'product_variants v
            inner join ' . DB::getTablePrefix() . 'products p on p.product_id = v.product_id
            left join ' . DB::getTablePrefix() . 'product_attribute_values as av1 on av1.product_attribute_value_id = v.product_attribute_value_id1
            left join ' . DB::getTablePrefix() . 'product_attribute_values as av2 on av2.product_attribute_value_id = v.product_attribute_value_id2
            left join ' . DB::getTablePrefix() . 'product_attribute_values as av3 on av3.product_attribute_value_id = v.product_attribute_value_id3
            left join ' . DB::getTablePrefix() . 'product_attribute_values as av4 on av4.product_attribute_value_id = v.product_attribute_value_id4
            left join ' . DB::getTablePrefix() . 'product_attribute_values as av5 on av5.product_attribute_value_id = v.product_attribute_value_id5
            left join ' . DB::getTablePrefix() . 'product_attributes as pa1 on pa1.product_attribute_id = av1.product_attribute_id
            left join ' . DB::getTablePrefix() . 'product_attributes as pa2 on pa2.product_attribute_id = av2.product_attribute_id
            left join ' . DB::getTablePrefix() . 'product_attributes as pa3 on pa3.product_attribute_id = av3.product_attribute_id
            left join ' . DB::getTablePrefix() . 'product_attributes as pa4 on pa4.product_attribute_id = av4.product_attribute_id
            left join ' . DB::getTablePrefix() . 'product_attributes as pa5 on pa5.product_attribute_id = av5.product_attribute_id
            WHERE p.product_id = :productid'), array('productid' => $product->product_id));

            // $resp = new \stdClass();
            //         $resp->code = 0;
            //         $resp->status = 'success';
            //         $resp->dataproduct = $product;
            //         $resp->promo_products = $promo_products;
            //         $resp->attributes = $attributes;
            // return \Response::json($resp);
            $result['product'] = $product;
            $result['promo'] = $promo_products;
            $result['attributes'] = $attributes;
            $this->response->data = $result;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        return $this->render();
    }

    /**
     * POST - API for checking Cart Based Promotion
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postCartBasedPromotion()
    {
        try {
            $retailer = $this->getRetailerInfo();
            // check for cart based promotions
            $promo_carts = \Promotion::with('promotionrule')->excludeDeleted()
                ->where('is_coupon', 'N')
                ->where('promotion_type', 'cart')
                ->where('merchant_id', $retailer['parent']['merchant_id'])
                ->whereHas('retailers', function($q) use ($retailer)
                {
                    $q->where('promotion_retailer.retailer_id', Config::get('orbit.shop.id'));
                })
                ->where(function($q) 
                {
                    $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(function($qr)
                    {
                        $qr->where('begin_date', '<=', Carbon::now())->where('is_permanent', '=', 'Y');
                    });
                })
                ->get();

            $this->response->status = 'success';
            $this->response->message = 'success';
            $this->response->data = $promo_carts;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        return $this->render();
    }

    private function just40CharMid($str)
    {
        $nnn = strlen($str);
        if ($nnn>40) {
            $all = explode('::break-here::', wordwrap($str,38,'::break-here::'));
            $tmp = '';
            foreach ($all as $str) {
                $space = round( (40-strlen($str))/2 );
                $spc = '';
                for ($i=0;$i<$space;$i++) { $spc .= ' '; }
                $tmp .= $spc.$str." \n";
            }
        } else {
            $space = round( (40-strlen($str))/2 );
            $spc = '';
            for ($i=0;$i<$space;$i++) { $spc .= ' '; }
            $tmp = $spc.$str." \n";
        }

        return $tmp;
    }

    private function productListFormat($name, $price, $qty, $sku)
    {
        $all  = '';
        $sbT = number_format($price*$qty,2);
        $space = 40-strlen($name)-strlen($sbT); $spc = '';
        for ($i=0;$i<$space;$i++) { $spc .= ' '; }
        $all .= $name.$spc.$sbT." \n";
        $all .= '   '.$qty.' x '.number_format($price,2).' ('.$sku.')'." \n";

        return $all;
    }

    private function discountListFormat($discount_name, $discount_value)
    {
        $all  = '';
        $sbT = number_format($discount_value,2);
        $space = 36-strlen($discount_name)-strlen($sbT); $spc = '';
        for ($i=0;$i<$space;$i++) { $spc .= ' '; }
        $all .= '   '.$discount_name.$spc."-".$sbT." \n";

        return $all;
    }

    private function leftAndRight($left, $right)
    {
        $all  = '';
        $space = 40-strlen($left)-strlen($right); $spc = '';
        for($i=0;$i<$space;$i++){ $spc .= ' '; }
        $all .= $left.$spc.$right." \n";
        return $all;
    }

    public function getRetailerInfo()
    {
        try {
            $retailer_id = Config::get('orbit.shop.id');
            $retailer = \Retailer::with('parent')->where('merchant_id', $retailer_id)->first();
            return $retailer;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
    }
}
