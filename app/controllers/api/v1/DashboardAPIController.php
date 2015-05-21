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
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
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

            OrbitInput::get('begin_date', function ($beginDate) use ($products) {
               $products->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($products) {
               $products->where('activities.created_at', '<=', $endDate);
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

            $productTotal = RecordCounter::create($_products)->count();
            $productList = $products->get();

            $data = new stdclass();
            $data->total_records = $productTotal;
            $data->returned_records = count($productList);
            $data->records = $productList;

            if ($productTotal === 0) {
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
     * GET - TOP Product Attribute
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `merchant_id`   (optional) - limit by merchant id
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getTopProductAttribute()
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

            OrbitInput::get('begin_date', function ($beginDate) use ($products) {
                $products->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($products) {
                $products->where('activities.created_at', '<=', $endDate);
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

            $productTotal = RecordCounter::create($_products)->count();
            $productList = $products->get();

            $data = new stdclass();
            $data->total_records = $productTotal;
            $data->returned_records = count($productList);
            $data->records = $productList;

            if ($productTotal === 0) {
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
     * GET - TOP Product Family
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `merchant_id`   (optional) - limit by merchant id
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
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

            $categories = Category::select(
                    "categories.category_level",
                    DB::raw("count(distinct {$tablePrefix}activities.activity_id) as view_count")
                )
                ->leftJoin("activities", function ($join) {
                    $join->on('activities.object_id', '=', 'categories.category_id');
                    $join->where('activities.activity_name', '=', 'view_category');
                })
                ->groupBy('categories.category_level');


            OrbitInput::get('merchant_id', function ($merchantId) use ($categories) {
                $categories->whereIn('categories.merchant_id', $this->getArray($merchantId));
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($categories) {
                $categories->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($categories) {
                $categories->where('activities.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_products = clone $categories;

            $categories->orderBy('view_count', 'desc');

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
            $categories->take($take);

            $categoryTotal = RecordCounter::create($_products)->count();
            $categoryList = $categories->get();

            $data = new stdclass();
            $data->total_records = $categoryTotal;
            $data->returned_records = count($categoryList);
            $data->records = $categoryList;

            if ($categoryTotal === 0) {
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
     * GET - TOP Widget Click
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `merchant_id`   (optional) - limit by merchant id
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getTopWidgetClick()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.gettopwidgetclick.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.gettopwidgetclick.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.gettopwidgetclick.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.gettopwidgetclick.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.gettopwidgetclick.after.authz', array($this, $user));

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

            Event::fire('orbit.dashboard.gettopwidgetclick.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.gettopwidgetclick.after.validation', array($this, $validator));

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

            $widgets = Widget::select(
                    "widgets.widget_type",
                    DB::raw("count(distinct {$tablePrefix}activities.activity_id) as click_count")
                )
                ->leftJoin('activities', function ($join) {
                    $join->on('activities.object_id', '=', 'widgets.widget_id');
                    $join->where('activities.activity_name', '=', 'widget_click');
                })
                ->groupBy('widgets.widget_type');


            OrbitInput::get('merchant_id', function ($merchantId) use ($widgets) {
                $widgets->whereIn('widgets.merchant_id', $this->getArray($merchantId));
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($widgets) {
                $widgets->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($widgets) {
                $widgets->where('activities.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_widgets = clone $widgets;

            $widgets->orderBy('click_count', 'desc');

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
            $widgets->take($take);

            $widgetTotal = RecordCounter::create($_widgets)->count();
            $widgetList = $widgets->get();

            $data = new stdclass();
            $data->total_records = $widgetTotal;
            $data->returned_records = count($widgetList);
            $data->records = $widgetList;

            if ($widgetTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.gettopwidgetclick.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.gettopwidgetclick.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.gettopwidgetclick.query.error', array($this, $e));

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
            Event::fire('orbit.dashboard.gettopwidgetclick.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.gettopwidgetclick.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP User By Date
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserLoginByDate()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getuserloginbydate.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getuserloginbydate.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getuserloginbydate.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getuserloginbydate.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.getuserloginbydate.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.getuserloginbydate.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getuserloginbydate.after.validation', array($this, $validator));

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

            $users = User::select(
                DB::raw("ifnull(date({$tablePrefix}activities.created_at), date({$tablePrefix}users.created_at)) as last_login"),
                DB::raw("count(distinct {$tablePrefix}users.user_id) as user_count"),
                DB::raw("(count(distinct {$tablePrefix}users.user_id) - count(distinct new_users.user_id)) as returning_user_count"),
                DB::raw("count(distinct new_users.user_id) as new_user_count")
            )
                ->leftJoin('activities', function ($join) {
                    $join->on('activities.user_id', '=', 'users.user_id');
                    $join->where('activities.activity_name', '=', 'login_ok');
                })
                ->leftJoin("users as new_users", function ($join) use ($tablePrefix) {
                    $join->on(DB::raw("new_users.user_id"), '=', 'users.user_id');
                    $join->on(DB::raw("date(new_users.created_at)"), '>=', DB::raw("ifnull(date({$tablePrefix}activities.created_at), date({$tablePrefix}users.created_at))"));
                })
                ->groupBy('last_login');

            $isReport = false;
            OrbitInput::get('is_report', function ($_isReport) use (&$isReport, $users, $tablePrefix) {
                $isReport = !!$_isReport;
            });


            OrbitInput::get('begin_date', function ($beginDate) use ($users) {
                $users->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($users) {
                $users->where('activities.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_users = clone $users;

            $users->orderBy('last_login', 'desc');

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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            if ($isReport)
            {
                $users->take($take)
                    ->skip($skip);

                $summaryReport = DB::table(DB::raw("({$_users->toSql()}) as total_report"))
                    ->mergeBindings($_users->getQuery())
                    ->select(
                        DB::raw("sum(new_user_count) as new_user_count"),
                        DB::raw("sum(returning_user_count) as returning_user_count"),
                        DB::raw("sum(user_count) as user_count")
                    );

                $userList  = $users->get();
                $summary   = $summaryReport->first();
            } else {
                $users->take($take);
                $userList  = $users->get();
                $summary   = null;
            }

            $userTotal = RecordCounter::create($_users)->count();

            $data = new stdclass();
            $data->total_records = $userTotal;
            $data->returned_records = count($userList);
            $data->summary = $summary;
            $data->records = $userList;

            if ($userTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getuserloginbydate.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getuserloginbydate.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getuserloginbydate.query.error', array($this, $e));

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
            Event::fire('orbit.dashboard.getuserloginbydate.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getuserloginbydate.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP User By Gender
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserByGender()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getuserbygender.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getuserbygender.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getuserbygender.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getuserbygender.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.getuserbygender.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.getuserbygender.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getuserbygender.after.validation', array($this, $validator));

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

            $users = User::select(
                    DB::raw("(
                        case {$tablePrefix}details.gender
                            when 'f' then 'Female'
                            when 'm' then 'Male'
                            else 'Unspecified'
                        end
                    ) as user_gender"),
                    DB::raw("count(distinct {$tablePrefix}users.user_id) as user_count")
                )
                ->leftJoin("user_details as {$tablePrefix}details", function ($join) {
                    $join->on('details.user_id', '=', 'users.user_id');
                })
                ->groupBy('details.gender');

            $isReport = false;
            OrbitInput::get('is_report', function ($_isReport) use (&$isReport, $users, $tablePrefix) {
                if ($_isReport)
                {
                    $users->addSelect(
                        DB::raw("date({$tablePrefix}users.created_at) as created_at_date")
                    );
                    $users->groupBy(['details.gender', 'created_at_date']);
                    $isReport = true;
                }
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($users) {
                $users->where('users.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($users) {
                $users->where('users.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_users = clone $users;

            $users->orderBy('user_count', 'desc');

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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });


            if ($isReport)
            {
                $defaultSelect = [
                    DB::raw("sum(case user_gender when 'Female' then user_count end) as 'Female'"),
                    DB::raw("sum(case user_gender when 'Male' then user_count end) as 'Male'"),
                    DB::raw("sum(case user_gender when 'Unspecified' then user_count end) as 'Unspecified'")
                ];

                $toSelect = array_merge($defaultSelect, [
                    DB::raw("created_at_date")
                ]);

                $userReportQuery = $_users->getQuery();
                $userReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                    ->mergeBindings($userReportQuery)
                    ->select($toSelect)
                    ->groupBy('created_at_date')
                    ->take($take)
                    ->skip($skip);

                $summaryReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                    ->mergeBindings($userReportQuery)
                    ->select($defaultSelect);

                $totalReport = DB::table(DB::raw("({$userReport->toSql()}) as total_report"))
                    ->mergeBindings($userReport);

                $userList  = $userReport->get();
                $userTotal = $totalReport->count();
                $summary   = $summaryReport->first();
            } else {
                $users->take($take);
                $userTotal = RecordCounter::create($_users)->count();
                $userList  = $users->get();
                $summary   = null;
            }

            $data = new stdclass();
            $data->total_records = $userTotal;
            $data->returned_records = count($userList);
            $data->summary = $summary;
            $data->records = $userList;

            if ($userTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getuserbygender.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getuserbygender.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getuserbygender.query.error', array($this, $e));

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
            Event::fire('orbit.dashboard.getuserbygender.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getuserbygender.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP User By Age
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param boolean `is_report`     (optional) - display graphical or tabular data
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserByAge()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getuserbyage.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getuserbyage.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getuserbyage.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getuserbyage.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.getuserbyage.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.getuserbyage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getuserbyage.after.validation', array($this, $validator));

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

            $calculateAge = "(date_format(now(), '%Y') - date_format({$tablePrefix}details.birthdate, '%Y') -
                    (date_format(now(), '00-%m-%d') < date_format({$tablePrefix}details.birthdate, '00-%m-%d')))";

            $users = User::select(
                DB::raw("(
                    case
                        when {$calculateAge} < 15 then 'Unknown'
                        when {$calculateAge} < 20 then '15-20'
                        when {$calculateAge} < 25 then '20-25'
                        when {$calculateAge} < 30 then '25-30'
                        when {$calculateAge} < 40 then '30-40'
                        when {$calculateAge} >= 40 then '40+'
                        else 'Unknown'
                    end) as user_age"),
                DB::raw("count(distinct {$tablePrefix}users.user_id) as user_count")
            )
                ->leftJoin("user_details as {$tablePrefix}details", function ($join) {
                    $join->on('details.user_id', '=', 'users.user_id');
                })
                ->groupBy('user_age');

            $isReport = false;
            OrbitInput::get('is_report', function ($_isReport) use (&$isReport, $users, $tablePrefix) {
                if ($_isReport)
                {
                    $users->addSelect(
                        DB::raw("date({$tablePrefix}users.created_at) as created_at_date")
                    );
                    $users->groupBy(['user_age', 'created_at_date']);
                    $isReport = true;
                }
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($users) {
                $users->where('users.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($users) {
                $users->where('users.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_users = clone $users;

            $users->orderBy('user_count', 'desc');

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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            if ($isReport)
            {
                $userReportQuery = $_users->getQuery();
                $defaultSelect = [
                    DB::raw("sum(case report.user_age when '15-20' then report.user_count end) as '15-20'"),
                    DB::raw("sum(case report.user_age when '20-25' then report.user_count end) as '20-25'"),
                    DB::raw("sum(case report.user_age when '25-30' then report.user_count end) as '25-30'"),
                    DB::raw("sum(case report.user_age when '30-35' then report.user_count end) as '30-35'"),
                    DB::raw("sum(case report.user_age when '35-40' then report.user_count end) as '35-40'"),
                    DB::raw("sum(case report.user_age when '40+' then report.user_count end) as '40+'"),
                    DB::raw("sum(case report.user_age when 'Unknown' then report.user_count end) as 'Unknown'")
                ];

                $toSelect = array_merge($defaultSelect, [
                    DB::raw('report.created_at_date as created_at_date')
                ]);

                $userReport    = DB::table(DB::raw("({$_users->toSql()}) as report"))
                    ->mergeBindings($userReportQuery)
                    ->select($toSelect)
                    ->groupBy('created_at_date')
                    ->orderBy('created_at_date', 'desc')
                    ->take($take)
                    ->skip($skip);

                $summaryReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                    ->mergeBindings($userReportQuery)
                    ->select($defaultSelect);

                $totalReport   = DB::table(DB::raw("({$userReport->toSql()}) as total_report"))
                    ->mergeBindings($userReport);

                $userList      = $userReport->get();
                $userTotal     = $totalReport->count();
                $summary       = $summaryReport->first();
            } else {
                $users->take($take);
                $userList  = $users->get();
                $userTotal = RecordCounter::create($_users)->count();
                $summary   = null;
            }

            $data = new stdclass();
            $data->total_records = $userTotal;
            $data->returned_records = count($userList);
            $data->summary = $summary;
            $data->records = $userList;

            if ($userTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getuserbyage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getuserbyage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getuserbyage.query.error', array($this, $e));

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
            Event::fire('orbit.dashboard.getuserbyage.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getuserbyage.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP Timed User Login
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param boolean `is_report`     (optional) - display graphical or tabular data
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getHourlyUserLogin()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.gettimeduserlogin.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.gettimeduserlogin.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.gettimeduserlogin.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.gettimeduserlogin.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.gettimeduserlogin.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.gettimeduserlogin.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.gettimeduserlogin.after.validation', array($this, $validator));

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

            $formatDate = "(date_format(created_at, '%H'))";

            $activities = Activity::select(
                    DB::raw("(
                        case
                            when {$formatDate} < 10 then '9-10'
                            when {$formatDate} < 11 then '10-11'
                            when {$formatDate} < 12 then '11-12'
                            when {$formatDate} < 13 then '12-13'
                            when {$formatDate} < 14 then '13-14'
                            when {$formatDate} < 15 then '14-15'
                            when {$formatDate} < 16 then '15-16'
                            when {$formatDate} < 17 then '16-17'
                            when {$formatDate} < 18 then '17-18'
                            when {$formatDate} < 19 then '18-19'
                            when {$formatDate} < 20 then '19-20'
                            when {$formatDate} < 21 then '20-21'
                            when {$formatDate} < 22 then '21-22'
                            else '21-22'
                        end) as time_range"),
                    DB::raw("count(distinct activity_id) as login_count")
                )
                ->where('activity_name', '=', 'login_ok')
                ->groupBy('time_range');

            $isReport = false;
            OrbitInput::get('is_report', function ($_isReport) use (&$isReport, $activities) {
               if ($_isReport)
               {
                   $activities->addSelect(
                       DB::raw('date(created_at) as created_at_date')
                   );
                   $activities->groupBy(['time_range', 'created_at_date']);
                   $isReport = true;
               }
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($activities) {
                $activities->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($activities) {
                $activities->where('activities.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_activities = clone $activities;

            $activities->orderBy('login_count', 'desc');

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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            if ($isReport)
            {
                $defaultSelect = [];

                for ($x=9; $x<23; $x++)
                {
                    $name = sprintf("%s-%s", $x, $x+1);
                    array_push(
                        $defaultSelect,
                        DB::raw("sum(case report.time_range when '{$name}' then report.login_count end) as '{$name}'")
                    );
                }

                $toSelect = array_merge($defaultSelect, [
                    DB::raw("report.created_at_date")
                ]);

                $activityReportQuery = $_activities->getQuery();
                $activityReport = DB::table(DB::raw("({$_activities->toSql()}) as report"))
                    ->mergeBindings($activityReportQuery)
                    ->select($toSelect)
                    ->groupBy('created_at_date')
                    ->orderBy('created_at_date', 'desc')
                    ->take($take)
                    ->skip($skip);

                $summaryReport = DB::table(DB::raw("({$_activities->toSql()}) as report"))
                    ->mergeBindings($activityReportQuery)
                    ->select($defaultSelect);

                $totalReport   = DB::table(DB::raw("({$activityReport->toSql()}) as total_report"))
                                    ->mergeBindings($activityReport);

                $activityList  = $activityReport->get();
                $activityTotal = $totalReport->count();
                $summary       = $summaryReport->first();
            } else {
                $activities->take($take);
                $activityList  = $activities->get();
                $activityTotal = RecordCounter::create($_activities)->count();
                $summary       = null;
            }

            $data = new stdclass();
            $data->total_records = $activityTotal;
            $data->returned_records = count($activityList);
            $data->records = $activityList;
            $data->summary = $summary;

            if ($activityTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.gettimeduserlogin.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.gettimeduserlogin.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.gettimeduserlogin.query.error', array($this, $e));

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
            Event::fire('orbit.dashboard.gettimeduserlogin.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.gettimeduserlogin.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - User Connect Time
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param boolean `is_report`     (optional) - display graphical or tabular data
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserConnectTime()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getuserconnecttime.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getuserconnecttime.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getuserconnecttime.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getuserconnecttime.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.getuserconnecttime.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.getuserconnecttime.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getuserconnecttime.after.validation', array($this, $validator));

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

            $userActivities = Activity::select(
                    DB::raw("
                        timestampdiff(
                            MINUTE,
                            min(case activity_name when 'login_ok' then created_at end),
                            max(case activity_name when 'logout_ok' then created_at end)
                        ) as minute_connect
                    "),
                    DB::raw('date(created_at) as activity_date'),
                    DB::raw('count(distinct user_id) as user_count')
                )
                ->groupBy(['user_id', 'activity_date']);


            $activities = DB::table(DB::raw("({$userActivities->toSql()}) as {$tablePrefix}timed"))
                            ->select(
                                DB::raw("
                                    case
                                        when {$tablePrefix}timed.minute_connect < 5 then '<5'
                                        when {$tablePrefix}timed.minute_connect < 10 then '5-10'
                                        when {$tablePrefix}timed.minute_connect < 20 then '10-20'
                                        when {$tablePrefix}timed.minute_connect < 30 then '20-30'
                                        when {$tablePrefix}timed.minute_connect < 40 then '30-40'
                                        when {$tablePrefix}timed.minute_connect < 50 then '40-50'
                                        when {$tablePrefix}timed.minute_connect < 60 then '50-60'
                                        when {$tablePrefix}timed.minute_connect >= 60 then '60+'
                                        else 'Unrecorded'
                                    end as connect_time_range
                                "),
                                DB::raw("sum(ifnull({$tablePrefix}timed.user_count, 0)) as total_user")
                            );


            $averageQuery = clone $activities;

            $activities->groupBy('connect_time_range');

            OrbitInput::get('begin_date', function ($beginDate) use ($activities) {
                $activities->where('timed.activity_date', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($activities) {
                $activities->where('timed.activity_date', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_activities = clone $activities;

            $averageQuery->select(
                DB::raw("avg(case when {$tablePrefix}timed.minute_connect < 60 then {$tablePrefix}timed.minute_connect else 60 end) as average_time_connect")
            );

            $activities->orderBy('total_user', 'desc');

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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $activities->take($take);

            $activityList  = [];
            OrbitInput::get('detail_report', function($u) use (&$activityList, $activities) {
                $activityList = $activities->get();
            });

            $data = new stdclass();
            $data->total_records = count($activityList);
            $data->returned_records = count($activityList);
            $data->records = $activityList;

            if (count($activityList) === 0) {
                $data->average_time_connect = $averageQuery->first()->average_time_connect;
                $data->records = NULL;
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getuserconnecttime.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getuserconnecttime.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getuserconnecttime.query.error', array($this, $e));

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
            Event::fire('orbit.dashboard.getuserconnecttime.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getuserconnecttime.before.render', array($this, &$output));

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
