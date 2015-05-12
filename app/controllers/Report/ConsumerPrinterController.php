<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use User;
use Role;

class ConsumerPrinterController extends DataPrinterController
{
    public function getConsumerPrintView()
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
        $users = User::Consumers()
                    ->select('users.*')
                    ->join('user_details', 'user_details.user_id', '=', 'users.user_id')
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'user_details.last_visit_shop_id')
                    ->with(array('userDetail', 'userDetail.lastVisitedShop'))
                    ->excludeDeleted('users');

        //$users = User::excludeDeleted('users');

        // Filter by merchant ids
        OrbitInput::get('merchant_id', function($merchantIds) use ($users) {
            // $users->merchantIds($merchantIds);
            $listOfMerchantIds = (array)$merchantIds;
        });

        // @To do: Replace this stupid hacks
        if (! $user->isSuperAdmin()) {
            $listOfMerchantIds = $user->getMyMerchantIds();

            if (empty($listOfMerchantIds)) {
                $listOfMerchantIds = [-1];
            }

            //$users->merchantIds($listOfMerchantIds);
        } else {
            // if (! empty($listOfMerchantIds)) {
            //     $users->merchantIds($listOfMerchantIds);
            // }
        }

        //Filter by retailer (shop) ids
        OrbitInput::get('retailer_id', function($retailerIds) use ($users) {
            // $users->retailerIds($retailerIds);
            $listOfRetailerIds = (array)$retailerIds;
        });

        // @To do: Repalce this stupid hacks
        if (! $user->isSuperAdmin()) {
            $listOfRetailerIds = $user->getMyRetailerIds();

            if (empty($listOfRetailerIds)) {
                $listOfRetailerIds = [-1];
            }

            //$users->retailerIds($listOfRetailerIds);
        } else {
            // if (! empty($listOfRetailerIds)) {
            //     $users->retailerIds($listOfRetailerIds);
            // }
        }

        if (! $user->isSuperAdmin()) {
            // filter only by merchant_id, not include retailer_id yet.
            // @TODO: include retailer_id.
            $users->where(function($query) use($listOfMerchantIds) {
                // get users registered in shop.
                $query->whereIn('users.user_id', function($query2) use($listOfMerchantIds) {
                    $query2->select('user_details.user_id')
                        ->from('user_details')
                        ->whereIn('user_details.merchant_id', $listOfMerchantIds);
                })
                // get users have transactions in shop.
                ->orWhereIn('users.user_id', function($query3) use($listOfMerchantIds) {
                    $query3->select('customer_id')
                        ->from('transactions')
                        ->whereIn('merchant_id', $listOfMerchantIds)
                        ->groupBy('customer_id');
                });
            });
        }

        // Filter user by Ids
        OrbitInput::get('user_id', function ($userIds) use ($users) {
            $users->whereIn('users.user_id', $userIds);
        });

        // Filter user by username
        OrbitInput::get('username', function ($username) use ($users) {
            $users->whereIn('users.username', $username);
        });

        // Filter user by matching username pattern
        OrbitInput::get('username_like', function ($username) use ($users) {
            $users->where('users.username', 'like', "%$username%");
        });

        // Filter user by their firstname
        OrbitInput::get('firstname', function ($firstname) use ($users) {
            $users->whereIn('users.user_firstname', $firstname);
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
        OrbitInput::get('lastname_like', function ($lastname) use ($users) {
            $users->where('users.user_lastname', 'like', "%$lastname%");
        });

        // Filter user by their email
        OrbitInput::get('email', function ($email) use ($users) {
            $users->whereIn('users.user_email', $email);
        });

        // Filter user by their email pattern
        OrbitInput::get('email_like', function ($email) use ($users) {
            $users->where('users.user_email', 'like', "%$email%");
        });

        // Filter user by their status
        OrbitInput::get('status', function ($status) use ($users) {
            $users->whereIn('users.status', $status);
        });

        // Clone the query builder which still does not include the take,
        // skip, and order by
        $_users = clone $users;


        $totalRec = RecordCounter::create($_users)->count();

        $this->prepareUnbufferedQuery();

        $sql = $users->toSql();
        $binds = $users->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        switch ($mode) {
            case 'csv':
                $filename = 'consumer-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Email', 'Gender', 'Address', 'Last Visited Retailer', 'Last Visit Date', 'Last Spent Amount', 'Customer Since');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    //$address = $this->printAddress($row);
                    $customer_since = $this->printDateFormat($row);

                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', $row->user_email, '', '', '', '', '', $customer_since);
                }
                break;

            case 'print':
            default:
                $me = $this;
                $pageTitle = 'Consumer';
                require app_path() . '/views/printer/list-consumer-view.php';
        }
    }


    /**
     * Print expiration date type friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printAddress($consumer)
    {
        $return = '';
        $result = $consumer->city.','.$consumer->country;
        return $result;
    }


    /**
     * Print gender friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printGender($consumer)
    {
        $return = '';
        $gender = $consumer->gender;
        $gender = strtolower($gender);
        switch ($gender) {
            case 'm':
                $result = 'Male';
                break;

            case 'f':
                $result = 'Female';
                break;
            default:
                $result = '';
        }

        return $result;
    }


    /**
     * Print date friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printDateFormat($consumer)
    {
        $return = '';

        $date = $consumer->created_at;
        $date = explode(' ',$date);
        $time = strtotime($date[0]);
        $newformat = date('d M Y',$time);
        $result = $newformat;

        return $result;
    }    


}