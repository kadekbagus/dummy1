<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
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

        if ($user->isSuperAdmin()) {
            
                $users = User::Consumers()
                ->excludeDeleted('users')
                ->select('users.*',
                        'user_details.city as city',
                        'user_details.birthdate as birthdate',
                        'user_details.gender as gender',
                        'user_details.country as country',
                        'user_details.last_visit_any_shop as last_visit_date',
                        'user_details.relationship_status as relationship_status',
                        'user_details.number_of_children as number_of_children',
                        'user_details.occupation as occupation',
                        'user_details.sector_of_activity as sector_of_activity',
                        'user_details.last_education_degree as last_education_degree',
                        'user_details.avg_annual_income1 as avg_annual_income1',
                        'user_details.avg_monthly_spent1 as avg_monthly_spent1',
                        'user_details.preferred_language as preferred_language', 
                        'merchants.name as last_visited_store',
                        'user_details.last_visit_any_shop as last_visited_date',
                        'user_details.last_spent_any_shop as last_spent_amount',
                         DB::raw("GROUP_CONCAT(`{$prefix}personal_interests`.`personal_interest_value` SEPARATOR ', ') as personal_interest_list")
                        )
                ->join('user_details', 'user_details.user_id', '=', 'users.user_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'user_details.last_visit_shop_id')
                ->leftJoin('user_personal_interest', 'user_personal_interest.user_id', '=', 'users.user_id')
                ->leftJoin('personal_interests', 'personal_interests.personal_interest_id', '=', 'user_personal_interest.personal_interest_id') 
                ->with(array('userDetail', 'userDetail.lastVisitedShop'))
                ->groupBy('users.user_id');

        } else {

                // get merchant id from the current users
                $merchant_id = \Merchant::where('user_id', $user->user_id)->first()->merchant_id;

                if (empty($merchant_id)) {
                    $errorMessage = 'Merchant id not found';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $users = User::Consumers()
                ->excludeDeleted('users')
                ->select('users.*',
                        'user_details.city as city',
                        'user_details.birthdate as birthdate',
                        'user_details.gender as gender',
                        'user_details.country as country',
                        'user_details.last_visit_any_shop as last_visit_date',
                        'user_details.relationship_status as relationship_status',
                        'user_details.number_of_children as number_of_children',
                        'user_details.occupation as occupation',
                        'user_details.sector_of_activity as sector_of_activity',
                        'user_details.last_education_degree as last_education_degree',
                        'user_details.avg_annual_income1 as avg_annual_income1',
                        'user_details.avg_monthly_spent1 as avg_monthly_spent1',
                        'user_details.preferred_language as preferred_language', 
                         DB::raw("GROUP_CONCAT(`{$prefix}personal_interests`.`personal_interest_value` SEPARATOR ', ') as personal_interest_list"),
                         DB::raw("(SELECT m.name 
                                FROM {$prefix}activities at 
                                LEFT JOIN {$prefix}merchants m on m.merchant_id=at.location_id 
                                WHERE 
                                    at.user_id={$prefix}users.user_id AND 
                                    at.activity_name='login_ok' AND
                                    at.group = 'mobile-ci' AND
                                    m.parent_id = '{$merchant_id}'
                                ORDER BY at.created_at DESC LIMIT 1) as last_visited_store"),
                         DB::raw("(SELECT at.created_at 
                                FROM {$prefix}activities at 
                                LEFT JOIN {$prefix}merchants m2 on m2.merchant_id=at.location_id 
                                WHERE 
                                    at.user_id={$prefix}users.user_id AND 
                                    at.activity_name='login_ok' AND
                                    at.group = 'mobile-ci' AND
                                    m2.parent_id = '{$merchant_id}'
                                ORDER BY at.created_at DESC LIMIT 1) as last_visited_date"),
                         DB::raw("(SELECT tr.total_to_pay 
                                FROM {$prefix}transactions tr 
                                WHERE 
                                    tr.customer_id={$prefix}users.user_id AND 
                                    tr.status='paid' AND 
                                    tr.merchant_id='{$merchant_id}' 
                                GROUP BY tr.created_at 
                                ORDER BY tr.created_at DESC LIMIT 1) as last_spent_amount")
                        )
                ->join('user_details', 'user_details.user_id', '=', 'users.user_id')
                ->leftJoin('user_personal_interest', 'user_personal_interest.user_id', '=', 'users.user_id')
                ->leftJoin('personal_interests', 'personal_interests.personal_interest_id', '=', 'user_personal_interest.personal_interest_id') 
                ->with(array('userDetail', 'userDetail.lastVisitedShop'))
                ->groupBy('users.user_id');
        }



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
                'gender'                  => 'gender',
                'city'                    => 'city',
                'last_visit_shop'         => 'last_visited_store',
                'last_visit_date'         => 'last_visited_date',
                'last_spent_amount'       => 'last_spent_amount'
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

        $pageTitle = 'Consumer';
        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle));

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Consumer List', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Consumer', $totalRec, '', '', '', '','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Email', 'Gender', 'Location', 'Last Visited Store', 'Last Visit Date', 'Last Spent Amount', 'Customer Since', 'First Name', 'Last Name', 'Date of Birth', 'Relationship Status', 'Number of Children', 'Occupation', 'Sector of Activity', 'Education Level', 'Preferred Language', 'Annual Income (IDR)', 'Average Monthly Shopping Spent', 'Personal Interest');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $customer_since = $this->printCustomerSince($row);
                    $gender = $this->printGender($row);
                    $address = $this->printAddress($row);
                    $birthdate = $this->printBirthDate($row);
                    $last_visit_date = $this->printLastVisitDate($row);
                    $preferred_language = $this->printLanguage($row);
                    $occupation = $this->printOccupation($row);
                    $sector_of_activity = $this->printSectorOfActivity($row);
                    $avg_annual_income = $this->printAverageAnnualIncome($row);
                    $avg_monthly_spent = $this->printAverageShopping($row);

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\", %s,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n", 
                        '', $row->user_email, $gender, $address, $this->printUtf8($row->last_visited_store), $row->last_visited_date, $row->last_spent_amount, $row->created_at,
                        $this->printUtf8($row->user_firstname), $this->printUtf8($row->user_lastname), $row->birthdate, $row->relationship_status, $row->number_of_children, $occupation, $sector_of_activity,
                        $row->last_education_degree, $preferred_language, $avg_annual_income, $avg_monthly_spent, $row->personal_interest_list);
                }
                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-consumer-view.php';
        }
    }

    public function getRetailerInfo()
    {
        try {
            $retailer_id = Config::get('orbit.shop.id');
            $retailer = \Retailer::with('parent')->where('merchant_id', $retailer_id)->first();

            return $retailer;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
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
        if(!empty($consumer->city) && !empty($consumer->country)){
            $result = $consumer->city.', '.$consumer->country;
        }
        else if(empty($consumer->city) && !empty($consumer->country)){
            $result = $consumer->country;
        }
        else if(!empty($consumer->city) && empty($consumer->country)){
            $result = $consumer->city;
        }
        else if(empty($consumer->city) && empty($consumer->country)){
            $result = '';
        }

        return $this->printUtf8($result);
    }


    /**
     * Print Gender friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printGender($consumer)
    {
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
     * Print Language friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printLanguage($consumer)
    {
        $lang = $consumer->preferred_language;
        $lang = strtolower($lang);
        switch ($lang) {
            case 'en':
                $result = 'English';
                break;

            case 'id':
                $result = 'Indonesian';
                break;
            default:
                $result = $lang;
        }

        return $result;
    }


    /**
     * Print date friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printCustomerSince($consumer)
    {
        if($consumer->created_at==NULL || empty($consumer->created_at) || $consumer->created_at=="0000-00-00 00:00:00"){
            $result = "";
        }
        else {
            $date = $consumer->created_at;
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d F Y',$time);
            $result = $newformat;
        }


        return $result;
    }    


    /**
     * Print Birthdate friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printBirthDate($consumer)
    {
        if($consumer->birthdate==NULL || empty($consumer->birthdate)){
            $result = "";
        }
        else {
            $date = $consumer->birthdate;
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d F Y',$time);
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
        $date = $consumer->last_visited_date;
        if($consumer->last_visited_date==NULL || empty($consumer->last_visited_date)){
            $result = ""; 
        }else {
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d F Y',$time);
            $result = $newformat;
        }

        return $result;
    } 

    /**
     * Print Last Spent Amount friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printLastSpentAmount($consumer)
    {
        $user = $this->loggedUser;
        if ($user->isSuperAdmin()) {
            $currency = 'usd';
        } else {
            $currency = \Merchant::where('user_id', $user->user_id)->first()->currency;
            $currency = strtolower($currency);
        }

        if($currency=='usd'){
            if (!empty($consumer->last_spent_amount)) {
                $result = number_format($consumer->last_spent_amount, 2);
            } else {
                $result = '';
            }
            
        } else {
            if (!empty($consumer->last_spent_amount)) {
                $result = number_format($consumer->last_spent_amount);
            } else {
                $result = '';
            }
        }
        return $result;
    }


    /**
     * Print Occupation friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printOccupation($consumer)
    {
        $occupation = $consumer->occupation;
        switch ($occupation) {
            case 'p':
                $result = 'Part-Time';
                break;

            case 'f':
                $result = 'Full Time Employee';
                break;

            case 'v':
                $result = 'Voluntary';
                break;
            
            case 'u':
                $result = 'Unemployed';
                break;

            case 'r':
                $result = 'Retired';
                break;

            case 's':
                $result = 'Student';
                break;

            default:
                $result = $occupation;
        }

        return $result;
    }


    /**
     * Print Sector of Activity friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printSectorOfActivity($consumer)
    {
        $sector_of_activity = $consumer->sector_of_activity;
        switch ($sector_of_activity) {
            case 'ma':
                $result = 'Management';
                break;

            case 'bf':
                $result = 'Business and Financial Operations';
                break;

            case 'cm':
                $result = 'Computer and Mathematical';
                break;
            
            case 'ae':
                $result = 'Architecture and Engineering';
                break;

            case 'lp':
                $result = 'Life, Physical, and Social Science';
                break;

            case 'cs':
                $result = 'Community and Social Service';
                break;

            case 'lg':
                $result = 'Legal';
                break;

            case 'et':
                $result = 'Education, Training, and Library';
                break;

            case 'ad':
                $result = 'Arts, Design, Entertainment, Sports, and Media';
                break;
            
            case 'hp':
                $result = 'Healthcare Practitioners and Technical';
                break;

            case 'hs':
                $result = 'Healthcare Support';
                break;

            case 'ps':
                $result = 'Protective Service';
                break;

            case 'fp':
                $result = 'Food Preparation and Serving Related';
                break;

            case 'bg':
                $result = 'Building and Grounds Cleaning and Maintenance';
                break;

            case 'pc':
                $result = 'Personal Care and Services';
                break;
            
            case 'sr':
                $result = 'Sales and Related';
                break;

            case 'oa':
                $result = 'Office and Administrative Support';
                break;

            case 'ff':
                $result = 'Farming, Fishing, and Forestry';
                break;

            case 'ce':
                $result = 'Construction and Extraction';
                break;

            case 'im':
                $result = 'Installation, Maintenance, and Repair';
                break;

            case 'pr':
                $result = 'Production';
                break;

            case 'tm':
                $result = 'Transportation and Material Moving';
                break;

            default:
                $result = $sector_of_activity;
        }

        return $result;
    }


    /**
     * Print Average Annual Income friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printAverageAnnualIncome($consumer)
    {
        $avg_income = $consumer->avg_annual_income1;
        switch ($avg_income) {
            case ($avg_income <= 20000000 ):
                $result = '< 20.000.000';
                break;

            case ($avg_income > 20000000 && $avg_income <= 50000000):
                $result = '20.000.000 - 50.000.000';
                break;

            case ($avg_income > 50000000 && $avg_income <= 100000000):
                $result = '50.000.000 - 100.000.000';
                break;
            
            case ($avg_income > 100000000 && $avg_income <= 200000000):
                $result = '100.000.000 - 200.000.000';
                break;

            case ($avg_income > 200000000):
                $result = '200.000.000 +++';
                break;

            default:
                $result = $avg_income;
        }

        return $result;
    }


    /**
     * Print Average Shopping friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printAverageShopping($consumer)
    {
        $avg_monthly_spent = $consumer->avg_monthly_spent1;
        switch ($avg_monthly_spent) {
            case ($avg_monthly_spent <= 200000 ):
                $result = '< 200.000';
                break;

            case ($avg_monthly_spent > 200000 && $avg_monthly_spent <= 500000):
                $result = '200.000 - 500.000';
                break;

            case ($avg_monthly_spent > 500000 && $avg_monthly_spent <= 1000000):
                $result = '500.000 - 1.000.000';
                break;
            
            case ($avg_monthly_spent > 1000000 && $avg_monthly_spent <= 2000000):
                $result = '1.000.000 - 2.000.000';
                break;

            case ($avg_monthly_spent > 2000000 && $avg_monthly_spent <= 5000000):
                $result = '2.000.000 - 5.000.000';
                break;

            case ($avg_monthly_spent > 5000000):
                $result = '5.000.000 +++';
                break;

            default:
                $result = $avg_monthly_spent;
        }

        return $result;
    }


    /**
     * output utf8.
     *
     * @param string $input
     * @return string
     */
    public function printUtf8($input)
    {
        return utf8_encode($input);
    }

}