<?php namespace Report;
/**
 * Intermediate Controller to print purchase history
 *
 * Class PurchaseHistoryPrinterController
 * @package Report
 */

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use PDO;
use DB;
use Config;
use TransactionDetail;

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
                })
                ->leftJoin("merchants as {$prefix}merchant", function ($join) {
                    $join->on("transactions.merchant_id", "=", "merchant.merchant_id");
                })
                ->leftJoin("merchants as {$prefix}retailer", function ($join) {
                    $join->on("transactions.retailer_id", "=", "retailer.merchant_id");
                })
                ->leftJoin("products", function ($join) {
                    $join->on("transaction_details.product_id", "=", "products.product_id");
                });


            $filters = [];

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
            OrbitInput::get('unit_price', function ($price) use ($transactions) {
                $transactions->where('price', '=', $price);
            });

            $this->prepareUnbufferedQuery();
            $_transactions = clone $transactions;

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
                    printf("%s,%s,%s,%s,%s,%s\n", '', $user->getFullName(), '', $pageTitle, '', '');
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
        } catch(\Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }
}

