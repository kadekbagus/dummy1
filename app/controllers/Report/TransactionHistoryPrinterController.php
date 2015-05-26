<?php namespace Report;
/**
 * Intermediate Controller to print purchase history
 *
 * Class PurchaseHistoryPrinterController
 * @package Report
 */

use Illuminate\Support\Facades\Response;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use PDO;
use DB;
use Config;
use Transaction;
use TransactionDetail;
use Exception;

class TransactionHistoryPrinterController extends  DataPrinterController
{
    public function getProductListPrintView()
    {
        try {
            $this->preparePDO();
            $prefix = DB::getTablePrefix();

            $mode = OrbitInput::get('export', 'print');
            $user = $this->loggedUser;
            $now  = date('Y-m-d H:i:s');

            $transactions = TransactionDetail::select(["products.product_name",
                                                        "transaction_details.price",
                                                        "transaction_details.quantity",
                                                        "transactions.created_at",
                                                        "transactions.merchant_id",
                                                        "transactions.retailer_id",
                                                        "merchant.name as merchant_name",
                                                        "retailer.name as retailer_name"])
                ->leftJoin("transactions", function ($join) {
                    $join->on("transactions.transaction_id", "=", 'transaction_details.transaction_id');
                    $join->where('transactions.status', '=', DB::raw('paid'));
                })
                ->leftJoin("merchants as {$prefix}merchant", function ($join) {
                    $join->on("transactions.merchant_id", "=", "merchant.merchant_id");
                })
                ->leftJoin("merchants as {$prefix}retailer", function ($join) {
                    $join->on("transactions.retailer_id", "=", "retailer.merchant_id");
                })
                ->leftJoin("products", function ($join) {
                    $join->on("transaction_details.product_id", "=", "products.product_id");
                })
                ->where('transactions.customer_id', '=', $user->user_id);

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
            OrbitInput::get('unit_price', function ($price) use ($transactions) {
                $transactions->where('transaction_details.price', '=', $price);
            });

            $this->prepareUnbufferedQuery();
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

            $query      = $transactions->toSql();
            $bindings   = $transactions->getBindings();

            $statement  = $this->pdo->prepare($query);
            $statement->execute($bindings);

            $total      = RecordCounter::create($_transactions)->count();
            $rowCounter = 0;
            $pageTitle  = 'Report Purchase History';

            $formatDate = function($time) {
                return date('d-M-Y H:i:s', strtotime($time));
            };

            switch ($mode) {
                case 'csv':
                    $filename = 'transaction-product-list-' . $now . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);
                    // TITLE HEADER
                    printf("%s,%s,%s,%s,%s,%s\n", '', '', '', $pageTitle, '', '');
                    printf("%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '');

                    // Total Purchase
                    printf("%s,%s,%s,%s,%s,%s\n", '', 'Total', ':', $total, '', '');

                    // ROW HEADER
                    printf("%s,%s,%s,%s,%s,%s", 'No.', 'Product Name', 'Quantity', 'Store Name', 'Unit Price', 'Purchase Date');

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        printf("\n%s,%s,%s,%s,%s,%s", ++$rowCounter, $row->product_name, $row->quantity, $row->retailer_name, number_format($row->price), $formatDate($row->created_at));
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/purchase-history/list-product-view.php';
            }
        } catch(Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }

    public function getReceiptReportPrintView()
    {
        try {
            $this->preparePDO();

            $mode = OrbitInput::get('export', 'print');
            $now  = date('Y-m-d H:i:s');

            $tablePrefix  = DB::getTablePrefix();
            $transactions = Transaction::select(
                    "transactions.created_at",
                    "transactions.transaction_id",
                    "transactions.total_to_pay",
                    "transactions.payment_method",
                    "customer.user_firstname as customer_first_name",
                    "customer.user_lastname as customer_last_name",
                    "cashier.user_firstname as cashier_first_name",
                    "cashier.user_lastname as cashier_last_name"
                )
                ->join("users as {$tablePrefix}customer", function ($join) {
                    $join->on('customer.user_id', '=', 'transactions.customer_id');
                })
                ->join("users as {$tablePrefix}cashier", function ($join) {
                    $join->on('cashier.user_id', '=', 'transactions.cashier_id');
                });

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
                $transactions->where('cashier.user_firstname', 'like', "%{$cashierName}%")
                    ->orWhere('cashier.user_lastname', 'like', "%{$cashierName}%");
            });

            OrbitInput::get('customer_name_like', function ($customerName) use ($transactions, $tablePrefix) {
                $transactions->where('customer.user_firstname', 'like', "%{$customerName}%")
                    ->orWhere('customer.user_lastname', 'like', "%{$customerName}%");
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_transactions = clone $transactions;

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

            $subTotalQuery    = $_transactions->toSql();
            $subTotalBindings = $_transactions->getQuery();
            $subTotal = DB::table(DB::raw("({$subTotalQuery}) as sub_total"))
                ->mergeBindings($subTotalBindings)
                ->select([
                    DB::raw("sum(sub_total.total_to_pay) as transactions_total")
                ])->first();

            $this->prepareUnbufferedQuery();
            $_transactions = clone $transactions;

            $query      = $transactions->toSql();
            $bindings   = $transactions->getBindings();

            $statement  = $this->pdo->prepare($query);
            $statement->execute($bindings);

            $total      = RecordCounter::create($_transactions)->count();
            $rowCounter = 0;
            $pageTitle  = 'Report Receipt List';

            $getDate = function($time) {
                return date('d-M-Y', strtotime($time));
            };

            $getTime = function($time) {
                return date('H:i:s', strtotime($time));
            };

            $getFullName = function ($row, $type) {
                $firstName = "{$type}_first_name";
                $lastName = "{$type}_last_name";

                return "{$row->$firstName} {$row->$lastName}";
            };

            switch ($mode) {
                case 'csv':
                    $filename = 'transaction-product-list-' . $now . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);
                    // TITLE HEADER
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '',$pageTitle,'','','','','');
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '');

                    // Total Purchase
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', 'Total Records', ':', $total, '', '', '');
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', 'Total Sales', ':', $subTotal->transactions_total, '', '', '', '');

                    // ROW HEADER
                    printf(
                        "%s,%s,%s,%s,%s,%s,%s,%s",
                        'No.', // 1
                        'Date', // 2
                        'Time', // 3
                        'Receipt Number', // 4
                        'Total Value', // 5
                        'Payment Type', // 6
                        'Customer (if known)', // 7
                        'Cashier'  // 8
                    );

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        printf("\n%s,%s,%s,%s,%s,%s,%s,%s",
                            ++$rowCounter, // 1
                            $getDate($row->created_at), // 2
                            $getTime($row->created_at), // 3
                            $row->transaction_id, // 4
                            $row->total_to_pay, // 5
                            $row->payment_method, // 6
                            $getFullName($row, 'customer'), // 7
                            $getFullName($row, 'cashier') // 8
                        );
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/list-receipt-report-view.php';
            }

        } catch (Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }

    public function getDetailReportPrintView()
    {
        try {
            $this->preparePDO();
            $mode = OrbitInput::get('export', 'print');
            $now  = date('Y-m-d H:i:s');
            $tablePrefix  = DB::getTablePrefix();

            $transactions = TransactionDetail::select(
                    'transactions.transaction_id',
                    'transaction_details.upc as product_sku',
                    'transaction_details.product_name',
                    'transaction_details.quantity',
                    'transaction_details.price',
                    'transactions.payment_method',
                    'transaction_details.created_at',
                    DB::raw("sum(ifnull({$tablePrefix}tax.total_tax, 0)) as total_tax"),
                    DB::raw("(quantity * (price + sum(ifnull({$tablePrefix}tax.total_tax, 0)))) as sub_total"),
                    'cashier.user_firstname as cashier_user_firstname',
                    'cashier.user_lastname as cashier_user_lastname',
                    'customer.user_firstname as customer_user_firstname',
                    'customer.user_lastname as customer_user_lastname'
                )
                ->join("transactions", function ($join) {
                    $join->on("transactions.transaction_id", '=', "transaction_details.transaction_id");
                })
                ->join("transaction_detail_taxes as {$tablePrefix}tax", function ($join) {
                    $join->on("transaction_details.transaction_detail_id", '=', 'tax.transaction_detail_id');
                })
                ->join("users as {$tablePrefix}customer", function ($join) {
                    $join->on('customer.user_id', '=', 'transactions.customer_id');
                })
                ->join("users as {$tablePrefix}cashier", function ($join) {
                    $join->on('cashier.user_id', '=', 'transactions.cashier_id');
                });

            OrbitInput::get('merchant_id', function ($merchantId) use ($transactions) {
                $transactions->whereIn('transactions.merchant_id', $this->getArray($merchantId));
            });

            OrbitInput::get('transaction_id', function ($transactionCode) use ($transactions) {
                $transactions->whereIn('transactions.transaction_id', $this->getArray($transactionCode));
            });

            OrbitInput::get('upc_code', function ($upcCode) use ($transactions) {
                $transactions->whereIn('upc', $this->getArray($upcCode));
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

            $transactions->groupBy('transaction_details.transaction_detail_id');

            $_transactions = clone $transactions;

            // Default sort by
            $sortBy = 'transactions.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'transaction_id'  => 'transactions.transaction_id',
                    'product_sku'     => 'transaction_details.upc',
                    'quantity'        => 'transaction_details.quantity',
                    'price'           => 'transaction_details.price',
                    'payment_method'  => 'transactions.payment_method',
                    'created_at'      => 'transaction_details.created_at',
                    'total_tax'       => 'total_tax',
                    'sub_total'       => 'sub_total',
                    'cashier_name'    => 'cashier.user_firstname',
                    'customer_name'   => 'customer.user_firstname'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });

            $transactions->orderBy($sortBy, $sortMode);

            $query      = $transactions->toSql();
            $bindings   = $transactions->getBindings();

            $statement  = $this->pdo->prepare($query);
            $statement->execute($bindings);

            $total      = RecordCounter::create($_transactions)->count();
            $rowCounter = 0;
            $pageTitle  = 'Report Detail Sales';

            // Consider last pages
            $subTotalQuery    = $_transactions->toSql();
            $subTotalBindings = $_transactions->getQuery();
            $subTotal = DB::table(DB::raw("({$subTotalQuery}) as sub_total"))
                ->mergeBindings($subTotalBindings)
                ->select([
                    DB::raw("sum(sub_total.quantity) as quantity_total"),
                    DB::raw("sum(sub_total.sub_total) as sub_total")
                ])->first();

            $formatDate = function ($time) {
              return date('d-M-Y H:i:s', strtotime($time));
            };

            $getFullName = function ($row, $type) {
                $firstName = "{$type}_user_firstname";
                $lastName = "{$type}_user_lastname";

                return "{$row->$firstName} {$row->$lastName}";
            };

            switch ($mode) {
                case 'csv':
                    $filename = 'list-detail-sales-report-' . $now . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);
                    // TITLE HEADER
                    printf(" , ,%s, , , , , , , , , \n", $pageTitle);
                    printf(" , , , , , , , , , , , \n");

                    // Total Purchase
                    printf(" , ,%s,%s,%s, , , , , , , \n", 'Total Records', ':', $total);
                    printf(" , ,%s,%s,%s, , , , , , , \n", 'Total Quantity', ':', $subTotal->quantity_total);
                    printf(" , ,%s,%s,%s, , , , , , , \n", 'Total Sales', ':', $subTotal->sub_total);

                    // ROW HEADER
                    printf(
                        "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s",
                        'No.', // 1
                        'Date', // 2
                        'Receipt Number', // 3
                        'Product SKU', // 4
                        'Product Name', // 5
                        'Quantity', // 6
                        'Unit Price', // 7
                        'Tax Value',  // 8
                        'Sub Total',  // 9
                        'Payment Type',  // 10
                        'Customer', // 11
                        'Cashier' // 12
                    );

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        printf("\n%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s",
                            ++$rowCounter, // 1
                            $formatDate($row->created_at), // 2
                            $row->transaction_id, // 3
                            $row->product_sku, // 4
                            $row->product_name, // 5
                            $row->quantity, // 6
                            $row->price, // 7
                            $row->total_tax, // 8
                            $row->sub_total, //9
                            $row->payment_method, // 10
                            $getFullName($row, 'customer'), // 11
                            $getFullName($row, 'cashier') // 12
                        );
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/list-detail-sales-report-view.php';
            }

        } catch (Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
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

