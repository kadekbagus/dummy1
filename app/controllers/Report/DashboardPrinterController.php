<?php namespace Report;
/**
 * Class DashboardPrinterController
 * @package Report
 */

use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use DB;
use PDO;
use User;
use Product;
use Activity;

class DashboardPrinterController extends DataPrinterController
{
    public function getTopProductPrintView()
    {
        try {
            $this->preparePDO();
            $tablePrefix = DB::getTablePrefix();
            $mode = OrbitInput::get('export', 'print');

            $products = Product::select(
                "products.product_id",
                "products.product_code",
                "products.product_name",
                DB::raw("count(distinct {$tablePrefix}activities.activity_id) as view_count")
            )
                ->join("activities", function ($join) {
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

            $this->prepareUnbufferedQuery();

            $_products = clone $products;

            $_products->addSelect(
                DB::raw("date({$tablePrefix}activities.created_at) as created_at_date")
            )->groupBy('created_at_date');

            $dateFormatter = function ($date) {
                return date('Y-m-d', strtotime($date));
            };

            $productNames = $products->orderBy('view_count', 'desc')->take(20)->get();
            $defaultSelect = [];
            $productIds    = [];
            $summaryHeaders  = [];
            $rowNames     = ['created_at_date' => 'Date'];
            $rowFormatter = ['created_at_date' => $dateFormatter];
            $stringFormat = [];

            foreach ($productNames as $product)
            {
                array_push($productIds, $product->product_id);
                array_push($stringFormat, '%s');
                $summaryHeaders[$product->product_id] = $product->product_name;
                $rowNames[$product->product_id] = $product->product_name;
                $rowFormatter[$product->product_id] = false;
                array_push($defaultSelect, DB::raw("ifnull(sum(case product_id when {$product->product_id} then view_count end), 0) as '{$product->product_id}'"));
            }

            $stringFormat = implode(',', $stringFormat);

            $toSelect  = array_merge($defaultSelect, ['created_at_date']);

            $_products->whereIn('activities.product_id', $productIds);

            $productReportQuery = $_products->getQuery();
            $productReport = DB::table(DB::raw("({$_products->toSql()}) as report"))
                ->mergeBindings($productReportQuery)
                ->select($toSelect)
                ->whereIn('product_id', $productIds)
                ->groupBy('created_at_date');

            $_productReport = clone $productReport;

            $productReport->orderBy('created_at_date', 'desc');

            $total    = DB::table(DB::raw("({$_productReport->toSql()}) as total_report"))
                ->mergeBindings($_productReport)->count();

            $summary = DB::table(DB::raw("({$_products->toSql()}) as report"))
                ->mergeBindings($productReportQuery)
                ->select($defaultSelect)->first();

            $statement = $this->pdo->prepare($productReport->toSql());
            $statement->execute($productReport->getBindings());

            $rowCounter = 0;
            $pageTitle  = 'List Top Product Report';
            switch($mode)
            {
                case 'csv':
                    $filename = 'dashboard-list-user-connect-time-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(' , %s,'.str_replace('%s',' ', $stringFormat)."\n", $pageTitle);
                    printf(' , ,'.str_replace('%s',' ', $stringFormat)."\n");

                    printf(' , Total Records: %s'.str_replace('%s',' ', $stringFormat)."\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(' , %s: %s, '.str_replace('%s',' ', $stringFormat)."\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    vprintf("%s,%s,".$stringFormat."\n", $rowHeader);

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $format)
                        {
                            array_push($current, $format ? $format($row->$name) : $row->$name);
                        }
                        vprintf("%s,%s,".$stringFormat."\n", $current);
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/dashboard-view.php';
            }
        } catch(Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }

    public function getTopProductFamilyPrintView()
    {
        try {
            $this->preparePDO();
            $tablePrefix = DB::getTablePrefix();
            $mode = OrbitInput::get('export', 'print');

            $categories = Activity::select(
                "categories.category_level",
                DB::raw("count(distinct {$tablePrefix}activities.activity_id) as view_count"),
                DB::raw("date({$tablePrefix}activities.created_at) as created_at_date")
            )
                ->join("categories", function ($join) {
                    $join->on('activities.object_id', '=', 'categories.category_id');
                    $join->where('activities.activity_name', '=', 'view_category');
                })
                ->groupBy('categories.category_level', 'created_at_date');

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
            $_categories = clone $categories;

            $categories->orderBy('view_count', 'desc');

            $this->prepareUnbufferedQuery();

            $defaultSelect = [
                DB::raw("ifnull(sum(case category_level when 1 then view_count end), 0) as '1'"),
                DB::raw("ifnull(sum(case category_level when 2 then view_count end), 0) as '2'"),
                DB::raw("ifnull(sum(case category_level when 3 then view_count end), 0) as '3'"),
                DB::raw("ifnull(sum(case category_level when 4 then view_count end), 0) as '4'"),
                DB::raw("ifnull(sum(case category_level when 5 then view_count end), 0) as '5'")
            ];
            $toSelect = array_merge($defaultSelect, ['created_at_date']);

            $categoryReportQuery = $_categories->getQuery();
            $categoryReport = DB::table(DB::raw("({$_categories->toSql()}) as report"))
                ->mergeBindings($categoryReportQuery)
                ->select($toSelect)
                ->groupBy('created_at_date');

            $_categoryReport = clone $categoryReport;

            $categoryReport->orderBy('created_at_date', 'desc');

            $totalReport = DB::table(DB::raw("({$_categoryReport->toSql()}) as total_report"))
                ->mergeBindings($_categoryReport);


            $summaryReport = DB::table(DB::raw("({$_categories->toSql()}) as report"))
                ->mergeBindings($categoryReportQuery)
                ->select($defaultSelect);
            $summary  = $summaryReport->first();

            $pageTitle = 'Dashboard Top Product Family Report';

            $statement  = $this->pdo->prepare($categoryReport->toSql());
            $statement->execute($categoryReport->getBindings());

            $total      = $totalReport->count();
            $rowCounter = 0;

            $dateFormatter = function ($date)
            {
              return date('Y-m-d', strtotime($date));
            };

            $summaryHeaders = array(
                '1' => 'Family Level 1',
                '2' => 'Family Level 2',
                '3' => 'Family Level 3',
                '4' => 'Family Level 4',
                '5' => 'Family Level 5'
            );

            $rowNames  = array(
                'created_at_date' => 'Date',
                '1' => 'Family Level 1',
                '2' => 'Family Level 2',
                '3' => 'Family Level 3',
                '4' => 'Family Level 4',
                '5' => 'Family Level 5'
            );

            $rowFormatter  = [
                'created_at_date' => $dateFormatter,
                '1' => false,
                '2' => false,
                '3' => false,
                '4' => false,
                '5' => false
            ];

            $stringFormat = "%s,%s,%s,%s,%s";

            switch($mode)
            {
                case 'csv':
                    $filename = 'dashboard-list-user-connect-time-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(' , %s,'.str_replace('%s',' ', $stringFormat)."\n", $pageTitle);
                    printf(' , ,'.str_replace('%s',' ', $stringFormat)."\n");

                    printf(' , Total Records: %s'.str_replace('%s',' ', $stringFormat)."\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(' , %s: %s, '.str_replace('%s',' ', $stringFormat)."\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    vprintf("%s,%s,".$stringFormat."\n", $rowHeader);

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $format)
                        {
                            array_push($current, $format ? $format($row->$name) : $row->$name);
                        }
                        vprintf("%s,%s,".$stringFormat."\n", $current);
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/dashboard-view.php';
            }
        } catch(Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }

    public function getTopWidgetClickPrintView()
    {
        try {
            $this->preparePDO();
            $mode = OrbitInput::get('export', 'print');

            $now  = date('Y-m-d H:i:s');
            $tablePrefix = DB::getTablePrefix();

            $widgets = Activity::select(
                "widgets.widget_type",
                DB::raw("count(distinct {$tablePrefix}activities.activity_id) as click_count"),
                DB::raw("date({$tablePrefix}activities.created_at) as created_at_date")
            )
                ->join('widgets', function ($join) {
                    $join->on('activities.object_id', '=', 'widgets.widget_id');
                    $join->where('activities.activity_name', '=', 'widget_click');
                })
                ->groupBy('widgets.widget_type', 'created_at_date');

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

            $this->prepareUnbufferedQuery();

            $widgetReportQuery = $_widgets->getQuery();

            $defaultSelect = [
                DB::raw("ifnull(sum(case widget_type when 'coupon' then click_count end), 0) as 'coupon'"),
                DB::raw("ifnull(sum(case widget_type when 'promotion' then click_count end), 0) as 'promotion'"),
                DB::raw("ifnull(sum(case widget_type when 'new_product' then click_count end), 0) as 'new_product'"),
                DB::raw("ifnull(sum(case widget_type when 'catalogue' then click_count end), 0) as 'catalogue'")
            ];

            $toSelect     = array_merge($defaultSelect, ["created_at_date"]);
            $widgetReport = DB::table(DB::raw("({$_widgets->toSql()}) as report"))
                ->mergeBindings($widgetReportQuery)
                ->select($toSelect)
                ->groupBy('created_at_date');

            $_widgetReport = clone $widgetReport;

            $widgetReport->orderBy('created_at_date', 'desc');


            $totalReport = DB::table(DB::raw("({$_widgetReport->toSql()}) as total_report"))
                ->mergeBindings($_widgetReport);

            $total = $totalReport->count();

            $summaryReport = DB::table(DB::raw("({$_widgets->toSql()}) as report"))
                ->mergeBindings($widgetReportQuery)
                ->select($defaultSelect);
            $summary  = $summaryReport->first();

            $statement = $this->pdo->prepare($widgetReport->toSql());
            $statement->execute($widgetReport->getBindings());

            $pageTitle = 'Dashboard List Top Widget Click';

            $summaryHeaders = [
                'coupon'      => 'Coupon',
                'promotion'   => 'Promotion',
                'new_product' => 'New Product',
                'catalogue'   => 'Catalogue'
            ];

            $rowNames = [
                'created_at_date' => 'Date',
                'coupon'      => 'Coupon',
                'promotion'   => 'Promotion',
                'new_product' => 'New Product',
                'catalogue'   => 'Catalogue'
            ];

            $dateFormatter = function ($date) {
                return date('Y-m-d', strtotime($date));
            };

            $rowFormatter  = [
                'created_at_date' => $dateFormatter,
                'coupon'      => false,
                'promotion'   => false,
                'new_product' => false,
                'catalogue'   => false
            ];

            $rowCounter = 0;

            switch($mode)
            {
                case 'csv':
                    $filename = 'dashboard-list-top-widget-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s, , , \n", $pageTitle);
                    printf(" , , , , \n");

                    printf(" ,Total Records : %s, , , , , \n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s : %s, , , \n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    vprintf("%s,%s,%s,%s,%s\n", $rowHeader);

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $format)
                        {
                            array_push($current, $format ? $format($row->$name) : $row->$name);
                        }
                        vprintf("%s,%s,%s,%s,%s\n", $current);
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/dashboard-view.php';
            }
        } catch(Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }

    public function getUserLoginByDatePrintView()
    {
        try {
            $this->preparePDO();
            $mode = OrbitInput::get('export', 'print');

            $now  = date('Y-m-d H:i:s');
            $tablePrefix = DB::getTablePrefix();

            $users = Activity::select(
                DB::raw("ifnull(date({$tablePrefix}activities.created_at), date({$tablePrefix}users.created_at)) as last_login"),
                DB::raw("count(distinct {$tablePrefix}users.user_id) as user_count"),
                DB::raw("(count(distinct {$tablePrefix}users.user_id) - count(distinct new_users.user_id)) as returning_user_count"),
                DB::raw("count(distinct new_users.user_id) as new_user_count")
            )
                ->leftJoin('users', function ($join) {
                    $join->on('activities.user_id', '=', 'users.user_id');
                    $join->where('activities.activity_name', '=', 'login_ok');
                })
                ->leftJoin("users as new_users", function ($join) use ($tablePrefix) {
                    $join->on(DB::raw("new_users.user_id"), '=', 'users.user_id');
                    $join->on(DB::raw("date(new_users.created_at)"), '>=', DB::raw("ifnull(date({$tablePrefix}activities.created_at), date({$tablePrefix}users.created_at))"));
                })
                ->groupBy('last_login');

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

            $this->prepareUnbufferedQuery();

            $total = RecordCounter::create($_users)->count();

            // Consider Last Page
            $summaryReport = DB::table(DB::raw("({$_users->toSql()}) as total_report"))
                ->mergeBindings($_users->getQuery())
                ->select(
                    DB::raw("ifnull(sum(new_user_count), 0) as new_user_count"),
                    DB::raw("ifnull(sum(returning_user_count), 0) as returning_user_count"),
                    DB::raw("ifnull(sum(user_count), 0) as user_count")
                );
            $summary   = $summaryReport->first();

            $statement = $this->pdo->prepare($users->toSql());
            $statement->execute($users->getBindings());

            $summaryHeaders = [
                'new_user_count'       => 'New User',
                'returning_user_count' => 'Returning User',
                'user_count'           => 'Total User'
            ];

            $rowNames = [
                'last_login'           => 'Date',
                'new_user_count'       => 'New User',
                'returning_user_count' => 'Returning User',
                'user_count'           => 'Total User'
            ];

            $dateFormatter = function ($date) {
                return date('Y-m-d', strtotime($date));
            };

            $rowFormatter  = [
                'last_login'             => $dateFormatter,
                'new_user_count'         => false,
                'returning_user_count'   => false,
                'user_count'             => false,
            ];

            $rowCounter = 0;
            $pageTitle  = 'User Login History Report';

            switch($mode)
            {
                case 'csv':
                    $filename = 'dashboard-list-login-by-date-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s, , , \n", $pageTitle);
                    printf(" , , , , \n");

                    printf(" ,Total Records : %s, , , , , \n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s : %s, , , \n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    vprintf("%s,%s,%s,%s,%s\n", $rowHeader);

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $format)
                        {
                            array_push($current, $format ? $format($row->$name) : $row->$name);
                        }
                        vprintf("%s,%s,%s,%s,%s\n", $current);
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/dashboard-view.php';
            }
        } catch(Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }

    public function getUserByGenderPrintView()
    {
        try {
            $this->preparePDO();
            $mode = OrbitInput::get('export', 'print');
            $tablePrefix = DB::getTablePrefix();

            $users = User::select(
                DB::raw("(
                        case {$tablePrefix}details.gender
                            when 'f' then 'Female'
                            when 'm' then 'Male'
                            else 'Unspecified'
                        end
                    ) as user_gender"),
                DB::raw("count(distinct {$tablePrefix}users.user_id) as user_count"),
                DB::raw("date({$tablePrefix}users.created_at) as created_at_date")
            )
                ->leftJoin("user_details as {$tablePrefix}details", function ($join) {
                    $join->on('details.user_id', '=', 'users.user_id');
                })
                ->groupBy('details.gender', 'created_at_date');

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


            $this->prepareUnbufferedQuery();

            $defaultSelect = [
                DB::raw("ifnull(sum(case user_gender when 'Female' then user_count end), 0) as 'Female'"),
                DB::raw("ifnull(sum(case user_gender when 'Male' then user_count end), 0) as 'Male'"),
                DB::raw("ifnull(sum(case user_gender when 'Unspecified' then user_count end), 0) as 'Unspecified'")
            ];

            $toSelect = array_merge($defaultSelect, [
                DB::raw("created_at_date")
            ]);

            $userReportQuery = $_users->getQuery();
            $userReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                ->mergeBindings($userReportQuery)
                ->select($toSelect)
                ->groupBy('created_at_date');

            $_userReport = clone $userReport;

            $userReport->orderBy('created_at_date', 'desc');


            $totalReport = DB::table(DB::raw("({$_userReport->toSql()}) as total_report"))
                ->mergeBindings($_userReport);

            $total = $totalReport->count();

            $summaryReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                ->mergeBindings($userReportQuery)
                ->select($defaultSelect);
            $summary   = $summaryReport->first();

            $statement = $this->pdo->prepare($userReport->toSql());
            $statement->execute($userReport->getBindings());

            $summaryHeaders = [
                'Female'      => 'Female',
                'Male'        => 'Male',
                'Unspecified' => 'Unspecified'
            ];

            $rowNames = [
                'created_at_date' => 'Date',
                'Female'          => 'Female',
                'Male'            => 'Male',
                'Unspecified'     => 'Unspecified'
            ];

            $dateFormatter = function ($date) {
                return date('Y-m-d', strtotime($date));
            };

            $rowFormatter  = [
                'created_at_date' => $dateFormatter,
                'Female'          => false,
                'Male'            => false,
                'Unspecified'     => false,
            ];

            $rowCounter = 0;
            $pageTitle  = 'User By Gender Report';
            switch($mode)
            {
                case 'csv':
                    $filename = 'dashboard-list-user-by-gender-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s, , , \n", $pageTitle);
                    printf(" , , , , \n");

                    printf(" ,Total Records : %s, , , , , \n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s : %s, , , \n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    vprintf("%s,%s,%s,%s,%s\n", $rowHeader);

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $format)
                        {
                            array_push($current, $format ? $format($row->$name) : $row->$name);
                        }
                        vprintf("%s,%s,%s,%s,%s\n", $current);
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/dashboard-view.php';
            }
        } catch(Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }

    public function getUserByAgePrintView()
    {
        try {
            $this->preparePDO();
            $mode = OrbitInput::get('export', 'print');
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
                DB::raw("count(distinct {$tablePrefix}users.user_id) as user_count"),
                DB::raw("date({$tablePrefix}users.created_at) as created_at_date")
            )
                ->leftJoin("user_details as {$tablePrefix}details", function ($join) {
                    $join->on('details.user_id', '=', 'users.user_id');
                })
                ->groupBy('user_age', 'created_at_date');

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

            $this->prepareUnbufferedQuery();

            $userReportQuery = $_users->getQuery();
            $defaultSelect = [
                DB::raw("ifnull(sum(case report.user_age when '15-20' then report.user_count end), 0) as '15-20'"),
                DB::raw("ifnull(sum(case report.user_age when '20-25' then report.user_count end), 0) as '20-25'"),
                DB::raw("ifnull(sum(case report.user_age when '25-30' then report.user_count end), 0) as '25-30'"),
                DB::raw("ifnull(sum(case report.user_age when '30-35' then report.user_count end), 0) as '30-35'"),
                DB::raw("ifnull(sum(case report.user_age when '35-40' then report.user_count end), 0) as '35-40'"),
                DB::raw("ifnull(sum(case report.user_age when '40+' then report.user_count end), 0) as '40+'"),
                DB::raw("ifnull(sum(case report.user_age when 'Unknown' then report.user_count end), 0) as 'Unknown'")
            ];

            $toSelect = array_merge($defaultSelect, [
                DB::raw('report.created_at_date as created_at_date')
            ]);

            $userReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                ->mergeBindings($userReportQuery)
                ->select($toSelect)
                ->groupBy('created_at_date')
                ->orderBy('created_at_date', 'desc');

            $_userReport = clone $userReport;

            $userReport->orderBy('created_at_date', 'desc');

            $totalReport = DB::table(DB::raw("({$_userReport->toSql()}) as total_report"))
                ->mergeBindings($_userReport);

            $total = $totalReport->count();

            $summaryReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                ->mergeBindings($userReportQuery)
                ->select($defaultSelect);
            $summary    = $summaryReport->first();

            $statement = $this->pdo->prepare($userReport->toSql());
            $statement->execute($userReport->getBindings());

            $summaryHeaders = [
                '15-20' => '15-20',
                '20-25' => '20-25',
                '25-30' => '25-30',
                '30-35' => '30-35',
                '35-40' => '35-40',
                '40+' => '40+',
                'Unknown' => 'Unknown'
            ];

            $rowNames = [
                'created_at_date' => 'Date',
                '15-20' => '15-20',
                '20-25' => '20-25',
                '25-30' => '25-30',
                '30-35' => '30-35',
                '35-40' => '35-40',
                '40+' => '40+',
                'Unknown' => 'Unknown'
            ];

            $dateFormatter = function ($date) {
                return date('Y-m-d', strtotime($date));
            };

            $rowFormatter  = [
                'created_at_date' => $dateFormatter,
                '15-20' => false,
                '20-25' => false,
                '25-30' => false,
                '30-35' => false,
                '35-40' => false,
                '40+' => false,
                'Unknown' => false
            ];

            $rowCounter = 0;
            $pageTitle  = 'User By Gender Report';
            switch($mode)
            {
                case 'csv':
                    $filename = 'dashboard-list-user-by-age-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s, , , , , , , \n", $pageTitle);
                    printf(" , , , , , , , , \n");

                    printf(" ,Total Records : %s, , , , , , , \n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s : %s, , , , , , , \n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    vprintf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", $rowHeader);

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $format)
                        {
                            array_push($current, $format ? $format($row->$name) : $row->$name);
                        }
                        vprintf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", $current);
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/dashboard-view.php';
            }
        } catch(Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }

    public function getHourlyUserLoginPrintView()
    {
        try {
            $this->preparePDO();
            $mode = OrbitInput::get('export', 'print');
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
                DB::raw("count(distinct activity_id) as login_count"),
                DB::raw('date(created_at) as created_at_date')
            )
                ->where('activity_name', '=', 'login_ok')
                ->groupBy('time_range', 'created_at_date');

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

            $this->prepareUnbufferedQuery();

            $dateFormatter = function ($date) {
                return date('Y-m-d', strtotime($date));
            };

            $defaultSelect  = [];
            $summaryHeaders = [];
            $rowNames       = ['created_at_date' => 'Date'];
            $rowFormatter   = ['created_at_date' => $dateFormatter];

            for ($x=9; $x<23; $x++)
            {
                $name = sprintf("%s-%s", $x, $x+1);
                $summaryHeaders[$name] = $name;
                $rowNames[$name]       = $name;
                $rowFormatter[$name]   = false;
                array_push(
                    $defaultSelect,
                    DB::raw("ifnull(sum(case report.time_range when '{$name}' then report.login_count end), 0) as '{$name}'")
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
                ->orderBy('created_at_date', 'desc');

            $_activityReport = clone $activityReport;

            $activityReport->orderBy('created_at_date', 'desc');

            $totalReport   = DB::table(DB::raw("({$_activityReport->toSql()}) as total_report"))
                ->mergeBindings($_activityReport);

            $total = $totalReport->count();
            $summaryReport = DB::table(DB::raw("({$_activities->toSql()}) as report"))
                ->mergeBindings($activityReportQuery)
                ->select($defaultSelect);

            $summary  = $summaryReport->first();

            $statement = $this->pdo->prepare($activityReport->toSql());
            $statement->execute($activityReport->getBindings());

            $rowCounter = 0;
            $pageTitle  = 'Time Range User Login Report';
            switch($mode)
            {
                case 'csv':
                    $filename = 'dashboard-list-hourly-login-user-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s, , , , , , , \n", $pageTitle);
                    printf(" , , , , , , , , \n");

                    printf(" ,Total Records : %s, , , , , , , \n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s : %s, , , , , , , \n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    vprintf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", $rowHeader);

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $format)
                        {
                            array_push($current, $format ? $format($row->$name) : $row->$name);
                        }
                        vprintf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", $current);
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/dashboard-view.php';
            }
        } catch(Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }

    public function getUserConnectTimePrintView()
    {
        try {
            $this->preparePDO();
            $mode = OrbitInput::get('export', 'print');

            $tablePrefix = DB::getTablePrefix();

            $userActivities = Activity::select(
                DB::raw("
                        timestampdiff(
                            MINUTE,
                            min(case activity_name when 'login_ok' then created_at end),
                            max(case activity_name when 'logout_ok' then created_at end)
                        ) as minute_connect
                    "),
                DB::raw('date(created_at) as created_at_date'),
                DB::raw('count(distinct user_id) as user_count')
            )
                ->groupBy(['user_id', 'created_at_date']);


            $activities = DB::table(DB::raw("({$userActivities->toSql()}) as {$tablePrefix}timed"))
                ->select(
                    DB::raw("avg(
                                    case
                                        when {$tablePrefix}timed.minute_connect < 60 then {$tablePrefix}timed.minute_connect
                                        else 60
                                    end) as average_time_connect"
                    ),
                    DB::raw("
                            case
                                  when minute_connect < 5 then '<5'
                                  when minute_connect < 10 then '5-10'
                                  when minute_connect < 20 then '10-20'
                                  when minute_connect < 30 then '20-30'
                                  when minute_connect < 40 then '30-40'
                                  when minute_connect < 50 then '40-50'
                                  when minute_connect < 60 then '50-60'
                                  when minute_connect >= 60 then '60+'
                                  else 'Unrecorded'
                            end as time_range"),
                    DB::raw("sum(ifnull(user_count, 0)) as user_count"),
                    "created_at_date"
                )
                ->groupBy(['time_range', 'created_at_date']);

            OrbitInput::get('begin_date', function ($beginDate) use ($activities) {
                $activities->where('timed.created_at_date', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($activities) {
                $activities->where('timed.created_at_date', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_activities = clone $activities;

            $this->prepareUnbufferedQuery();

            $defaultSelect = [
                DB::raw("ifnull(sum(case time_range when '<5' then user_count end), 0) as '<5'"),
                DB::raw("ifnull(sum(case time_range when '5-10' then user_count end), 0) as '5-10'"),
                DB::raw("ifnull(sum(case time_range when '10-20' then user_count end), 0) as '10-20'"),
                DB::raw("ifnull(sum(case time_range when '20-30' then user_count end), 0) as '20-30'"),
                DB::raw("ifnull(sum(case time_range when '30-40' then user_count end), 0) as '30-40'"),
                DB::raw("ifnull(sum(case time_range when '40-50' then user_count end), 0) as '40-50'"),
                DB::raw("ifnull(sum(case time_range when '50-60' then user_count end), 0) as '50-60'"),
                DB::raw("ifnull(sum(case time_range when '60+' then user_count end), 0) as '60+'"),
                DB::raw("ifnull(sum(case time_range when 'Unrecorded' then user_count end), 0) as 'Unrecorded'")
            ];

            $toSelect = array_merge($defaultSelect, ['created_at_date']);
            $activityReport = DB::table(DB::raw("({$_activities->toSql()}) as report"))
                ->mergeBindings($_activities)
                ->select($toSelect)
                ->groupBy('created_at_date');

            $_activityReport = clone $activityReport;

            $activityReport->orderBy('created_at_date', 'desc');

            $totalReport = DB::table(DB::raw("({$_activityReport->toSql()}) as total_report"))
                ->mergeBindings($_activityReport);

            $total = $totalReport->count();

            $summaryReport  = DB::table(DB::raw("({$_activities->toSql()}) as report"))
                ->mergeBindings($_activities)
                ->select($defaultSelect);
            $summary  = $summaryReport->first();

            $statement = $this->pdo->prepare($activityReport->toSql());
            $statement->execute($activityReport->getBindings());

            $summaryHeaders = [
                '<5'  => '<5' ,
                '5-10' => '5-10',
                '10-20' => '10-20',
                '20-30' => '20-30',
                '30-40' => '30-40',
                '40-50' => '40-50',
                '50-60' => '50-60',
                '60+'  => '60+' ,
                'Unrecorded' => 'Unrecorded'
            ];

            $rowNames = [
                'created_at_date' => 'Date',
                '<5'  => '<5' ,
                '5-10' => '5-10',
                '10-20' => '10-20',
                '20-30' => '20-30',
                '30-40' => '30-40',
                '40-50' => '40-50',
                '50-60' => '50-60',
                '60+'  => '60+' ,
                'Unrecorded' => 'Unrecorded'
            ];

            $dateFormatter = function ($date) {
                return date('Y-m-d', strtotime($date));
            };

            $rowFormatter = [
                'created_at_date' => $dateFormatter,
                '<5'    => false,
                '5-10'  => false,
                '10-20' => false,
                '20-30' => false,
                '30-40' => false,
                '40-50' => false,
                '50-60' => false,
                '60+'   => false,
                'Unrecorded' => false
            ];

            $rowCounter = 0;
            $pageTitle  = 'Time Range User Login Report';
            switch($mode)
            {
                case 'csv':
                    $filename = 'dashboard-list-user-connect-time-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s, , , , , , , \n", $pageTitle);
                    printf(" , , , , , , , , \n");

                    printf(" ,Total Records : %s, , , , , , , \n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s : %s, , , , , , , \n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    vprintf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", $rowHeader);

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $format)
                        {
                            array_push($current, $format ? $format($row->$name) : $row->$name);
                        }
                        vprintf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", $current);
                    }
                    break;
                case 'print':
                default:
                    require app_path() . '/views/printer/dashboard-view.php';
            }
        } catch(Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
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

    /**
     * @param array $row
     * @param array $formatter\
     * @return array
     */
    private function getRow($row, $formatter)
    {
        return array_map(function($i) use ($row, $formatter) {
            $c = $formatter[$i];
            return $c ? $c($row->$i) : $row->$i;
        }, array_keys($formatter));
    }
}
