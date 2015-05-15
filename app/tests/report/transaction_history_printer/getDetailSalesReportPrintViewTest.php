<?php
/**
 * PHP Unit Test for TransactionHistroyAPIController#getDetailSalesReport
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class getDetailReportPrintViewTest extends TestCase
{
    private $baseUrl = '/printer/consumer-transaction-history/detail-sales-report';

    protected $transactions;
    protected $merchant;
    protected $authData;
    protected $useIntermediate = true;
    protected $faker;

    public static function prepareDatabase()
    {
        $faker    = \Faker\Factory::create();
        $authData = Factory::create('apikey_super_admin');
        $merchant = Factory::create('Merchant', ['user_id' => $authData->user_id]);
        $retailer = Factory::create('Retailer', ['user_id' => $merchant->user_id, 'parent_id' => $merchant->merchant_id]);

        $cashier_1 = Factory::create('User', ['user_firstname' => 'Cashier001', 'user_lastname' => 'Jakarta']);

        $transactions = Factory::times(5)->create('Transaction', [
            'merchant_id' => $merchant->merchant_id,
            'retailer_id' => $retailer->merchant_id,
            'customer_id' => $authData->user_id,
            'cashier_id'  => $cashier_1->user_id
        ]);

        foreach ($transactions as $transaction) {
            $detail = Factory::create('TransactionDetail', ['transaction_id' => $transaction->transaction_id]);

            Factory::create('TransactionDetailTax', [
                'tax_name'  => 'VAT',
                'tax_value' => 0.1000,
                'total_tax' => 0.1 * $detail->price,
                'transaction_id' => $detail->transaction_id,
                'transaction_detail_id' => $detail->transaction_detail_id
            ]);

            Factory::create('TransactionDetailTax', [
                'tax_name'  => 'Services',
                'tax_value' => 0.0500,
                'total_tax' => 0.05 * $detail->price,
                'transaction_id' => $detail->transaction_id,
                'transaction_detail_id' => $detail->transaction_detail_id
            ]);
        }

        static::addData('authData', $authData);
        static::addData('transactions', $transactions);
        static::addData('faker', $faker);
        static::addData('merchant', $merchant);
    }

    public function testOK_get_transaction_filtered_by_date_filter()
    {
        $headerNum = 6;
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", $response));
            return $response;
        };

        $response = $makeRequest([
            'purchase_date_begin' => $this->faker->dateTimeBetween('-2days', '-1day')->format("Y-m-d H:i:s")
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));


        // Date to before all records
        $response = $makeRequest([
            'purchase_date_end' => $this->faker->dateTimeBetween('-2days', '-1days')->format("Y-m-d H:i:s")
        ]);

        $this->assertResponseOk();

        $this->assertSame(0 + $headerNum, count($response));

        // Date from and to combined
        $response = $makeRequest([
            'purchase_date_begin' => $this->faker->dateTimeBetween('-2days', '-1days')->format("Y-m-d H:i:s"),
            'purchase_date_end' => $this->faker->dateTimeBetween('+1days', '+2days')->format("Y-m-d H:i:s")
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));
    }

    public function testOK_get_transactions_filtered_with_payment_method_transaction_id()
    {
        $headerNum = 6;
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", $response));
            return $response;
        };

        $response = $makeRequest([
            'payment_method' => $this->transactions[0]->payment_method
        ]);

        $this->assertResponseOk();

        $this->assertSame(1 + $headerNum, count($response));


        $response = $makeRequest([
            'transaction_id' => $this->transactions[0]->transaction_id
        ]);

        $this->assertResponseOk();

        $this->assertSame(1 + $headerNum, count($response));
    }

    public function testOK_get_transaction_with_cashier_name_filter()
    {
        $headerNum = 6;
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", $response));
            return $response;
        };

        $response = $makeRequest([
            'cashier_name_like' => 'Cashier'
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));


        $response = $makeRequest([
            'cashier_name_like' => 'Jakarta'
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));
    }

    public function testOK_get_transaction_with_customer_name()
    {
        $headerNum = 6;
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", $response));
            return $response;
        };

        $response = $makeRequest([
            'customer_name_like' => $this->authData->user->user_firstname
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));

        $response = $makeRequest([
            'customer_name_like' => $this->authData->user->user_lastname
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));

        $response = $makeRequest([
            'customer_name_like' => $this->authData->user->user_lastname
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));
    }

    public function testOK_get_transactions_filtered_with_product()
    {
        $product = null;
        foreach ($this->transactions as $i=>$transaction)
        {
            $product = Factory::create('Product', ['product_name' => "Transactional {$i}"]);
            $detail  = Factory::create('TransactionDetail',  [
                'transaction_id' => $transaction->transaction_id,
                'product_id'     => $product->product_id,
                'product_name'   => $product->product_name,
                'price'          => $product->price,
                'upc'            => $product->upc_code
            ]);
            Factory::create('TransactionDetailTax', [
                'tax_name'  => 'VAT',
                'tax_value' => 0.1000,
                'total_tax' => 0.1 * $detail->price,
                'transaction_id' => $detail->transaction_id,
                'transaction_detail_id' => $detail->transaction_detail_id
            ]);

            Factory::create('TransactionDetailTax', [
                'tax_name'  => 'Services',
                'tax_value' => 0.0500,
                'total_tax' => 0.05 * $detail->price,
                'transaction_id' => $detail->transaction_id,
                'transaction_detail_id' => $detail->transaction_detail_id
            ]);
        }

        $headerNum = 6;
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", $response));
            return $response;
        };

        $response = $makeRequest([
            'upc_code' => $product->upc_code
        ]);

        $this->assertResponseOk();

        $this->assertSame(1 + $headerNum, count($response));

        $response = $makeRequest([
            'product_name_like' => 'Transactional'
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));
    }



    public function testOK_get_print_product_list_without_additional_parameters()
    {
        $makeRequest = function ($getData) {
            $_GET                    = array_merge($_GET, $getData);
            $_GET['user_id']         = $this->authData->user_id;
            $_GET['orbit_session']   = $this->session->getSessionId();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            return $response;
        };
        $response = $makeRequest(['export' => 'print']);

        $this->assertResponseOk();
    }
}
