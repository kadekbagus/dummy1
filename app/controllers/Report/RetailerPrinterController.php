<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Retailer;

class RetailerPrinterController extends DataPrinterController
{
    public function getRetailerPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $now = date('Y-m-d H:i:s');

        // Builder object
        $retailers = Retailer::excludeDeleted('merchants')
                            ->allowedForUser($user)
                            ->select('merchants.*', DB::raw('m.name as merchant_name'))
                            ->join('merchants AS m', DB::raw('m.merchant_id'), '=', 'merchants.parent_id');

        // Filter retailer by Ids
        OrbitInput::get('merchant_id', function($merchantIds) use ($retailers)
        {
            $retailers->whereIn('merchants.merchant_id', $merchantIds);
        });

        // Filter retailer by Ids
        OrbitInput::get('user_id', function($userIds) use ($retailers)
        {
            $retailers->whereIn('merchants.user_id', $userIds);
        });

        // Filter retailer by name
        OrbitInput::get('name', function($name) use ($retailers)
        {
            $retailers->whereIn('merchants.name', $name);
        });

        // Filter retailer by matching name pattern
        OrbitInput::get('name_like', function($name) use ($retailers)
        {
            $retailers->where('merchants.name', 'like', "%$name%");
        });

        // Filter retailer by description
        OrbitInput::get('description', function($description) use ($retailers)
        {
            $retailers->whereIn('merchants.description', $description);
        });

        // Filter retailer by description pattern
        OrbitInput::get('description_like', function($description) use ($retailers)
        {
            $description->where('merchants.description', 'like', "%$description%");
        });

        // Filter retailer by their email
        OrbitInput::get('email', function($email) use ($retailers)
        {
            $retailers->whereIn('merchants.email', $email);
        });

        // Filter retailer by address1
        OrbitInput::get('address1', function($address1) use ($retailers)
        {
            $retailers->where('merchants.address_line1', "%$address1%");
        });

        // Filter retailer by address1 pattern
        OrbitInput::get('address1', function($address1) use ($retailers)
        {
            $retailers->where('merchants.address_line1', 'like', "%$address1%");
        });

        // Filter retailer by address2
        OrbitInput::get('address2', function($address2) use ($retailers)
        {
            $retailers->where('merchants.address_line2', "%$address2%");
        });

        // Filter retailer by address2 pattern
        OrbitInput::get('address2', function($address2) use ($retailers)
        {
            $retailers->where('merchants.address_line2', 'like', "%$address2%");
        });

         // Filter retailer by address3
        OrbitInput::get('address3', function($address3) use ($retailers)
        {
            $retailers->where('merchants.address_line3', "%$address3%");
        });

         // Filter retailer by address3 pattern
        OrbitInput::get('address3', function($address3) use ($retailers)
        {
            $retailers->where('merchants.address_line3', 'like', "%$address3%");
        });

        // Filter retailer by postal code
        OrbitInput::get('postal_code', function ($postalcode) use ($retailers) {
            $retailers->whereIn('merchants.postal_code', $postalcode);
        });

         // Filter retailer by cityID
        OrbitInput::get('city_id', function($cityIds) use ($retailers)
        {
            $retailers->whereIn('merchants.city_id', $cityIds);
        });

         // Filter retailer by city
        OrbitInput::get('city', function($city) use ($retailers)
        {
            $retailers->whereIn('merchants.city', $city);
        });

         // Filter retailer by city pattern
        OrbitInput::get('city_like', function($city) use ($retailers)
        {
            $retailers->where('merchants.city', 'like', "%$city%");
        });

        // Filter retailer by province
        OrbitInput::get('province', function ($province) use ($retailers) {
            $retailers->whereIn('merchants.province', $province);
        });

        // Filter retailer by province pattern
        OrbitInput::get('province_like', function ($province) use ($retailers) {
            $retailers->where('merchants.province', 'like', "%$province%");
        });

         // Filter retailer by countryID
        OrbitInput::get('country_id', function($countryId) use ($retailers)
        {
            $retailers->whereIn('merchants.country_id', $countryId);
        });

         // Filter retailer by country
        OrbitInput::get('country', function($country) use ($retailers)
        {
            $retailers->whereIn('merchants.country', $country);
        });

         // Filter retailer by country pattern
        OrbitInput::get('country_like', function($country) use ($retailers)
        {
            $retailers->where('merchants.country', 'like', "%$country%");
        });

         // Filter retailer by phone
        OrbitInput::get('phone', function($phone) use ($retailers)
        {
            $retailers->whereIn('merchants.phone', $phone);
        });

         // Filter retailer by fax
        OrbitInput::get('fax', function($fax) use ($retailers)
        {
            $retailers->whereIn('merchants.fax', $fax);
        });

         // Filter retailer by phone
        OrbitInput::get('phone', function($phone) use ($retailers)
        {
            $retailers->whereIn('merchants.phone', $phone);
        });

         // Filter retailer by status
        OrbitInput::get('status', function($status) use ($retailers)
        {
            $retailers->whereIn('merchants.status', $status);
        });

        // Filter retailer by currency
        OrbitInput::get('currency', function($currency) use ($retailers)
        {
            $retailers->whereIn('merchants.currency', $currency);
        });

        // Filter retailer by contact person firstname
        OrbitInput::get('contact_person_firstname', function ($contact_person_firstname) use ($retailers) {
            $retailers->whereIn('merchants.contact_person_firstname', $contact_person_firstname);
        });

        // Filter retailer by contact person firstname like
        OrbitInput::get('contact_person_firstname_like', function ($contact_person_firstname) use ($retailers) {
            $retailers->where('merchants.contact_person_firstname', 'like', "%$contact_person_firstname%");
        });

        // Filter retailer by contact person lastname
        OrbitInput::get('contact_person_lastname', function ($contact_person_lastname) use ($retailers) {
            $retailers->whereIn('merchants.contact_person_lastname', $contact_person_lastname);
        });

        // Filter retailer by contact person lastname like
        OrbitInput::get('contact_person_lastname_like', function ($contact_person_lastname) use ($retailers) {
            $retailers->where('merchants.contact_person_lastname', 'like', "%$contact_person_lastname%");
        });

        // Filter retailer by contact person position
        OrbitInput::get('contact_person_position', function ($contact_person_position) use ($retailers) {
            $retailers->whereIn('merchants.contact_person_position', $contact_person_position);
        });

        // Filter retailer by contact person position like
        OrbitInput::get('contact_person_position_like', function ($contact_person_position) use ($retailers) {
            $retailers->where('merchants.contact_person_position', 'like', "%$contact_person_position%");
        });

        // Filter retailer by contact person phone
        OrbitInput::get('contact_person_phone', function ($contact_person_phone) use ($retailers) {
            $retailers->whereIn('merchants.contact_person_phone', $contact_person_phone);
        });

        // Filter retailer by contact person phone2
        OrbitInput::get('contact_person_phone2', function ($contact_person_phone2) use ($retailers) {
            $retailers->whereIn('merchants.contact_person_phone2', $contact_person_phone2);
        });

        // Filter retailer by contact person email
        OrbitInput::get('contact_person_email', function ($contact_person_email) use ($retailers) {
            $retailers->whereIn('merchants.contact_person_email', $contact_person_email);
        });

        // Filter retailer by sector of activity
        OrbitInput::get('sector_of_activity', function ($sector_of_activity) use ($retailers) {
            $retailers->whereIn('merchants.sector_of_activity', $sector_of_activity);
        });

        // Filter retailer by url
        OrbitInput::get('url', function ($url) use ($retailers) {
            $retailers->whereIn('merchants.url', $url);
        });

        // Filter retailer by masterbox_number
        OrbitInput::get('masterbox_number', function ($masterbox_number) use ($retailers) {
            $retailers->whereIn('merchants.masterbox_number', $masterbox_number);
        });

        // Filter retailer by slavebox_number
        OrbitInput::get('slavebox_number', function ($slavebox_number) use ($retailers) {
            $retailers->whereIn('merchants.slavebox_number', $slavebox_number);
        });

        // Filter retailer by parent_id
        OrbitInput::get('parent_id', function($parentIds) use ($retailers)
        {
            $retailers->whereIn('merchants.parent_id', $parentIds);
        });

        // Filter retailer by matching merchant name pattern
        OrbitInput::get('merchant_name_like', function($name) use ($retailers)
        {
            $retailers->where(DB::raw('m.name'), 'like', "%$name%");
        });

        // Filter retailer by start_date_activity for begin_date
        OrbitInput::get('start_activity_begin_date', function($begindate) use ($retailers)
        {
            $retailers->where('merchants.start_date_activity', '>=', $begindate);
        });

        // Filter retailer by start_date_activity for end_date
        OrbitInput::get('start_activity_end_date', function($enddate) use ($retailers)
        {
            $retailers->where('merchants.start_date_activity', '<=', $enddate);
        });

        // Add new relation based on request
        OrbitInput::get('with', function($with) use ($retailers) {
            $with = (array)$with;

            // Make sure the with_count also in array format
            $withCount = array();
            OrbitInput::get('with_count', function($_wcount) use (&$withCount) {
                $withCount = (array)$_wcount;
            });

            foreach ($with as $relation) {
                $retailers->with($relation);

                // Also include number of count if consumer ask it
                if (in_array($relation, $withCount)) {
                    $countRelation = $relation . 'Number';
                    $retailers->with($countRelation);
                }
            }
        });

        // Clone the query builder which still does not include the take,
        // skip, and order by
        $_retailers = clone $retailers;

        // Default sort by
        $sortBy = 'merchants.name';
        // Default sort mode
        $sortMode = 'asc';

        OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
        {
               // Map the sortby request to the real column name
              $sortByMapping = array(
              'orid' => 'merchants.orid',
              'registered_date' => 'merchants.created_at',
              'retailer_name' => 'merchants.name',
              'retailer_email' => 'merchants.email',
              'retailer_userid' => 'merchants.user_id',
              'retailer_description' => 'merchants.description',
              'retailerid' => 'merchants.merchant_id',
              'retailer_address1' => 'merchants.address_line1',
              'retailer_address2' => 'merchants.address_line2',
              'retailer_address3' => 'merchants.address_line3',
              'retailer_cityid' => 'merchants.city_id',
              'retailer_city' => 'merchants.city',
              'retailer_province' => 'merchants.province',
              'retailer_countryid' => 'merchants.country_id',
              'retailer_country' => 'merchants.country',
              'retailer_phone' => 'merchants.phone',
              'retailer_fax' => 'merchants.fax',
              'retailer_status' => 'merchants.status',
              'retailer_currency' => 'merchants.currency',
              'contact_person_firstname' => 'merchants.contact_person_firstname',
              'merchant_name' => 'merchant_name',
              );

            $sortBy = $sortByMapping[$_sortBy];
        });

        OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
        {
            if (strtolower($_sortMode) !== 'asc') {
                $sortMode = 'desc';
            }
        });
        $retailers->orderBy($sortBy, $sortMode);

        $totalRec = RecordCounter::create($_retailers)->count();

        $this->prepareUnbufferedQuery();

        $sql = $retailers->toSql();
        $binds = $retailers->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        switch ($mode) {
            case 'csv':
                $filename = 'retailer-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                printf("%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s\n", '', 'Retailer List', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s\n", '', 'Total Retailer', $totalRec, '', '', '');

                printf("%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s\n", '', 'Retailer Name', 'Contact Person (Name)', 'Retailer ID', 'Merchant Name', 'Status');
                printf("%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $contact = $this->printContactPersonName($row);
                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n", '', $row->name, $contact, $row->merchant_id, $row->merchant_name, $row->status);

                }
                break;

            case 'print':
            default:
                $me = $this;
                $pageTitle = 'Retailer';
                require app_path() . '/views/printer/list-retailer-view.php';
        }
    }


    /**
     * Print gender friendly name.
     *
     * @param $retailer $retailer
     * @return string
     */
    public function printGender($retailer)
    {
        $return = '';
        $gender = $retailer->gender;
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
     * @param $retailer $retailer
     * @return string
     */
    public function printStartingDate($retailer)
    {
        $return = '';

        $date = $retailer->start_date_activity;
        $date = explode(' ',$date);
        $time = strtotime($date[0]);
        $newformat = date('d M Y',$time);
        $result = $newformat;

        return $result;
    }


    /**
     * Print contact person name friendly name.
     *
     * @param $retailer $retailer
     * @return string
     */
    public function printContactPersonName($retailer)
    {
        $return = '';
        $result = $retailer->contact_person_firstname . ' ' . $retailer->contact_person_lastname;
        return $result;
    }

}