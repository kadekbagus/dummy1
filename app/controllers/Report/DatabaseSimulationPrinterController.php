<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Activity;

class DatabaseSimulationPrinterController extends DataPrinterController
{
    public function getDatabaseSimulationPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $now = date('Y-m-d H:i:s');

        // Builder object
        $with = array('user', 'retailer', 'promotion', 'coupon', 'product', 'productVariant', 'children', 'staff');
        // Include other relationship
        OrbitInput::get('with', function($_with) use (&$with) {
            $with = array_merge($with, $_with);
        });

        $activities = Activity::with($with)->select('activities.*',
                                                    DB::Raw("DATE_FORMAT({$prefix}activities.created_at, '%d-%m-%Y %H:%i:%s') as created_at_reverse"));

        // Filter by ids
        OrbitInput::get('id', function($activityIds) use ($activities) {
            $activities->whereIn('activities.activity_id', $activityIds);
        });

        // Filter by activity type
        OrbitInput::get('activity_types', function($types) use ($activities) {
            $activities->whereIn('activities.activity_type', $types);
        });

        // Filter by activity name
        if (! empty($_GET['activity_names'])) {
            OrbitInput::get('activity_names', function($names) use ($activities) {
                $activities->whereIn('activities.activity_name', $names);
            });
        } else {
            $activities->whereNotNull('activities.activity_name');
        }

        // Filter by activity name long
        OrbitInput::get('activity_name_longs', function($nameLongs) use ($activities) {
            $activities->whereIn('activities.activity_name_long', $nameLongs);
        });

        // Filter by merchant ids
        OrbitInput::get('merchant_ids', function($merchantIds) use ($activities) {
            $activities->merchantIds($merchantIds);
        });

        // Filter by retailer ids
        OrbitInput::get('retailer_ids', function($retailerIds) use ($activities) {
            $activities->whereIn('activities.location_id', $retailerIds);
        });

        // Filter by user emails
        OrbitInput::get('user_emails', function($emails) use ($activities) {
            $activities->whereIn('activities.user_email', $emails);
        });

        // Filter by groups
        if (! empty($_GET['groups'])) {
            OrbitInput::get('groups', function($groups) use ($activities) {
                $activities->whereIn('activities.group', $groups);
            });
        } else {
            $activities->whereIn('activities.group', ['mobile-ci', 'pos']);
        }

        // Filter by role_ids
        OrbitInput::get('role_ids', function($roleIds) use ($activities) {
            $activities->whereIn('activities.role_id', $roleIds);
        });

        // Filter by object ids
        OrbitInput::get('object_ids', function($objectIds) use ($activities) {
            $activities->whereIn('activities.object_id', $roleIds);
        });

        // Filter by object names
        OrbitInput::get('object_names', function($names) use ($activities) {
            $activities->whereIn('activities.object_name', $names);
        });

        OrbitInput::get('product_names', function($names) use ($activities) {
            $activities->whereIn('activities.product_name', $names);
        });

        OrbitInput::get('promotion_names', function($names) use ($activities) {
            $activities->whereIn('activities.promotion_name', $names);
        });

        OrbitInput::get('coupon_names', function($names) use ($activities) {
            $activities->whereIn('activities.coupon_name', $names);
        });

        OrbitInput::get('event_names', function($names) use ($activities) {
            $activities->whereIn('activities.event_name', $names);
        });

        // Filter by staff Ids
        OrbitInput::get('staff_ids', function($staff) use ($activities) {
            $activities->whereIn('activities.staff_id', $staff);
        });

        // Filter by status
        OrbitInput::get('status', function ($status) use ($activities) {
            $activities->whereIn('activities.status', $status);
        });

        // Filter by status
        OrbitInput::get('ip_address_like', function ($ip) use ($activities) {
            $activities->where('activities.ip_address', 'like', "%{$ip}%");
        });

        OrbitInput::get('user_agent_like', function ($ua) use ($activities) {
            $activities->where('activities.user_agent', 'like', "%{$ua}%");
        });

        OrbitInput::get('full_name_like', function ($name) use ($activities) {
            $activities->where('activities.full_name', 'like', "%{$name}%");
        });

        if (! empty($_GET['response_statuses'])) {
            // Filter by response status
            OrbitInput::get('response_statuses', function ($status) use ($activities) {
                $activities->whereIn('activities.response_status', $status);
            });
        } else {
            $activities->whereIn('activities.response_status', ['OK']);
        }

        // Filter by start date
        OrbitInput::get('start_date', function($_start) use ($activities) {
            $activities->where('activities.created_at', '>=', $_start);
        });

        // Filter by end date
        OrbitInput::get('end_date', function($_end) use ($activities, $user) {
            $activities->where('activities.created_at', '<=', $_end);
        });

        // Only shows activities which belongs to this merchant
        if ($user->isSuperAdmin() !== TRUE) {
            $locationIds = $user->getMyRetailerIds();

            if (empty($locationIds)) {
                // Just to make sure it query the wrong one.
                $locationIds = [-1];
            }

            // Filter by user location id
            $activities->whereIn('activities.location_id', $locationIds);
        } else {
            // Filter by user ids, Super Admin could filter all
            OrbitInput::get('user_ids', function($userIds) use ($activities) {
                $activities->whereIn('activities.user_id', $userIds);
            });

            // Filter by user location id
            OrbitInput::get('location_ids', function($locationIds) use ($activities) {
                $activities->whereIn('activities.location_id', $locationIds);
            });
        }

        // Clone the query builder which still does not include the take,
        // skip, and order by
        $_activities = clone $activities;

        // Default sort by
        $sortBy = 'activities.created_at';
        // Default sort mode
        $sortMode = 'desc';

        OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
            // Map the sortby request to the real column name
            $sortByMapping = array(
                'id'                => 'activities.activity_id',
                'ip_address'        => 'activities.ip_address',
                'created'           => 'activities.created_at',
                'registered_at'     => 'activities.created_at',
                'email'             => 'activities.user_email',
                'full_name'         => 'activities.full_name',
                'object_name'       => 'activities.object_name',
                'product_name'      => 'activities.product_name',
                'coupon_name'       => 'activities.coupon_name',
                'promotion_name'    => 'activities.promotion_name',
                'event_name'        => 'activities.event_name',
                'action_name'       => 'activities.activity_name',
                'action_name_long'  => 'activities.activity_name_long',
                'activity_type'     => 'activities.activity_type',
                'staff_name'        => 'activities.staff_name',
                'gender'            => 'activities.gender',
                'module_name'       => 'activities.module_name',
            );

            if (array_key_exists($_sortBy, $sortByMapping)) {
                $sortBy = $sortByMapping[$_sortBy];
            }
        });

        OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
            if (strtolower($_sortMode) !== 'desc') {
                $sortMode = 'asc';
            }
        });
        $activities->orderBy($sortBy, $sortMode);


        $totalRec = RecordCounter::create($_activities)->count();

        $this->prepareUnbufferedQuery();

        $sql = $activities->toSql();
        $binds = $activities->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        switch ($mode) {
            case 'csv':
                $filename = 'databasesimulation-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Database Simulation List', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Database Simulation', $totalRec, '', '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Customer', 'Gender', 'Date & Time', 'Origin', 'Module', 'Action', 'Product', 'Promotions', 'Coupon', 'Cashier');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');
                
                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $gender = $this->printGender($row);
                    $date = $this->printDateTime($row);
                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n", $count, $row->user_email, $gender, $date, strtoupper($row->group), $row->module_name, $row->activity_name_long, $row->product_name, $row->promotion_name, $row->coupon_name, $row->staff_name);
                    $count++;

                }
                break;

            case 'print':
            default:
                $me = $this;
                $pageTitle = 'Database Simulation';
                require app_path() . '/views/printer/list-databasesimulation-view.php';
        }
    }


    /**
     * Print gender friendly name.
     *
     * @param $databasesimulation $databasesimulation
     * @return string
     */
    public function printGender($databasesimulation)
    {
        $return = '';
        $gender = $databasesimulation->gender;
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
     * Print date and time friendly name.
     *
     * @param $databasesimulation $databasesimulation
     * @return string
     */
    public function printDateTime($databasesimulation)
    {
        $return = '';
        if($databasesimulation->created_at==NULL || empty($databasesimulation->created_at)){
            $result = "";
        }
        else {
            $date = $databasesimulation->created_at;
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d M Y',$time);
            $result = $newformat.' '.$date[1];
        }

        return $result;
    }

}