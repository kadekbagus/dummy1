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
use Str;
use Product;
use Activity;
use DashboardAPIController as API;

class DashboardPrinterController extends DataPrinterController
{
    public function getTopProductPrintView()
    {
        try {
            $this->preparePDO();
            $mode = OrbitInput::get('export', 'print');

            $builder = API::create()->getBuilderFor('getTopProduct');
            $productReport = $builder->getBuilder();

            $productNames    = $builder->getOptions()->productNames;
            $productIds      = [];
            $summaryHeaders  = [];
            $rowNames     = ['created_at_date' => 'Date'];
            $rowFormatter = ['created_at_date' => array('Orbit\\Text', 'formatDate')];

            foreach ($productNames as $product)
            {
                array_push($productIds, $product->product_id);
                $summaryHeaders[$product->product_id] = $product->product_name;
                $rowNames[$product->product_id] = $product->product_name;
                $rowFormatter[$product->product_id] = false;
            }

            $total   = DB::table(DB::raw("({$productReport->toSql()}) as total_report"))
                ->mergeBindings($productReport)->count();

            $summary = $builder->getUnsorted()->first();

            $this->prepareUnbufferedQuery();
            $statement = $this->pdo->prepare($productReport->toSql());
            $statement->execute($productReport->getBindings());

            $rowCounter = 0;
            $pageTitle  = 'Orbit Top 20 Products';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'dashboard-list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s\n", $pageTitle);
                    printf(" ,\n");

                    printf(" ,Total Records,:,%s\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s,:,%s\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    $strFormat = ["%s"];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($strFormat, "%s");
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    $strFormat = implode(',', $strFormat) . "\n";

                    vprintf($strFormat, $rowHeader);
                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $u)
                        {
                            // array_push($current, $format ? $format($row->$name) : $row->$name);
                            // CSV use as it is from database so no formatting
                            array_push($current, $row->$name);
                        }
                        vprintf($strFormat, $current);
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

            $mode = OrbitInput::get('export', 'print');

            $builder = API::create()->getBuilderFor('getTopProductFamily');

            $categoryReport = $builder->getBuilder();
            $summary   = $builder->getUnsorted()->first();

            $this->prepareUnbufferedQuery();

            $statement  = $this->pdo->prepare($categoryReport->toSql());
            $statement->execute($categoryReport->getBindings());

            $total = DB::table(DB::raw("({$categoryReport->toSql()}) as total_report"))
                ->mergeBindings($categoryReport)->count();

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
                'created_at_date' => array('Orbit\\Text', 'formatDate'),
                '1' => false,
                '2' => false,
                '3' => false,
                '4' => false,
                '5' => false
            ];

            $rowCounter = 0;
            $pageTitle  = 'Orbit Product Family';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'dashboard-list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s\n", $pageTitle);
                    printf(" ,\n");

                    printf(" ,Total Records,:,%s\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s,:,%s\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    $strFormat = ["%s"];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($strFormat, "%s");
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    $strFormat = implode(',', $strFormat) . "\n";

                    vprintf($strFormat, $rowHeader);
                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $u)
                        {
                            // array_push($current, $format ? $format($row->$name) : $row->$name);
                            // CSV use as it is from database so no formatting
                            array_push($current, $row->$name);
                        }
                        vprintf($strFormat, $current);
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

            $builder = API::create()->getBuilderFor('getTopWidgetClick');

            $widgetReport = $builder->getBuilder();

            $total = DB::table(DB::raw("({$widgetReport->toSql()}) as total_report"))
                ->mergeBindings($widgetReport)->count();

            $summaryReport = $builder->getUnsorted();
            $summary  = $summaryReport->first();

            $statement = $this->pdo->prepare($widgetReport->toSql());
            $statement->execute($widgetReport->getBindings());

            $summaryHeaders = [
                'promotion'   => 'Promotion',
                'coupon'      => 'Coupon',
                'new_product' => 'New Product',
                'catalogue'   => 'Catalogue'
            ];

            $rowNames = [
                'created_at_date' => 'Date',
                'promotion'   => 'Promotion',
                'coupon'      => 'Coupon',
                'new_product' => 'New Product',
                'catalogue'   => 'Catalogue'
            ];

            $rowFormatter  = [
                'created_at_date' => array('Orbit\\Text', 'formatDate'),
                'promotion'   => false,
                'coupon'      => false,
                'new_product' => false,
                'catalogue'   => false
            ];

            $rowCounter = 0;
            $pageTitle  = 'Orbit Navigation Widgets Report';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'dashboard-list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s\n", $pageTitle);
                    printf(" ,\n");

                    printf(" ,Total Records,:,%s\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s,:,%s\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    $strFormat = ["%s"];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($strFormat, "%s");
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    $strFormat = implode(',', $strFormat) . "\n";

                    vprintf($strFormat, $rowHeader);
                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $u)
                        {
                            // array_push($current, $format ? $format($row->$name) : $row->$name);
                            // CSV use as it is from database so no formatting
                            array_push($current, $row->$name);
                        }
                        vprintf($strFormat, $current);
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

            $builder = API::create()->getBuilderFor('getUserLoginByDate');

            $users = $builder->getBuilder();

            $total = RecordCounter::create($users)->count();
            // Consider Last Page
            $summaryReport = $builder->getUnsorted();
            $summary   = $summaryReport->first();

            $this->prepareUnbufferedQuery();
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

            $rowFormatter  = [
                'last_login'             => array('Orbit\\Text', 'formatDate'),
                'new_user_count'         => false,
                'returning_user_count'   => false,
                'user_count'             => false,
            ];

            $rowCounter = 0;
            $pageTitle  = 'Orbit Customers';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'dashboard-list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s\n", $pageTitle);
                    printf(" ,\n");

                    printf(" ,Total Records,:,%s\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s,:,%s\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    $strFormat = ["%s"];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($strFormat, "%s");
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    $strFormat = implode(',', $strFormat) . "\n";

                    vprintf($strFormat, $rowHeader);
                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $u)
                        {
                            // array_push($current, $format ? $format($row->$name) : $row->$name);
                            // CSV use as it is from database so no formatting
                            array_push($current, $row->$name);
                        }
                        vprintf($strFormat, $current);
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

            $builder = API::create()->getBuilderFor('getUserByGender');

            $userReport = $builder->getBuilder();

            $totalReport = DB::table(DB::raw("({$userReport->toSql()}) as total_report"))
                ->mergeBindings($userReport);

            $total = $totalReport->count();

            $summaryReport = $builder->getUnsorted();
            $summary   = $summaryReport->first();

            $this->prepareUnbufferedQuery();
            $statement = $this->pdo->prepare($userReport->toSql());
            $statement->execute($userReport->getBindings());

            $summaryHeaders = [
                'Female'      => 'Female',
                'Male'        => 'Male',
                'Unspecified' => 'Unknown'
            ];

            $rowNames = [
                'created_at_date' => 'Date',
                'Male'            => 'Male',
                'Female'          => 'Female',
                'Unspecified'     => 'Unknown'
            ];

            $rowFormatter  = [
                'created_at_date' => array('Orbit\\Text', 'formatDate'),
                'Male'            => false,
                'Female'          => false,
                'Unspecified'     => false,
            ];

            $rowCounter = 0;
            $pageTitle  = 'Orbit Customer Type (Gender)';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'dashboard-list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s\n", $pageTitle);
                    printf(" ,\n");

                    printf(" ,Total Records,:,%s\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s,:,%s\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    $strFormat = ["%s"];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($strFormat, "%s");
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    $strFormat = implode(',', $strFormat) . "\n";

                    vprintf($strFormat, $rowHeader);
                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $u)
                        {
                            // array_push($current, $format ? $format($row->$name) : $row->$name);
                            // CSV use as it is from database so no formatting
                            array_push($current, $row->$name);
                        }
                        vprintf($strFormat, $current);
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
            $builder = API::create()->getBuilderFor('getUserByAge');

            $userReport = $builder->getBuilder();

            $totalReport = DB::table(DB::raw("({$userReport->toSql()}) as total_report"))
                ->mergeBindings($userReport);
            $total = $totalReport->count();

            $summaryReport = $builder->getUnsorted();
            $summary    = $summaryReport->first();

            $this->prepareUnbufferedQuery();

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

            $rowFormatter  = [
                'created_at_date' => array('Orbit\\Text', 'formatDate'),
                '15-20' => false,
                '20-25' => false,
                '25-30' => false,
                '30-35' => false,
                '35-40' => false,
                '40+' => false,
                'Unknown' => false
            ];

            $rowCounter = 0;
            $pageTitle  = 'Orbit Customer Type (Age)';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'dashboard-list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s\n", $pageTitle);
                    printf(" ,\n");

                    printf(" ,Total Records,:,%s\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s,:,%s\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    $strFormat = ["%s"];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($strFormat, "%s");
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    $strFormat = implode(',', $strFormat) . "\n";

                    vprintf($strFormat, $rowHeader);
                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $u)
                        {
                            // array_push($current, $format ? $format($row->$name) : $row->$name);
                            // CSV use as it is from database so no formatting
                            array_push($current, $row->$name);
                        }
                        vprintf($strFormat, $current);
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


            $builder = API::create()->getBuilderFor('getHourlyUserLogin');

            $activityReport = $builder->getBuilder();

            $totalReport   = DB::table(DB::raw("({$activityReport->toSql()}) as total_report"))
                ->mergeBindings($activityReport);

            $total = $totalReport->count();
            $summaryReport = $builder->getUnsorted();
            $summary  = $summaryReport->first();

            $this->prepareUnbufferedQuery();

            $statement = $this->pdo->prepare($activityReport->toSql());
            $statement->execute($activityReport->getBindings());

            $summaryHeaders = [];
            $rowNames       = ['created_at_date' => 'Date'];
            $rowFormatter   = ['created_at_date' => array('Orbit\\Text', 'formatDate')];

            for ($x=9; $x<23; $x++)
            {
                $name = sprintf("%s-%s", $x, $x+1);
                $summaryHeaders[$name] = $name;
                $rowNames[$name]       = $name;
                $rowFormatter[$name]   = false;
            }

            $rowCounter = 0;
            $pageTitle  = 'Orbit Customer Sign in Number';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'dashboard-list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s\n", $pageTitle);
                    printf(" ,\n");

                    printf(" ,Total Records,:,%s\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s,:,%s\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    $strFormat = ["%s"];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($strFormat, "%s");
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    $strFormat = implode(',', $strFormat) . "\n";

                    vprintf($strFormat, $rowHeader);
                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $u)
                        {
                            // array_push($current, $format ? $format($row->$name) : $row->$name);
                            // CSV use as it is from database so no formatting
                            array_push($current, $row->$name);
                        }
                        vprintf($strFormat, $current);
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

            $builder = API::create()->getBuilderFor('getUserConnectTime');

            $activityReport = $builder->getBuilder();

            $totalReport = DB::table(DB::raw("({$activityReport->toSql()}) as total_report"))
                ->mergeBindings($activityReport);
            $total = $totalReport->count();

            $summaryReport  = $builder->getUnsorted();
            $summary  = $summaryReport->first();

            $this->prepareUnbufferedQuery();

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
                '60+'  => '60+'
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
                '60+'  => '60+'
            ];

            $rowFormatter = [
                'created_at_date' => array('Orbit\\Text', 'formatDate'),
                '<5'    => false,
                '5-10'  => false,
                '10-20' => false,
                '20-30' => false,
                '30-40' => false,
                '40-50' => false,
                '50-60' => false,
                '60+'   => false
            ];

            $rowCounter = 0;
            $pageTitle  = 'Connecting Average Time';
            switch($mode)
            {
                case 'csv':
                    $filename   = 'dashboard-list-' . Str::slug($pageTitle) . '-' . date('D_M_Y_HiA') . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);

                    printf(" ,%s\n", $pageTitle);
                    printf(" ,\n");

                    printf(" ,Total Records,:,%s\n", $total);
                    foreach ($summaryHeaders as $name => $title)
                    {
                        printf(" ,%s,:,%s\n", $title, $summary->$name);
                    }

                    $rowHeader = ['No.'];
                    $strFormat = ["%s"];
                    foreach ($rowFormatter as $name => $i)
                    {
                        array_push($strFormat, "%s");
                        array_push($rowHeader, $rowNames[$name]);
                    }
                    $strFormat = implode(',', $strFormat) . "\n";

                    vprintf($strFormat, $rowHeader);
                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        $current = [++$rowCounter];
                        foreach ($rowFormatter as $name => $u)
                        {
                            // array_push($current, $format ? $format($row->$name) : $row->$name);
                            // CSV use as it is from database so no formatting
                            array_push($current, $row->$name);
                        }
                        vprintf($strFormat, $current);
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
}
