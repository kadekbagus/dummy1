<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Merchant;

class MerchantPrinterController extends DataPrinterController
{
    public function getMerchantPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $now = date('Y-m-d H:i:s');

        $merchants = Merchant::excludeDeleted('merchants')
                            ->allowedForUser($user)
                            ->select('merchants.*',
                                DB::raw('count(distinct retailer.merchant_id) as merchant_count'), 
                                DB::raw("GROUP_CONCAT(`retailer`.`name`,' ',`retailer`.`city` SEPARATOR ' , ') as retailer_list"))
                            ->leftJoin('merchants AS retailer', function($join) {
                                    $join->on(DB::raw('retailer.parent_id'), '=', 'merchants.merchant_id')
                                        ->where(DB::raw('retailer.status'), '!=', 'deleted');
                                })
                            ->groupBy('merchants.merchant_id');

        // Filter merchant by Ids
        OrbitInput::get('merchant_id', function ($merchantIds) use ($merchants) {
            $merchants->whereIn('merchants.merchant_id', $merchantIds);
        });

        // Filter merchant by omid
        OrbitInput::get('omid', function ($omid) use ($merchants) {
            $merchants->whereIn('merchants.omid', $omid);
        });

        // Filter merchant by user Ids
        OrbitInput::get('user_id', function ($userIds) use ($merchants) {
            $merchants->whereIn('merchants.user_id', $userIds);
        });

        // Filter merchant by name
        OrbitInput::get('name', function ($name) use ($merchants) {
            $merchants->whereIn('merchants.name', $name);
        });

        // Filter merchant by name pattern
        OrbitInput::get('name_like', function ($name) use ($merchants) {
            $merchants->where('merchants.name', 'like', "%$name%");
        });

        // Filter merchant by description
        OrbitInput::get('description', function ($description) use ($merchants) {
            $merchants->whereIn('merchants.description', $description);
        });

        // Filter merchant by description pattern
        OrbitInput::get('description_like', function ($description) use ($merchants) {
            $merchants->where('merchants.description', 'like', "%$description%");
        });

        // Filter merchant by email
        OrbitInput::get('email', function ($email) use ($merchants) {
            $merchants->whereIn('merchants.email', $email);
        });

        // Filter merchant by email pattern
        OrbitInput::get('email_like', function ($email) use ($merchants) {
            $merchants->where('merchants.email', 'like', "%$email%");
        });

        // Filter merchant by address1
        OrbitInput::get('address1', function ($address1) use ($merchants) {
            $merchants->whereIn('merchants.address_line1', $address1);
        });

        // Filter merchant by address1 pattern
        OrbitInput::get('address1_like', function ($address1) use ($merchants) {
            $merchants->where('merchants.address_line1', 'like', "%$address1%");
        });

        // Filter merchant by address2
        OrbitInput::get('address2', function ($address2) use ($merchants) {
            $merchants->whereIn('merchants.address_line2', $address2);
        });

        // Filter merchant by address2 pattern
        OrbitInput::get('address2_like', function ($address2) use ($merchants) {
            $merchants->where('merchants.address_line2', 'like', "%$address2%");
        });

        // Filter merchant by address3
        OrbitInput::get('address3', function ($address3) use ($merchants) {
            $merchants->whereIn('merchants.address_line3', $address3);
        });

        // Filter merchant by address3 pattern
        OrbitInput::get('address3_like', function ($address3) use ($merchants) {
            $merchants->where('merchants.address_line3', 'like', "%$address3%");
        });

        // Filter merchant by postal code
        OrbitInput::get('postal_code', function ($postalcode) use ($merchants) {
            $merchants->whereIn('merchants.postal_code', $postalcode);
        });

        // Filter merchant by cityID
        OrbitInput::get('city_id', function ($cityIds) use ($merchants) {
            $merchants->whereIn('merchants.city_id', $cityIds);
        });

        // Filter merchant by city
        OrbitInput::get('city', function ($city) use ($merchants) {
            $merchants->whereIn('merchants.city', $city);
        });

        // Filter merchant by city pattern
        OrbitInput::get('city_like', function ($city) use ($merchants) {
            $merchants->where('merchants.city', 'like', "%$city%");
        });

        // Filter merchant by province
        OrbitInput::get('province', function ($province) use ($merchants) {
            $merchants->whereIn('merchants.province', $province);
        });

        // Filter merchant by province pattern
        OrbitInput::get('province_like', function ($province) use ($merchants) {
            $merchants->where('merchants.province', 'like', "%$province%");
        });

        // Filter merchant by countryID
        OrbitInput::get('country_id', function ($countryId) use ($merchants) {
            $merchants->whereIn('merchants.country_id', $countryId);
        });

        // Filter merchant by country
        OrbitInput::get('country', function ($country) use ($merchants) {
            $merchants->whereIn('merchants.country', $country);
        });

        // Filter merchant by country pattern
        OrbitInput::get('country_like', function ($country) use ($merchants) {
            $merchants->where('merchants.country', 'like', "%$country%");
        });

        // Filter merchant by phone
        OrbitInput::get('phone', function ($phone) use ($merchants) {
            $merchants->whereIn('merchants.phone', $phone);
        });

        // Filter merchant by fax
        OrbitInput::get('fax', function ($fax) use ($merchants) {
            $merchants->whereIn('merchants.fax', $fax);
        });

        // Filter merchant by status
        OrbitInput::get('status', function ($status) use ($merchants) {
            $merchants->whereIn('merchants.status', $status);
        });

        // Filter merchant by currency
        OrbitInput::get('currency', function ($currency) use ($merchants) {
            $merchants->whereIn('merchants.currency', $currency);
        });

        // Filter merchant by contact person firstname
        OrbitInput::get('contact_person_firstname', function ($contact_person_firstname) use ($merchants) {
            $merchants->whereIn('merchants.contact_person_firstname', $contact_person_firstname);
        });

        // Filter merchant by contact person firstname like
        OrbitInput::get('contact_person_firstname_like', function ($contact_person_firstname) use ($merchants) {
            $merchants->where('merchants.contact_person_firstname', 'like', "%$contact_person_firstname%");
        });

        // Filter merchant by contact person lastname
        OrbitInput::get('contact_person_lastname', function ($contact_person_lastname) use ($merchants) {
            $merchants->whereIn('merchants.contact_person_lastname', $contact_person_lastname);
        });

        // Filter merchant by contact person lastname like
        OrbitInput::get('contact_person_lastname_like', function ($contact_person_lastname) use ($merchants) {
            $merchants->where('merchants.contact_person_lastname', 'like', "%$contact_person_lastname%");
        });

        // Filter merchant by contact person position
        OrbitInput::get('contact_person_position', function ($contact_person_position) use ($merchants) {
            $merchants->whereIn('merchants.contact_person_position', $contact_person_position);
        });

        // Filter merchant by contact person position like
        OrbitInput::get('contact_person_position_like', function ($contact_person_position) use ($merchants) {
            $merchants->where('merchants.contact_person_position', 'like', "%$contact_person_position%");
        });

        // Filter merchant by contact person phone
        OrbitInput::get('contact_person_phone', function ($contact_person_phone) use ($merchants) {
            $merchants->whereIn('merchants.contact_person_phone', $contact_person_phone);
        });

        // Filter merchant by contact person phone2
        OrbitInput::get('contact_person_phone2', function ($contact_person_phone2) use ($merchants) {
            $merchants->whereIn('merchants.contact_person_phone2', $contact_person_phone2);
        });

        // Filter merchant by contact person email
        OrbitInput::get('contact_person_email', function ($contact_person_email) use ($merchants) {
            $merchants->whereIn('merchants.contact_person_email', $contact_person_email);
        });

        // Filter merchant by sector of activity
        OrbitInput::get('sector_of_activity', function ($sector_of_activity) use ($merchants) {
            $merchants->whereIn('merchants.sector_of_activity', $sector_of_activity);
        });

        // Filter merchant by url
        OrbitInput::get('url', function ($url) use ($merchants) {
            $merchants->whereIn('merchants.url', $url);
        });

        // Filter merchant by masterbox_number
        OrbitInput::get('masterbox_number', function ($masterbox_number) use ($merchants) {
            $merchants->whereIn('merchants.masterbox_number', $masterbox_number);
        });

        // Filter merchant by slavebox_number
        OrbitInput::get('slavebox_number', function ($slavebox_number) use ($merchants) {
            $merchants->whereIn('merchants.slavebox_number', $slavebox_number);
        });

        // Filter merchant by mobile_default_language
        OrbitInput::get('mobile_default_language', function ($mobile_default_language) use ($merchants) {
            $merchants->whereIn('merchants.mobile_default_language', $mobile_default_language);
        });

        // Filter merchant by pos_language
        OrbitInput::get('pos_language', function ($pos_language) use ($merchants) {
            $merchants->whereIn('merchants.pos_language', $pos_language);
        });

        // Filter merchant by start_date_activity for begin_date
        OrbitInput::get('start_activity_begin_date', function($begindate) use ($merchants)
        {
            $merchants->where('merchants.start_date_activity', '>=', $begindate);
        });

        // Filter merchant by start_date_activity for end_date
        OrbitInput::get('start_activity_end_date', function($enddate) use ($merchants)
        {
            $merchants->where('merchants.start_date_activity', '<=', $enddate);
        });

        // Add new relation based on request
        OrbitInput::get('with', function ($with) use ($merchants) {
            $with = (array) $with;

            // Make sure the with_count also in array format
            $withCount = array();
            OrbitInput::get('with_count', function ($_wcount) use (&$withCount) {
                $withCount = (array) $_wcount;
            });

            foreach ($with as $relation) {
                $merchants->with($relation);

                // Also include number of count if consumer ask it
                if (in_array($relation, $withCount)) {
                    $countRelation = $relation . 'Number';
                    $merchants->with($countRelation);
                }
            }
        });

        $_merchants = clone $merchants;

        // Default sort by
        $sortBy = 'merchants.name';
        // Default sort mode
        $sortMode = 'asc';

        OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
            // Map the sortby request to the real column name
            $sortByMapping = array(
                'merchant_omid'        => 'merchants.omid',
                'registered_date'      => 'merchants.created_at',
                'merchant_name'        => 'merchants.name',
                'merchant_email'       => 'merchants.email',
                'merchant_userid'      => 'merchants.user_id',
                'merchant_description' => 'merchants.description',
                'merchantid'           => 'merchants.merchant_id',
                'merchant_address1'    => 'merchants.address_line1',
                'merchant_address2'    => 'merchants.address_line2',
                'merchant_address3'    => 'merchants.address_line3',
                'merchant_cityid'      => 'merchants.city_id',
                'merchant_city'        => 'merchants.city',
                'merchant_province'    => 'merchants.province',
                'merchant_countryid'   => 'merchants.country_id',
                'merchant_country'     => 'merchants.country',
                'merchant_phone'       => 'merchants.phone',
                'merchant_fax'         => 'merchants.fax',
                'merchant_status'      => 'merchants.status',
                'merchant_currency'    => 'merchants.currency',
                'start_date_activity'  => 'merchants.start_date_activity',
                'total_retailer'       => 'total_retailer',
            );

            $sortBy = $sortByMapping[$_sortBy];
        });

        OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
            if (strtolower($_sortMode) !== 'asc') {
                $sortMode = 'desc';
            }
        });
        $merchants->orderBy($sortBy, $sortMode);

        $totalRec = RecordCounter::create($_merchants)->count();

        $this->prepareUnbufferedQuery();

        $sql = $merchants->toSql();
        $binds = $merchants->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        switch ($mode) {
            case 'csv':
                $filename = 'merchant-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Merchant List', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Merchant', $totalRec, '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Merchant Name', 'Location', 'Starting Date', 'Number of Retailer', 'Retailer', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $location = $this->printLocation($row);
                    $starting_date = $this->printStartingDate($row);
                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%s\n", '', $row->name, $location, $starting_date, $row->merchant_count, $row->retailer_list, $row->status);

                }
                break;

            case 'print':
            default:
                $me = $this;
                $pageTitle = 'Merchant';
                require app_path() . '/views/printer/list-merchant-view.php';
        }
    }


    /**
     * Print gender friendly name.
     *
     * @param $merchant $merchant
     * @return string
     */
    public function printGender($merchant)
    {
        $gender = $merchant->gender;
        $gender = strtolower($gender);
        switch ($gender) {
            case 'm':
                $result = 'male';
                break;

            case 'f':
                $result = 'female';
                break;
            default:
                $result = '';
        }

        return $result;
    }

    /**
     * Print starting date friendly name.
     *
     * @param $merchant $merchant
     * @return string
     */
    public function printStartingDate($merchant)
    {
        if($merchant->start_date_activity==NULL || empty($merchant->start_date_activity))
        {
            $result = "";
        } else {
            $date = $merchant->start_date_activity;
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d M Y',$time);
            $result = $newformat;
        }

        return $result;
    }


    /**
     * Print location friendly name.
     *
     * @param $merchant $merchant
     * @return string
     */
    public function printLocation($merchant)
    {
        if(!empty($merchant->city) && !empty($merchant->country)){
            $result = $merchant->city.','.$merchant->country;
        }
        else if(empty($merchant->city) && !empty($merchant->country)){
            $result = $merchant->country;
        }
        else if(!empty($merchant->city) && empty($merchant->country)){
            $result = $merchant->city;
        }
        else if(empty($merchant->city) && empty($merchant->country)){
            $result = '';
        }
        return utf8_encode($result);
    }

}