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

        $users = User::excludeDeleted('users');

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

        $totalRec = RecordCounter::create($_users)->count();

        $this->prepareUnbufferedQuery();

        $sql = $users->toSql();
        $binds = $users->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        switch ($mode) {
            case 'csv':
                $filename = 'event-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                printf("%s,%s,%s,%s\n", '', '', '', '');
                printf("%s,%s,%s,%s\n", '', 'Name', 'Login ID', 'Position');
                printf("%s,%s,%s,%s\n", '', '', '', '');

                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $fullname = $this->printFullName($row);
                    printf("%s,%s,%s,%s\n", '', $fullname, '', '');
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

            $activityQuery = DB::raw("
                SELECT
                    `user_id` as activity_user_id,
                    `full_name` as activity_full_name,
                    `role` as activity_role,
                    `group` as activity_group,
                    date(`created_at`) as activity_date,
                    min(case activity_type when 'login' then `created_at` end) as login_at,
                    max(case activity_type when 'logout' then `created_at` end) as logout_at
                FROM {$tablePrefix}activities
                WHERE `role`          like 'cashier'
                AND   `group`         = 'pos'
                AND   `activity_type` IN ('login', 'logout')
                GROUP BY `activity_date`, `activity_user_id`");

            $transactions = Transaction::select([
                'cashier_activities.*',
                DB::raw("count(distinct transaction_id) as transactions_count"),
                DB::raw('sum(total_to_pay) as transactions_total')])

                ->leftJoin("users as {$tablePrefix}customer", function ($join) {
                    $join->on('customer.user_id', '=', 'transactions.customer_id');
                })

                ->leftJoin(DB::raw("({$activityQuery}) as {$tablePrefix}cashier_activities"), function ($join) {
                    $join->on('activity_user_id', '=', 'transactions.cashier_id');
                });

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

            OrbitInput::get('customer_id', function ($customerId) use ($transactions) {
                $transactions->whereIn("customer.user_id", $this->getArray($customerId));
            });

            OrbitInput::get('customer_firstname', function ($customerName) use ($transactions) {
                $transactions->whereIn("customer.user_firstname", $this->getArray($customerName));
            });

            OrbitInput::get('customer_lastname', function ($customerName) use ($transactions) {
                $transactions->whereIn("customer.user_lastname", $this->getArray($customerName));
            });

            OrbitInput::get('customer_name_like', function ($customerName) use ($transactions) {
                $transactions->where("customer.user_firstname", 'like', "%{$customerName}%")
                    ->whereOr("customer.user_lastname", 'like', "%{$customerName}%");
            });

            OrbitInput::get('payment_method', function ($paymentType) use ($transactions) {
                $transactions->whereIn('transactions.payment_method', $this->getArray($paymentType));
            });

            OrbitInput::get('purchase_code', function ($purchaseCode) use ($transactions) {
                $transactions->whereIn('transactions.transaction_code', $this->getArray($purchaseCode));
            });

            $transactions->groupBy(['transactions.cashier_id', 'cashier_activities.activity_date']);
            // Default sort by
            $sortBy = 'transactions.created_at';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                $sortByMapping = array(
                    'cashier_id'  => 'activity_user_id',
                    'cashier_name' => 'activity_full_name',
                    'customer_id'  => 'customer.user_id',
                    'customer_firstname' => 'customer.user_firstname',
                    'customer_lastname'  => 'customer.user_lastname',
                    'payment_method'     => 'transactions.payment_method',
                    'purchase_code'  => 'transactions.transaction_code'
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

            $total      = RecordCounter::create($_transactions)->count();

            $subTotalQuery    = $_transactions->toSql();
            $subTotalBindings = $_transactions->getQuery();
            $subTotal = DB::table(DB::raw("({$subTotalQuery}) as sub_total"))
                ->mergeBindings($subTotalBindings)
                ->select([
                    DB::raw("sum(sub_total.transactions_count) as transactions_count"),
                    DB::raw("sum(sub_total.transactions_total) as transactions_total")
                ])->first();

            $rowCounter = 0;
            $pageTitle  = 'Report Cashier Time Table';

            $formatDate = function($time) {
                return date('d-M-Y H:i:s', strtotime($time));
            };

            $totalTime = function($start, $end) {
                $time = strtotime($end) - strtotime($start);
                return date('d-M-Y H:i:s', $time);
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
                    require app_path() . '/views/printer/cashier/cashier-time-view.php';
            }
        } catch(\Exception $e) {
            $responseText = Config::get("app.debug") ? $e->__toString() : "";
            return Response::make($responseText, 500);
        }
    }


    /**
     * Print expiration date type friendly name.
     *
     * @param $promotion $promotion
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


}
