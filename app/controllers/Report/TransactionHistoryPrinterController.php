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
use Exception;
use TransactionHistoryAPIController as API;

class TransactionHistoryPrinterController extends  DataPrinterController
{
    public function getProductListPrintView()
    {
        try {
            $this->preparePDO();

            $mode = OrbitInput::get('export', 'print');
            $user = $this->loggedUser;

            $_GET['user_id'] = $user->user_id;
            $builder = API::create()->getBuilderFor('getProductList');

            $transactions = $builder->getBuilder();
            $_transactions = $builder->getUnsorted();

            $transactions->select([
                "products.product_name",
                "transaction_details.price",
                "transaction_details.quantity",
                "transactions.created_at",
                "transactions.merchant_id",
                "transactions.retailer_id",
                "merchant.name as merchant_name",
                "retailer.name as retailer_name"
            ]);

            $this->prepareUnbufferedQuery();

            $query      = $transactions->toSql();
            $bindings   = $transactions->getBindings();

            $statement  = $this->pdo->prepare($query);
            $statement->execute($bindings);

            $total      = RecordCounter::create($_transactions)->count();

            $summaryHeaders = [];
            $summary = [];

            $rowFormatter = [
                'product_name'  => false,
                'quantity'      => false,
                'retailer_name' => false,
                'price'      => array('Orbit\\Text', 'formatNumber'),
                'created_at' => array('Orbit\\Text', 'formatDate')
            ];

            $rowNames = [
                'product_name'  => 'Product Name',
                'quantity'      => 'Quantity',
                'retailer_name' => 'Retailer(s)',
                'price'      => 'Unit Price',
                'created_at' => 'Purchase Date'
            ];

            $rowCounter = 0;
            $pageTitle  = 'Purchase History';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HiA') . '.csv';
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

            $summaryFormatter = [
                'transactions_total' => array('Orbit\\Text', 'formatNumber')
            ];

            $rowNames = [
                'created_at_date' => 'Date',
                'created_at' =>  'Time',
                'transaction_id' =>  'Receipt Number',
                'total_to_pay' =>  'Total Value',
                'payment_type' => 'Payment Type',
                'customer_full_name' => 'Customer (if known)',
                'cashier_full_name' => 'Cashier'
            ];


            $rowFormatter = [
                'created_at_date' => array('Orbit\\Text', 'formatDate'),
                'created_at' =>  array('Orbit\\Text', 'formatTime'),
                'transaction_id' =>  false,
                'total_to_pay' =>  array('Orbit\\Text', 'formatNumber'),
                'payment_type' => false,
                'customer_full_name' => false,
                'cashier_full_name' => false
            ];

            $rowCounter = 0;
            $pageTitle  = 'Receipt Report';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HiA') . '.csv';
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

            $summaryFormatter = [
                'quantity_total' => false,
                'sub_total' => array('Orbit\\Text', 'formatNumber')
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
                'payment_type'   => 'Payment Type',
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
                'payment_type'         => false,
                'customer_user_email'  => false,
                'cashier_user_fullname' => false
            ];

            $rowCounter = 0;
            $pageTitle  = 'Detail Sales Report';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HiA') . '.csv';
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

