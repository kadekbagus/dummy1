<?php
/**
 * PHP Unit Test for TransactionHistroyPrinterController#getProductListPrintView
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getProductListPrintViewTest extends TestCase
{
    private $baseUrl = '/printer/consumer-transaction-history/product-list';
    protected $transactions;
    protected $retailer;
    protected $merchant;
    protected $faker;
    protected $useIntermediate = true;

    public static function prepareDatabase()
    {
        $faker    = Faker::create();
        $authData = Factory::create('apikey_super_admin');
        $merchant = Factory::create('Merchant', ['user_id' => $authData->user_id]);
        $retailer = Factory::create('Retailer', ['user_id' => $merchant->user_id, 'parent_id' => $merchant->merchant_id]);
        $transactions = Factory::times(20)->create('Transaction', [
            'merchant_id' => $merchant->merchant_id,
            'retailer_id' => $retailer->merchant_id,
            'customer_id' => $authData->user_id
        ]);

        foreach ($transactions as $transaction) {
            Factory::create('TransactionDetail', [
                'transaction_id' => $transaction->transaction_id
            ]);
        }

        static::addData('transactions', $transactions);
        static::addData('merchant', $merchant);
        static::addData('retailer', $retailer);
        static::addData('authData', $authData);
        static::addData('faker', $faker);
    }

    public function testOK_get_product_list_without_additional_parameters()
    {
        $makeRequest = function ($getData) {
            $_GET                    = array_merge($_GET, $getData);
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
        $response = $makeRequest(['export' => 'csv']);

        $this->assertResponseOk();
        $this->assertSame(20 + 4, count($response));
    }



    public function testOK_get_transaction_filtered_by_date_filter()
    {
        $headerNum = 4;
        $makeRequest = function ($getData = []) {
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
            'purchase_date_begin' => $this->faker->dateTimeBetween('-2days', '-1day')->format('Y-m-d H:i:s')
        ]);

        $this->assertResponseOk();

        $this->assertSame(20 + $headerNum, count($response));


        // Date to before all records
        $response = $makeRequest([
            'purchase_date_end' => $this->faker->dateTimeBetween('-4days', '-3days')->format('Y-m-d H:i:s')
        ]);

        $this->assertResponseOk();

        $this->assertSame(0 + $headerNum, count($response));

        // Date from and to combined
        $response = $makeRequest([
            'purchase_date_begin' => $this->faker->dateTimeBetween('-2days', '-1days')->format('Y-m-d H:i:s'),
            'purchase_date_end' => $this->faker->dateTimeBetween('+1days', '+2days')->format('Y-m-d H:i:s')
        ]);

        $this->assertResponseOk();

        $this->assertSame(20 + $headerNum, count($response));
    }

    public function testOK_get_transactions_filtered_with_product_name()
    {
        for ($i=0; $i<5; $i++)
        {
            $product = Factory::create('Product', ['product_name' => "Transactional {$i}"]);
            Factory::create('TransactionDetail',  [
                'transaction_id' => $this->transactions[$i]->transaction_id,
                'product_id'     => $product->product_id
            ]);
        }

        $headerNum = 4;
        $makeRequest = function ($getData = []) {
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
            'product_name' => 'Transactional 1'
        ]);

        $this->assertResponseOk();

        $this->assertSame(1 + $headerNum, count($response));


        $response = $makeRequest([
            'product_name_like' => 'Transactional'
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));
    }

    public function testOK_get_transactions_filtered_with_retailer_name()
    {
        $retailer = Factory::create('Retailer', [
            'user_id' => $this->merchant->user_id,
            'parent_id' => $this->merchant->merchant_id,
            'name' => 'SomeRandom Retailer'
        ]);
        $transactions = Factory::times(5)->create('Transaction', [
            'merchant_id' => $this->merchant->merchant_id,
            'retailer_id' => $retailer->merchant_id,
            'customer_id' => $this->authData->user_id
        ]);

        foreach ($transactions as $transaction) {
            Factory::create('TransactionDetail', ['transaction_id' => $transaction->transaction_id]);
        }

        $headerNum = 4;
        $makeRequest = function ($getData = []) {
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
            'retailer_name' => 'SomeRandom Retailer'
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));


        $response = $makeRequest([
            'retailer_name_like' => 'SomeRandom'
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));
    }

    public function testOK_get_transaction_with_quantity_filter()
    {
        for ($i=0; $i<5; $i++)
        {
            $product = Factory::create('Product', ['product_name' => "Quantities {$i}"]);
            Factory::create('TransactionDetail',  [
                'transaction_id' => $this->transactions[$i]->transaction_id,
                'product_id'     => $product->product_id,
                'quantity'       => 10 + $i
            ]);
        }

        $headerNum = 4;
        $makeRequest = function ($getData) {
            $_GET                    = array_merge($_GET, $getData);
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
            'quantity' => 11
        ]);

        $this->assertResponseOk();

        $this->assertSame(1 + $headerNum, count($response));
    }

    public function testOK_get_transaction_with_price_filter()
    {

        foreach ($this->transactions as $i=>$transaction)
        {
            $product = Factory::create('Product', ['product_name' => "Price Based {$i}"]);
            Factory::create('TransactionDetail',  [
                'transaction_id' => $transaction->transaction_id,
                'product_id'     => $product->product_id,
                'price'          => $i * 1000
            ]);
        }

        $headerNum = 4;
        $makeRequest = function ($getData) {
            $_GET                    = array_merge($_GET, $getData);
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
            'unit_price' => 2000
        ]);

        $this->assertResponseOk();

        $this->assertSame(1 + $headerNum, count($response));
    }


    public function testOK_get_print_product_list_without_additional_parameters()
    {
        $makeRequest = function ($getData) {
            $_GET                    = array_merge($_GET, $getData);
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
