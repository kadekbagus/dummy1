<?php
/**
 * API to display several dashboard informations
 * Class DashboardAPIController
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class DashboardAPIController extends ControllerAPI
{
    /**
     * GET - TOP Product
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `merchant_id`   (optional) - limit by merchant id
     * @return Illuminate\Support\Facades\Response
     */
    public function getTopProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.gettopproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.gettopproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.gettopproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.gettopproduct.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.gettopproduct.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $merchantId = OrbitInput::get('merchant_id');
            $validator = Validator::make(
                array(
                    'merchant_id' => $merchantId,
                    'take' => $take
                ),
                array(
                    'merchant_id' => 'required|array|min:0',
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.gettopproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.gettopproduct.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.product.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.product.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            $products = Product::select(
                            "products.product_id",
                            "products.product_code",
                            "products.product_name",
                            DB::raw("count(distinct {$tablePrefix}activities.activity_id) as view_count")
                        )
                        ->leftJoin("activities", function ($join) {
                            $join->on('products.product_id', '=', 'activities.product_id');
                            $join->where('activities.activity_name', '=', 'view_product');
                        })
                        ->groupBy('products.product_id');


            OrbitInput::get('merchant_id', function ($merchantId) use ($products) {
               $products->whereIn('products.merchant_id', $this->getArray($merchantId));
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_products = clone $products;

            $products->orderBy('view_count', 'desc');

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $products->take($take);

            $totalCoupons = RecordCounter::create($_products)->count();
            $listOfCoupons = $products->get();

            $data = new stdclass();
            $data->total_records = $totalCoupons;
            $data->returned_records = count($listOfCoupons);
            $data->records = $listOfCoupons;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.gettopproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.gettopproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.gettopproduct.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            $httpCode = 500;
            Event::fire('orbit.dashboard.gettopproduct.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.gettopproduct.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP Product Family
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `merchant_id`   (optional) - limit by merchant id
     * @return Illuminate\Support\Facades\Response
     */
    public function getTopProductFamily()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.gettopproductfamily.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.gettopproductfamily.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.gettopproductfamily.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.gettopproductfamily.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.gettopproductfamily.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $merchantId = OrbitInput::get('merchant_id');
            $validator = Validator::make(
                array(
                    'merchant_id' => $merchantId,
                    'take' => $take
                ),
                array(
                    'merchant_id' => 'required|array|min:0',
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.gettopproductfamily.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.gettopproductfamily.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.product.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.product.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            $products = ProductVariant::select(
                    'attr.product_attribute_name as family_name',
                    'attr_val.value as family_value',
                    DB::raw("count(distinct {$tablePrefix}activities.activity_id) as view_count")
                )
                ->leftJoin('products', function ($join) {
                    $join->on('product_variants.product_id', '=', 'products.product_id');
                })
                ->leftJoin("product_attribute_values as {$tablePrefix}attr_val", function ($join) use ($tablePrefix) {
                    $join->on('attr_val.product_attribute_value_id', 'in', DB::raw("
                        (`{$tablePrefix}product_variants`.`product_attribute_value_id1`,
                         `{$tablePrefix}product_variants`.`product_attribute_value_id2`,
                         `{$tablePrefix}product_variants`.`product_attribute_value_id3`,
                         `{$tablePrefix}product_variants`.`product_attribute_value_id4`,
                         `{$tablePrefix}product_variants`.`product_attribute_value_id5`)"));
                })
                ->leftJoin("product_attributes as {$tablePrefix}attr", function ($join) {
                    $join->on('attr_val.product_attribute_id', '=', 'attr.product_attribute_id');
                })
                ->leftJoin("activities", function ($join) {
                    $join->on('products.product_id', '=', 'activities.product_id');
                    $join->where('activities.activity_name', '=', 'view_product');
                })
                ->whereNotNull('attr_val.value')
                ->groupBy('attr_val.product_attribute_id');


            OrbitInput::get('merchant_id', function ($merchantId) use ($products) {
                $products->whereIn('products.merchant_id', $this->getArray($merchantId));
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_products = clone $products;

            $products->orderBy('view_count', 'desc');

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $products->take($take);

            $totalCoupons = RecordCounter::create($_products)->count();
            $listOfCoupons = $products->get();

            $data = new stdclass();
            $data->total_records = $totalCoupons;
            $data->returned_records = count($listOfCoupons);
            $data->records = $listOfCoupons;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.gettopproductfamily.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.gettopproductfamily.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.gettopproductfamily.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            $httpCode = 500;
            Event::fire('orbit.dashboard.gettopproductfamily.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.gettopproductfamily.before.render', array($this, &$output));

        return $output;
    }

    /**
     * @param mixed $mixed
     * @return array
     */
    private function getArray($mixed)
    {
        $arr = [];
        if (is_array($mixed)) {
            $arr = array_merge($arr, $mixed);
        } else {
            array_push($arr, $mixed);
        }

        return $arr;
    }
}
