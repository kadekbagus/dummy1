<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use Response;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use User;
use Role;
use Orbit\Text as OrbitText;
use CashierAPIController as CashierAPI;

class CashierPrinterController extends DataPrinterController
{
    public function getCashierPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $now = date('Y-m-d H:i:s');

        // Available merchant to query
        $listOfMerchantIds = [];

        // Available retailer to query
        $listOfRetailerIds = [];

        // Builder object
        $joined = FALSE;
        $defaultWith = array('employee.retailers');

        // Include Relationship
        $with = $defaultWith;
        OrbitInput::get('with', function ($_with) use (&$with) {
            $with = array_merge($with, $_with);
        });

        $users = User::select(DB::raw($prefix . "users.*"), DB::raw($prefix . "user_employee.position as position"))
                        ->leftJoin("employees as {$prefix}user_employee", 'user_employee.user_id', '=', 'users.user_id')
                        ->excludeDeleted('users');

        // Filter user by Ids
        OrbitInput::get('user_ids', function ($userIds) use ($users) {
            $users->whereIn('users.user_id', $userIds);
        });

        // Filter user by Retailer Ids
        OrbitInput::get('retailer_ids', function ($retailerIds) use ($listOfMerchantIds, $joined) {
            // $joined = TRUE;
            // $users->employeeRetailerIds($retailerIds);
            $listOfRetailerIds = (array)$retailerIds;
        });

        // Filter user by Merchant Ids
        OrbitInput::get('merchant_ids', function ($merchantIds) use ($listOfMerchantIds, $joined) {
            // $joined = TRUE;
            // $users->employeeMerchantIds($retailerIds);
            $listOfMerchantIds = (array)$merchantIds;
        });

        // @To do: Repalce this stupid hacks
        if (! $user->isSuperAdmin()) {
            $joined = TRUE;
            $listOfRetailerIds = $user->getMyRetailerIds();

            if (empty($listOfRetailerIds)) {
                $listOfRetailerIds = [-1];
            }

            $users->employeeRetailerIds($listOfRetailerIds);
        } else {
            if (! empty($listOfRetailerIds)) {
                $users->employeeRetailerIds($listOfRetailerIds);
            }
        }

        // @To do: Replace this stupid hacks
        if (! $user->isSuperAdmin()) {
            $joined = TRUE;
            $listOfMerchantIds = $user->getMyMerchantIds();

            if (empty($listOfMerchantIds)) {
                $listOfMerchantIds = [-1];
            }

            $users->employeeMerchantIds($listOfMerchantIds);
        } else {
            if (! empty($listOfMerchantIds)) {
                $users->employeeMerchantIds($listOfMerchantIds);
            }
        }

        // Filter user by username
        OrbitInput::get('usernames', function ($username) use ($users) {
            $users->whereIn('users.username', $username);
        });

        // Filter user by matching username pattern
        OrbitInput::get('username_like', function ($username) use ($users) {
            $users->where('users.username', 'like', "%$username%");
        });

        // Filter user by their firstname
        OrbitInput::get('firstnames', function ($firstname) use ($users) {
            $users->whereIn('users.user_firstname', $firstname);
        });

        // Filter user by their employee_id_char
        OrbitInput::get('employee_id_char', function ($idChars) use ($users, $joined) {
            $joined = TRUE;
            $users->employeeIdChars($idChars);
        });

       // Filter user by their employee_id_char pattern
        OrbitInput::get('employee_id_char_like', function ($idCharLike) use ($users, $joined) {
            $joined = TRUE;
            $users->employeeIdCharLike($idCharLike);
        });

        // Filter user by their firstname pattern
        OrbitInput::get('firstname_like', function ($firstname) use ($users) {
            $users->where('users.user_firstname', 'like', "%$firstname%");
        });

        // Filter user by their lastname
        OrbitInput::get('lastname', function ($lastname) use ($users) {
            $users->whereIn('users.user_lastname', $lastname);
        });

        // Filter user by their lastname pattern
        OrbitInput::get('lastname_like', function ($firstname) use ($users) {
            $users->where('users.user_lastname', 'like', "%$firstname%");
        });

        // Filter user by their fullname (firstname and lastname) pattern
        OrbitInput::get('fullname_like', function ($fullname) use ($users) {
            $users->where(DB::raw('CONCAT(user_firstname, " ", user_lastname)'), 'like', "%$fullname%");
        });

        // Filter user by employee position pattern
        OrbitInput::get('position_like', function ($position) use ($users) {
            $users->whereHas('employee', function ($q) use ($position) {
                $q->where('position', 'like', "%$position%");
            });
        });

        // Filter user by their status
        OrbitInput::get('statuses', function ($status) use ($users) {
            $users->whereIn('users.status', $status);
        });

        // Filter user by their role id
        OrbitInput::get('role_id', function ($roleId) use ($users) {
            $users->whereIn('users.user_role_id', $roleId);
        });

        if (empty(OrbitInput::get('role_id'))) {
            $invalidRoles = ['super admin', 'administrator', 'consumer', 'customer', 'merchant owner', 'retailer owner', 'guest'];
            $roles = Role::whereIn('role_name', $invalidRoles)->get();

            $ids = array();
            foreach ($roles as $role) {
                $ids[] = $role->role_id;
            }
            $users->whereNotIn('users.user_role_id', $ids);
        }

        $_users = clone $users;

        // Default sort by
        $sortBy = 'users.user_firstname';
        // Default sort mode
        $sortMode = 'asc';

        OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy, $users, $joined) {
            if ($_sortBy === 'employee_id_char' || $_sortBy === 'position') {
                if ($joined === FALSE) {
                    $users->prepareEmployeeRetailer();
                }
            }

            // Map the sortby request to the real column name
            $sortByMapping = array(
                'registered_date'   => 'users.created_at',
                'username'          => 'users.username',
                'employee_id_char'  => 'employees.employee_id_char',
                'lastname'          => 'users.user_lastname',
                'firstname'         => 'users.user_firstname',
                'position'          => 'employees.position'
            );

            $sortBy = $sortByMapping[$_sortBy];
        });

        OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
            if (strtolower($_sortMode) !== 'asc') {
                $sortMode = 'desc';
            }
        });
        $users->orderBy($sortBy, $sortMode);

        $totalRec = RecordCounter::create($_users)->count();

        $this->prepareUnbufferedQuery();

        $sql = $users->toSql();
        $binds = $users->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Cashier';
        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle));

                printf("%s,%s,%s,%s\n", '', '', '', '');
                printf("%s,%s,%s,%s\n", '', 'Cashier List', '', '');
                printf("%s,%s,%s,%s\n", '', 'Total Cashier', $totalRec, '');

                printf("%s,%s,%s,%s\n", '', '', '', '');
                printf("%s,%s,%s,%s\n", '', 'Name', 'Position', 'Login ID');
                printf("%s,%s,%s,%s\n", '', '', '', '');

                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $fullname = $this->printFullName($row);
                    printf("\"%s\",\"%s\",\"%s\",\"%s\"\n", '', $fullname, $row->position, $row->username);
                }
                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-cashier-view.php';
        }
    }

    public function getCashierTimeReportPrintView()
    {
        try {
            $this->preparePDO();
            $mode = OrbitInput::get('export', 'print');
            $user = $this->loggedUser;
            $now  = date('Y-m-d H:i:s');

            $builder = CashierAPI::create()->getBuilderFor('getCashierTimeReport');

            $this->prepareUnbufferedQuery();

            $transactions = $builder->getBuilder();
            $_transactions = $builder->getUnsorted();

            $query      = $transactions->toSql();
            $bindings   = $transactions->getBindings();

            $statement  = $this->pdo->prepare($query);
            $statement->execute($bindings);


            $subTotalQuery    = $_transactions->toSql();
            $total      = DB::table(DB::raw("({$subTotalQuery}) as sub_total"))
                ->mergeBindings($_transactions)
                ->count();


            $summary = DB::table(DB::raw("({$subTotalQuery}) as sub_total"))
                ->mergeBindings($_transactions)
                ->select([
                    DB::raw("sum(sub_total.total_time) as total_time"),
                    DB::raw("sum(sub_total.transactions_count) as transactions_count"),
                    DB::raw("sum(sub_total.transactions_total) as transactions_total")
                ])->first();

            $summaryHeaders = [
                'total_time'         => 'Total Time',
                'transactions_count' => 'Total Receipts',
                'transactions_total' => 'Total Sales'
            ];

            $summaryFormatter = [
                'total_time'         => array('Orbit\\Text', 'formatNumberWithoutPrecision'),
                'transactions_count' => false,
                'transactions_total' => array('Orbit\\Text', 'formatNumber')
            ];

            $rowNames = [
                'activity_date' => 'Date',
                'activity_full_name' => 'Employee Name',
                'login_at_hour' => 'Clock In',
                'logout_at_hour' => 'Clock Out',
                'total_time' => 'Total Time (mins)',
                'transactions_count' => 'Number of Receipt',
                'transactions_total' => 'Total Sales'
            ];

            $rowFormatter = [
                'activity_date' => array('Orbit\\Text', 'formatDate'),
                'activity_full_name' => false,
                'login_at_hour' => array('Orbit\\Text', 'formatTime'),
                'logout_at_hour' => array('Orbit\\Text', 'formatTime'),
                'total_time' => false,
                'transactions_count' => false,
                'transactions_total' => array('Orbit\\Text', 'formatNumber')
            ];

            $rowCounter = 0;
            $pageTitle  = 'Cashier Report';
            switch($mode)
            {
                case 'csv':
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle));

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
                    require app_path() . '/views/printer/cashier-view.php';
            }
        } catch(\Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }


    /**
     * Print expiration date type friendly name.
     *
     * @param $cashier $cashier
     * @return string
     */
    public function printFullName($cashier)
    {
        $result = $cashier->user_firstname.' '.$cashier->user_lastname;
        return $result;
    }


    /**
     * Print activity date type friendly name.
     *
     * @param $cashier $cashier
     * @return string
     */
    public function printActivityDate($cashier)
    {
        if($cashier->activity_date==NULL || empty($cashier->activity_date)){
            $result = "";
        } else {
            $date = $cashier->activity_date;
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d F Y',$time);
            $result = $newformat;
        }
        return $result;
    }


    /**
     * Print date time friendly name.
     *
     * @param $date $date
     * @return string
     */
    public function printDateTime($date)
    {
        if($date==NULL || empty($date)){
            $result = "";
        } else {
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d F Y',$time);
            $result = $newformat.' '.$date[1];
        }
        return $result;
    }


    /**
     * Print number format friendly name.
     *
     * @param $number $number
     * @return string
     */
    public function printNumberFormat($number)
    {
        $result = number_format($number, 2);
        return $result;
    }

}
