<?php
/**
 * An API controller for managing transaction history on Orbit.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;

class TransactionHistoryAPIController extends ControllerAPI
{
    /**
     * GET - List of Merchant for particular user
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `user_id`               (required) - ID of the user
     * @param string    `sortby`                (optional) - column order by, e.g: 'name', 'last_transaction'
     * @param string    `sortmode`              (optional) - asc or desc
     * @param integer   `take`                  (optional) - limit
     * @param integer   `skip`                  (optional) - limit offset
     * @return Illuminate\Support\Facades\Response
     */
    public function getMerchantList()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.transactionhistory.getmerchantlist.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.transactionhistory.getmerchantlist.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.transactionhistory.getmerchantlist.before.authz', array($this, $user));

            $user_id = OrbitInput::get('user_id');
            if (! ACL::create($user)->isAllowed('view_transaction_history')) {
                if ((string)$user_id !== (string)$user->user_id) {
                    Event::fire('orbit.transactionhistory.getmerchantlist.authz.notallowed', array($this, $user));

                    $errorMessage = Lang::get('validation.orbit.actionlist.view_transaction_history');
                    $message = Lang::get('validation.orbit.access.view_activity', array('action' => $errorMessage));

                    ACL::throwAccessForbidden($message);
                }
            }
            Event::fire('orbit.transactionhistory.getmerchantlist.after.authz', array($this, $user));

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by'       => $sort_by,
                    'user_id'       => $user_id
                ),
                array(
                    'user_id'       => 'required',
                    'sort_by'       => 'in:name,last_transaction'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.transactionhistory.merchantlist.sortby'),
                )
            );

            Event::fire('orbit.transactionhistory.getmerchantlist.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.transactionhistory.getmerchantlist.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.transaction_history.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.transaction_history.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $merchants = Merchant::transactionCustomerIds(array($user_id))
                                 ->excludeDeleted('merchants');

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_merchants = clone $merchants;

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $merchants->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $merchants) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $merchants->skip($skip);

            // Default sort by
            $sortBy = 'transactions.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name'              => 'merchants.name',
                    'last_transaction'  => 'transactions.created_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $merchants->orderBy($sortBy, $sortMode);

            $totalMerchants = RecordCounter::create($_merchants)->count();
            $listOfMerchants = $merchants->get();

            $data = new stdclass();
            $data->total_records = $totalMerchants;
            $data->returned_records = count($listOfMerchants);
            $data->records = $listOfMerchants;

            if ($listOfMerchants === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.attribute');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.transactionhistory.getmerchantlist.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.transactionhistory.getmerchantlist.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.transactionhistory.getmerchantlist.query.error', array($this, $e));

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
            Event::fire('orbit.transactionhistory.getmerchantlist.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.transactionhistory.getmerchantlist.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - List of Retailer for particular user
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `merchant_id`           (required) - ID of the merchant
     * @param integer   `user_id`               (required) - ID of the user
     * @param string    `sortby`                (optional) - column order by, e.g: 'name', 'last_transaction'
     * @param string    `sortmode`              (optional) - asc or desc
     * @param integer   `take`                  (optional) - limit
     * @param integer   `skip`                  (optional) - limit offset
     * @return Illuminate\Support\Facades\Response
     */
    public function getRetailerList()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.transactionhistory.getretailerlist.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.transactionhistory.getretailerlist.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.transactionhistory.getretailerlist.before.authz', array($this, $user));

            $user_id = OrbitInput::get('user_id');
            if (! ACL::create($user)->isAllowed('view_transaction_history')) {
                if ((string)$user_id !== (string)$user->user_id) {
                    Event::fire('orbit.transactionhistory.getretailerlist.authz.notallowed', array($this, $user));

                    $errorMessage = Lang::get('validation.orbit.actionlist.view_transaction_history');
                    $message = Lang::get('validation.orbit.access.view_activity', array('action' => $errorMessage));

                    ACL::throwAccessForbidden($message);
                }
            }
            Event::fire('orbit.transactionhistory.getretailerlist.after.authz', array($this, $user));

            $sort_by = OrbitInput::get('sortby');
            $merchant_id = OrbitInput::get('merchant_id');
            $validator = Validator::make(
                array(
                    'sort_by'       => $sort_by,
                    'user_id'       => $user_id,
                    'merchant_id'   => $merchant_id,
                ),
                array(
                    'user_id'       => 'required',
                    'merchant_id'   => 'required',
                    'sort_by'       => 'in:name,last_transaction',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.transactionhistory.retailerlist.sortby'),
                )
            );

            Event::fire('orbit.transactionhistory.getretailerlist.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.transactionhistory.getretailerlist.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.transaction_history.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.transaction_history.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $retailers = Retailer::transactionCustomerMerchantIds(array($user_id), array($merchant_id))
                                 ->excludeDeleted('merchants');

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_retailers = clone $retailers;

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $retailers->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $retailers) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $retailers->skip($skip);

            // Default sort by
            $sortBy = 'transactions.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name'              => 'merchants.name',
                    'last_transaction'  => 'transactions.created_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $retailers->orderBy($sortBy, $sortMode);

            $totalRetailers = RecordCounter::create($_retailers)->count();
            $listOfRetailers = $retailers->get();

            $data = new stdclass();
            $data->total_records = $totalRetailers;
            $data->returned_records = count($listOfRetailers);
            $data->records = $listOfRetailers;

            if ($listOfRetailers === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.attribute');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.transactionhistory.getretailerlist.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.transactionhistory.getretailerlist.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.transactionhistory.getretailerlist.query.error', array($this, $e));

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
            Event::fire('orbit.transactionhistory.getretailerlist.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.transactionhistory.getretailerlist.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - List of transaction history of Product for particular user
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array     `user_id`               (required) - ID of the user
     * @param array     `retailer_ids`          (optional) - IDs of Retailer
     * @param array     `merchant_ids`          (optional) - IDs of Merchant
     * @param string    `sortby`                (optional) - column order by, e.g: 'product_name,last_transaction,qty,price'
     * @param string    `sortmode`              (optional) - asc or desc
     * @param date      `purchase_date_begin`   (optional) - filter date from: DD-MM-YYYY HH:MM:SS
     * @param date      `purchase_date_end`     (optional) - filter date to: DD-MM-YYYY HH:MM:SS
     * @param string    `product_name`          (optional) - filter product name
     * @param string    `product_name_like`     (optional) - filter product name like
     * @param integer   `unit_price`            (optional) - filter by unit price
     * @param integer   `quantity`              (optional) - filter by purchase quantity
     * @param integer   `take`                  (optional) - limit
     * @param integer   `skip`                  (optional) - limit offset
     * @return Illuminate\Support\Facades\Response
     */
    public function getProductList()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.transactionhistory.getproductlist.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.transactionhistory.getproductlist.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.transactionhistory.getproductlist.before.authz', array($this, $user));

            $user_id = OrbitInput::get('user_id');
            if (! ACL::create($user)->isAllowed('view_transaction_history')) {
                if ((string)$user_id !== (string)$user->user_id) {
                    Event::fire('orbit.transactionhistory.getproductlist.authz.notallowed', array($this, $user));

                    $errorMessage = Lang::get('validation.orbit.actionlist.view_transaction_history');
                    $message = Lang::get('validation.orbit.access.view_activity', array('action' => $errorMessage));

                    ACL::throwAccessForbidden($message);
                }
            }
            Event::fire('orbit.transactionhistory.getproductlist.after.authz', array($this, $user));

            $sort_by = OrbitInput::get('sortby');
            $retailer_ids = OrbitInput::get('retailer_ids');
            $validator = Validator::make(
                array(
                    'sort_by'       => $sort_by,
                    'user_id'       => $user_id
                ),
                array(
                    'user_id'       => 'required',
                    'retailer_ids'  => 'array|min:0',
                    'merchant_ids'  => 'array|min:0',
                    'sort_by'       => 'in:product_name,last_transaction,qty,price'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.transactionhistory.productlist.sortby'),
                )
            );

            Event::fire('orbit.transactionhistory.getproductlist.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.transactionhistory.getproductlist.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.transaction_history.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.transaction_history.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $transactions = TransactionDetail::with('product.media', 'productVariant', 'transaction', 'transaction.merchant', 'transaction.retailer')
                                             ->transactionJoin();

            OrbitInput::get('user_id', function($userId) use ($transactions) {
                $transactions->whereIn('transactions.customer_id', (array)$userId);
            });

            OrbitInput::get('retailer_ids', function($retailerIds) use ($transactions) {
                $transactions->whereIn('transactions.retailer_id', $retailerIds);
            });

            OrbitInput::get('merchant_ids', function($merchantIds) use ($transactions) {
                $transactions->whereIn('transactions.merchant_id', $merchantIds);
            });

            // Filter by date from
            OrbitInput::get('purchase_date_begin', function ($dateBegin) use ($transactions) {
                $transactions->where('transactions.created_at', '>', $dateBegin);
            });

            // Filter by date to
            OrbitInput::get('purchase_date_end', function ($dateEnd) use ($transactions) {
                $transactions->where('transactions.created_at', '<', $dateEnd);
            });

            // Quantity filter
            OrbitInput::get('quantity', function ($qty) use ($transactions) {
               $transactions->where('transaction_details.quantity', $qty);
            });

            // Product Name Filter
            OrbitInput::get('product_name', function ($productName) use ($transactions) {
                 $transactions->where('products.product_name', 'like', $productName);
            });

            // Product name like filter
            OrbitInput::get('product_name_like', function ($productName) use ($transactions) {
                $transactions->where('products.product_name', 'like', "%{$productName}%");
            });

            // Retailer name filter
            OrbitInput::get('retailer_name', function ($retailerName) use ($transactions) {
                $transactions->where('retailer.name', 'like', $retailerName);
            });

            // Retailer name like filter
            OrbitInput::get('retailer_name_like', function ($retailerName) use ($transactions) {
                $transactions->where('retailer.name', 'like', "%{$retailerName}%");
            });

            // Unit Price filter
            OrbitInput::get('unit_price', function ($_price) use ($transactions) {
               $transactions->where('transaction_details.price', '=', $_price);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_transactions = clone $transactions;

            // Default sort by
            $sortBy = 'transaction_details.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'product_name'      => 'transaction_details.product_name',
                    'last_transaction'  => 'transaction_details.created_at',
                    'qty'               => 'transaction_details.quantity',
                    'price'             => 'transaction_details.price'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $transactions->orderBy($sortBy, $sortMode);
            // double sort hack to make sorted list same everytime
            if ($sortBy != 'transaction_details.created_at') {
                $transactions->orderBy('transaction_details.created_at', 'desc');
            }

            if ($this->builderOnly)
            {
                return $this->builderObject($transactions, $_transactions);
            }
            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $transactions->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $transactions) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $transactions->skip($skip);

            $totalTransactions = RecordCounter::create($_transactions)->count();
            $listOfTransactions = $transactions->get();

            $data = new stdclass();
            $data->total_records = $totalTransactions;
            $data->returned_records = count($listOfTransactions);
            $data->records = $listOfTransactions;

            if ($totalTransactions === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.attribute');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.transactionhistory.getproductlist.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.transactionhistory.getproductlist.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.transactionhistory.getproductlist.query.error', array($this, $e));

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
            Event::fire('orbit.transactionhistory.getproductlist.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.transactionhistory.getproductlist.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Transactions History Receipt Report
     * @endpoint 'api/v1/consumer-transactions-history/receipt-list'
     * List of API Parameters
     * ----------------------
     * @param array     `merchant_id`           (optional) - IDs of Merchant
     * @param string    `sortby`                (optional) - column order by, e.g: 'product_name,last_transaction,qty,price'
     * @param string    `sortmode`              (optional) - asc or desc
     * @param date      `purchase_date_begin`   (optional) - filter date from: DD-MM-YYYY HH:MM:SS
     * @param date      `purchase_date_end`     (optional) - filter date to: DD-MM-YYYY HH:MM:SS
     * @param array     `cashier_id`            (optional) - filter Cashier id
     * @param array     `customer_id`           (optional) - filter customer id
     * @param string    `cashier_name_like`     (optional) - filter customer name
     * @param string    `customer_name_like`    (optional) - filter cashier name
     * @param integer   `transaction_id`        (optional) - filter by transaction code
     * @param integer   `payment_method`        (optional) - filter by payment method
     * @param integer   `take`                  (optional) - limit
     * @param integer   `skip`                  (optional) - limit offset
     * @return Illuminate\Support\Facades\Response
     */
    public function getReceiptReport()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.transactionhistory.getreceiptreport.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.transactionhistory.getreceiptreport.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.transactionhistory.getreceiptreport.before.authz', array($this, $user));

            $user_id = OrbitInput::get('user_id');
            if (! ACL::create($user)->isAllowed('view_transaction_history')) {
                if ((string)$user_id !== (string)$user->user_id) {
                    Event::fire('orbit.transactionhistory.getreceiptreport.authz.notallowed', array($this, $user));

                    $errorMessage = Lang::get('validation.orbit.actionlist.view_transaction_history');
                    $message = Lang::get('validation.orbit.access.view_activity', array('action' => $errorMessage));

                    ACL::throwAccessForbidden($message);
                }
            }
            Event::fire('orbit.transactionhistory.getreceiptreport.after.authz', array($this, $user));

            $sort_by = OrbitInput::get('sortby');
            $retailer_ids = OrbitInput::get('retailer_ids');
            $validator = Validator::make(
                array(
                    'sort_by'       => $sort_by,
                    'user_id'       => $user_id
                ),
                array(
                    'retailer_ids'  => 'array|min:0',
                    'merchant_ids'  => 'array|min:0',
                    'sort_by'       => 'in:transaction_id,payment_method,customer_name,cashier_name,created_at,total_to_pay'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.transactionhistory.productlist.sortby'),
                )
            );

            Event::fire('orbit.transactionhistory.getreceiptreport.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.transactionhistory.getreceiptreport.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.transaction_history.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.transaction_history.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix  = DB::getTablePrefix();
            $transactions = Transaction::select(
                    DB::raw("(
                        case payment_method
                           when 'cash' then 'Cash'
                           when 'online_payment' then 'Online Payment'
                           when 'paypal' then 'Paypal'
                           when 'card' then 'Card'
                           else payment_method
                        end
                    ) as payment_type"),
                    "transactions.*",
                    DB::raw("date({$tablePrefix}transactions.created_at) as created_at_date"),
                    DB::raw("time({$tablePrefix}transactions.created_at) as created_at_time"),
                    DB::raw("concat({$tablePrefix}customer.user_firstname, ' ', {$tablePrefix}customer.user_lastname) as customer_full_name"),
                    DB::raw("concat({$tablePrefix}cashier.user_firstname, ' ', {$tablePrefix}cashier.user_lastname) as cashier_full_name")
                )
                ->leftJoin("users as {$tablePrefix}customer", function ($join) {
                    $join->on('customer.user_id', '=', 'transactions.customer_id');
                })
                ->leftJoin("users as {$tablePrefix}cashier", function ($join) {
                    $join->on('cashier.user_id', '=', 'transactions.cashier_id');
                })
                ->with('user', 'cashier');

            OrbitInput::get('merchant_id', function ($merchantId) use ($transactions) {
                $transactions->whereIn('transactions.merchant_id', $this->getArray($merchantId));
            });

            OrbitInput::get('transaction_id', function ($transactionCode) use ($transactions) {
                $transactions->whereIn('transactions.transaction_id', $this->getArray($transactionCode));
            });

            OrbitInput::get('payment_method', function ($payementMethod) use ($transactions) {
                $transactions->whereIn('transactions.payment_method', $this->getArray($payementMethod));
            });

            // Filter by date from
            OrbitInput::get('purchase_date_begin', function ($dateBegin) use ($transactions) {
                $transactions->where('transactions.created_at', '>', $dateBegin);
            });

            // Filter by date to
            OrbitInput::get('purchase_date_end', function ($dateEnd) use ($transactions) {
                $transactions->where('transactions.created_at', '<', $dateEnd);
            });

            OrbitInput::get('cashier_id', function ($cashierId) use ($transactions) {
                $transactions->whereIn('cashier.user_id', $this->getArray($cashierId));
            });

            OrbitInput::get('customer_id', function ($cashierId) use ($transactions) {
                $transactions->whereIn('customer.user_id', $this->getArray($cashierId));
            });

            // filter by cashier either first or last name
            OrbitInput::get('cashier_name_like', function ($cashierName) use ($transactions, $tablePrefix) {
                $transactions->where(function ($q) use ($cashierName) {
                    $q->where('cashier.user_firstname', 'like', "%{$cashierName}%");
                    $q->orWhere('cashier.user_lastname', 'like', "%{$cashierName}%");
                });
            });

            OrbitInput::get('customer_name_like', function ($customerName) use ($transactions, $tablePrefix) {
                $transactions->where(function ($q) use ($customerName) {
                    $q->where('customer.user_firstname', 'like', "%{$customerName}%");
                    $q->orWhere('customer.user_lastname', 'like', "%{$customerName}%");
                });
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_transactions = clone $transactions;

            // Default sort by
            $sortBy = 'transactions.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'created_at'        => 'transactions.created_at',
                    'transaction_id'    => 'transactions.transaction_id',
                    'payment_method'    => 'transactions.payment_method',
                    'total_to_pay'      => 'transactions.total_to_pay',
                    'customer_name'     => 'customer.user_firstname',
                    'cashier_name'      => 'cashier.user_firstname'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $transactions->orderBy($sortBy, $sortMode);

            if ($this->builderOnly)
            {
                return $this->builderObject($transactions, $_transactions);
            }

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $transactions->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $transactions) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $transactions->skip($skip);

            $totalTransactions = RecordCounter::create($_transactions)->count();
            $listOfTransactions = $transactions->get();

            $data = new stdclass();
            $data->total_records = $totalTransactions;
            $data->last_page     = false;
            $data->returned_records = count($listOfTransactions);
            $data->records = $listOfTransactions;

            // Consider last pages
            if (($totalTransactions - $take) <= $skip)
            {
                $subTotalQuery    = $_transactions->toSql();
                $subTotalBindings = $_transactions->getQuery();
                $subTotal = DB::table(DB::raw("({$subTotalQuery}) as sub_total"))
                    ->mergeBindings($subTotalBindings)
                    ->select([
                        DB::raw("sum(sub_total.total_to_pay) as transactions_total")
                    ])->first();

                $data->last_page  = true;
                $data->sub_total  = $subTotal;
            }

            if ($totalTransactions === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.attribute');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.transactionhistory.getreceiptreport.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.transactionhistory.getreceiptreport.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.transactionhistory.getreceiptreport.query.error', array($this, $e));

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
            Event::fire('orbit.transactionhistory.getreceiptreport.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.transactionhistory.getreceiptreport.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Transactions History Detail Sales Report Report
     * @endpoint 'api/v1/consumer-transactions-history/detail-sales-report'
     * List of API Parameters
     * ----------------------
     * @param array     `merchant_id`           (optional) - IDs of Merchant
     * @param string    `sortby`                (optional) - column order by, e.g: 'product_name,last_transaction,qty,price'
     * @param string    `sortmode`              (optional) - asc or desc
     * @param date      `purchase_date_begin`   (optional) - filter date from: DD-MM-YYYY HH:MM:SS
     * @param date      `purchase_date_end`     (optional) - filter date to: DD-MM-YYYY HH:MM:SS
     * @param array     `cashier_id`            (optional) - filter Cashier id
     * @param array     `customer_id`           (optional) - filter customer id
     * @param string    `cashier_name_like`     (optional) - filter customer name
     * @param string    `customer_name_like`    (optional) - filter cashier name
     * @param integer   `transaction_id`        (optional) - filter by transaction code
     * @param string    `product_name_like`     (optional) - filter by product name
     * @param string    `upc_code`              (optional) - filter by product upc
     * @param integer   `payment_method`        (optional) - filter by payment method
     * @param integer   `take`                  (optional) - limit
     * @param integer   `skip`                  (optional) - limit offset
     * @return Illuminate\Support\Facades\Response
     */
    public function getDetailSalesReport()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.transactionhistory.getdetailsalesreport.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.transactionhistory.getdetailsalesreport.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.transactionhistory.getdetailsalesreport.before.authz', array($this, $user));

            $user_id = OrbitInput::get('user_id');
            if (! ACL::create($user)->isAllowed('view_transaction_history')) {
                if ((string)$user_id !== (string)$user->user_id) {
                    Event::fire('orbit.transactionhistory.getdetailsalesreport.authz.notallowed', array($this, $user));

                    $errorMessage = Lang::get('validation.orbit.actionlist.view_transaction_history');
                    $message = Lang::get('validation.orbit.access.view_activity', array('action' => $errorMessage));

                    ACL::throwAccessForbidden($message);
                }
            }
            Event::fire('orbit.transactionhistory.getdetailsalesreport.after.authz', array($this, $user));

            $sort_by = OrbitInput::get('sortby');
            $retailer_ids = OrbitInput::get('retailer_ids');
            $validator = Validator::make(
                array(
                    'sort_by'       => $sort_by,
                    'user_id'       => $user_id
                ),
                array(
                    'retailer_ids'  => 'array|min:0',
                    'merchant_ids'  => 'array|min:0',
                    'sort_by'       => 'in:transaction_id,product_sku,product_name,customer_user_email,quantity,price,payment_method,created_at,total_tax,sub_total,cashier_name,customer_name'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.transactionhistory.productlist.sortby'),
                )
            );

            Event::fire('orbit.transactionhistory.getdetailsalesreport.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.transactionhistory.getdetailsalesreport.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.transaction_history.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.transaction_history.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $transactions = TransactionDetail::detailSalesReport();

            OrbitInput::get('merchant_id', function ($merchantId) use ($transactions) {
                $transactions->whereIn('transactions.merchant_id', $this->getArray($merchantId));
            });

            OrbitInput::get('transaction_id', function ($transactionCode) use ($transactions) {
                $transactions->whereIn('transactions.transaction_id', $this->getArray($transactionCode));
            });

            OrbitInput::get('product_sku', function ($productSKU) use ($transactions) {
                $transactions->whereIn('transaction_details.product_code', $this->getArray($productSKU));
            });

            OrbitInput::get('payment_method', function ($payementMethod) use ($transactions) {
                $transactions->whereIn('transactions.payment_method', $this->getArray($payementMethod));
            });

            // Filter by date from
            OrbitInput::get('purchase_date_begin', function ($dateBegin) use ($transactions) {
                $transactions->where('transactions.created_at', '>=', $dateBegin);
            });

            // Filter by date to
            OrbitInput::get('purchase_date_end', function ($dateEnd) use ($transactions) {
                $transactions->where('transactions.created_at', '<=', $dateEnd);
            });

            OrbitInput::get('cashier_id', function ($cashierId) use ($transactions) {
                $transactions->whereIn('cashier.user_id', $this->getArray($cashierId));
            });

            OrbitInput::get('customer_id', function ($cashierId) use ($transactions) {
                $transactions->whereIn('customer.user_id', $this->getArray($cashierId));
            });

            // filter by cashier either first or last name
            OrbitInput::get('product_name_like', function ($productName) use ($transactions) {
                $transactions->where('product_name', 'like', "%{$productName}%");
            });

            // filter by cashier either first or last name
            OrbitInput::get('cashier_name_like', function ($cashierName) use ($transactions) {
                $transactions->where('cashier.user_firstname', 'like', "%{$cashierName}%")
                    ->orWhere('cashier.user_lastname', 'like', "%{$cashierName}%");
            });

            OrbitInput::get('customer_name_like', function ($customerName) use ($transactions) {
                $transactions->where('customer.user_firstname', 'like', "%{$customerName}%")
                    ->orWhere('customer.user_lastname', 'like', "%{$customerName}%");
            });

            $transactions->groupBy(
                'transaction_details.product_id',
                'transaction_details.transaction_detail_id'
            );

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_transactions = clone $transactions;

            // Default sort by
            $sortBy = 'transactions.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'transaction_id'  => 'transactions.transaction_id',
                    'product_sku'     => 'transaction_details.product_code',
                    'product_name'    => 'transaction_details.product_name',
                    'quantity'        => 'transaction_details.quantity',
                    'price'           => 'transaction_details.price',
                    'payment_method'  => 'transactions.payment_method',
                    'created_at'      => 'transaction_details.created_at',
                    'total_tax'       => 'total_tax',
                    'sub_total'       => 'sub_total',
                    'cashier_name'    => 'cashier.user_firstname',
                    'customer_name'   => 'customer.user_firstname',
                    'customer_user_email' => 'customer.user_email'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $transactions->orderBy($sortBy, $sortMode);

            if ($this->builderOnly)
            {
                return $this->builderObject($transactions, $_transactions);
            }

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $transactions->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $transactions) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $transactions->skip($skip);

            $totalTransactions = RecordCounter::create($_transactions)->count();
            $listOfTransactions = $transactions->get();

            $data = new stdclass();
            $data->total_records = $totalTransactions;
            $data->returned_records = count($listOfTransactions);
            $data->last_page = false;
            $data->records = $listOfTransactions;

            // Consider last pages
            if (($totalTransactions - $take) <= $skip)
            {
                $subTotalQuery    = $_transactions->toSql();
                $subTotalBindings = $_transactions->getQuery();
                $subTotal = DB::table(DB::raw("({$subTotalQuery}) as sub_total"))
                    ->mergeBindings($subTotalBindings)
                    ->select([
                        DB::raw("sum(sub_total.quantity) as quantity_total"),
                        DB::raw("sum(sub_total.sub_total) as sub_total")
                    ])->first();

                $data->last_page  = true;
                $data->sub_total  = $subTotal;
            }

            if ($listOfTransactions === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.attribute');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.transactionhistory.getdetailsalesreport.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.transactionhistory.getdetailsalesreport.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.transactionhistory.getdetailsalesreport.query.error', array($this, $e));

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
            Event::fire('orbit.transactionhistory.getdetailsalesreport.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.transactionhistory.getdetailsalesreport.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        $user = $this->api->user;
        Validator::extend('orbit.check.merchants', function ($attribute, $value, $parameters) use ($user) {
            $merchants = Merchant::excludeDeleted()
                ->allowedForUser($user)
                ->whereIn('merchant_id', $value)
                ->limit(50)
                ->get();

            $merchantIds = array();

            foreach ($merchants as $id) {
                $merchantIds[] = $id;
            }

            App::instance('orbit.check.merchants', $merchantIds);

            return TRUE;
        });
    }

    /**
     * @param mixed $mixed
     * @return array
     */
    private function getArray($mixed)
    {
        $arr = [];
        if (is_array($mixed)) {
            $arr = array_merge($arr, $mixed);
        } else {
            array_push($arr, $mixed);
        }

        return $arr;
    }
}
