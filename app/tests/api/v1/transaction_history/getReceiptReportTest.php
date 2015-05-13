<?php
/**
 * PHP Unit Test for TransactionHistroyAPIController#getProductList
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class getReceiptReportListTest extends TestCase
{
    private $baseUrl = '/api/v1/consumer-transaction-history/receipt-list';

    protected $transactions;
    protected $merchant;
    protected $authData;
    protected $faker;
    protected $useTruncate = false;

    public function setUp()
    {
        parent::setUp();
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
            Factory::create('TransactionDetail', ['transaction_id' => $transaction->transaction_id]);
        }

        $this->authData     = $authData;
        $this->transactions = $transactions;
        $this->faker        = $faker;
        $this->merchant     = $merchant;
    }

    public function testOK_get_transaction_filtered_by_date_filter()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = $makeRequest([
            'purchase_date_begin' => $this->faker->dateTimeBetween('-2days', '-1day')
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(5, $response->data->total_records);
        $this->assertSame(5, $response->data->returned_records);


        // Date to before all records
        $response = $makeRequest([
            'purchase_date_end' => $this->faker->dateTimeBetween('-2days', '-1days')
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(0, $response->data->total_records);
        $this->assertSame(0, $response->data->returned_records);

        // Date from and to combined
        $response = $makeRequest([
            'purchase_date_begin' => $this->faker->dateTimeBetween('-2days', '-1days'),
            'purchase_date_end' => $this->faker->dateTimeBetween('+1days', '+2days')
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(5, $response->data->total_records);
        $this->assertSame(5, $response->data->returned_records);
    }

    public function testOK_get_transactions_filtered_with_payment_method_transaction_id()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = $makeRequest([
            'payment_method' => $this->transactions[0]->payment_method
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, $response->data->returned_records);


        $response = $makeRequest([
            'transaction_id' => 2
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, $response->data->returned_records);
    }

    public function testOK_get_transaction_with_cashier_name_filter()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = $makeRequest([
            'cashier_name_like' => 'Cashier'
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(5, $response->data->total_records);
        $this->assertSame(5, $response->data->returned_records);


        $response = $makeRequest([
            'cashier_name_like' => 'Jakarta'
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(5, $response->data->total_records);
        $this->assertSame(5, $response->data->returned_records);
    }

    public function testOK_get_transaction_with_customer_name()
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

        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['user_id']      = $this->authData->user_id;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = $makeRequest([
            'customer_name_like' => $this->authData->user->user_firstname
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(5, $response->data->total_records);
        $this->assertSame(5, $response->data->returned_records);

        $response = $makeRequest([
            'customer_name_like' => $this->authData->user->user_lastname
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(5, $response->data->total_records);
        $this->assertSame(5, $response->data->returned_records);

        $response = $makeRequest([
            'customer_name_like' => $this->authData->user->user_lastname
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(5, $response->data->total_records);
        $this->assertSame(5, $response->data->returned_records);
    }
}
