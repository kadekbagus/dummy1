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
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Config;

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

            $user = User::with('apikey', 'userdetail', 'role', 'merchants')
                        ->active()
                        ->where('username', $username)
                        ->where('user_role_id', Role::where('role_name','Cashier')->first()->role_id)
                        ->first();

            if (is_object($user)) {
                if( ! \Hash::check($password, $user->user_password)){
                    $message = \Lang::get('validation.orbit.access.loginfailed');
                    ACL::throwAccessForbidden($message);
                }else{
                    // Start the orbit session
                    $data = array(
                        'logged_in' => TRUE,
                        'user_id'   => $user->user_id,
                    );
                    $config = new SessionConfig(Config::get('orbit.session'));
                    $session = new Session($config);
                    $session->enableForceNew()->start($data);
                }
            } else {
                $message = \Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            $user->setHidden(array('user_password', 'apikey'));

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
        try {
            $config = new SessionConfig(Config::get('orbit.session'));
            $session = new Session($config);
            $this->prepareSession();

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

            $transaction->save();

            //insert to table transaction_details
            foreach($cart as $k => $v){
                $transactionDetails = new \TransactionDetail();
                $transactionDetails->transaction_id = $transaction->transaction_id;
                $transactionDetails->product_id     = $v['product_id'];
                $transactionDetails->product_name   = $v['product_name'];
                $transactionDetails->product_code   = $v['product_code'];
                $transactionDetails->quantity       = $v['qty'];
                $transactionDetails->upc            = $v['upc_code'];
                $transactionDetails->price          = str_replace( ',', '', $v['price'] );

                $transactionDetails->save();
            }
            
            //only payment cash
            if($payment_method == 'cash') self::postCashDrawer();

            $this->response->data  = $transaction;
            // Commit the changes
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
            $transaction_id = trim(OrbitInput::post('transaction_id'));

            $transaction = \Transaction::with('details', 'cashier')->where('transaction_id',$transaction_id)->first();

            if (! is_object($transaction)) {
                $message = \Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }
            
            $this->response->data = $transaction;   

            foreach ($transaction['details'] as $key => $value) {
               if($key==0){
                $product = $this->producListFormat(substr($value['product_name'], 0,25), $value['price'], $value['quantity'], $value['product_code']);
               }
               else {
                $product .= $this->producListFormat(substr($value['product_name'], 0,25), $value['price'], $value['quantity'], $value['product_code']);
               }
            }
            
            $payment = $transaction['payment_method'];
            $date  =  $transaction['created_at']->timezone('Asia/Jakarta')->format('d M Y H:i:s');
            $customer = "guest";
            if($payment=='cash'){$payment='Cash';}
            if($payment=='card'){$payment='Card';}
            $cashier = $transaction['cashier']->user_firstname." ".$transaction['cashier']->user_lastname;
            $bill_no = $transaction['transaction_id'];

            $head  = $this->just40CharMid('MATAHARI');
            $head .= $this->just40CharMid('DEPARTMENT STORE');
            $head .= $this->just40CharMid('Jl. Raya Semer 88');
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
            $footer .= $this->just40CharMid('Thank you for your purchase');
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

            //$this->response->data = "tes";
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
            $cmd = 'sudo '.$driver.' --device '.$params.' --words '.$amount;
            $card = shell_exec($cmd);

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
            $driver = Config::get('orbit.devices.barcode.path');
            $params = Config::get('orbit.devices.barcode.params');
            $cmd = 'sudo '.$driver.' '.$params;
            $barcode = shell_exec($cmd);
            
            $barcode = trim($barcode);
            $cart = \Cart::with('details', 'users')->where('cart_code', $barcode)
                    ->active()
                    ->first();      

            if (! is_object($cart)) {
                $message = \Lang::get('validation.orbit.empty.product');
                ACL::throwAccessForbidden($message);
            }

            $this->response->data = $cart;
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

    private function producListFormat($name, $price, $qty, $sku)
    {
        $all  = '';
        $sbT = number_format($price*$qty,2);
        $space = 40-strlen($name)-strlen($sbT); $spc = '';
        for ($i=0;$i<$space;$i++) { $spc .= ' '; }
        $all .= $name.$spc.$sbT." \n";
        $all .= '   '.$qty.' x '.number_format($price,2).' ('.$sku.')'." \n";

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
}
