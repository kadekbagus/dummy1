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
use Transaction;
use Activity;

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

        switch ($mode) {
            case 'csv':
                $filename = 'cashier-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

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
                $pageTitle = 'Cashier';
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

            $tablePrefix = DB::getTablePrefix();

            $activities  = Activity::select(
                    'user_id as activity_user_id',
                    'full_name as activity_full_name',
                    'role as activity_role',
                    'group as activity_group',
                    DB::raw('date(created_at) as activity_date'),
                    DB::raw("min(case activity_name when 'login_ok' then created_at end) as login_at"),
                    DB::raw("max(case activity_name when 'logout_ok' then created_at end) as logout_at")
                )
                ->where('role', 'like', 'cashier')
                ->where('group', '=', 'pos')
                ->whereIn('activity_name', ['login_ok', 'logout_ok'])
                ->groupBy('activity_date', 'activity_user_id');
            $activitiesQuery = $activities->getQuery();

            $transactionByDate = Transaction::select(
                    DB::raw('count(distinct transaction_id) as transactions_count'),
                    DB::raw('sum(total_to_pay) as transactions_total'),
                    DB::raw('date(created_at) as transaction_date'),
                    'merchant_id',
                    'cashier_id',
                    'customer_id'
                )
                ->groupBy('transaction_date', 'cashier_id');
            $transactionByDateQuery = $transactionByDate->getQuery();

            $transactions = DB::table(DB::raw("({$activities->toSql()}) as {$tablePrefix}activities"))
                ->mergeBindings($activitiesQuery)
                ->leftJoin(DB::raw("({$transactionByDate->toSql()}) as {$tablePrefix}transactions"), function ($join) {
                    $join->on('activity_user_id', '=','cashier_id');
                    $join->on('activity_date', '=','transaction_date');
                })
                ->mergeBindings($transactionByDateQuery)
                ->groupBy('activity_date', 'cashier_id');

            OrbitInput::get('merchant_id', function ($merchantId) use ($transactions) {
                $transactions->whereIn('transactions.merchant_id', $this->getArray($merchantId));
            });

            OrbitInput::get('cashier_id', function ($cashierId) use ($transactions) {
                $transactions->whereIn("activity_user_id", $this->getArray($cashierId));
            });

            OrbitInput::get('cashier_name', function ($cashierName) use ($transactions) {
                $transactions->whereIn("activity_full_name", $this->getArray($cashierName));
            });

            OrbitInput::get('cashier_name_like', function ($cashierName) use ($transactions) {
                $transactions->where("activity_full_name", 'like', "%{$cashierName}%");
            });

            $transactions->groupBy(['transactions.cashier_id', 'activities.activity_date']);
            // Default sort by
            $sortBy = 'transactions.transaction_date';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                $sortByMapping = array(
                    'cashier_id'   => 'activity_user_id',
                    'cashier_name' => 'activity_full_name',
                    'login_at'     => 'login_at',
                    'logout_at'    => 'logout_at',
                    'transactions_count'  => 'transactions_count',
                    'transactions_total'  => 'transactions_total'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $transactions->orderBy($sortBy, $sortMode);

            $this->prepareUnbufferedQuery();
            $_transactions = clone $transactions;

            $query      = $transactions->toSql();
            $bindings   = $transactions->getBindings();

            $statement  = $this->pdo->prepare($query);
            $statement->execute($bindings);

            $total      = DB::table(DB::raw("({$_transactions->toSql()}) as sub"))
                ->mergeBindings($_transactions)
                ->count();

            $subTotalQuery    = $_transactions->toSql();
            $subTotalBindings = $_transactions;
            $subTotal = DB::table(DB::raw("({$subTotalQuery}) as sub_total"))
                ->mergeBindings($subTotalBindings)
                ->select([
                    DB::raw("sum(sub_total.transactions_count) as transactions_count"),
                    DB::raw("sum(sub_total.transactions_total) as transactions_total")
                ])->first();

            $rowCounter = 0;
            $pageTitle  = 'Report Cashier Time Table';

            $formatDate = function($time) {
                $time = strtotime($time);
                if ($time <= 1) {
                    return '-';
                }
                return date('d-M-Y H:i:s', $time);
            };

            $totalTime = function($start, $end) {
                $start = strtotime($start);
                $end   = strtotime($end);
                if ($end <= 1 || $start <= 1)
                {
                    return '-';
                }
                return $end - $start;
            };

            switch ($mode) {
                case 'csv':
                    $filename = 'transaction-product-list-' . $now . '.csv';
                    @header('Content-Description: File Transfer');
                    @header('Content-Type: text/csv');
                    @header('Content-Disposition: attachment; filename=' . $filename);
                    // TITLE HEADER
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '','',$pageTitle,'','','','','');
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '');

                    // Total Purchase
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', 'Total Records', ':', $total, '', '', '');
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', 'Total Transactions', ':', $subTotal->transactions_count, '', '', '');
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', 'Total Sales', ':', $subTotal->transactions_total, '', '', '', '');

                    // ROW HEADER
                    printf(
                        "%s,%s,%s,%s,%s,%s,%s,%s",
                        'No.', // 1
                        'Date', // 2
                        'Employee Name', // 3
                        'Clock In', // 4
                        'Clock Out', // 5
                        'Total Time', // 6
                        'Number Of Receipt', // 7
                        'Total Sales'  // 8
                    );

                    while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        printf("\n%s,%s,%s,%s,%s,%s,%s,%s",
                            ++$rowCounter, // 1
                            $row->activity_date, // 2
                            $row->activity_full_name, // 3
                            $formatDate($row->login_at), // 4
                            $formatDate($row->logout_at), // 5
                            $totalTime($row->login_at, $row->logout_at), // 6
                            $row->transactions_count, // 7
                            $row->transactions_total // 8
                        );
                    }
                    break;
                case 'print':
                default:
                    $me = $this;
                    require app_path() . '/views/printer/list-cashier-time-view.php';
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
        $return = '';
        $result = $cashier->user_firstname.' '.$cashier->user_lastname;
        return $result;
    }

    /**
     * Make a variable always array
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
            $newformat = date('d M Y',$time);
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
            $newformat = date('d M Y',$time);
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
