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
use Str;
use PDO;
use DB;
use Config;
use Transaction;
use TransactionDetail;
use Exception;
use TransactionHistoryAPIController as API;

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
                    $filename = 'transaction-product-list-' . date('d_M_Y_HiA') . '.csv';
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
                        printf("\n%s,%s,%s,%s,%s,%s", ++$rowCounter, $row->product_name, $row->quantity, $row->retailer_name, $row->price, $formatDate($row->created_at));
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

            $builders = API::create()->getBuilderFor('getReceiptReport');

            $transactions  = $builders->getBuilder();
            $_transactions = $builders->getUnsorted();

            $subTotalQuery    = $_transactions->toSql();
            $subTotalBindings = $_transactions->getQuery();
            $summary = DB::table(DB::raw("({$subTotalQuery}) as sub_total"))
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

            $summaryHeaders = [
                'transactions_total' => 'Total Sales'
            ];

            $rowNames = [
                'created_at_date' => 'Date',
                'created_at' =>  'Time',
                'transaction_id' =>  'Receipt Number',
                'total_to_pay' =>  'Total Value',
                'payment_method' => 'Payment Type',
                'customer_full_name' => 'Customer (if known)',
                'cashier_full_name' => 'Cashier'
            ];


            $rowFormatter = [
                'created_at_date' => array('Orbit\\Text', 'formatDate'),
                'created_at' =>  array('Orbit\\Text', 'formatTime'),
                'transaction_id' =>  false,
                'total_to_pay' =>  array('Orbit\\Text', 'formatNumber'),
                'payment_method' => false,
                'customer_full_name' => false,
                'cashier_full_name' => false
            ];

            $rowCounter = 0;
            $pageTitle  = 'Receipt Report';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HIA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s\n", $pageTitle);
                    printf(" ,\n");

                    printf(" ,Total Records,:,%s\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s,:,%s\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    $strFormat = ["%s"];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($strFormat, "%s");
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    $strFormat = implode(',', $strFormat) . "\n";

                    vprintf($strFormat, $rowHeader);
                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $u)
                        {
                            // array_push($current, $format ? $format($row->$name) : $row->$name);
                            // CSV use as it is from database so no formatting
                            array_push($current, $row->$name);
                        }
                        vprintf($strFormat, $current);
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/transaction-history-view.php';
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

            $builders = API::create()->getBuilderFor('getDetailSalesReport');

            $transactions  = $builders->getBuilder();
            $_transactions = $builders->getUnsorted();

            $query      = $transactions->toSql();
            $bindings   = $transactions->getBindings();

            $this->prepareUnbufferedQuery();

            $statement  = $this->pdo->prepare($query);
            $statement->execute($bindings);

            $total      = RecordCounter::create($_transactions)->count();

            // Consider last pages
            $subTotalQuery    = $_transactions->toSql();
            $subTotalBindings = $_transactions->getQuery();
            $summary = DB::table(DB::raw("({$subTotalQuery}) as sub_total"))
                ->mergeBindings($subTotalBindings)
                ->select([
                    DB::raw("sum(sub_total.quantity) as quantity_total"),
                    DB::raw("sum(sub_total.sub_total) as sub_total")
                ])->first();

            $summaryHeaders = [
                'quantity_total' => 'Total Quantity',
                'sub_total' => 'Total Sales'
            ];

            $rowNames = [
                'created_at'     => 'Date',
                'transaction_id' => 'Receipt Number',
                'product_sku'    => 'Product SKU',
                'product_name'   => 'Product Name',
                'quantity'       => 'Quantity',
                'price'          => 'Unit Price',
                'total_tax'      => 'Tax Value',
                'sub_total'      => 'Total',
                'payment_method' => 'Payment Type',
                'customer_user_email'   => 'Customer Email',
                'cashier_user_fullname' => 'Cashier'
            ];

            $rowFormatter = [
                'created_at'     => array('Orbit\\Text', 'formatDateTime'),
                'transaction_id' => false,
                'product_sku'    => false,
                'product_name'   => false,
                'quantity'       => false,
                'price'     => array('Orbit\\Text', 'formatNumber'),
                'total_tax' => array('Orbit\\Text', 'formatNumber'),
                'sub_total' => array('Orbit\\Text', 'formatNumber'),
                'payment_method'       => false,
                'customer_user_email'  => false,
                'cashier_user_fullname' => false
            ];

            $rowCounter = 0;
            $pageTitle  = 'Detail Sales Report';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HIA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s\n", $pageTitle);
                    printf(" ,\n");

                    printf(" ,Total Records,:,%s\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s,:,%s\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    $strFormat = ["%s"];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($strFormat, "%s");
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    $strFormat = implode(',', $strFormat) . "\n";

                    vprintf($strFormat, $rowHeader);
                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $u)
                        {
                            // array_push($current, $format ? $format($row->$name) : $row->$name);
                            // CSV use as it is from database so no formatting
                            array_push($current, $row->$name);
                        }
                        vprintf($strFormat, $current);
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/transaction-history-view.php';
            }
        } catch (Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }
    /**
     * Print Price friendly name.
     *
     * @param $price $price
     * @return string
     */
    public function printPrice($price)
    {
        $result = number_format($price, 2);
        $result .= chr(27);

        return $result;
    }
}

