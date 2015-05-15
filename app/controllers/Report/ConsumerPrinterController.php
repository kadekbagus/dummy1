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

        // Get the maximum record
        $maxRecord = (int) Config::get('orbit.pagination.user.max_record');
        if ($maxRecord <= 0) {
            // Fallback
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }
        }
        // Get default per page (take)
        $perPage = (int) Config::get('orbit.pagination.user.per_page');
        if ($perPage <= 0) {
            // Fallback
            $perPage = (int) Config::get('orbit.pagination.per_page');
            if ($perPage <= 0) {
                $perPage = 20;
            }
        }

        // Available merchant to query
        $listOfMerchantIds = [];

        // Available retailer to query
        $listOfRetailerIds = [];

        // Builder object
        $users = User::Consumers()
                    ->select('users.*', 'user_details.gender as gender', 'user_details.city as city', 
                        'user_details.country as country', 
                        'merchants.name as merchant_name',
                        'user_details.last_visit_any_shop as last_visit_date',
                        'user_details.last_spent_any_shop as last_spent_amount',
                        'user_details.relationship_status as relationship_status',
                        'user_details.number_of_children as number_of_children',
                        'user_details.occupation as occupation',
                        'user_details.sector_of_activity as sector_of_activity',
                        'user_details.last_education_degree as last_education_degree',
                        'user_details.avg_annual_income1 as avg_annual_income1',
                        'user_details.avg_monthly_spent1 as avg_monthly_spent1')
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

        // Filter by retailer (shop) ids
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

        // Filter user by gender
        OrbitInput::get('gender', function ($gender) use ($users) {
            $users->whereHas('userdetail', function ($q) use ($gender) {
                $q->whereIn('gender', $gender);
            });
        });

        // Filter user by their location('city, country') pattern
        OrbitInput::get('location_like', function ($location) use ($users) {
            $users->whereHas('userdetail', function ($q) use ($location) {
                $q->where(DB::raw('CONCAT(city, ", ", country)'), 'like', "%$location%");
            });
        });

        // Filter user by their city pattern
        OrbitInput::get('city_like', function ($city) use ($users) {
            $users->whereHas('userdetail', function ($q) use ($city) {
                $q->where('city', 'like', "%$city%");
            });
        });

        // Filter user by their last_visit_shop pattern
        OrbitInput::get('last_visit_shop_name_like', function ($shopName) use ($users) {
            $users->whereHas('userdetail', function ($q) use ($shopName) {
                $q->whereHas('lastVisitedShop', function ($q) use ($shopName) {
                    $q->where('name', 'like', "%$shopName%");
                });
            });
        });

        // Filter user by last_visit_begin_date
        OrbitInput::get('last_visit_begin_date', function($begindate) use ($users)
        {
            $users->whereHas('userdetail', function ($q) use ($begindate) {
                $q->where('last_visit_any_shop', '>=', $begindate);
            });
        });

        // Filter user by last_visit_end_date
        OrbitInput::get('last_visit_end_date', function($enddate) use ($users)
        {
            $users->whereHas('userdetail', function ($q) use ($enddate) {
                $q->where('last_visit_any_shop', '<=', $enddate);
            });
        });

        // Filter user by created_at for begin_date
        OrbitInput::get('created_begin_date', function($begindate) use ($users)
        {
            $users->where('users.created_at', '>=', $begindate);
        });

        // Filter user by created_at for end_date
        OrbitInput::get('created_end_date', function($enddate) use ($users)
        {
            $users->where('users.created_at', '<=', $enddate);
        });

        // Clone the query builder which still does not include the take,
        // skip, and order by
        $_users = clone $users;

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
        $users->take($take);

        $skip = 0;
        OrbitInput::get('skip', function ($_skip) use (&$skip, $users) {
            if ($_skip < 0) {
                $_skip = 0;
            }

            $skip = $_skip;
        });
        $users->skip($skip);

        // Default sort by
        $sortBy = 'users.user_email';
        // Default sort mode
        $sortMode = 'asc';

        OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
            // Map the sortby request to the real column name
            $sortByMapping = array(
                'registered_date'         => 'users.created_at',
                'username'                => 'users.username',
                'email'                   => 'users.user_email',
                'lastname'                => 'users.user_lastname',
                'firstname'               => 'users.user_firstname',
                'gender'                  => 'user_details.gender',
                'city'                    => 'user_details.city',
                'last_visit_shop'         => 'merchants.name',
                'last_visit_date'         => 'user_details.last_visit_any_shop',
                'last_spent_amount'       => 'user_details.last_spent_any_shop'
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
                $filename = 'consumer-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Consumer List', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Consumer', $totalRec, '', '', '', '','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Email', 'Gender', 'Address', 'Last Visited Retailer', 'Last Visit Date', 'Last Spent Amount', 'Customer Since', 'First Name', 'Last Name', 'Date of Birth', 'Number of Children', 'Occupation', 'Sector of Activity', 'Education Level', 'Average Annual Income', 'Average Shopping Spent');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $customer_since = $this->printDateFormat($row);
                    $gender = $this->printGender($row);
                    $address = $this->printAddress($row);
                    $last_visit_date = $this->printLastVisitDate($row);

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n", 
                        '', $row->user_email, $gender, $address, $row->merchant_name,  $last_visit_date, number_format($row->last_spent_amount), $customer_since,
                        $row->user_firstname, $row->user_lastname, $row->relationship_status, $row->number_of_children, $row->occupation, $row->sector_of_activity,
                        $row->last_education_degree, number_format($row->avg_annual_income1), number_format($row->avg_monthly_spent1));
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
        if($consumer->created_at==NULL || empty($consumer->created_at)){
            $result = "";
        }
        else {
            $date = $consumer->created_at;
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d M Y',$time);
            $result = $newformat;
        }


        return $result;
    }    


    /**
     * Print last visit date friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printLastVisitDate($consumer)
    {
        $return = '';
        $date = $consumer->last_visit_date;
        if($consumer->last_visit_date==NULL || empty($consumer->last_visit_date)){
            $result = ""; 
        }else {
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d M Y',$time);
            $result = $newformat;
        }


        return $result;
    } 


}