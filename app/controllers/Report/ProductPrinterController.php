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

        // Get the maximum record
        // $maxRecord = (int) Config::get('orbit.pagination.product.max_record');
        // if ($maxRecord <= 0) {
        //     // Fallback
        //     $maxRecord = (int) Config::get('orbit.pagination.max_record');
        //     if ($maxRecord <= 0) {
        //         $maxRecord = 20;
        //     }
        // }
        // // Get default per page (take)
        // $perPage = (int) Config::get('orbit.pagination.product.per_page');
        // if ($perPage <= 0) {
        //     // Fallback
        //     $perPage = (int) Config::get('orbit.pagination.per_page');
        //     if ($perPage <= 0) {
        //         $perPage = 20;
        //     }
        // }

        $now = date('Y-m-d H:i:s');
        // $products = Product::
        //                     allowedForUser($user)
        //                     ->JoinProductRetailer()
        //                     ->select('products.*', DB::raw('CASE WHEN (new_from <= "'.$now.'" AND new_from != "0000-00-00 00:00:00") AND (new_until >= "'.$now.'" OR new_until = "0000-00-00 00:00:00") THEN "Yes" ELSE "No" END AS is_new'));

        $products = Product::excludeDeleted('products')->select(
            "products.*","merchants.name as merchant_name",
            // DB::raw('count(distinct orbs_merchants.merchant_id) as merchant_count'),
            DB::raw('CASE WHEN (new_from <= "'.$now.'" AND new_from != "0000-00-00 00:00:00") AND (new_until >= "'.$now.'" OR new_until = "0000-00-00 00:00:00") THEN "Yes" ELSE "No" END AS is_new'),
            DB::raw("GROUP_CONCAT(`{$prefix}merchants`.`name`,' ',`{$prefix}merchants`.`city` SEPARATOR ' , ') as retailer_list")
        )->leftJoin('product_retailer', 'product_retailer.product_id', '=', 'products.product_id')
        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'product_retailer.retailer_id')
        ->groupBy('products.product_id');
                                 

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

        // Filter product by product_code
        OrbitInput::get('product_code', function ($product_code) use ($products) {
            $products->whereIn('products.product_code', $product_code);
        });

        // Filter product by product_code pattern
        OrbitInput::get('product_code_like', function ($product_code) use ($products) {
            $products->where('products.product_code', 'like', "%$product_code%");
        });

        // Filter product by upc_code
        OrbitInput::get('upc_code', function ($upc_code) use ($products) {
            $products->whereIn('products.upc_code', $upc_code);
        });

        // Filter product by upc_code pattern
        OrbitInput::get('upc_code_like', function ($upc_code) use ($products) {
            $products->where('products.upc_code', 'like', "%$upc_code%");
        });

        // Filter product by name
        OrbitInput::get('product_name', function ($name) use ($products) {
            $products->whereIn('products.product_name', $name);
        });

        // Filter product by name pattern
        OrbitInput::get('product_name_like', function ($name) use ($products) {
            $products->where('products.product_name', 'like', "%$name%");
        });

        // Filter product by price
        OrbitInput::get('price', function ($price) use ($products) {
            $products->whereIn('products.price', $price);
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

        // Filter product by created_at for begin_date
        OrbitInput::get('created_begin_date', function($begindate) use ($products)
        {
            $products->where('products.created_at', '>=', $begindate);
        });

        // Filter product by created_at for end_date
        OrbitInput::get('created_end_date', function($enddate) use ($products)
        {
            $products->where('products.created_at', '<=', $enddate);
        });

        // Filter product by is_new
        OrbitInput::get('is_new', function ($is_new) use ($products, $now) {
            $products->whereIn(DB::raw('CASE WHEN (new_from <= "'.$now.'" AND new_from != "0000-00-00 00:00:00") AND (new_until >= "'.$now.'" OR new_until = "0000-00-00 00:00:00") THEN "Yes" ELSE "No" END'), $is_new);
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

        // Add new relation based on request
        OrbitInput::get('with', function ($with) use ($products) {
            $with = (array) $with;
            foreach ($with as $relation) {
                if ($relation === 'family') {
                    $with = array_merge($with, array('category1', 'category2', 'category3', 'category4', 'category5'));
                    break;
                }
            }
            $products->with($with);
        });

        $_products = clone $products;

        // Get the take args
        // $take = $perPage;
        // OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
        //     if ($_take > $maxRecord) {
        //         $_take = $maxRecord;
        //     }
        //     $take = $_take;

        //     if ((int)$take <= 0) {
        //         $take = $maxRecord;
        //     }
        // });
        // $products->take($take);

        // $skip = 0;
        // OrbitInput::get('skip', function ($_skip) use (&$skip, $products) {
        //     if ($_skip < 0) {
        //         $_skip = 0;
        //     }

        //     $skip = $_skip;
        // });
        // $products->skip($skip);

        // Default sort by
        $sortBy = 'products.product_name';
        // Default sort mode
        $sortMode = 'asc';

        OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
            // Map the sortby request to the real column name
            $sortByMapping = array(
                'registered_date'           => 'products.created_at',
                'product_id'                => 'products.product_id',
                'product_name'              => 'products.product_name',
                'product_sku'               => 'products.product_code',
                'product_code'              => 'products.product_code',
                'product_upc'               => 'products.upc_code',
                'product_price'             => 'products.price',
                'product_short_description' => 'products.short_description',
                'product_long_description'  => 'products.long_description',
                'product_is_new'            => 'is_new',
                'product_new_until'         => 'products.new_until',
                'product_merchant_id'       => 'products.merchant_id',
                'product_status'            => 'products.status',
            );

            if (array_key_exists($_sortBy, $sortByMapping)) {
                $sortBy = $sortByMapping[$_sortBy];
            }
        });

        OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
            if (strtolower($_sortMode) !== 'asc') {
                $sortMode = 'desc';
            }
        });
        $products->orderBy($sortBy, $sortMode);

        $totalRec = RecordCounter::create($_products)->count();

        $this->prepareUnbufferedQuery();

        $sql = $products->toSql();
        $binds = $products->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        switch ($mode) {
            case 'csv':
                $filename = 'product-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Product List', '', '', '','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Product', $totalRec, '', '','','','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'SKU Number', 'Barcode', 'Name', 'Price', 'Retailer', 'New', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '','','','');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n", '', $row->product_code, $row->upc_code, $row->product_name, $row->price, '', $row->is_new, $row->status);
                }
                break;

            case 'print':
            default:
                $me = $this;
                $pageTitle = 'Product';
                require app_path() . '/views/printer/list-product-view.php';
        }
    }


}