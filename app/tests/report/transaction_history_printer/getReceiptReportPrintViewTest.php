<?php
/**
 * PHP Unit Test for TransactionHistroyPrinterController#getReceiptReportPrintView
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getReceiptReportPrintViewTest extends TestCase
{
    private $baseUrl = '/printer/consumer-transaction-history/receipt-report';
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
        $cashier_1 = Factory::create('User', ['user_firstname' => 'Cashier001', 'user_lastname' => 'Jakarta']);
        $transactions = Factory::times(5)->create('Transaction', [
            'merchant_id' => $merchant->merchant_id,
            'retailer_id' => $retailer->merchant_id,
            'customer_id' => $authData->user_id,
            'cashier_id'  => $cashier_1->user_id
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

    public function testOK_get_transaction_filtered_by_date_filter()
    {
        $headerNum = 5;
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();
            $_GET['apikey']          = $this->authData->api_key;
            $_GET['apitimestamp']    = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", rtrim($response, "\n")));
            return $response;
        };

        $response = $makeRequest([
            'purchase_date_begin' => $this->faker->dateTimeBetween('-2days', '-1day')->format('Y-m-d H:i:s')
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));


        // Date to before all records
        $response = $makeRequest([
            'purchase_date_end' => $this->faker->dateTimeBetween('-2days', '-1days')->format('Y-m-d H:i:s')
        ]);

        $this->assertResponseOk();

        $this->assertSame(0 + $headerNum, count($response));

        // Date from and to combined
        $response = $makeRequest([
            'purchase_date_begin' => $this->faker->dateTimeBetween('-2days', '-1days')->format('Y-m-d H:i:s'),
            'purchase_date_end' => $this->faker->dateTimeBetween('+1days', '+2days')->format('Y-m-d H:i:s')
        ]);

        $this->assertResponseOk();

        $this->assertSame(5 + $headerNum, count($response));
    }

    public function testOK_get_transactions_filtered_with_payment_method_transaction_id()
    {
        $headerNum = 5;
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();
            $_GET['apikey']          = $this->authData->api_key;
            $_GET['apitimestamp']    = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", rtrim($response, "\n")));
            return $response;
        };

        $response = $makeRequest([
            'payment_method' => $this->transactions[0]->payment_method
        ]);

        $this->assertResponseOk();

        $this->assertSame(1 + $headerNum, count($response));


        $response = $makeRequest([
            'transaction_id' => 2
        ]);

        $this->assertResponseOk();

        $this->assertSame(1 + $headerNum, count($response));
    }

    public function testOK_get_transaction_with_cashier_name_filter()
    {
        $headerNum = 5;
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();
            $_GET['apikey']          = $this->authData->api_key;
            $_GET['apitimestamp']    = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", rtrim($response, "\n")));
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
        $headerNum = 5;
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();
            $_GET['apikey']          = $this->authData->api_key;
            $_GET['apitimestamp']    = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", rtrim($response, "\n")));
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

    public function testOK_get_print_product_list_without_additional_parameters()
    {
        $makeRequest = function ($getData) {
            $_GET                    = array_merge($_GET, $getData);
            $_GET['user_id']         = $this->authData->user_id;
            $_GET['orbit_session']   = $this->session->getSessionId();
            $_GET['apikey']          = $this->authData->api_key;
            $_GET['apitimestamp']    = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

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
