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
        try {
            $username = trim(OrbitInput::post('username'));
            $password = trim(OrbitInput::post('password'));

            if (trim($username) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'username'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (trim($password) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'password'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = User::with('apikey', 'userdetail', 'role')
                        ->active()
                        ->where('username', $username)
                        ->where('user_role_id', Role::where('role_name','Cashier')->first()->role_id)
                        ->first();           

            if (! is_object($user)) {
                $message = \Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            if (! \Hash::check($password, $user->user_password)) {
                $message = \Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            \Auth::login($user);

            $this->response->data = $user;
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
     * POST - Logout cashier
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postLogoutCashier()
    {
        \Auth::logout();
        $this->response->code = 0;
        $this->response->message = 'success';
        $this->response->data = 'sukses atuh kang';
        return $this->render();
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
            $driver = '~/drivers/64bits/barcode';
            $device = '/dev/domino/scanner';
            $cmd = 'sudo '.$driver.' '.$device;
            $barcode = shell_exec($cmd);

            //echo "barcode nya ".$barcode;
            $barcode = trim($barcode);
            $product = Product::where('upc_code', $barcode)
                    ->active()
                    ->first();      

            if (! is_object($product)) {
                $message = \Lang::get('validation.orbit.empty.product');
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

            $products = Product::with('retailers')->excludeDeleted()->allowedForUser($user);

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
}
