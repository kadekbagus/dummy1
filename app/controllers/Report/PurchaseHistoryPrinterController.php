<?php namespace Report;
/**
 * Intermediate Controller to print purchase history
 *
 * Class PurchaseHistoryPrinterController
 * @package Report
 */

use Illuminate\Support\Facades\Response;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use PDO;
use DB;

class PurchaseHistoryPrinterController extends  DataPrinterController
{
    public function getProductListPrintView()
    {
        try {
            $this->preparePDO();
            $prefix = DB::getTablePrefix();

            $mode = OrbitInput::get('export', 'print');
            $user = $this->loggedUser;
            $now  = date('Y-m-d H:i:s');

            $query = "SELECT
              p.product_name,
              d.price,
              d.created_at,
              d.quantity,
              t.merchant_id,
              t.retailer_id,
              m.name AS merchant_name,
              r.name AS retailer_name FROM
              {$prefix}transaction_details AS d
              LEFT OUTER JOIN {$prefix}transactions AS t ON t.transaction_id = d.transaction_id
              LEFT OUTER JOIN {$prefix}merchants    AS m ON t.merchant_id    = m.merchant_id
              LEFT OUTER JOIN {$prefix}merchants    AS r ON t.retailer_id    = r.merchant_id
              LEFT OUTER JOIN {$prefix}products     AS p ON d.product_id     = p.product_id
              WHERE t.status = 'paid'";

            $filters = [];

            // Product Name Filter
            OrbitInput::get('product_name', function ($productName) use (&$query, $filters) {
                $query .= " AND p.product_name = '{$productName}'";
            });

            // Product name like filter
            OrbitInput::get('product_name_like', function ($productNameLike) use (&$query, $filters) {
                $query .= " AND p.product_name LIKE '%{$productNameLike}%'";
            });

            OrbitInput::get('user_id', function($userId) use (&$query, $filters) {
                $query .= " AND t.customer_id = {$userId}";
            });

            OrbitInput::get('retailer_ids', function($retailerIds) use (&$query, $filters) {
                $retailerIds = implode(',', $retailerIds);
                $query .= " AND t.retailer_id IN ({$retailerIds})";
            });

            OrbitInput::get('merchant_ids', function($merchantIds) use (&$query) {
                $merchantIds = implode(',', $merchantIds);
                $query .= " AND t.merchant_id IN ({$merchantIds})";
            });

            // Filter by date from
            OrbitInput::get('purchase_date_begin', function ($dateBegin) use (&$query) {
                $query .= " AND t.created_at > '{$dateBegin}'";
            });

            // Filter by date to
            OrbitInput::get('purchase_date_end', function ($dateEnd) use (&$query) {
                $query .= " AND t.created_at < '{$dateEnd}'";
            });

            // Quantity filter
            OrbitInput::get('quantity', function ($quantity) use (&$query) {
                $query .= " AND d.quantity = '{$quantity}'";
            });

            // Unit Price filter
            OrbitInput::get('unit_price', function ($price) use (&$query) {
                $query .= " AND d.price = {$price}";
            });

            $this->prepareUnbufferedQuery();

            $statement  = $this->pdo->prepare(DB::raw($query));
            $statement->execute();
            $total      = DB::table(DB::raw("({$query}) as subquery"))->count();
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
            return Response::make($e->getMessage(), 500);
        }
    }
}

