<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Product;

class ProductPrinterController extends DataPrinterController
{
    public function getProductPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $now = date('Y-m-d H:i:s');


        $products = Product::excludeDeleted()
                            ->allowedForUser($user)
                            ->select('products.*', DB::raw('CASE WHEN (new_from <= "'.$now.'" AND new_from != "0000-00-00 00:00:00") AND (new_until >= "'.$now.'" OR new_until = "0000-00-00 00:00:00") THEN "Yes" ELSE "No" END AS is_new'));

        // Check the value of `with_params` argument
        OrbitInput::get('with_params', function ($withParams) use ($products) {
            if (isset($withParams['variant.exclude_default'])) {
                if ($withParams['variant.exclude_default'] === 'yes') {
                    Config::set('model:product.variant.exclude_default', 'yes');
                }
            }

            if (isset($withParams['variant.include_transaction_status'])) {
                if ($withParams['variant.include_transaction_status'] === 'yes') {
                    Config::set('model:product.variant.include_transaction_status', 'yes');
                }
            }
        });

        // Filter product by Ids
        OrbitInput::get('product_id', function ($productIds) use ($products) {
            $products->whereIn('products.product_id', $productIds);
        });

        // Filter product by merchant Ids
        OrbitInput::get('merchant_id', function ($merchantIds) use ($products) {
            $products->whereIn('products.merchant_id', $merchantIds);
        });

        // Filter product by product code
        OrbitInput::get('product_code', function ($product_code) use ($products) {
            $products->whereIn('products.product_code', $product_code);
        });

        // Filter product by name
        OrbitInput::get('product_name', function ($name) use ($products) {
            $products->whereIn('products.product_name', $name);
        });

        // Filter product by name pattern
        OrbitInput::get('product_name_like', function ($name) use ($products) {
            $products->where('products.product_name', 'like', "%$name%");
        });

        // Filter product by short description
        OrbitInput::get('short_description', function ($short_description) use ($products) {
            $products->whereIn('products.short_description', $short_description);
        });

        // Filter product by short description pattern
        OrbitInput::get('short_description_like', function ($short_description) use ($products) {
            $products->where('products.short_description', 'like', "%$short_description%");
        });

        // Filter product by long description
        OrbitInput::get('long_description', function ($long_description) use ($products) {
            $products->whereIn('products.long_description', $long_description);
        });

        // Filter product by long description pattern
        OrbitInput::get('long_description_like', function ($long_description) use ($products) {
            $products->where('products.long_description', 'like', "%$long_description%");
        });

        // Filter product by status
        OrbitInput::get('status', function ($status) use ($products) {
            $products->whereIn('products.status', $status);
        });

        // Filter product by merchant_tax_id1
        OrbitInput::get('merchant_tax_id1', function ($merchant_tax_id1) use ($products) {
            $products->whereIn('products.merchant_tax_id1', $merchant_tax_id1);
        });

        // Filter product by merchant_tax_id2
        OrbitInput::get('merchant_tax_id2', function ($merchant_tax_id2) use ($products) {
            $products->whereIn('products.merchant_tax_id2', $merchant_tax_id2);
        });

        // Filter product by retailer_ids
        OrbitInput::get('retailer_ids', function($retailerIds) use ($products) {
            $products->whereHas('retailers', function($q) use ($retailerIds) {
                $q->whereIn('product_retailer.retailer_id', $retailerIds);
            });
        });

        // Filter product by current retailer
        OrbitInput::get('is_current_retailer_only', function ($is_current_retailer_only) use ($products) {
            if ($is_current_retailer_only === 'Y') {
                $retailer_id = Setting::where('setting_name', 'current_retailer')->first();
                if (! empty($retailer_id)) {
                    $products->whereHas('retailers', function($q) use ($retailer_id) {
                                $q->where('product_retailer.retailer_id', $retailer_id->setting_value);
                            });
                }
            }
        });

        $_products = clone $products;

        $totalRec = RecordCounter::create($_products)->count();

        $this->prepareUnbufferedQuery();

        $sql = $products->toSql();
        $binds = $products->getBindings();
       // print_r($binds);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        switch ($mode) {
            case 'csv':
                $filename = 'product-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                printf("%s,%s,%s,%s,%s,%s\n", '', 'SKU Number', 'Barcode', 'Name', 'Price USD','');
                printf("%s,%s,%s,%s,%s,%s\n", '', '', '', '', '','');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                        printf("%s,%s,%s,%s,%s,%s\n", '', $row->product_code, $row->upc_code, $row->product_name, $row->price, '');
                }
                break;

            case 'print':
            default:
                require app_path() . '/views/printer/list-product-view.php';
        }
    }



    public function getRetailerInfo()
    {
        try {
            $retailer_id = Config::get('orbit.shop.id');
            $retailer = \Retailer::with('parent')->where('merchant_id', $retailer_id)->first();

            return $retailer;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
    }
}