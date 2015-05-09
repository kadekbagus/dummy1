<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use User;
use Role;

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


}