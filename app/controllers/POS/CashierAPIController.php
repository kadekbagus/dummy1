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
use \User;
use \UserDetail;
use \Role;
use \Lang;
use \Apikey;
use \Validator;
use \Product;
use \CartCoupon;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Config;
use \DB;
use Carbon\Carbon as Carbon;
use \Hash;
use Activity;
use \CartDetail;
use \Promotion;
use \Coupon;
use \IssuedCoupon;
use Helper\EloquentRecordCounter as RecordCounter;
use \Mail;
use Exception;

class CashierAPIController extends ControllerAPI
{

    /**
     * POST - Login cashier in shop
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `username`  (required) - username of the cashier
     * @param string    `password`  (required) - password of the cashier
     * @return \Illuminate\Support\Facades\Response
     */
    public function postLoginCashier()
    {
        $activity = Activity::pos()->setActivityType('login');
        try {
            $username = OrbitInput::post('username');
            $password = OrbitInput::post('password');

            $validator = Validator::make(
                array(
                    'username' => $username,
                    'password' => $password,
                ),
                array(
                    'username' => 'required',
                    'password' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $retailer = $this->getRetailerInfo();

            $role = Role::where('role_name', 'cashier')->first();
            if (empty($role)) {
                $message = Lang::get('validation.orbit.empty.employee.role',
                    array('role' => 'Cashier')
                );
                ACL::throwAccessForbidden($message);
            }

            $user = User::with('apikey', 'role', 'employee')
                        ->active('users')
                        ->where('username', $username)
                        ->where('user_role_id', $role->role_id)
                        ->employeeMerchantIds([$retailer->parent_id])
                        ->first();

            $merchant = $retailer->parent;

            // This should be not happens unless someone messing up the database
            // manually
            if (empty($user->employee)) {
                $message = Lang::get('Internal error, employee object is empty.');
                ACL::throwAccessForbidden($message);
            }

            // Check to make sure the cashier only can login to current merchant
            // on the box
            $cashierMerchantIds = $user->employee->getMyMerchantIds();
            if (! in_array($merchant->merchant_id, $cashierMerchantIds)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

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
                             ->setSessionId($session->getSessionId())
                             ->responseOK();
                }
            } else {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            $user->setHidden(array('user_password', 'apikey'));

            $result['user'] = $user;
            $result['merchant'] = $merchant;

            $this->response->data = $result;
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
            $this->response->code = $this->getNonZeroCode($e->getCode());
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
     * @return \Illuminate\Support\Facades\Response
     */
    public function postLogoutCashier()
    {
        $activity = Activity::pos()->setActivityType('logout');
        try {
            $config  = new SessionConfig(Config::get('orbit.session'));
            $session = new Session($config);

            $user    = User::where('user_id', $session->read('user_id'))->first() || 'Guest';
            $session->start(array(), 'no-session-creation');
            $session->destroy();

            // Successfull login
            $activity->setUser($user)
                ->setActivityName('logout_ok')
                ->setActivityNameLong('Logout OK')
                ->responseOK();

        } catch (Exception $e) {
            $activity->setUser($user)
                ->setActivityName('logout_failed')
                ->setActivityNameLong('Logout Failed')
                ->setNotes($e->getMessage())
                ->responseFailed();
        }

        $activity->save();

        return \Redirect::to('/pos');
    }

    /**
     * POST - Scan Barcode New
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return \Illuminate\Support\Facades\Response
     */
    public function postScanBarcode()
    {
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $retailer = $this->getRetailerInfo();

            // Check the device exist or not
            if (!file_exists(Config::get('orbit.devices.barcode.params'))) {
                $message = 'Scanner not found';
                ACL::throwAccessForbidden($message);
            }

            // Check the driver exist or not
            if (!file_exists(Config::get('orbit.devices.barcode.path'))) {
                $message = 'Scanner driver not found';
                ACL::throwAccessForbidden($message);
            }

            // Kill the previous barcode process
            // @Todo use config file
            shell_exec('sudo /usr/bin/killall /opt/programs/orbit-driver/64bit/barcode');

            $driver = Config::get('orbit.devices.barcode.path');
            $params = Config::get('orbit.devices.barcode.params');
            $cmd = 'sudo '.$driver.' '.$params;
            $barcode = shell_exec($cmd);

            if ($barcode === NULL) {
                throw new Exception ('shell error');
            }

            $barcode = trim($barcode);

            $product = Product::with(array('variants' => function ($q) use ($barcode) {
                $q->where('product_variants.upc', $barcode);
            }, 'variants.attributeValue1', 'variants.attributeValue2', 'variants.attributeValue3', 'variants.attributeValue4', 'variants.attributeValue5'))
            ->whereHas('variants', function ($q) use ($barcode) {
                $q->where('product_variants.upc', $barcode);
            })
            ->whereHas('retailers', function ($q) use ($retailer) {
                $q->where('product_retailer.retailer_id', $retailer->merchant_id);
            })
            ->active()
            ->first();

            if (! is_object($product)) {
                $message = \Lang::get('validation.orbit.empty.upc_code');
                ACL::throwAccessForbidden($message);
            }

            $product_x = $product->variants;
            $product_id = null;

            if ($product_x[0]->default_variant == "yes") {
                $product_id = $product_x[0]->product_id;
                $product = Product::with(array('variants' => function ($q) use ($product_id) {
                    $q->where('product_variants.product_id', $product_id);
                }, 'variants.attributeValue1', 'variants.attributeValue2', 'variants.attributeValue3', 'variants.attributeValue4', 'variants.attributeValue5'))
                ->whereHas('variants', function ($q) use ($product_id) {
                    $q->where('product_variants.product_id', $product_id);
                })
                ->whereHas('retailers', function ($q) use ($retailer) {
                    $q->where('product_retailer.retailer_id', $retailer->merchant_id);
                })
                ->active()
                ->first();
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
            $this->response->code = $this->getNonZeroCode($e->getCode());
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
     * @return \Illuminate\Support\Facades\Response
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

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $maxRecord = 300;

            $retailer = $this->getRetailerInfo();

            $products = Product::whereHas('retailers', function ($query) use ($retailer) {
                            $query->where('retailer_id', $retailer->merchant_id);
                        })->where('merchant_id', $retailer->parent_id)->where('status', 'active');

            // Filter product by name pattern
            OrbitInput::get('keyword', function ($name) use ($products) {
                $products->where(function ($q) use ($name) {
                    $q->where('products.product_name', 'like', "%$name%")
                        ->orWhere('products.upc_code', 'like', "%$name%")
                        ->orWhere('products.product_code', 'like', "%$name%")
                        ->orWhere('products.long_description', 'like', "%$name%")
                        ->orWhere('products.short_description', 'like', "%$name%");
                });
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
            $sortBy = 'products.product_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sort_by', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'product_name'      => 'products.product_name',
                    'price'             => 'products.price',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sort_mode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                } else {
                    $sortMode = 'desc';
                }
            });
            $products->orderBy($sortBy, $sortMode);

            $totalRec = RecordCounter::create($_products)->count();
            $listOfProduct = $products->get();

            $data = new \stdClass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfProduct);
            $data->records = $listOfProduct;

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
     * @return \Illuminate\Support\Facades\Response
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

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

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
        $httpCode = 200;
        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * POST - Save The Transaction
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Agung <agung@dominopos.com>
     *
     * @return \Illuminate\Support\Facades\Response
     */
    public function postSaveTransaction()
    {
        $activity = Activity::POS()
                            ->setActivityType('payment');
        $user = null;
        $customer = null;
        $activity_payment = null;
        $activity_payment_label = null;
        $transaction = null;
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $retailer = $this->getRetailerInfo();
            $total_item       = trim(OrbitInput::post('total_item'));
            $subtotal         = trim(OrbitInput::post('subtotal'));
            $vat              = trim(OrbitInput::post('vat'));
            $total_to_pay     = trim(OrbitInput::post('total_to_pay'));
            $tendered         = trim(OrbitInput::post('tendered'));
            $change           = trim(OrbitInput::post('change'));
            $merchant_id      = $retailer->parent->merchant_id;
            $retailer_id      = $retailer->merchant_id;
            $cashier_id       = trim(OrbitInput::post('cashier_id'));
            $customer_id      = trim(OrbitInput::post('customer_id'));
            $payment_method   = trim(OrbitInput::post('payment_method'));
            $currency         = trim(OrbitInput::post('currency'));
            $cart             = OrbitInput::post('cart'); //data of array
            $cart_promotion   = OrbitInput::post('cart_promotion'); // data of array
            $cart_coupon      = OrbitInput::post('cart_coupon'); // data of array
            $taxes            = OrbitInput::post('taxes'); // data of array
            $cart_id = null;

            $activity_payment = 'payment_cash';
            $activity_payment_label = 'Payment Cash';

            //only payment cash
            if ($payment_method == 'cash') {
                self::postCashDrawer();
            } else {
                $activity_payment = 'payment_card';
                $activity_payment_label = 'Payment Card';
            }

            if (empty($customer_id)) {
                $customer_id = 0;
            } else {
                $customer = User::excludeDeleted()->find($customer_id);
            }

            $validator = Validator::make(
                array(
                    'total_item'       => $total_item,
                    'subtotal'         => $subtotal,
                    'vat'              => $vat,
                    'total_to_pay'     => $total_to_pay,
                    'tendered'         => $tendered,
                    'change'           => $change,
                    'cashier_id'       => $cashier_id,
                    'payment_method'   => $payment_method,
                    'cart'             => $cart,
                ),
                array(
                    'total_item'       => 'required',
                    'subtotal'         => 'required',
                    'vat'              => 'required',
                    'total_to_pay'     => 'required',
                    'tendered'         => 'required',
                    'change'           => 'required',
                    'cashier_id'       => 'required',
                    'payment_method'   => 'required',
                    'cart'             => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Begin database transaction
            $this->beginTransaction();

            //insert to table transaction
            $transaction = new \Transaction();
            $transaction->total_item     = $total_item;
            $transaction->subtotal       = $subtotal;
            $transaction->vat            = $vat;
            $transaction->total_to_pay   = $total_to_pay;
            $transaction->tendered       = $tendered;
            $transaction->change         = $change;
            $transaction->merchant_id    = $merchant_id;
            $transaction->retailer_id    = $retailer_id;
            $transaction->cashier_id     = $cashier_id;
            $transaction->customer_id    = $customer_id;
            $transaction->payment_method = $payment_method;
            $transaction->currency       = $currency;
            $transaction->status         = 'paid';

            $transaction->save();

            if ($customer_id != 0 || $customer_id != NULL) {
                $update_user_detail = \UserDetail::where('user_id', $customer_id)->first();
                $update_user_detail->last_spent_any_shop = $total_to_pay;
                $update_user_detail->last_spent_shop_id = $retailer_id;
                $update_user_detail->save();
            }

            //insert to table transaction_details
            foreach ($cart as $cart_key => $cart_value) {
                $cart_id = $cart_value['cart_id'];
                $transactiondetail = new \TransactionDetail();
                $transactiondetail->transaction_id = $transaction->transaction_id;

                if (!empty($cart_value['product_id'])) {
                    $transactiondetail->product_id = $cart_value['product_id'];
                }
                if (!empty($cart_value['product_name'])) {
                    $transactiondetail->product_name = $cart_value['product_name'];
                }
                if (!empty($cart_value['product_code'])) {
                    $transactiondetail->product_code = $cart_value['product_code'];
                }
                if (!empty($cart_value['qty'])) {
                    $transactiondetail->quantity = $cart_value['qty'];
                }
                if (!empty($cart_value['upc_code'])) {
                    $transactiondetail->upc = $cart_value['upc_code'];
                }
                if (!empty($cart_value['price'])) {
                    $transactiondetail->price = str_replace( ',', '', $cart_value['price'] );
                }
                if (!empty($cart_value['currency'])) {
                    $transactiondetail->currency = $cart_value['currency'];
                }

                if (!empty($cart_value['variants'])) {
                    if (!empty($cart_value['variants']['product_variant_id'])) {
                        $transactiondetail->product_variant_id  = $cart_value['variants']['product_variant_id'];
                    }
                    if (!empty($cart_value['variants']['price'])) {
                        $transactiondetail->variant_price       = $cart_value['variants']['price'];
                    }
                    if (!empty($cart_value['variants']['upc'])) {
                        $transactiondetail->variant_upc         = $cart_value['variants']['upc'];
                    }
                    if (!empty($cart_value['variants']['sku'])) {
                        $transactiondetail->variant_sku         = $cart_value['variants']['sku'];
                    }

                    if (! empty($cart_value['variants']['product_attribute_value_id1'])) {
                        $transactiondetail->product_attribute_value_id1 = $cart_value['variants']['product_attribute_value_id1'];
                        $product_attribute_name1 = \ProductAttributeValue::with('attribute')->where('product_attribute_value_id', $cart_value['variants']['product_attribute_value_id1'])->first()->attribute->product_attribute_name;
                        if (! empty($product_attribute_name1)) {
                            $transactiondetail->product_attribute_name1 = $product_attribute_name1;
                        }
                    }
                    if (! empty($cart_value['variants']['product_attribute_value_id2'])) {
                        $transactiondetail->product_attribute_value_id2 = $cart_value['variants']['product_attribute_value_id2'];
                        $product_attribute_name2 = \ProductAttributeValue::with('attribute')->where('product_attribute_value_id', $cart_value['variants']['product_attribute_value_id2'])->first()->attribute->product_attribute_name;
                        if (! empty($product_attribute_name2)) {
                            $transactiondetail->product_attribute_name2 = $product_attribute_name2;
                        }
                    }
                    if (! empty($cart_value['variants']['product_attribute_value_id3'])) {
                        $transactiondetail->product_attribute_value_id3 = $cart_value['variants']['product_attribute_value_id3'];
                        $product_attribute_name3 = \ProductAttributeValue::with('attribute')->where('product_attribute_value_id', $cart_value['variants']['product_attribute_value_id3'])->first()->attribute->product_attribute_name;
                        if (! empty($product_attribute_name3)) {
                            $transactiondetail->product_attribute_name3 = $product_attribute_name3;
                        }
                    }
                    if (! empty($cart_value['variants']['product_attribute_value_id4'])) {
                        $transactiondetail->product_attribute_value_id4 = $cart_value['variants']['product_attribute_value_id4'];
                        $product_attribute_name4 = \ProductAttributeValue::with('attribute')->where('product_attribute_value_id', $cart_value['variants']['product_attribute_value_id4'])->first()->attribute->product_attribute_name;
                        if (! empty($product_attribute_name4)) {
                            $transactiondetail->product_attribute_name4 = $product_attribute_name4;
                        }
                    }
                    if (! empty($cart_value['variants']['product_attribute_value_id5'])) {
                        $transactiondetail->product_attribute_value_id5 = $cart_value['variants']['product_attribute_value_id5'];
                        $product_attribute_name5 = \ProductAttributeValue::with('attribute')->where('product_attribute_value_id', $cart_value['variants']['product_attribute_value_id5'])->first()->attribute->product_attribute_name;
                        if (! empty($product_attribute_name5)) {
                            $transactiondetail->product_attribute_name5 = $product_attribute_name5;
                        }
                    }

                    if (! empty($cart_value['attributes'][0])) {
                        $transactiondetail->product_attribute_value1 = $cart_value['attributes'][0];
                    }

                    if (! empty($cart_value['attributes'][1])) {
                        $transactiondetail->product_attribute_value2 = $cart_value['attributes'][1];
                    }

                    if (! empty($cart_value['attributes'][2])) {
                        $transactiondetail->product_attribute_value3 = $cart_value['attributes'][2];
                    }

                    if (! empty($cart_value['attributes'][3])) {
                        $transactiondetail->product_attribute_value4 = $cart_value['attributes'][3];
                    }

                    if (! empty($cart_value['attributes'][4])) {
                        $transactiondetail->product_attribute_value5 = $cart_value['attributes'][4];
                    }
                }

                if (!empty($cart_value['product_details']['merchant_tax_id1'])) {
                    $transactiondetail->merchant_tax_id1 = $cart_value['product_details']['merchant_tax_id1'];
                }
                if (!empty($cart_value['product_details']['merchant_tax_id2'])) {
                    $transactiondetail->merchant_tax_id2 = $cart_value['product_details']['merchant_tax_id2'];
                }
                if (!empty($cart_value['product_details']['attribute_id1'])) {
                    $transactiondetail->attribute_id1    = $cart_value['product_details']['attribute_id1'];
                }
                if (!empty($cart_value['product_details']['attribute_id2'])) {
                    $transactiondetail->attribute_id2    = $cart_value['product_details']['attribute_id2'];
                }
                if (!empty($cart_value['product_details']['attribute_id3'])) {
                    $transactiondetail->attribute_id3    = $cart_value['product_details']['attribute_id3'];
                }
                if (!empty($cart_value['product_details']['attribute_id4'])) {
                    $transactiondetail->attribute_id4    = $cart_value['product_details']['attribute_id4'];
                }
                if (!empty($cart_value['product_details']['attribute_id5'])) {
                    $transactiondetail->attribute_id5    = $cart_value['product_details']['attribute_id5'];
                }

                $transactiondetail->save();

                // product based promotion
                if (!empty($cart_value['promotion'])) {
                    foreach ($cart_value['promotion'] as $key => $value) {
                        if ($value['promotion_name'] != "Subtotal") {
                            $transactiondetailpromotion = new \TransactionDetailPromotion();
                            $transactiondetailpromotion->transaction_detail_id = $transactiondetail->transaction_detail_id;
                            $transactiondetailpromotion->transaction_id = $transaction->transaction_id;
                            if (!empty($value['promotion_id'])) {
                                $transactiondetailpromotion->promotion_id = $value['promotion_id'];
                            }
                            if (!empty($value['promotion_name'])) {
                                $transactiondetailpromotion->promotion_name = $value['promotion_name'];
                            }

                            if (!empty($value['promotion_type'])) {
                                $transactiondetailpromotion->promotion_type = $value['promotion_type'];
                            } elseif (!empty($value['promotion_detail']['promotion_type'])) {
                                $transactiondetailpromotion->promotion_type = $value['promotion_detail']['promotion_type'];
                            }

                            if (!empty($value['rule_type'])) {
                                $transactiondetailpromotion->rule_type = $value['rule_type'];
                            }

                            if (!empty($value['rule_value'])) {
                                $transactiondetailpromotion->rule_value = $value['rule_value'];
                            } elseif (!empty($value['promotion_detail']['rule_value'])) {
                                $transactiondetailpromotion->rule_value = $value['promotion_detail']['rule_value'];
                            }

                            if (!empty($value['discount_object_type'])) {
                                $transactiondetailpromotion->discount_object_type = $value['discount_object_type'];
                            } elseif (!empty($value['promotion_detail']['discount_object_type'])) {
                                $transactiondetailpromotion->discount_object_type = $value['promotion_detail']['discount_object_type'];
                            }

                            if (!empty($value['oridiscount_value'])) {
                                $transactiondetailpromotion->discount_value = $value['oridiscount_value'];
                            }

                            if (!empty($value['tmpafterpromotionprice'])) {
                                $transactiondetailpromotion->value_after_percentage = $value['tmpafterpromotionprice'];
                            }

                            if (!empty($value['description'])) {
                                $transactiondetailpromotion->description = $value['description'];
                            } elseif (!empty($value['promotion_detail']['description'])) {
                                $transactiondetailpromotion->description = $value['promotion_detail']['description'];
                            }

                            if (!empty($value['begin_date'])) {
                                $transactiondetailpromotion->begin_date = $value['begin_date'];
                            } elseif (!empty($value['promotion_detail']['begin_date'])) {
                                $transactiondetailpromotion->begin_date = $value['promotion_detail']['begin_date'];
                            }

                            if (!empty($value['end_date'])) {
                                $transactiondetailpromotion->end_date = $value['end_date'];
                            } elseif (!empty($value['promotion_detail']['end_date'])) {
                                $transactiondetailpromotion->end_date = $value['promotion_detail']['end_date'];
                            }

                            $transactiondetailpromotion->save();
                        }
                    }
                }

                // product based coupon
                if (!empty($cart_value['product_details']['coupon_for_this_product'])) {
                    foreach ($cart_value['product_details']['coupon_for_this_product'] as $key => $value) {
                            $transactiondetailcoupon = new \TransactionDetailCoupon();
                            $transactiondetailcoupon->transaction_detail_id = $transactiondetail->transaction_detail_id;
                            $transactiondetailcoupon->transaction_id = $transaction->transaction_id;
                            if (!empty($value['issuedcoupon']['issued_coupon_id'])) {
                                $transactiondetailcoupon->promotion_id = $value['issuedcoupon']['issued_coupon_id'];
                            }
                            if (!empty($value['issuedcoupon']['promotion_name'])) {
                                $transactiondetailcoupon->promotion_name = $value['issuedcoupon']['promotion_name'];
                            }
                            if (!empty($value['issuedcoupon']['promotion_type'])) {
                                $transactiondetailcoupon->promotion_type = $value['issuedcoupon']['promotion_type'];
                            }
                            if (!empty($value['issuedcoupon']['rule_type'])) {
                                $transactiondetailcoupon->rule_type = $value['issuedcoupon']['rule_type'];
                            }
                            if (!empty($value['issuedcoupon']['rule_value'])) {
                                $transactiondetailcoupon->rule_value = $value['issuedcoupon']['rule_value'];
                            }
                            if (!empty($value['issuedcoupon']['rule_object_id1'])) {
                                $transactiondetailcoupon->category_id1 = $value['issuedcoupon']['rule_object_id1'];
                            }
                            if (!empty($value['issuedcoupon']['rule_object_id2'])) {
                                $transactiondetailcoupon->category_id2 = $value['issuedcoupon']['rule_object_id2'];
                            }
                            if (!empty($value['issuedcoupon']['rule_object_id3'])) {
                                $transactiondetailcoupon->category_id3 = $value['issuedcoupon']['rule_object_id3'];
                            }
                            if (!empty($value['issuedcoupon']['rule_object_id4'])) {
                                $transactiondetailcoupon->category_id4 = $value['issuedcoupon']['rule_object_id4'];
                            }
                            if (!empty($value['issuedcoupon']['rule_object_id5'])) {
                                $transactiondetailcoupon->category_id5 = $value['issuedcoupon']['rule_object_id5'];
                            }
                            if (!empty($value['issuedcoupon']['discount_object_id1'])) {
                                $transactiondetailcoupon->category_name1 = $value['issuedcoupon']['discount_object_id1'];
                            }
                            if (!empty($value['issuedcoupon']['discount_object_id2'])) {
                                $transactiondetailcoupon->category_name2 = $value['issuedcoupon']['discount_object_id2'];
                            }
                            if (!empty($value['issuedcoupon']['discount_object_id3'])) {
                                $transactiondetailcoupon->category_name3 = $value['issuedcoupon']['discount_object_id3'];
                            }
                            if (!empty($value['issuedcoupon']['discount_object_id4'])) {
                                $transactiondetailcoupon->category_name4 = $value['issuedcoupon']['discount_object_id4'];
                            }
                            if (!empty($value['issuedcoupon']['discount_object_id5'])) {
                                $transactiondetailcoupon->category_name5 = $value['issuedcoupon']['discount_object_id5'];
                            }
                            if (!empty($value['issuedcoupon']['discount_object_type'])) {
                                $transactiondetailcoupon->discount_object_type = $value['issuedcoupon']['discount_object_type'];
                            }
                            if (!empty($value['oridiscount_value'])) {
                                $transactiondetailcoupon->discount_value = $value['oridiscount_value'];
                            }
                            if (!empty($value['tmpafterpromotionprice'])) {
                                $transactiondetailcoupon->value_after_percentage = $value['tmpafterpromotionprice'];
                            }
                            if (!empty($value['issuedcoupon']['coupon_redeem_rule_value'])) {
                                $transactiondetailcoupon->coupon_redeem_rule_value = $value['issuedcoupon']['coupon_redeem_rule_value'];
                            }
                            if (!empty($value['issuedcoupon']['description'])) {
                                $transactiondetailcoupon->description = $value['issuedcoupon']['description'];
                            }
                            if (!empty($value['issuedcoupon']['begin_date'])) {
                                $transactiondetailcoupon->begin_date = $value['issuedcoupon']['begin_date'];
                            }
                            if (!empty($value['issuedcoupon']['end_date'])) {
                                $transactiondetailcoupon->end_date = $value['issuedcoupon']['end_date'];
                            }

                            $transactiondetailcoupon->save();

                            // coupon redeemed
                            if (!empty($value['issuedcoupon']['issued_coupon_id'])) {
                                $coupon_id = intval($value['issuedcoupon']['issued_coupon_id']);
                                $coupon_redeemed = IssuedCoupon::where('status', 'active')->where('issued_coupon_id', $coupon_id)->update(array('status' => 'redeemed'));
                            }
                    }
                }

            }

            // transaction detail taxes
            if (!empty($taxes)) {
                foreach ($taxes as $key => $value) {
                    $transactiondetailtax = new \TransactionDetailTax();
                    $transactiondetailtax->transaction_detail_id = $transactiondetail->transaction_detail_id;
                    $transactiondetailtax->transaction_id = $transaction->transaction_id;
                    if (!empty($value['id'])) {
                        $transactiondetailtax->tax_id = $value['id'];
                    }
                    if (!empty($value['name'])) {
                        $transactiondetailtax->tax_name = $value['name'];
                    }
                    if (!empty($value['value'])) {
                        $transactiondetailtax->tax_value = $value['value'];
                    }
                    if (!empty($value['total'])) {
                        $transactiondetailtax->total_tax = $value['total'];
                    }
                    $transactiondetailtax->save();
                }
            }

            // cart based promotion
            if (!empty($cart_promotion)) {
                foreach ($cart_promotion as $key => $value) {
                    if ($value['promotion_name']!="Subtotal") {
                        $transactiondetailpromotion = new \TransactionDetailPromotion();
                        $transactiondetailpromotion->transaction_detail_id = $transactiondetail->transaction_detail_id;
                        $transactiondetailpromotion->transaction_id = $transaction->transaction_id;
                        if (!empty($value['promotion_id'])) {
                            $transactiondetailpromotion->promotion_id = $value['promotion_id'];
                            $promoid =  $value['promotion_id'];
                        } else {
                            $promoid =  0;
                        }
                        if (!empty($value['promotion_name'])) {
                            $transactiondetailpromotion->promotion_name = $value['promotion_name'];
                            $promoname = $value['promotion_name'];
                        } else {
                            $promoname = '';
                        }
                        if (!empty($value['promotion_type'])) {
                            $transactiondetailpromotion->promotion_type = $value['promotion_type'];
                        }
                        if (!empty($value['promotionrule']['rule_type'])) {
                            $transactiondetailpromotion->rule_type = $value['promotionrule']['rule_type'];
                        }
                        if (!empty($value['promotionrule']['rule_value'])) {
                            $transactiondetailpromotion->rule_value = $value['promotionrule']['rule_value'];
                        }
                        if (!empty($value['promotionrule']['discount_object_type'])) {
                            $transactiondetailpromotion->discount_object_type = $value['promotionrule']['discount_object_type'];
                        }
                        if ($value['promotionrule']['rule_type'] == "cart_discount_by_percentage") {
                            $discount_percent = intval($value['promotionrule']['discount'])/100;
                            $discount_value = $this->removeFormat($value['promotionrule']['discount_value']);
                            $transactiondetailpromotion->discount_value = $discount_percent;
                            $transactiondetailpromotion->value_after_percentage = $discount_value;
                        } else {
                            $discount_value = $this->removeFormat($value['promotionrule']['discount_value']);
                            $transactiondetailpromotion->discount_value = $discount_value;
                            $transactiondetailpromotion->value_after_percentage = $discount_value;
                        }
                        if (!empty($value['description'])) {
                            $transactiondetailpromotion->description = $value['description'];
                        }
                        if (!empty($value['begin_date'])) {
                            $transactiondetailpromotion->begin_date = $value['begin_date'];
                        }
                        if (!empty($value['end_date'])) {
                            $transactiondetailpromotion->end_date = $value['end_date'];
                        }
                        $transactiondetailpromotion->save();

                        // activity cart promotion
                        $this->CartBasedPromoActivity($customer_id, $promoid, $promoname);
                    }

                }
            }

            // cart based coupon
            if (!empty($cart_coupon)) {
                foreach ($cart_coupon as $key => $value) {
                    if ($value['issuedcoupon']['promotion_name'] != "Subtotal") {
                        $transactiondetailcoupon = new \TransactionDetailCoupon();
                        $transactiondetailcoupon->transaction_detail_id = $transactiondetail->transaction_detail_id;
                        $transactiondetailcoupon->transaction_id = $transaction->transaction_id;
                        if (!empty($value['issuedcoupon']['issued_coupon_id'])) {
                            $transactiondetailcoupon->promotion_id = $value['issuedcoupon']['issued_coupon_id'];
                        }
                        if (!empty($value['issuedcoupon']['promotion_name'])) {
                            $transactiondetailcoupon->promotion_name = $value['issuedcoupon']['promotion_name'];
                        }
                        if (!empty($value['issuedcoupon']['promotion_type'])) {
                            $transactiondetailcoupon->promotion_type = $value['issuedcoupon']['promotion_type'];
                        }
                        if (!empty($value['issuedcoupon']['rule_type'])) {
                            $transactiondetailcoupon->rule_type = $value['issuedcoupon']['rule_type'];
                        }
                        if (!empty($value['issuedcoupon']['rule_value'])) {
                            $transactiondetailcoupon->rule_value = $value['issuedcoupon']['rule_value'];
                        }
                        if (!empty($value['issuedcoupon']['rule_object_id1'])) {
                            $transactiondetailcoupon->category_id1 = $value['issuedcoupon']['rule_object_id1'];
                        }
                        if (!empty($value['issuedcoupon']['rule_object_id2'])) {
                            $transactiondetailcoupon->category_id2 = $value['issuedcoupon']['rule_object_id2'];
                        }
                        if (!empty($value['issuedcoupon']['rule_object_id3'])) {
                            $transactiondetailcoupon->category_id3 = $value['issuedcoupon']['rule_object_id3'];
                        }
                        if (!empty($value['issuedcoupon']['rule_object_id4'])) {
                            $transactiondetailcoupon->category_id4 = $value['issuedcoupon']['rule_object_id4'];
                        }
                        if (!empty($value['issuedcoupon']['rule_object_id5'])) {
                            $transactiondetailcoupon->category_id5 = $value['issuedcoupon']['rule_object_id5'];
                        }
                        if (!empty($value['issuedcoupon']['discount_object_id1'])) {
                            $transactiondetailcoupon->category_name1 = $value['issuedcoupon']['discount_object_id1'];
                        }
                        if (!empty($value['issuedcoupon']['discount_object_id2'])) {
                            $transactiondetailcoupon->category_name2 = $value['issuedcoupon']['discount_object_id2'];
                        }
                        if (!empty($value['issuedcoupon']['discount_object_id3'])) {
                            $transactiondetailcoupon->category_name3 = $value['issuedcoupon']['discount_object_id3'];
                        }
                        if (!empty($value['issuedcoupon']['discount_object_id4'])) {
                            $transactiondetailcoupon->category_name4 = $value['issuedcoupon']['discount_object_id4'];
                        }
                        if (!empty($value['issuedcoupon']['discount_object_id5'])) {
                            $transactiondetailcoupon->category_name5 = $value['issuedcoupon']['discount_object_id5'];
                        }
                        if (!empty($value['issuedcoupon']['discount_object_type'])) {
                            $transactiondetailcoupon->discount_object_type = $value['issuedcoupon']['discount_object_type'];
                        }

                        if ($value['issuedcoupon']['rule_type'] == "cart_discount_by_percentage") {
                            $discount_percent = intval($value['issuedcoupon']['discount'])/100;
                            $discount_value = $this->removeFormat($value['issuedcoupon']['discount_value']);
                            $transactiondetailcoupon->discount_value = $discount_percent;
                            $transactiondetailcoupon->value_after_percentage = $discount_value;
                        } else {
                            $discount_value = $this->removeFormat($value['issuedcoupon']['discount_value']);
                            $transactiondetailcoupon->discount_value = $discount_value;
                            $transactiondetailcoupon->value_after_percentage = $discount_value;
                        }
                        if (!empty($value['issuedcoupon']['coupon_redeem_rule_value'])) {
                            $transactiondetailcoupon->coupon_redeem_rule_value = $value['issuedcoupon']['coupon_redeem_rule_value'];
                        }
                        if (!empty($value['issuedcoupon']['description'])) {
                            $transactiondetailcoupon->description = $value['issuedcoupon']['description'];
                        }
                        if (!empty($value['issuedcoupon']['begin_date'])) {
                            $transactiondetailcoupon->begin_date = $value['issuedcoupon']['begin_date'];
                        }
                        if (!empty($value['issuedcoupon']['end_date'])) {
                            $transactiondetailcoupon->end_date = $value['issuedcoupon']['end_date'];
                        }

                        $transactiondetailcoupon->save();

                        // coupon redeemed
                        if (!empty($value['issuedcoupon']['issued_coupon_id'])) {
                            $coupon_id = intval($value['issuedcoupon']['issued_coupon_id']);
                            $coupon_redeemed = IssuedCoupon::where('status', 'active')->where('issued_coupon_id', $coupon_id)->update(array('status' => 'redeemed'));
                        }
                    }
                }
            }

            // issue product based coupons (if any)
            if ($customer_id != 0 ||$customer_id != NULL) {
                foreach ($cart as $k => $v) {
                    if (!empty($v['product_id'])) {
                        $product_id = $v['product_id'];

                        $coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                        inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                        inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
                        inner join ' . DB::getTablePrefix() . 'products prod on
                        (
                            (pr.rule_object_type="product" AND pr.rule_object_id1 = prod.product_id)
                            OR
                            (
                                (pr.rule_object_type="family") AND
                                ((pr.rule_object_id1 IS NULL) OR (pr.rule_object_id1=prod.category_id1)) AND
                                ((pr.rule_object_id2 IS NULL) OR (pr.rule_object_id2=prod.category_id2)) AND
                                ((pr.rule_object_id3 IS NULL) OR (pr.rule_object_id3=prod.category_id3)) AND
                                ((pr.rule_object_id4 IS NULL) OR (pr.rule_object_id4=prod.category_id4)) AND
                                ((pr.rule_object_id5 IS NULL) OR (pr.rule_object_id5=prod.category_id5))
                            )
                        )
                        WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND prod.product_id = :productid '), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product_id));

                        if ($coupons != NULL) {
                            foreach ($coupons as $c) {
                                $issued = IssuedCoupon::where('promotion_id', $c->promotion_id)->count();

                                if ($issued < $c->maximum_issued_coupon) {
                                    $issue_coupon = new IssuedCoupon();
                                    $issue_coupon->promotion_id = $c->promotion_id;
                                    $issue_coupon->transaction_id = $transaction->transaction_id;
                                    $issue_coupon->issued_coupon_code = '';
                                    $issue_coupon->user_id = $customer_id;
                                    $issue_coupon->expired_date = Carbon::now()->addDays($c->coupon_validity_in_days);
                                    $issue_coupon->issued_date = Carbon::now();
                                    $issue_coupon->issuer_retailer_id = Config::get('orbit.shop.id');
                                    $issue_coupon->status = 'active';
                                    $issue_coupon->save();
                                    $issue_coupon->issued_coupon_code = IssuedCoupon::ISSUE_COUPON_INCREMENT+$issue_coupon->issued_coupon_id;
                                    $issue_coupon->save();
                                }
                            }
                        }
                    }
                }
            }

            // issue cart based coupons (if any)
            if ($customer_id != 0 ||$customer_id != NULL) {
                $coupon_carts = Coupon::join('promotion_rules', function ($q) use ($total_to_pay) {
                    $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.rule_value', '<=', $total_to_pay);
                })->excludeDeleted()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function ($q) use ($retailer) {
                    $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                })
                ->get();

                if (!empty($coupon_carts)) {
                    foreach ($coupon_carts as $kupon) {
                        $issued = IssuedCoupon::where('promotion_id', $kupon->promotion_id)->count();
                        if ($issued < $kupon->maximum_issued_coupon) {
                            $issue_coupon = new IssuedCoupon();
                            $issue_coupon->promotion_id = $kupon->promotion_id;
                            $issue_coupon->transaction_id = $transaction->transaction_id;
                            $issue_coupon->issued_coupon_code = '';
                            $issue_coupon->user_id = $customer_id;
                            $issue_coupon->expired_date = Carbon::now()->addDays($kupon->coupon_validity_in_days);
                            $issue_coupon->issued_date = Carbon::now();
                            $issue_coupon->issuer_retailer_id = Config::get('orbit.shop.id');
                            $issue_coupon->status = 'active';
                            $issue_coupon->save();
                            $issue_coupon->issued_coupon_code = IssuedCoupon::ISSUE_COUPON_INCREMENT+$issue_coupon->issued_coupon_id;
                            $issue_coupon->save();
                        }
                    }
                }
            }

            // delete the cart
            if ($cart_id != NULL) {
                $cart_delete = \Cart::where('status', 'cashier')->where('cart_id', $cart_id)->where('cashier_id', $cashier_id)->first();
                if (is_object($cart_delete)) {
                    $cart_delete->delete();
                    $cart_delete->save();
                    $cart_detail_delete = \CartDetail::where('status', 'cashier')->where('cart_id', $cart_id)->update(array('status' => 'deleted'));
                }
            }

            $this->response->data = $transaction;
            $this->commit();

            $activity->setUser($customer)
                    ->setActivityName($activity_payment)
                    ->setActivityNameLong($activity_payment_label . ' Success')
                    ->setObject($transaction)
                    ->setModuleName('Cart')
                    // ->setNotes()
                    ->setStaff($user)
                    ->responseOK()
                    ->save();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            // Rollback the changes
            $this->rollBack();

            $activity->setUser($customer)
                    ->setActivityName($activity_payment)
                    ->setActivityNameLong($activity_payment_label . ' Failed')
                    ->setObject($transaction)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            // Rollback the changes
            $this->rollBack();

            $activity->setUser($customer)
                    ->setActivityName($activity_payment)
                    ->setActivityNameLong($activity_payment_label . ' Failed')
                    ->setObject($transaction)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            // Rollback the changes
            $this->rollBack();

            $activity->setUser($customer)
                    ->setActivityName($activity_payment)
                    ->setActivityNameLong($activity_payment_label . ' Failed')
                    ->setObject($transaction)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        }

        return $this->render();
    }

    /**
     * POST - Print Ticket
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return \Illuminate\Support\Facades\Response
     */
    public function postPrintTicket()
    {
        $activity = Activity::POS()
                            ->setActivityType('payment');
        $customer = null;
        $user = null;
        $activity_payment = 'print_ticket';
        $activity_payment_label = 'Print Ticket';
        $transaction = null;
        try {

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $retailer = $this->getRetailerInfo();

            // currency from merchant table (IDR,USD)
            $currency = $retailer->parent->currency;

            $transaction_id = trim(OrbitInput::post('transaction_id'));

            $validator = Validator::make(
                array(
                    'transaction_id' => $transaction_id,
                ),
                array(
                    'transaction_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Check the device exist or not
            if (!file_exists(Config::get('orbit.devices.printer.params'))) {
                $message = 'Printer not found';
                ACL::throwAccessForbidden($message);
            }

            $transaction = \Transaction::with('details', 'detailcoupon', 'detailpromotion', 'detailtax', 'cashier', 'user')->where('transaction_id',$transaction_id)->first();
            $issuedcoupon = \IssuedCoupon::with('coupon.couponrule', 'coupon.redeemretailers')->where('transaction_id', $transaction_id)->get();

            if (! is_object($transaction)) {
                $message = \Lang::get('validation.orbit.empty.transaction');
                ACL::throwAccessForbidden($message);
            }

            $this->response->data = $transaction;
            $customer = $transaction->user;

            $details = $transaction->details->toArray();
            $detailcoupon = $transaction->detailcoupon->toArray();
            $detailpromotion = $transaction->detailpromotion->toArray();
            $detailtax = $transaction->detailtax->toArray();
            $_issuedcoupon = $issuedcoupon->toArray();
            $total_issuedcoupon = count($_issuedcoupon);
            $acquired_coupon = null;

            foreach ($_issuedcoupon as $key => $value) {
                $datex = Carbon::parse($value['expired_date'])->timezone(Config::get('app.timezone'))->format('d M Y H:i');
                if ($key == 0) {
                  $acquired_coupon .= " \n";
                  $acquired_coupon .= '----------------------------------------'." \n";
                  $acquired_coupon .=  $this->just40CharMid('Acquired Coupon');
                  $acquired_coupon .= '----------------------------------------'." \n";
                  $acquired_coupon .= $this->just40CharMid($value['coupon']['promotion_name']);
                  $acquired_coupon .= $this->just40CharMid($value['coupon']['description']);
                  $acquired_coupon .= $this->just40CharMid("Coupon Code " . $value['issued_coupon_code']);
                  $acquired_coupon .= $this->just40CharMid("Valid until " . $datex);
                } else {
                  $acquired_coupon .= '----------------------------------------'." \n";
                  $acquired_coupon .= $this->just40CharMid($value['coupon']['promotion_name']);
                  $acquired_coupon .= $this->just40CharMid($value['coupon']['description']);
                  $acquired_coupon .= $this->just40CharMid("Coupon Code " . $value['issued_coupon_code']);
                  $acquired_coupon .= $this->just40CharMid("Valid until " . $datex);
                  if ($key == $total_issuedcoupon-1) {
                    $acquired_coupon .= '----------------------------------------'." \n";
                  }
                }
            }

            foreach ($details as $details_key => $details_value) {
                if ($details_key == 0) {
                    if (empty($details_value['variant_price'])) {
                        if(!empty($details_value['variant_sku'])){
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['price'], $details_value['quantity'], $details_value['variant_sku']);
                        } else {
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['price'], $details_value['quantity'], $details_value['product_code']);
                        }

                    } else {
                        if(!empty($details_value['variant_sku'])){
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['variant_price'], $details_value['quantity'], $details_value['variant_sku']);
                        } else {
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['variant_price'], $details_value['quantity'], $details_value['product_code']);
                        }
                    }
                } else {
                    if (empty($details_value['variant_price'])) {
                        if(!empty($details_value['variant_sku'])){
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['price'], $details_value['quantity'], $details_value['variant_sku']);
                        } else {
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['price'], $details_value['quantity'], $details_value['product_code']);
                        }
                    } else {
                        if(!empty($details_value['variant_sku'])){
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['variant_price'], $details_value['quantity'], $details_value['variant_sku']);
                        } else {
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['variant_price'], $details_value['quantity'], $details_value['product_code']);
                        }
                    }
                }

                foreach ($detailpromotion as $detailpromotion_key => $detailpromotion_value) {
                    if ($details_value['transaction_detail_id'] == $detailpromotion_value['transaction_detail_id'] && $detailpromotion_value['promotion_type'] == 'product') {
                        $product .= $this->discountListFormat(substr($detailpromotion_value['promotion_name'], 0, 25), $detailpromotion_value['value_after_percentage']);
                    }
                }

                foreach ($detailcoupon as $detailcoupon_key => $detailcoupon_value) {
                    if ($details_value['transaction_detail_id'] == $detailcoupon_value['transaction_detail_id'] && ($detailcoupon_value['promotion_type'] == 'product' || ($detailcoupon_value['promotion_type'] == 'cart' && $detailcoupon_value['discount_object_type'] != 'cash_rebate' ))) {
                        $product .= $this->discountListFormat(substr($detailcoupon_value['promotion_name'], 0, 25), $detailcoupon_value['value_after_percentage']);
                    }
                }
            }

            $product .= '----------------------------------------'." \n";

            $promo = false;

            foreach ($details as $details_key => $details_value) {
                $x = 0;
                foreach ($detailpromotion as $detailpromotion_key => $detailpromotion_value) {
                    if ($details_value['transaction_detail_id'] == $detailpromotion_value['transaction_detail_id'] && $detailpromotion_value['promotion_type'] == 'cart') {
                        if ($x == 0) {
                            $cart_based_promo = "Cart Promotions"." \n";
                            $promo = true;
                        }
                        $x = $x+1;
                        $promo = true;
                        $cart_based_promo .= $this->discountListFormat(substr($detailpromotion_value['promotion_name'], 0, 23), $detailpromotion_value['value_after_percentage']);
                    }
                }

            }

            foreach ($details as $details_key => $details_value) {
                $x = 0;
                foreach ($detailcoupon as $detailcoupon_key => $detailcoupon_value) {
                    if ($details_value['transaction_detail_id'] == $detailcoupon_value['transaction_detail_id'] && $detailcoupon_value['promotion_type'] == 'cart' && $detailcoupon_value['discount_object_type'] == 'cash_rebate') {
                        if ($x == 0) {
                            if (!$promo) {
                                $cart_based_promo = "Cart Coupons"." \n";
                                $promo = true;
                            } else {
                                $cart_based_promo .= "Cart Coupons"." \n";
                            }

                        }
                        $x = $x+1;
                        $promo = true;
                        $cart_based_promo .= $this->discountListFormat(substr($detailcoupon_value['promotion_name'], 0, 23), $detailcoupon_value['value_after_percentage']);
                    }
                }

            }

            if ($promo) {
                $cart_based_promo .= '----------------------------------------'." \n";
            }

            $payment = $transaction['payment_method'];
            if ($payment == 'cash') {$payment='Cash';}
            if ($payment == 'card') {$payment='Card';}

            $date  =  $transaction['created_at']->timezone(Config::get('app.timezone'))->format('d M Y H:i:s');

            if ($transaction['user'] == NULL) {
                $customer = "Guest";
            } elseif ($transaction['user']->user_firstname != NULL && $transaction['user']->user_firstname != "") {
                $customer = $transaction['user']->user_firstname." ".$transaction['user']->user_lastname;
            } else {
                $customer = $transaction['user']->user_email;
            }

            $cashier = $transaction['cashier']->user_firstname." ".$transaction['cashier']->user_lastname;
            $bill_no = $transaction['transaction_id'];

            $head  = $this->just40CharMid($retailer->parent->name);
            $head .= $this->just40CharMid($retailer->name);
            $head .= $this->just40CharMid($retailer->address_line1)."\n";

            // ticket header
            $ticket_header = $retailer->parent->ticket_header;
            $ticket_header_line = explode("\n", $ticket_header);
            foreach ($ticket_header_line as $line => $value) {
                $head .= $this->just40CharMid($value);
            }
            $head .= '----------------------------------------'." \n";
            $head .= 'Date : '.$date." \n";
            $head .= 'Bill No  : '.$bill_no." \n";
            $head .= 'Cashier : '.$cashier." \n";
            $head .= 'Customer : '.$customer." \n";
            $head .= '----------------------------------------'." \n";

            $pay   = $this->leftAndRight('SUB TOTAL', number_format($transaction['subtotal'], 2));
            $pay  .= $this->leftAndRight('TAX', number_format($transaction['vat'], 2));
            $pay  .= $this->leftAndRight('TOTAL', number_format($transaction['total_to_pay'], 2));
            $pay  .= " \n";

            foreach ($detailtax as $tax) {
                if (! empty($tax['total_tax'])) {
                    $pay  .= $this->leftAndRight($tax['tax_name'] . '(' . ($tax['tax_value'] * 100) . '%)', number_format($tax['total_tax'], 2));
                }
            }
            $pay  .= " \n";

            $pay  .= $this->leftAndRight('Payment Method', $payment);
            if ($payment == 'Cash') {
                $pay  .= $this->leftAndRight('Tendered', number_format($transaction['tendered'], 2));
                $pay  .= $this->leftAndRight('Change', number_format($transaction['change'], 2));
                $pay  .= $this->leftAndRight('Amount in', $currency);
            }
            if ($payment == 'Card') {
                $pay  .= $this->leftAndRight('Total Paid', number_format($transaction['total_to_pay'], 2));
                $pay  .= $this->leftAndRight('Amount in', $currency);
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

            $file = storage_path()."/views/receipt.txt";

            if (!empty($cart_based_promo)) {
                $write = $head.$product.$cart_based_promo.$pay.$acquired_coupon.$footer;
            } else {
                $write = $head.$product.$pay.$acquired_coupon.$footer;
            }

            $fp = fopen($file, 'w');
            fwrite($fp, $write);
            fclose($fp);

            $print = "cat ".storage_path()."/views/receipt.txt > ".Config::get('orbit.devices.printer.params');
            $cut = Config::get('orbit.devices.cutpaper.path');

            shell_exec($print);

            shell_exec($cut);

            $activity->setUser($customer)
                    ->setActivityName($activity_payment)
                    ->setActivityNameLong($activity_payment_label . ' Success')
                    ->setObject($transaction)
                    // ->setNotes()
                    ->setStaff($user)
                    ->responseOK()
                    ->save();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_payment)
                    ->setActivityNameLong($activity_payment_label . ' Failed')
                    ->setObject($transaction)
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $activity->setUser($customer)
                    ->setActivityName($activity_payment)
                    ->setActivityNameLong($activity_payment_label . ' Failed')
                    ->setObject($transaction)
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $activity->setUser($customer)
                    ->setActivityName($activity_payment)
                    ->setActivityNameLong($activity_payment_label . ' Failed')
                    ->setObject($transaction)
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        }

        return $this->render();
    }

    /**
     * POST - Card Payment
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return \Illuminate\Support\Facades\Response
     */
    public function postCardPayment()
    {
        try {

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            // Check the device exist or not
            if (!file_exists(Config::get('orbit.devices.edc.params'))) {
                $message = 'Payment Terminal not found';
                ACL::throwAccessForbidden($message);
            }

            // Check the driver exist or not
            if (!file_exists(Config::get('orbit.devices.edc.path'))) {
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

            // command for ict220
            $cmd = 'sudo '.$driver.' --d '.$params.' --A '.$amount.' --s';
            $card = shell_exec($cmd);

            $card = trim($card);

            // read the output ict220
            $x = explode('--------~>', $card);
            $z = explode('<----------', $x[1]);
            $output = trim($z[0]);

            if ($output == 'FAILED' || $output == 'Failed') {
                $message = 'Payment Failed';
                ACL::throwAccessForbidden($message);
            }

            $this->response->data = $output;

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
     * @return \Illuminate\Support\Facades\Response
     */
    public function postCashDrawer()
    {
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

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
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return \Illuminate\Support\Facades\Response
     */
    public function postScanCart()
    {

        $activity = Activity::POS()
                            ->setActivityType('cart');
        $user = null;
        $customer = null;
        $activity_transfer = 'transfer_cart';
        $activity_transfer_label = 'Transfer Cart';
        $cart = null;

        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $cashier = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $barcode = OrbitInput::post('barcode');

            $retailer = $this->getRetailerInfo();

            if (empty($barcode)) {

                // Check the device exist or not
                if (!file_exists(Config::get('orbit.devices.barcode.params'))) {
                    $message = 'Scanner not found';
                    ACL::throwAccessForbidden($message);
                }

                // Check the driver exist or not
                if (!file_exists(Config::get('orbit.devices.barcode.path'))) {
                    $message = 'Scanner driver not found';
                    ACL::throwAccessForbidden($message);
                }

                $driver = Config::get('orbit.devices.barcode.path');
                $params = Config::get('orbit.devices.barcode.params');
                $cmd = 'sudo '.$driver.' '.$params;
                $barcode = shell_exec($cmd);
            }

            $barcode = trim($barcode);

            $cart = \Cart::where('status', 'active')->where('cart_code', $barcode)->first();

            if (! is_object($cart)) {
                $message = \Lang::get('validation.orbit.empty.upc_code');
                ACL::throwAccessForbidden($message);
            }

            $user = $cart->users;
            $customer = $user;

            // Begin database transaction
            $this->beginTransaction();

            $cartdata = $this->cartCalc($cart, $user, 'active', $barcode, $retailer);

            //update cart and cart_detail status to 'cashier'
            if (!empty($cart)) {
                $cart_update = \Cart::where('status', 'active')->where('cart_id', $cart->cart_id)->first();
                if (is_object($cart_update)) {
                    $cart_update->status = 'cashier';
                    $cart_update->cashier_id = $cashier->user_id;
                    $cart_update->save();
                    $cart_detail_update = \CartDetail::where('status', 'active')->where('cart_id', $cart->cart_id)->update(array('status' => 'cashier'));
                }
            }

            $this->commit();

            $activity->setUser($customer)
                    ->setActivityName($activity_transfer)
                    ->setActivityNameLong($activity_transfer_label . ' Success')
                    ->setObject($cart)
                    ->setModuleName('Cart')
                    // ->setNotes()
                    ->setStaff($cashier)
                    ->responseOK()
                    ->save();

            $this->response->data = $cartdata;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $this->rollBack();

            $activity->setUser($customer)
                    ->setActivityName($activity_transfer)
                    ->setActivityNameLong($activity_transfer_label . ' Failed')
                    ->setObject($cart)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($cashier)
                    ->responseFailed()
                    ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $this->rollBack();

            $activity->setUser($customer)
                    ->setActivityName($activity_transfer)
                    ->setActivityNameLong($activity_transfer_label . ' Failed')
                    ->setObject($cart)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($cashier)
                    ->responseFailed()
                    ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $this->rollBack();

            $activity->setUser($customer)
                    ->setActivityName($activity_transfer)
                    ->setActivityNameLong($activity_transfer_label . ' Failed')
                    ->setObject($cart)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($cashier)
                    ->responseFailed()
                    ->save();
        }

        return $this->render();
    }

    /**
     * POST - Customer display
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return \Illuminate\Support\Facades\Response
     */
    public function postCustomerDisplay()
    {
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

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

            // Prepare Screen
            $prepare_screen_cmd = Config::get('orbit.devices.prepare_screen.path', null);
            if (is_null($prepare_screen_cmd)) {
                throw new Exception ('Could not find prepare_screen command.');
            }
            $cmd = $prepare_screen_cmd;
            $screen = shell_exec($cmd . ' 2>&1 >/dev/null');

            if (strlen($line1)<20) {
                $line1_length = strlen($line1);
                $fill = 40 - $line1_length;
                $line1 = str_pad($line1, $fill, "\ ");
            }

            if (strlen($line2)<20) {
                $line2_length = strlen($line2);
                $fill = 36 - $line2_length;
                $line2 = str_pad($line2, $fill, "\ ");
            }

            // hacks to prevent customer display removing the first character of
            // line 1 when line 2 is more or equals than 20 chars
            if (strlen($line2) >= 20) {
                $line2 = substr($line2, 0, 19);
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
            $this->response->code = $this->getNonZeroCode($e->getCode());
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
     * @return \Illuminate\Support\Facades\Response
     */
    public function postProductDetail()
    {
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $product_id = trim(OrbitInput::post('product_id'));

            $validator = Validator::make(
            array(
                'product_id' => $product_id,
            ),
            array(
                'product_id' => 'required',
            )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $retailer = \Retailer::with('parent')->where('merchant_id', Config::get('orbit.shop.id'))->first();

            $product = Product::with('tax1', 'tax2', 'variants', 'attribute1', 'attribute2', 'attribute3', 'attribute4', 'attribute5')->whereHas('retailers', function ($query) use ($retailer) {
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
                inner join ' . DB::getTablePrefix() . 'products p on p.product_id = v.product_id AND p.status="active" AND v.status="active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av1 on av1.product_attribute_value_id = v.product_attribute_value_id1 AND av1.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av2 on av2.product_attribute_value_id = v.product_attribute_value_id2 AND av2.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av3 on av3.product_attribute_value_id = v.product_attribute_value_id3 AND av3.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av4 on av4.product_attribute_value_id = v.product_attribute_value_id4 AND av4.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av5 on av5.product_attribute_value_id = v.product_attribute_value_id5 AND av5.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa1 on pa1.product_attribute_id = av1.product_attribute_id AND pa1.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa2 on pa2.product_attribute_id = av2.product_attribute_id AND pa2.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa3 on pa3.product_attribute_id = av3.product_attribute_id AND pa3.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa4 on pa4.product_attribute_id = av4.product_attribute_id AND pa4.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa5 on pa5.product_attribute_id = av5.product_attribute_id AND pa5.status = "active"
                WHERE p.product_id = :productid'), array('productid' => $product->product_id));

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
     * @return \Illuminate\Support\Facades\Response
     */
    public function postCartBasedPromotion()
    {
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $retailer = $this->getRetailerInfo();
            // check for cart based promotions
            $promo_carts = \Promotion::with('promotionrule')->excludeDeleted()
                ->where('status', 'active')
                ->where('is_coupon', 'N')
                ->where('promotion_type', 'cart')
                ->where('merchant_id', $retailer['parent']['merchant_id'])
                ->whereHas('retailers', function ($q) use ($retailer) {
                    $q->where('promotion_retailer.retailer_id', Config::get('orbit.shop.id'));
                })
                ->where(function ($q) {
                    $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(function ($qr) {
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

    /**
     * GET - List of POS Quick Product
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param array         `product_ids`           (optional) - IDs of the product
     * @param array         `merchant_ids`          (optional) - IDs of the merchant
     * @param array         `retailer_ids`          (optional) - IDs of the
     * @param string        `sort_by`               (optional) - column order by
     * @param string        `sort_mode`             (optional) - asc or desc
     * @param integer       `take`                  (optional) - limit
     * @param integer       `skip`                  (optional) - limit offset
     * @return \Illuminate\Support\Facades\Response
     */
    public function getPosQuickProduct()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $retailer = $this->getRetailerInfo();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:id,price,name,product_order',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.posquickproduct_sortby'),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.product_attribute.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }

            // Builder object
            $posQuickProducts = \PosQuickProduct::excludeDeleted('pos_quick_products')
                                                ->with('product')
                                                ->where('pos_quick_products.merchant_id', $retailer->parent_id)
                                                ->where('pos_quick_products.retailer_id', $retailer->merchant_id);

            // Filter by ids
            OrbitInput::get('id', function ($posQuickIds) use ($posQuickProducts) {
                $posQuickProducts->whereIn('pos_quick_products.pos_quick_product_id', $posQuickIds);
            });

            // Filter by merchant ids
            OrbitInput::get('merchant_ids', function ($merchantIds) use ($posQuickProducts) {
                $posQuickProducts->whereIn('pos_quick_products.merchant_id', $merchantIds);
            });

            // Filter by retailer ids
            OrbitInput::get('retailer_ids', function ($retailerIds) use ($posQuickProducts) {
                $posQuickProducts->whereIn('product_retailer.retailer_id', $retailerIds);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_posQuickProducts = clone $posQuickProducts;

            // Get the take args
            $take = $maxRecord;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;
            });
            $posQuickProducts->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $posQuickProducts) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $posQuickProducts->skip($skip);

            // Default sort by
            $sortBy = 'pos_quick_products.product_order';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'id'            => 'pos_quick_products.pos_quick_product_id',
                    'name'          => 'products.product_name',
                    'product_order' => 'pos_quick_products.product_prder',
                    'price'         => 'products.price',
                    'created'       => 'pos_quick_products.created_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $posQuickProducts->orderBy($sortBy, $sortMode);

            $totalPosQuickProducts = RecordCounter::create($_posQuickProducts)->count();
            $listOfPosQuickProducts = $posQuickProducts->get();

            $data = new \stdclass();
            $data->total_records = $totalPosQuickProducts;
            $data->returned_records = count($listOfPosQuickProducts);
            $data->records = $listOfPosQuickProducts;

            if ($listOfPosQuickProducts === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.attribute');
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
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * GET - Merchant info without login
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @return \Illuminate\Support\Facades\Response
     */
    public function getMerchantInfo()
    {
        try {
                $retailer = $this->getRetailerInfo();
                $merchant = $retailer->parent;

                $this->response->data = $merchant;
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

    public function CartBasedPromoActivity($customer_id, $promotion_id, $promotion_name)
    {
        $activity = Activity::POS()
                    ->setActivityType('add');
        $user = null;
        $customer = null;
        $activity_name = 'add_promotion';
        $activity_name_label = 'Add Promotion ';
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            if ($customer_id == null) {
                $customer_id = -1;
            }
            $customer = User::excludeDeleted()->find($customer_id);

            if (!empty($promotion_id)) {
                $promo_cart = Promotion::where('promotion_id', $promotion_id)->first();
                $activity_name_label = 'Add Promotion ' . $promotion_name;
            } else {
                $promo_cart = null;
                $activity_name_label = 'Add Promotion ';
            }

            $activity->setUser($customer)
                    ->setActivityName($activity_name)
                    ->setActivityNameLong($activity_name_label . ' Success')
                    ->setObject($promo_cart)
                    ->setPromotion($promo_cart)
                    ->setModuleName('Promotion')
                    // ->setNotes()
                    ->setStaff($user)
                    ->responseOK()
                    ->save();

            $this->response->data = null;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_name)
                    ->setActivityNameLong($activity_name_label . ' Failed')
                    ->setObject(null)
                    ->setModuleName('Promotion')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_name)
                    ->setActivityNameLong($activity_name_label . ' Failed')
                    ->setObject(null)
                    ->setModuleName('Promotion')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_name)
                    ->setActivityNameLong($activity_name_label . ' Failed')
                    ->setObject(null)
                    ->setModuleName('Promotion')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        }

        return $this->render();
    }

    public function postSaveActivityCheckout()
    {
        $activity = Activity::POS()
                    ->setActivityType('cart');
        $user = null;
        $customer = null;
        $activity_checkout = 'cart_checkout';
        $activity_checkout_label = 'Cart Checkout';
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $customer_id = OrbitInput::post('customer_id', -1);
            $customer = User::excludeDeleted()->find($customer_id);

            $activity->setUser($customer)
                    ->setActivityName($activity_checkout)
                    ->setActivityNameLong($activity_checkout_label . ' Success')
                    ->setObject(null)
                    ->setModuleName('Cart')
                    // ->setNotes()
                    ->setStaff($user)
                    ->responseOK()
                    ->save();

            $this->response->data = null;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_checkout)
                    ->setActivityNameLong($activity_checkout_label . ' Failed')
                    ->setObject(null)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_checkout)
                    ->setActivityNameLong($activity_checkout_label . ' Failed')
                    ->setObject(null)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_checkout)
                    ->setActivityNameLong($activity_checkout_label . ' Failed')
                    ->setObject(null)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        }

        return $this->render();
    }

    public function postSaveActivityClear()
    {
        $activity = Activity::POS()
                    ->setActivityType('cart');
        $user = null;
        $customer = null;
        $activity_clear = 'cart_clear';
        $activity_clear_label = 'Cart Clear';
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $customer_id = OrbitInput::post('customer_id', -1);
            $customer = User::excludeDeleted()->find($customer_id);

            $activity->setUser($customer)
                    ->setActivityName($activity_clear)
                    ->setActivityNameLong($activity_clear_label . ' Success')
                    ->setObject(null)
                    ->setModuleName('Cart')
                    // ->setNotes()
                    ->setStaff($user)
                    ->responseOK()
                    ->save();

            $this->response->data = null;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_clear)
                    ->setActivityNameLong($activity_clear_label . ' Failed')
                    ->setObject(null)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_clear)
                    ->setActivityNameLong($activity_clear_label . ' Failed')
                    ->setObject(null)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_clear)
                    ->setActivityNameLong($activity_clear_label . ' Failed')
                    ->setObject(null)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        }

        return $this->render();
    }

    public function postSaveActivityAddProduct()
    {
        $activity = Activity::POS()
                    ->setActivityType('cart');
        $user = null;
        $customer = null;
        $activity_add = 'cart_add';
        $activity_add_label = 'Add to Cart';
        $product = null;
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $customer_id = OrbitInput::post('customer_id', -1);
            $product_id = OrbitInput::post('product_id', -1);
            $customer = User::excludeDeleted()->find($customer_id);
            $product = Product::active()->find($product_id);

            $activity->setUser($customer)
                    ->setActivityName($activity_add)
                    ->setActivityNameLong($activity_add_label . ' Success')
                    ->setObject($product)
                    ->setProduct($product)
                    ->setModuleName('Cart')
                    // ->setNotes()
                    ->setStaff($user)
                    ->responseOK()
                    ->save();

            $this->response->data = null;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_add)
                    ->setActivityNameLong($activity_add_label . ' Failed')
                    ->setObject($product)
                    ->setProduct($product)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_add)
                    ->setActivityNameLong($activity_add_label . ' Failed')
                    ->setObject($product)
                    ->setProduct($product)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_add)
                    ->setActivityNameLong($activity_add_label . ' Failed')
                    ->setObject($product)
                    ->setProduct($product)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        }

        return $this->render();
    }

    public function postSaveActivityDeleteProduct()
    {
        $activity = Activity::POS()
                    ->setActivityType('cart');
        $user = null;
        $customer = null;
        $activity_delete = 'cart_delete';
        $activity_delete_label = 'Delete from Cart';
        $product = null;
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $customer_id = OrbitInput::post('customer_id', -1);
            $product_id = OrbitInput::post('product_id', -1);
            $customer = User::excludeDeleted()->find($customer_id);
            $product = Product::active()->find($product_id);

            $activity->setUser($customer)
                    ->setActivityName($activity_delete)
                    ->setActivityNameLong($activity_delete_label . ' Success')
                    ->setObject($product)
                    ->setProduct($product)
                    ->setModuleName('Cart')
                    // ->setNotes()
                    ->setStaff($user)
                    ->responseOK()
                    ->save();

            $this->response->data = null;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_delete)
                    ->setActivityNameLong($activity_delete_label . ' Failed')
                    ->setObject($product)
                    ->setProduct($product)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_delete)
                    ->setActivityNameLong($activity_delete_label . ' Failed')
                    ->setObject($product)
                    ->setProduct($product)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($customer)
                    ->setActivityName($activity_delete)
                    ->setActivityNameLong($activity_delete_label . ' Failed')
                    ->setObject($product)
                    ->setProduct($product)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->setStaff($user)
                    ->responseFailed()
                    ->save();
        }

        return $this->render();
    }

    /**
     * GET - Cart Cashier
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return \Illuminate\Support\Facades\Response
     */
    public function getCartCashier()
    {
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $retailer = $this->getRetailerInfo();

            $cart = \Cart::where('status', 'cashier')->where('cashier_id', $user->user_id)->first();

            if (! is_object($cart)) {
                $message = \Lang::get('validation.orbit.empty.upc_code');
                ACL::throwAccessForbidden($message);
            }
            $user = $cart->users;
            $cartdata = $this->cartCalc($cart, $user, 'cashier', null, $retailer);

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
     * POST - Delete Cart
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return \Illuminate\Support\Facades\Response
     */
    public function postDeleteCart()
    {
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $cart_id = trim(OrbitInput::post('cart_id'));

            $validator = Validator::make(
                array(
                    'cart_id' => $cart_id,
                ),
                array(
                    'cart_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Begin database transaction
            $this->beginTransaction();

            $cart_delete = \Cart::where('status', 'cashier')->where('cart_id', $cart_id)->where('cashier_id', $user->user_id)->first();

            if (! is_object($cart_delete)) {
                $message = "cart not found";
                ACL::throwAccessForbidden($message);
            }

            $cart_delete->delete();
            $cart_delete->save();
            $cart_detail_delete = \CartDetail::where('status', 'cashier')->where('cart_id', $cart_id)->update(array('status' => 'deleted'));

            $this->response->data = $cart_delete;
            $this->commit();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            // Rollback the changes
            $this->rollBack();
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
        for ($i=0;$i<$space;$i++) { $spc .= ' '; }
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

    public function removeFormat($s)
    {
        $x =  explode("-", $s);

        return trim(str_replace(',','',$x[1]));
    }

    public function getPaymentTerminalStatus()
    {

        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            // Check the device exist or not
            if (!file_exists(Config::get('orbit.devices.edc.params'))) {
                $message = 'Terminal not found';
                ACL::throwAccessForbidden($message);
            } else {
                $message = 'Terminal exist';
            }

            $this->response->data = $message;
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

    public function postSendTicketEmail()
    {
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;

            // Make sure only Super Admin and Cashier which are able to call
            // this URL
            $this->CashierAndSuperAdminOnly();

            $retailer = $this->getRetailerInfo();

            // currency from merchant table (IDR,USD)
            $currency = $retailer->parent->currency;

            $transaction_id = trim(OrbitInput::post('transaction_id'));

            $validator = Validator::make(
                array(
                    'transaction_id' => $transaction_id,
                ),
                array(
                    'transaction_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $transaction = \Transaction::with('details', 'detailcoupon', 'detailpromotion', 'detailtax', 'cashier', 'user')->where('transaction_id',$transaction_id)->first();
            $issuedcoupon = \IssuedCoupon::with('coupon.couponrule', 'coupon.redeemretailers')->where('transaction_id', $transaction_id)->get();

            if (! is_object($transaction)) {
                $message = \Lang::get('validation.orbit.empty.transaction');
                ACL::throwAccessForbidden($message);
            }

            if (empty($transaction->user->user_email)) {
                $message = 'email not found';
                ACL::throwAccessForbidden($message);
            }

            $this->response->data = $transaction;
            $customer = $transaction->user;

            $details = $transaction->details->toArray();
            $detailcoupon = $transaction->detailcoupon->toArray();
            $detailpromotion = $transaction->detailpromotion->toArray();
            $detailtax = $transaction->detailtax->toArray();
            $_issuedcoupon = $issuedcoupon->toArray();
            $total_issuedcoupon = count($_issuedcoupon);
            $acquired_coupon = null;

            foreach ($_issuedcoupon as $key => $value) {
                $datex = Carbon::parse($value['expired_date'])->timezone(Config::get('app.timezone'))->format('d M Y H:i');
                if ($key == 0) {
                    $acquired_coupon .= " \n";
                    $acquired_coupon .= '----------------------------------------'." \n";
                    $acquired_coupon .=  $this->just40CharMid('Acquired Coupon');
                    $acquired_coupon .= '----------------------------------------'." \n";
                    $acquired_coupon .= $this->just40CharMid($value['coupon']['promotion_name']);
                    $acquired_coupon .= $this->just40CharMid($value['coupon']['description']);
                    $acquired_coupon .= $this->just40CharMid("Coupon Code " . $value['issued_coupon_code']);
                    $acquired_coupon .= $this->just40CharMid("Valid until " . $datex);
                } else {
                    $acquired_coupon .= '----------------------------------------'." \n";
                    $acquired_coupon .= $this->just40CharMid($value['coupon']['promotion_name']);
                    $acquired_coupon .= $this->just40CharMid($value['coupon']['description']);
                    $acquired_coupon .= $this->just40CharMid("Coupon Code " . $value['issued_coupon_code']);
                    $acquired_coupon .= $this->just40CharMid("Valid until " . $datex);
                    if ($key == $total_issuedcoupon-1) {
                        $acquired_coupon .= '----------------------------------------'." \n";
                    }
                }
            }

            foreach ($details as $details_key => $details_value) {
                if ($details_key == 0) {
                    if (empty($details_value['variant_price'])) {
                        if(!empty($details_value['variant_sku'])){
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['price'], $details_value['quantity'], $details_value['variant_sku']);
                        } else {
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['price'], $details_value['quantity'], $details_value['product_code']);
                        }

                    } else {
                        if(!empty($details_value['variant_sku'])){
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['variant_price'], $details_value['quantity'], $details_value['variant_sku']);
                        } else {
                            $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['variant_price'], $details_value['quantity'], $details_value['product_code']);
                        }
                    }
                } else {
                    if (empty($details_value['variant_price'])) {
                        if(!empty($details_value['variant_sku'])){
                            $product .= $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['price'], $details_value['quantity'], $details_value['variant_sku']);
                        } else {
                            $product .= $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['price'], $details_value['quantity'], $details_value['product_code']);
                        }
                    } else {
                        if(!empty($details_value['variant_sku'])){
                            $product .= $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['variant_price'], $details_value['quantity'], $details_value['variant_sku']);
                        } else {
                            $product .= $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['variant_price'], $details_value['quantity'], $details_value['product_code']);
                        }
                    }
                }

                foreach ($detailcoupon as $detailcoupon_key => $detailcoupon_value) {
                    if ($details_value['transaction_detail_id'] == $detailcoupon_value['transaction_detail_id'] && $detailcoupon_value['promotion_type'] == 'product') {
                        $product .= $this->discountListFormat(substr($detailcoupon_value['promotion_name'], 0, 25), $detailcoupon_value['value_after_percentage']);
                    }
                }

                foreach ($detailpromotion as $detailpromotion_key => $detailpromotion_value) {
                    if ($details_value['transaction_detail_id'] == $detailpromotion_value['transaction_detail_id'] && $detailpromotion_value['promotion_type'] == 'product') {
                        $product .= $this->discountListFormat(substr($detailpromotion_value['promotion_name'], 0, 25), $detailpromotion_value['value_after_percentage']);
                    }
                }
            }

            $product .= '----------------------------------------'." \n";

            $promo = false;

            foreach ($details as $details_key => $details_value) {
                $x = 0;
                foreach ($detailpromotion as $detailpromotion_key => $detailpromotion_value) {
                    if ($details_value['transaction_detail_id'] == $detailpromotion_value['transaction_detail_id'] && $detailpromotion_value['promotion_type'] == 'cart') {
                        if ($x == 0) {
                            $cart_based_promo = "Cart Promotions"." \n";
                            $promo = true;
                        }
                        $x = $x+1;
                        $promo = true;
                        $cart_based_promo .= $this->discountListFormat(substr($detailpromotion_value['promotion_name'], 0, 23), $detailpromotion_value['value_after_percentage']);
                    }
                }

            }

            foreach ($details as $details_key => $details_value) {
                $x = 0;
                foreach ($detailcoupon as $detailcoupon_key => $detailcoupon_value) {
                    if ($details_value['transaction_detail_id'] == $detailcoupon_value['transaction_detail_id'] && $detailcoupon_value['promotion_type'] == 'cart') {
                        if ($x == 0) {
                            if (!$promo) {
                                $cart_based_promo = "Cart Coupons"." \n";
                                $promo = true;
                            } else {
                                $cart_based_promo .= "Cart Coupons"." \n";
                            }

                        }
                        $x = $x+1;
                        $promo = true;
                        $cart_based_promo .= $this->discountListFormat(substr($detailcoupon_value['promotion_name'], 0, 23), $detailcoupon_value['value_after_percentage']);
                    }
                }

            }

            if ($promo) {
                $cart_based_promo .= '----------------------------------------'." \n";
            }

            $payment = $transaction['payment_method'];
            if ($payment == 'cash') {$payment = 'Cash';}
            if ($payment == 'card') {$payment = 'Card';}

            $date  =  $transaction['created_at']->timezone(Config::get('app.timezone'))->format('d M Y H:i:s');

            if ($transaction['user'] == NULL) {
                $customer = "Guest";
            } elseif ($transaction['user']->user_firstname != NULL && $transaction['user']->user_firstname != "") {
                $customer = $transaction['user']->user_firstname." ".$transaction['user']->user_lastname;
            } else {
                $customer = $transaction['user']->user_email;
            }

            $cashier = $transaction['cashier']->user_firstname." ".$transaction['cashier']->user_lastname;
            $bill_no = $transaction['transaction_id'];

            $head  = " \n";
            $head .= " \n";
            $head .= $this->just40CharMid($retailer->parent->name);
            $head .= $this->just40CharMid($retailer->name);
            $head .= $this->just40CharMid($retailer->address_line1)."\n";

            // ticket header
            $ticket_header = $retailer->parent->ticket_header;
            $ticket_header_line = explode("\n", $ticket_header);
            foreach ($ticket_header_line as $line => $value) {
                $head .= $this->just40CharMid($value);
            }
            $head .= '----------------------------------------'." \n";
            $head .= 'Date : '.$date." \n";
            $head .= 'Bill No  : '.$bill_no." \n";
            $head .= 'Cashier : '.$cashier." \n";
            $head .= 'Customer : '.$customer." \n";

            $head .= '----------------------------------------'." \n";

            $pay   = $this->leftAndRight('SUB TOTAL', number_format($transaction['subtotal'], 2));
            $pay  .= $this->leftAndRight('TAX', number_format($transaction['vat'], 2));
            $pay  .= $this->leftAndRight('TOTAL', number_format($transaction['total_to_pay'], 2));
            $pay  .= " \n";

            foreach ($detailtax as $tax) {
                if (! empty($tax['total_tax'])) {
                    $pay  .= $this->leftAndRight($tax['tax_name'] . '(' . ($tax['tax_value'] * 100) . '%)', number_format($tax['total_tax'], 2));
                }
            }
            $pay  .= " \n";
            $pay  .= $this->leftAndRight('Payment Method', $payment);
            if ($payment == 'Cash') {
                $pay  .= $this->leftAndRight('Tendered', number_format($transaction['tendered'], 2));
                $pay  .= $this->leftAndRight('Change', number_format($transaction['change'], 2));
                $pay  .= $this->leftAndRight('Amount in', $currency);
            }
            if ($payment == 'Card') {
                $pay  .= $this->leftAndRight('Total Paid', number_format($transaction['total_to_pay'], 2));
                $pay  .= $this->leftAndRight('Amount in', $currency);
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

            $filepath = storage_path() . '/views/receipt.txt';
            $transaction_date = str_replace(' ', '_', $transaction->created_at);
            $transaction_date = str_replace(':', '', $transaction->created_at);

            $tr_date = strtotime($transaction_date);
            $_tr_date = date('d-m-Y H-i-s', $tr_date);

            $attachment_name = sprintf('receipt-%s-%s.png', $transaction->transaction_id, $_tr_date);

            if (!empty($cart_based_promo)) {
                $write = $head.$product.$cart_based_promo.$pay.$acquired_coupon.$footer;
            } else {
                $write = $head.$product.$pay.$acquired_coupon.$footer;
            }

            $fontsize = 12;

            $font_path = public_path() . '/templatepos/courier.ttf';
            $size = imagettfbbox($fontsize, 0, $font_path, $write);
            $xsize = abs($size[0]) + abs($size[2]);
            $ysize = abs($size[5]) + abs($size[1]);

            $image = imagecreate($xsize, $ysize);
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = ImageColorAllocate($image, 0, 0, 0);
            imagettftext($image, $fontsize, 0, abs($size[0]), abs($size[5]), $black, $font_path, $write);

            ob_start ();

              $image_data = imagepng($image);
              $image_data = ob_get_contents ();

            ob_end_clean ();

            $base64img = base64_encode($image_data);

            $date = str_replace(' ', '_', $transaction->created_at);

            $filename = 'receipt-' . $date . '.txt';

            $mailviews = array(
                'html' => 'emails.receipt.receipt-html',
                'text' => 'emails.receipt.receipt-text'
            );

            $retailer = $this->getRetailerInfo();
            $customer_email = $transaction->user->user_email;

            Mail::send($mailviews, array('user' => $transaction->user, 'retailer' => $retailer, 'transactiondetails' => $transaction->details, 'transaction' => $transaction), function ($message) use ($base64img, $user, $filepath, $attachment_name, $write, $customer_email, $transaction) {
                $message->to($customer_email, $transaction->user->getFullName())->subject('Orbit Receipt');
                $message->attachData(base64_decode($base64img), $attachment_name, ['mime' => 'img/png']);
            });

            $this->response->message = 'success';
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

    protected function cartCalc($cart, $user, $status, $barcode, $retailer)
    {
        $cartdetails = CartDetail::with(array('product' => function ($q) {
                $q->where('products.status','active');
            }, 'variant' => function ($q) {
                $q->where('product_variants.status','active');
            }), 'tax1', 'tax2')
            ->where('status', $status)
            ->where('cart_id', $cart->cart_id)
            ->whereHas('product', function ($q) {
                $q->where('products.status', 'active');
            })
            ->get();

        $cartdata = new \stdclass();
        $cartdata->cart = $cart;
        $cartdata->cartdetails = $cartdetails;

        $promo_products = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
            inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N" AND p.merchant_id = :merchantid
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

        $used_product_coupons = CartCoupon::with(array('cartdetail' => function ($q) {
            $q->join('product_variants', 'cart_details.product_variant_id', '=', 'product_variants.product_variant_id');
        }, 'issuedcoupon' => function ($q) use ($user) {
            $q->where('issued_coupons.user_id', $user->user_id)
            ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
            ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');
        }))->whereHas('issuedcoupon', function ($q) use ($user) {
            $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.status', 'deleted');
        })->whereHas('cartdetail', function ($q) {
            $q->where('cart_coupons.object_type', '=', 'cart_detail');
        })->get();

        $promo_carts = Promotion::with('promotionrule')->active()->where('is_coupon', 'N')->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('retailers', function ($q) use ($retailer) {
            $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
        })
        ->where(function ($q) {
            $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(function ($qr) {
                $qr->where('begin_date', '<=', Carbon::now())->where('is_permanent', '=', 'Y');
            });
        })->get();

        $used_cart_coupons = CartCoupon::with(array('cart', 'issuedcoupon' => function ($q) use ($user) {
            $q->where('issued_coupons.user_id', $user->user_id)
            ->where('issued_coupons.status', 'deleted')
            ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
            ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');
        }))
        ->whereHas('cart', function ($q) use ($cartdata) {
            $q->where('cart_coupons.object_type', '=', 'cart')
            ->where('cart_coupons.object_id', '=', $cartdata->cart->cart_id);
        })
        ->where('cart_coupons.object_type', '=', 'cart')->get();

        $subtotal = 0;
        $subtotal_wo_tax = 0;
        $vat = 0;
        $total = 0;

        $taxes = \MerchantTax::active()->where('merchant_id', $retailer->parent_id)->get();

        $vat_included = $retailer->parent->vat_included;

        if ($vat_included === 'yes') {
            foreach ($cartdata->cartdetails as $cartdetail) {
                $attributes = array();
                $product_vat_value = 0;
                $original_price = $cartdetail->variant->price;
                $original_ammount = $original_price * $cartdetail->quantity;
                $ammount_after_promo = $original_ammount;
                $product_price_wo_tax = $original_price;

                $available_product_coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                        inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y"))
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
                        inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                        WHERE
                            ic.expired_date >= NOW()
                            AND p.merchant_id = :merchantid
                            AND prr.retailer_id = :retailerid
                            AND ic.user_id = :userid
                            AND prod.product_id = :productid

                        '), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $cartdetail->product_id));

                $cartdetail->available_product_coupons = count($available_product_coupons);

                if (!is_null($cartdetail->tax1)) {
                    $tax1 = $cartdetail->tax1->tax_value;
                    if (!is_null($cartdetail->tax2)) {
                        $tax2 = $cartdetail->tax2->tax_value;
                        if ($cartdetail->tax2->tax_type == 'service') {
                            $pwot  = $original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                            $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                            $tax1_total_value = $tax1_value * $cartdetail->quantity;
                        } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                            $tax1_value = ($original_price / (1 + $tax1 + $tax2)) * $tax1;
                            $tax1_total_value = $tax1_value * $cartdetail->quantity;
                        }
                    } else {
                        $tax1_value = ($original_price / (1 + $tax1)) * $tax1;
                        $tax1_total_value = $tax1_value * $cartdetail->quantity;
                    }
                    foreach ($taxes as $tax) {
                        if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                            $tax->total_tax = $tax->total_tax + $tax1_total_value;
                            $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax1_total_value;
                        }
                    }
                } else {
                    $tax1 = 0;
                }

                if (!is_null($cartdetail->tax2)) {
                    $tax2 = $cartdetail->tax2->tax_value;
                    if (!is_null($cartdetail->tax1)) {
                        if ($cartdetail->tax2->tax_type == 'service') {
                            $tax2_value = ($original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                            $tax2_total_value = $tax2_value * $cartdetail->quantity;
                        } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                            $tax2_value = ($original_price / (1 + $tax1 + $tax2)) * $tax2;
                            $tax2_total_value = $tax2_value * $cartdetail->quantity;
                        }
                    }
                    foreach ($taxes as $tax) {
                        if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                            $tax->total_tax = $tax->total_tax + $tax2_total_value;
                            $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax2_total_value;
                        }
                    }
                } else {
                    $tax2 = 0;
                }

                if (!is_null($cartdetail->tax2)) {
                    if ($cartdetail->tax2->tax_type == 'service') {
                        $product_price_wo_tax = $original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                    } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                        $product_price_wo_tax = $original_price / (1 + $tax1 + $tax2);
                    }
                } else {
                    $product_price_wo_tax = $original_price / (1 + $tax1);
                }

                $product_vat = ($original_price - $product_price_wo_tax) * $cartdetail->quantity;
                $vat = $vat + $product_vat;
                $product_price_wo_tax = $product_price_wo_tax * $cartdetail->quantity;
                $subtotal = $subtotal + $original_ammount;
                $subtotal_wo_tax = $subtotal_wo_tax + $product_price_wo_tax;

                $temp_price = $original_ammount;
                $promo_for_this_product_array = array();
                $promo_filters = array_filter($promo_products, function ($v) use ($cartdetail) { return $v->product_id == $cartdetail->product_id; });

                foreach ($promo_filters as $promo_filter) {
                    $promo_for_this_product = new \stdclass();
                    $promo_for_this_product = clone $promo_filter;
                    if ($promo_filter->rule_type == 'product_discount_by_percentage' || $promo_filter->rule_type == 'cart_discount_by_percentage') {
                        $discount = $promo_filter->discount_value * $original_price * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value * 100;
                    } elseif ($promo_filter->rule_type == 'product_discount_by_value' || $promo_filter->rule_type == 'cart_discount_by_value') {
                        $discount = $promo_filter->discount_value * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value;
                    } elseif ($promo_filter->rule_type == 'new_product_price') {
                        $discount = ($original_price - $promo_filter->discount_value) * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value;
                    }
                    $promo_for_this_product->promotion_id = $promo_filter->promotion_id;
                    $promo_for_this_product->promotion_name = $promo_filter->promotion_name;
                    $promo_for_this_product->rule_type = $promo_filter->rule_type;
                    $promo_for_this_product->discount = $discount;
                    $ammount_after_promo = $ammount_after_promo - $promo_for_this_product->discount;
                    $temp_price = $temp_price - $promo_for_this_product->discount;

                    if (!is_null($cartdetail->tax1)) {
                        $tax1 = $cartdetail->tax1->tax_value;
                        if (!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $pwot  = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                                $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                                $tax1_total_value = $tax1_value;
                            } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                $tax1_value = ($discount / (1 + $tax1 + $tax2)) * $tax1;
                                $tax1_total_value = $tax1_value;
                            }
                        } else {
                            $tax1_value = ($discount / (1 + $tax1)) * $tax1;
                            $tax1_total_value = $tax1_value;
                        }

                        foreach ($taxes as $tax) {
                            if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                            }
                        }
                    }

                    if (!is_null($cartdetail->tax2)) {
                        $tax2 = $cartdetail->tax2->tax_value;
                        if (!is_null($cartdetail->tax1)) {
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $tax2_value = ($discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                                $tax2_total_value = $tax2_value;
                            } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                $tax2_value = ($discount / (1 + $tax1 + $tax2)) * $tax2;
                                $tax2_total_value = $tax2_value;
                            }
                        }
                        foreach ($taxes as $tax) {
                            if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                            }
                        }
                    }

                    if (!is_null($cartdetail->tax2)) {
                        if ($cartdetail->tax2->tax_type == 'service') {
                            $promo_wo_tax = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                        } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                            $promo_wo_tax = $discount / (1 + $tax1 + $tax2);
                        }
                    } else {
                        $promo_wo_tax = $discount / (1 + $tax1);
                    }

                    $promo_vat = ($discount - $promo_wo_tax);
                    $vat = $vat - $promo_vat;
                    $promo_wo_tax = $promo_wo_tax;
                    $subtotal = $subtotal - $promo_for_this_product->discount;
                    $subtotal_wo_tax = $subtotal_wo_tax - $promo_wo_tax;
                    $promo_for_this_product_array[] = $promo_for_this_product;
                }

                $cartdetail->promo_for_this_product = $promo_for_this_product_array;

                $coupon_filter = array();
                foreach ($used_product_coupons as $used_product_coupon) {

                    if ($used_product_coupon->cartdetail->cart_detail_id == $cartdetail->cart_detail_id) {
                        if ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_percentage' || $used_product_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                            $discount = $used_product_coupon->issuedcoupon->discount_value * $original_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value * 100;
                        } elseif ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_value' || $used_product_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
                            $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                        } elseif ($used_product_coupon->issuedcoupon->rule_type == 'new_product_price') {
                            $discount = $original_price - $used_product_coupon->issuedcoupon->discount_value + 0;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                        }

                        $temp_price = $temp_price - $discount;
                        $used_product_coupon->discount = $discount;
                        $ammount_after_promo = $ammount_after_promo - $discount;

                        if (!is_null($cartdetail->tax1)) {
                            $tax1 = $cartdetail->tax1->tax_value;
                            if (!is_null($cartdetail->tax2)) {
                                $tax2 = $cartdetail->tax2->tax_value;
                                if ($cartdetail->tax2->tax_type == 'service') {
                                    $pwot  = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                                    $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                                    $tax1_total_value = $tax1_value;
                                } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                    $tax1_value = ($discount / (1 + $tax1 + $tax2)) * $tax1;
                                    $tax1_total_value = $tax1_value;
                                }
                            } else {
                                $tax1_value = ($discount / (1 + $tax1)) * $tax1;
                                $tax1_total_value = $tax1_value;
                            }
                            foreach ($taxes as $tax) {
                                if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                }
                            }
                        }

                        if (!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            if (!is_null($cartdetail->tax1)) {
                                if ($cartdetail->tax2->tax_type == 'service') {
                                    $tax2_value = ($discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                                    $tax2_total_value = $tax2_value;
                                } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                    $tax2_value = ($discount / (1 + $tax1 + $tax2)) * $tax2;
                                    $tax2_total_value = $tax2_value;
                                }
                            }
                            foreach ($taxes as $tax) {
                                if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                                }
                            }
                        }

                        if (!is_null($cartdetail->tax2)) {
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $coupon_wo_tax = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                            } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                $coupon_wo_tax = $discount / (1 + $tax1 + $tax2);
                            }
                        } else {
                            $coupon_wo_tax = $discount / (1 + $tax1);
                        }
                        $coupon_vat = ($discount - $coupon_wo_tax);
                        $vat = $vat - $coupon_vat;
                        $subtotal = $subtotal - $discount;
                        $subtotal_wo_tax = $subtotal_wo_tax - $coupon_wo_tax;
                        $coupon_filter[] = $used_product_coupon;
                    }
                }

                $cartdetail->coupon_for_this_product = $coupon_filter;
                $cartdetail->original_price = $original_price;
                $cartdetail->original_ammount = $original_ammount;
                $cartdetail->ammount_after_promo = $ammount_after_promo;

                if ($cartdetail->attributeValue1['value']) {
                    $attributes[] = $cartdetail->attributeValue1['value'];
                }
                if ($cartdetail->attributeValue2['value']) {
                    $attributes[] = $cartdetail->attributeValue2['value'];
                }
                if ($cartdetail->attributeValue3['value']) {
                    $attributes[] = $cartdetail->attributeValue3['value'];
                }
                if ($cartdetail->attributeValue4['value']) {
                    $attributes[] = $cartdetail->attributeValue4['value'];
                }
                if ($cartdetail->attributeValue5['value']) {
                    $attributes[] = $cartdetail->attributeValue5['value'];
                }
                $cartdetail->attributes = $attributes;
            }
            if (count($cartdata->cartdetails) > 0 && $subtotal_wo_tax > 0) {
                $cart_vat = $vat / $subtotal_wo_tax;
            } else {
                $cart_vat = 0;
            }

            $subtotal_before_cart_promo_without_tax = $subtotal_wo_tax;
            $vat_before_cart_promo = $vat;
            $cartdiscounts = 0;
            $acquired_promo_carts = array();
            $discount_cart_promo = 0;
            $discount_cart_promo_wo_tax = 0;
            $discount_cart_coupon = 0;
            $cart_promo_taxes = 0;
            $subtotal_before_cart_promo = $subtotal;
            $temp_subtotal = $subtotal;

            if (!empty($promo_carts)) {
                foreach ($promo_carts as $promo_cart) {
                    if ($subtotal >= $promo_cart->promotionrule->rule_value) {
                        if ($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') {
                            $discount = $subtotal * $promo_cart->promotionrule->discount_value;
                            if ($temp_subtotal < $discount) {
                                $discount = $temp_subtotal;
                            }
                            $promo_cart->disc_val_str = '-'.($promo_cart->promotionrule->discount_value * 100).'%';
                            $promo_cart->disc_val = '-'.($subtotal * $promo_cart->promotionrule->discount_value);
                        } elseif ($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') {
                            $discount = $promo_cart->promotionrule->discount_value;
                            if ($temp_subtotal < $discount) {
                                $discount = $temp_subtotal;
                            }
                            $promo_cart->disc_val_str = '-'.$promo_cart->promotionrule->discount_value + 0;
                            $promo_cart->disc_val = '-'.$promo_cart->promotionrule->discount_value + 0;
                        }
                        $temp_subtotal = $temp_subtotal - $discount;
                        $cart_promo_wo_tax = $discount / (1 + $cart_vat);
                        $cart_promo_tax = $discount - $cart_promo_wo_tax;
                        $cart_promo_taxes = $cart_promo_taxes + $cart_promo_tax;

                        foreach ($taxes as $tax) {
                            if (!empty($tax->total_tax)) {
                                $tax_reduction = ($tax->total_tax_before_cart_promo / $vat_before_cart_promo) * $cart_promo_tax;
                                $tax->total_tax = $tax->total_tax - $tax_reduction;
                            }
                        }

                        $discount_cart_promo = $discount_cart_promo + $discount;
                        $discount_cart_promo_wo_tax = $discount_cart_promo_wo_tax + $cart_promo_wo_tax;
                        $acquired_promo_carts[] = $promo_cart;

                    }
                }

            }

            $coupon_carts = Coupon::join('promotion_rules', function ($q) use ($subtotal) {
                $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.discount_object_type', '=', 'cash_rebate')->where('promotion_rules.coupon_redeem_rule_value', '<=', $subtotal);
            })->active()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function ($q) use ($retailer) {
                $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
            })
            ->whereHas('issuedcoupons',function ($q) use ($user) {
                $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
            })->with(array('issuedcoupons' => function ($q) use ($user) {
                $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
            }))
            ->get();

            $available_coupon_carts = array();
            $cart_discount_by_percentage_counter = 0;
            $discount_cart_coupon = 0;
            $discount_cart_coupon_wo_tax = 0;
            $total_cart_coupon_discount = 0;
            $cart_coupon_taxes = 0;
            $acquired_coupon_carts = array();
            if (!empty($used_cart_coupons)) {
                foreach ($used_cart_coupons as $used_cart_coupon) {
                    if (!empty($used_cart_coupon->issuedcoupon->coupon_redeem_rule_value)) {
                        if ($subtotal >= $used_cart_coupon->issuedcoupon->coupon_redeem_rule_value) {
                            if ($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                                $used_cart_coupon->disc_val_str = '-'.($used_cart_coupon->issuedcoupon->discount_value * 100).'%';
                                $used_cart_coupon->disc_val = '-'.($used_cart_coupon->issuedcoupon->discount_value * $subtotal);
                                $discount = $subtotal * $used_cart_coupon->issuedcoupon->discount_value;
                                if ($temp_subtotal < $discount) {
                                    $discount = $temp_subtotal;
                                }
                                $cart_discount_by_percentage_counter++;
                            } elseif ($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
                                $used_cart_coupon->disc_val_str = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                $used_cart_coupon->disc_val = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                $discount = $used_cart_coupon->issuedcoupon->discount_value;
                                if ($temp_subtotal < $discount) {
                                    $discount = $temp_subtotal;
                                }
                            }
                            $temp_subtotal = $temp_subtotal - $discount;
                            $cart_coupon_wo_tax = $discount / (1 + $cart_vat);
                            $cart_coupon_tax = $discount - $cart_coupon_wo_tax;

                            foreach ($taxes as $tax) {
                                if (!empty($tax->total_tax)) {
                                    $tax_reduction = ($tax->total_tax_before_cart_promo / $vat_before_cart_promo) * $cart_coupon_tax;
                                    $tax->total_tax = $tax->total_tax - $tax_reduction;
                                }
                            }

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
                            $used_cart_coupon->delete(true);
                            $this->commit();
                        }
                    }
                }
            }

            if (!empty($coupon_carts)) {
                foreach ($coupon_carts as $coupon_cart) {
                    if ($subtotal >= $coupon_cart->coupon_redeem_rule_value) {
                        if ($coupon_cart->rule_type == 'cart_discount_by_percentage') {
                            // prevent more than one cart_discount_by_percentage
                            if ($cart_discount_by_percentage_counter == 0) {
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
            $cartsummary->vat = round($vat, 2);
            $cartsummary->total_to_pay = round($subtotal, 2);
            $cartsummary->subtotal_wo_tax = $subtotal_wo_tax;
            $cartsummary->acquired_promo_carts = $acquired_promo_carts;
            $cartsummary->used_cart_coupons = $acquired_coupon_carts;
            $cartsummary->available_coupon_carts = $available_coupon_carts;
            $cartsummary->subtotal_before_cart_promo = round($subtotal_before_cart_promo, 2);
            $cartsummary->taxes = $taxes;
            $cartsummary->subtotal_before_cart_promo_without_tax = $subtotal_before_cart_promo_without_tax;
            $cartsummary->vat_before_cart_promo = $vat_before_cart_promo;
            $cartdata->cartsummary = $cartsummary;

        } else {
            foreach ($cartdata->cartdetails as $cartdetail) {
                $attributes = array();
                $product_vat_value = 0;
                $original_price = $cartdetail->variant->price;
                $subtotal_wo_tax = $subtotal_wo_tax + ($original_price * $cartdetail->quantity);
                $original_ammount = $original_price * $cartdetail->quantity;

                $available_product_coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                        inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y" and p.status = "active"  and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y"))
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
                        inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                        WHERE
                            ic.expired_date >= NOW()
                            AND p.merchant_id = :merchantid
                            AND prr.retailer_id = :retailerid
                            AND ic.user_id = :userid
                            AND prod.product_id = :productid

                        '), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $cartdetail->product_id));

                $cartdetail->available_product_coupons = count($available_product_coupons);

                if (!is_null($cartdetail->tax1)) {
                    $tax1 = $cartdetail->tax1->tax_value;
                    if (!is_null($cartdetail->tax2)) {
                        $tax2 = $cartdetail->tax2->tax_value;
                        if ($cartdetail->tax2->tax_type == 'service') {
                            $pwt = $original_price + ($original_price * $tax2) ;
                            $tax1_value = $pwt * $tax1;
                            $tax1_total_value = $tax1_value * $cartdetail->quantity;
                        } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                            $tax1_value = $original_price * $tax1;
                            $tax1_total_value = $tax1_value * $cartdetail->quantity;
                        }
                    } else {
                        $tax1_value = $original_price * $tax1;
                        $tax1_total_value = $tax1_value * $cartdetail->quantity;
                    }
                    foreach ($taxes as $tax) {
                        if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                            $tax->total_tax = $tax->total_tax + $tax1_total_value;
                            $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax1_total_value;
                        }
                    }
                } else {
                    $tax1 = 0;
                }

                if (!is_null($cartdetail->tax2)) {
                    $tax2 = $cartdetail->tax2->tax_value;
                    $tax2_value = $original_price * $tax2;
                    $tax2_total_value = $tax2_value * $cartdetail->quantity;
                    foreach ($taxes as $tax) {
                        if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                            $tax->total_tax = $tax->total_tax + $tax2_total_value;
                            $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax2_total_value;
                        }
                    }
                } else {
                    $tax2 = 0;
                }

                if (!is_null($cartdetail->tax2)) {
                    if ($cartdetail->tax2->tax_type == 'service') {
                        $product_price_with_tax = $original_price * (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                    } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                        $product_price_with_tax = $original_price * (1 + $tax1 + $tax2);
                    }
                } else {
                    $product_price_with_tax = $original_price * (1 + $tax1);
                }

                $product_vat = ($product_price_with_tax - $original_price) * $cartdetail->quantity;
                $vat = $vat + $product_vat;

                $product_price_with_tax = $product_price_with_tax * $cartdetail->quantity;
                $ammount_after_promo = $product_price_with_tax;
                $subtotal = $subtotal + $product_price_with_tax;
                $temp_price = $original_ammount;

                $promo_for_this_product_array = array();
                $promo_filters = array_filter($promo_products, function ($v) use ($cartdetail) { return $v->product_id == $cartdetail->product_id; });

                foreach ($promo_filters as $promo_filter) {
                    $promo_for_this_product = new \stdclass();
                    $promo_for_this_product = clone $promo_filter;
                    if ($promo_filter->rule_type == 'product_discount_by_percentage' || $promo_filter->rule_type == 'cart_discount_by_percentage') {
                        $discount = ($promo_filter->discount_value * $original_price) * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value * 100;
                    } elseif ($promo_filter->rule_type == 'product_discount_by_value' || $promo_filter->rule_type == 'cart_discount_by_value') {
                        $discount = $promo_filter->discount_value * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value;
                    } elseif ($promo_filter->rule_type == 'new_product_price') {
                        $discount = ($original_price - $promo_filter->discount_value) * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }

                        $promo_for_this_product->discount_str = $promo_filter->discount_value;
                    }
                    $promo_for_this_product->promotion_id = $promo_filter->promotion_id;
                    $promo_for_this_product->promotion_name = $promo_filter->promotion_name;
                    $promo_for_this_product->rule_type = $promo_filter->rule_type;
                    $promo_for_this_product->discount = $discount;
                    $ammount_after_promo = $ammount_after_promo - $promo_for_this_product->discount;
                    $temp_price = $temp_price - $promo_for_this_product->discount;

                    $promo_wo_tax = $discount / (1 + $product_vat_value);
                    if (!is_null($cartdetail->tax1)) {
                        $tax1 = $cartdetail->tax1->tax_value;
                        if (!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $pwt = $discount;
                                $tax1_value = $pwt * $tax1;
                                $tax1_total_value = $tax1_value;
                            } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                $tax1_value = $discount * $tax1;
                                $tax1_total_value = $tax1_value;
                            }
                        } else {
                            $tax1_value = $discount * $tax1;
                            $tax1_total_value = $tax1_value;
                        }
                        foreach ($taxes as $tax) {
                            if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                            }
                        }
                    }

                    if (!is_null($cartdetail->tax2)) {
                        $tax2 = $cartdetail->tax2->tax_value;
                        $tax2_value = $discount * $tax2;
                        $tax2_total_value = $tax2_value;

                        foreach ($taxes as $tax) {
                            if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                            }
                        }
                    }

                    if (!is_null($cartdetail->tax2)) {
                        if ($cartdetail->tax2->tax_type == 'service') {
                            $promo_with_tax = $discount * (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                        } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                            $promo_with_tax = $discount * (1 + $tax1 + $tax2);
                        }
                    } else {
                        $promo_with_tax = $discount * (1 + $tax1);
                    }

                    $promo_vat = ($promo_with_tax - $discount);

                    $vat = $vat - $promo_vat;
                    $promo_with_tax = $promo_with_tax;
                    $subtotal = $subtotal - $promo_with_tax;
                    $subtotal_wo_tax = $subtotal_wo_tax - ($discount);
                    $promo_for_this_product_array[] = $promo_for_this_product;
                }

                $cartdetail->promo_for_this_product = $promo_for_this_product_array;

                $coupon_filter = array();
                foreach ($used_product_coupons as $used_product_coupon) {

                    if ($used_product_coupon->cartdetail->product_variant_id == $cartdetail->product_variant_id) {
                        if ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_percentage' || $used_product_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                            $discount = $used_product_coupon->issuedcoupon->discount_value * $original_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value * 100;
                        } elseif ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_value' || $used_product_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
                            $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                        } elseif ($used_product_coupon->issuedcoupon->rule_type == 'new_product_price') {
                            $discount = $original_price - $used_product_coupon->issuedcoupon->discount_value + 0;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                        }
                        $temp_price = $temp_price - $discount;
                        $used_product_coupon->discount = $discount;
                        $ammount_after_promo = $ammount_after_promo - $discount;

                        if (!is_null($cartdetail->tax1)) {
                            $tax1 = $cartdetail->tax1->tax_value;
                            if (!is_null($cartdetail->tax2)) {
                                $tax2 = $cartdetail->tax2->tax_value;
                                if ($cartdetail->tax2->tax_type == 'service') {
                                    $pwt = $discount + ($discount * $tax2) ;
                                    $tax1_value = $pwt * $tax1;
                                    $tax1_total_value = $tax1_value;
                                } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                    $tax1_value = $discount * $tax1;
                                    $tax1_total_value = $tax1_value;
                                }
                            } else {
                                $tax1_value = $discount * $tax1;
                                $tax1_total_value = $tax1_value;
                            }
                            foreach ($taxes as $tax) {
                                if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                }
                            }
                        }

                        if (!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            $tax2_value = $discount * $tax2;
                            $tax2_total_value = $tax2_value;

                            foreach ($taxes as $tax) {
                                if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                                }
                            }
                        }

                        if (!is_null($cartdetail->tax2)) {
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $coupon_with_tax = $discount * (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                            } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                $coupon_with_tax = $discount * (1 + $tax1 + $tax2);
                            }
                        } else {
                            $coupon_with_tax = $discount * (1 + $tax1);
                        }

                        $coupon_vat = ($coupon_with_tax - $discount);
                        $vat = $vat - $coupon_vat;
                        $subtotal = $subtotal - $coupon_with_tax;
                        $subtotal_wo_tax = $subtotal_wo_tax - $discount;
                        $coupon_filter[] = $used_product_coupon;
                    }
                }

                $cartdetail->coupon_for_this_product = $coupon_filter;

                $cartdetail->original_price = $original_price;
                $cartdetail->original_ammount = $original_ammount;
                $cartdetail->ammount_after_promo = $ammount_after_promo;

                if ($cartdetail->attributeValue1['value']) {
                    $attributes[] = $cartdetail->attributeValue1['value'];
                }
                if ($cartdetail->attributeValue2['value']) {
                    $attributes[] = $cartdetail->attributeValue2['value'];
                }
                if ($cartdetail->attributeValue3['value']) {
                    $attributes[] = $cartdetail->attributeValue3['value'];
                }
                if ($cartdetail->attributeValue4['value']) {
                    $attributes[] = $cartdetail->attributeValue4['value'];
                }
                if ($cartdetail->attributeValue5['value']) {
                    $attributes[] = $cartdetail->attributeValue5['value'];
                }
                $cartdetail->attributes = $attributes;
            }

            if (count($cartdata->cartdetails) > 0 && $subtotal_wo_tax > 0) {
                $cart_vat = $vat / $subtotal_wo_tax;
            } else {
                $cart_vat = 0;
            }

            $subtotal_before_cart_promo_without_tax = $subtotal_wo_tax;
            $vat_before_cart_promo = $vat;
            $cartdiscounts = 0;
            $acquired_promo_carts = array();
            $discount_cart_promo = 0;
            $discount_cart_promo_with_tax = 0;
            $discount_cart_coupon = 0;
            $cart_promo_taxes = 0;
            $subtotal_before_cart_promo = $subtotal;
            $temp_subtotal = $subtotal_before_cart_promo_without_tax;

            if (!empty($promo_carts)) {
                foreach ($promo_carts as $promo_cart) {
                    if ($subtotal_before_cart_promo_without_tax >= $promo_cart->promotionrule->rule_value) {
                        if ($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') {
                            $discount = $subtotal_before_cart_promo_without_tax * $promo_cart->promotionrule->discount_value;
                            if ($temp_subtotal < $discount) {
                                $discount = $temp_subtotal;
                            }
                            $promo_cart->disc_val_str = '-'.($promo_cart->promotionrule->discount_value * 100).'%';
                            $promo_cart->disc_val = '-'.($subtotal_before_cart_promo_without_tax * $promo_cart->promotionrule->discount_value);
                        } elseif ($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') {
                            $discount = $promo_cart->promotionrule->discount_value;
                            if ($temp_subtotal < $discount) {
                                $discount = $temp_subtotal;
                            }
                            $promo_cart->disc_val_str = '-'.$promo_cart->promotionrule->discount_value + 0;
                            $promo_cart->disc_val = '-'.$promo_cart->promotionrule->discount_value + 0;
                        }
                        $temp_subtotal = $temp_subtotal - $discount;

                        $cart_promo_with_tax = $discount * (1 + $cart_vat);

                        $cart_promo_tax = $discount / $subtotal_wo_tax * $vat_before_cart_promo;
                        $cart_promo_taxes = $cart_promo_taxes + $cart_promo_tax;

                        foreach ($taxes as $tax) {
                            if (!empty($tax->total_tax)) {
                                $tax_reduction = ($discount / $subtotal_wo_tax) * $cart_promo_tax;
                                $tax->total_tax = $tax->total_tax - $tax_reduction;
                            }
                        }

                        $discount_cart_promo = $discount_cart_promo + $discount;
                        $discount_cart_promo_with_tax = $discount_cart_promo_with_tax - $cart_promo_with_tax;
                        $acquired_promo_carts[] = $promo_cart;
                    }
                }

            }

            $coupon_carts = Coupon::join('promotion_rules', function ($q) use ($subtotal_before_cart_promo_without_tax) {
                $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.discount_object_type', '=', 'cash_rebate')->where('promotion_rules.coupon_redeem_rule_value', '<=', $subtotal_before_cart_promo_without_tax);
            })->active()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function ($q) use ($retailer) {
                $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
            })
            ->whereHas('issuedcoupons',function ($q) use ($user) {
                $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
            })->with(array('issuedcoupons' => function ($q) use ($user) {
                $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
            }))
            ->get();

            $available_coupon_carts = array();
            $cart_discount_by_percentage_counter = 0;
            $discount_cart_coupon = 0;
            $discount_cart_coupon_with_tax = 0;
            $total_cart_coupon_discount = 0;
            $cart_coupon_taxes = 0;
            $acquired_coupon_carts = array();
            if (!empty($used_cart_coupons)) {
                foreach ($used_cart_coupons as $used_cart_coupon) {
                    if (!empty($used_cart_coupon->issuedcoupon->coupon_redeem_rule_value)) {
                        if ($subtotal_before_cart_promo_without_tax >= $used_cart_coupon->issuedcoupon->coupon_redeem_rule_value) {
                            if ($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                                $used_cart_coupon->disc_val_str = '-'.($used_cart_coupon->issuedcoupon->discount_value * 100).'%';
                                $used_cart_coupon->disc_val = '-'.($used_cart_coupon->issuedcoupon->discount_value * $subtotal_before_cart_promo_without_tax);
                                $discount = $subtotal_before_cart_promo_without_tax * $used_cart_coupon->issuedcoupon->discount_value;
                                if ($temp_subtotal < $discount) {
                                    $discount = $temp_subtotal;
                                }
                                $cart_discount_by_percentage_counter++;
                            } elseif ($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
                                $used_cart_coupon->disc_val_str = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                $used_cart_coupon->disc_val = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                $discount = $used_cart_coupon->issuedcoupon->discount_value;
                                if ($temp_subtotal < $discount) {
                                    $discount = $temp_subtotal;
                                }
                            }
                            $temp_subtotal = $temp_subtotal - $discount;
                            $cart_coupon_with_tax = $discount * (1 + $cart_vat);
                            $cart_coupon_tax = $discount / $subtotal_wo_tax * $vat_before_cart_promo;
                            $cart_coupon_taxes = $cart_coupon_taxes + $cart_coupon_tax;

                            foreach ($taxes as $tax) {
                                if (!empty($tax->total_tax)) {
                                    $tax_reduction = ($tax->total_tax_before_cart_promo / $vat_before_cart_promo) * $cart_coupon_tax;
                                    $tax->total_tax = $tax->total_tax - $tax_reduction;
                                }
                            }

                            $discount_cart_coupon = $discount_cart_coupon + $discount;
                            $discount_cart_coupon_with_tax = $discount_cart_coupon_with_tax - $cart_coupon_with_tax;

                            $total_cart_coupon_discount = $total_cart_coupon_discount + $discount;
                            $acquired_coupon_carts[] = $used_cart_coupon;
                        } else {
                            $this->beginTransaction();
                            $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $used_cart_coupon->issued_coupon_id)->first();
                            $issuedcoupon->makeActive();
                            $issuedcoupon->save();
                            $used_cart_coupon->delete(true);
                            $this->commit();
                        }
                    }
                }
            }

            if (!empty($coupon_carts)) {
                foreach ($coupon_carts as $coupon_cart) {
                    if ($subtotal_before_cart_promo_without_tax >= $coupon_cart->coupon_redeem_rule_value) {
                        if ($coupon_cart->rule_type == 'cart_discount_by_percentage') {
                            if ($cart_discount_by_percentage_counter == 0) { // prevent more than one cart_discount_by_percentage
                                $discount = $subtotal_before_cart_promo_without_tax * $coupon_cart->discount_value;
                                $cartdiscounts = $cartdiscounts + $discount;
                                $coupon_cart->disc_val_str = '-'.($coupon_cart->discount_value * 100).'%';
                                $coupon_cart->disc_val = '-'.($subtotal_before_cart_promo_without_tax * $coupon_cart->discount_value);
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

            $subtotal_wo_tax = $subtotal_wo_tax - $discount_cart_promo - $discount_cart_coupon;
            $subtotal = $subtotal + $discount_cart_promo_with_tax + $discount_cart_coupon_with_tax;
            $vat = $vat - $cart_promo_taxes - $cart_coupon_taxes;

            $cartsummary = new \stdclass();
            $cartsummary->vat = round($vat, 2);
            $cartsummary->total_to_pay = round($subtotal, 2);
            $cartsummary->subtotal_wo_tax = $subtotal_wo_tax;
            $cartsummary->acquired_promo_carts = $acquired_promo_carts;
            $cartsummary->used_cart_coupons = $acquired_coupon_carts;
            $cartsummary->available_coupon_carts = $available_coupon_carts;
            $cartsummary->subtotal_before_cart_promo = round($subtotal_before_cart_promo, 2);
            $cartsummary->taxes = $taxes;
            $cartsummary->subtotal_before_cart_promo_without_tax = $subtotal_before_cart_promo_without_tax;
            $cartsummary->vat_before_cart_promo = $vat_before_cart_promo;
            $cartdata->cartsummary = $cartsummary;
        }

        return $cartdata;
    }

    /**
     * Make sure only Super Admin and Cashier which are able to perform some
     * action.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    protected function CashierAndSuperAdminOnly()
    {
        // Make sure only Super Admin and Cashier which are able to call
        // this URL
        $user = $this->api->user;

        if (! $user->isSuperAdmin()) {
            $allowedRoleToCalls = ['cashier', 'merchant owner', 'retailer owner'];
            $myRoleName = $user->role->role_name;
            if (! in_array(strtolower($myRoleName), $allowedRoleToCalls)) {
                $message = Lang::get('validation.orbit.access.forbidden', ['action' => 'access']);
                ACL::throwAccessForbidden($message);
            }
        }
    }
}
