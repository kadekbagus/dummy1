<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use EventModel;

class EventPrinterController extends DataPrinterController
{
    public function getEventPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $now = date('Y-m-d H:i:s');

        // Get the maximum record
        // $maxRecord = (int) Config::get('orbit.pagination.event.max_record');
        // if ($maxRecord <= 0) {
        //     // Fallback
        //     $maxRecord = (int) Config::get('orbit.pagination.max_record');
        //     if ($maxRecord <= 0) {
        //         $maxRecord = 20;
        //     }
        // }
        // // Get default per page (take)
        // $perPage = (int) Config::get('orbit.pagination.event.per_page');
        // if ($perPage <= 0) {
        //     // Fallback
        //     $perPage = (int) Config::get('orbit.pagination.per_page');
        //     if ($perPage <= 0) {
        //         $perPage = 20;
        //     }
        // }

        // Builder object
        $events = EventModel::
                            select(DB::raw($prefix . "events.*"), DB::raw("GROUP_CONCAT(`{$prefix}merchants`.`name`,' ',`{$prefix}merchants`.`city` SEPARATOR ' , ') as retailer_list"))
                            ->leftJoin('event_retailer', 'event_retailer.event_id', '=', 'events.event_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'event_retailer.retailer_id')
                            ->groupBy('events.event_id');

        // Filter event by Ids
        OrbitInput::get('event_id', function($eventIds) use ($events)
        {
            $events->whereIn('events.event_id', $eventIds);
        });

        // Filter event by merchant Ids
        OrbitInput::get('merchant_id', function ($merchantIds) use ($events) {
            $events->whereIn('events.merchant_id', $merchantIds);
        });

        // Filter event by event name
        OrbitInput::get('event_name', function($eventname) use ($events)
        {
            $events->whereIn('events.event_name', $eventname);
        });

        // Filter event by matching event name pattern
        OrbitInput::get('event_name_like', function($eventname) use ($events)
        {
            $events->where('events.event_name', 'like', "%$eventname%");
        });

        // Filter event by event type
        OrbitInput::get('event_type', function($eventTypes) use ($events)
        {
            $events->whereIn('events.event_type', $eventTypes);
        });

        // Filter event by description
        OrbitInput::get('description', function($description) use ($events)
        {
            $events->whereIn('events.description', $description);
        });

        // Filter event by matching description pattern
        OrbitInput::get('description_like', function($description) use ($events)
        {
            $events->where('events.description', 'like', "%$description%");
        });

        // Filter event by begin date
        OrbitInput::get('begin_date', function($begindate) use ($events)
        {
            $events->where('events.begin_date', '<=', $begindate);
        });

        // Filter event by end date
        OrbitInput::get('end_date', function($enddate) use ($events)
        {
            $events->where('events.end_date', '>=', $enddate);
        });

        // Filter event by end_date for begin
        OrbitInput::get('expiration_begin_date', function($begindate) use ($events)
        {
            $events->where(function ($q) use ($begindate) {
                $q->where('events.end_date', '>=', $begindate)
                  ->orWhere('events.is_permanent', 'Y');
            });
        });

        // Filter event by end_date for end
        OrbitInput::get('expiration_end_date', function($enddate) use ($events)
        {
            $events->where(function ($q) use ($enddate) {
                $q->where('events.end_date', '<=', $enddate)
                  ->orWhere('events.is_permanent', 'Y');
            });
        });

        // Filter event by is permanent
        OrbitInput::get('is_permanent', function ($ispermanent) use ($events) {
            $events->whereIn('events.is_permanent', $ispermanent);
        });

        // Filter event by status
        OrbitInput::get('status', function ($statuses) use ($events) {
            $events->whereIn('events.status', $statuses);
        });

        // Filter event by link object type
        OrbitInput::get('link_object_type', function ($linkObjectTypes) use ($events) {
            $events->whereIn('events.link_object_type', $linkObjectTypes);
        });

        // Filter event by link object id1
        OrbitInput::get('link_object_id1', function ($linkObjectId1s) use ($events) {
            $events->whereIn('events.link_object_id1', $linkObjectId1s);
        });

        // Filter event by link object id2
        OrbitInput::get('link_object_id2', function ($linkObjectId2s) use ($events) {
            $events->whereIn('events.link_object_id2', $linkObjectId2s);
        });

        // Filter event by link object id3
        OrbitInput::get('link_object_id3', function ($linkObjectId3s) use ($events) {
            $events->whereIn('events.link_object_id3', $linkObjectId3s);
        });

        // Filter event by link object id4
        OrbitInput::get('link_object_id4', function ($linkObjectId4s) use ($events) {
            $events->whereIn('events.link_object_id4', $linkObjectId4s);
        });

        // Filter event by link object id5
        OrbitInput::get('link_object_id5', function ($linkObjectId5s) use ($events) {
            $events->whereIn('events.link_object_id5', $linkObjectId5s);
        });

        // Filter event by widget object type
        OrbitInput::get('widget_object_type', function ($widgetObjectTypes) use ($events) {
            $events->whereIn('events.widget_object_type', $widgetObjectTypes);
        });

        // Filter event retailer by retailer id
        OrbitInput::get('retailer_id', function ($retailerIds) use ($events) {
            $events->whereHas('retailers', function($q) use ($retailerIds) {
                $q->whereIn('retailer_id', $retailerIds);
            });
        });

        // Add new relation based on request
        OrbitInput::get('with', function ($with) use ($events) {
            $with = (array) $with;

            foreach ($with as $relation) {
                if ($relation === 'retailers') {
                    $events->with('retailers');
                } elseif ($relation === 'product') {
                    $events->with('linkproduct');
                } elseif ($relation === 'family') {
                    $events->with('linkcategory1', 'linkcategory2', 'linkcategory3', 'linkcategory4', 'linkcategory5');
                } elseif ($relation === 'promotion') {
                    $events->with('linkpromotion');
                } elseif ($relation === 'widget') {
                    $events->with('linkwidget');
                }
            }
        });

        $_events = clone $events;

        // Get the take args
        // $take = $perPage;
        // OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
        //     if ($_take > $maxRecord) {
        //         $_take = $maxRecord;
        //     }
        //     $take = $_take;

        //     if ((int)$take <= 0) {
        //         $take = $maxRecord;
        //     }
        // });
        // $events->take($take);

        // $skip = 0;
        // OrbitInput::get('skip', function($_skip) use (&$skip, $events)
        // {
        //     if ($_skip < 0) {
        //         $_skip = 0;
        //     }

        //     $skip = $_skip;
        // });
        // $events->skip($skip);

        // Default sort by
        $sortBy = 'events.event_name';
        // Default sort mode
        $sortMode = 'asc';

        OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
        {
            // Map the sortby request to the real column name
            $sortByMapping = array(
                'registered_date'   => 'events.created_at',
                'event_name'        => 'events.event_name',
                'event_type'        => 'events.event_type',
                'description'       => 'events.description',
                'begin_date'        => 'events.begin_date',
                'end_date'          => 'events.end_date',
                'is_permanent'      => 'events.is_permanent',
                'status'            => 'events.status'
            );

            $sortBy = $sortByMapping[$_sortBy];
        });

        OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
        {
            if (strtolower($_sortMode) !== 'asc') {
                $sortMode = 'desc';
            }
        });
        $events->orderBy($sortBy, $sortMode);

        $totalRec = RecordCounter::create($_events)->count();

        $this->prepareUnbufferedQuery();

        $sql = $events->toSql();
        $binds = $events->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        switch ($mode) {
            case 'csv':
                $filename = 'event-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Event List', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Event', $totalRec, '', '', '', '','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Name', 'Expiration Date & Time', 'Retailer', 'Event Type', 'Event Redirected To', 'Event Link', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $expiration_date = $this->printExpirationDate($row);
                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n", '', $row->event_name, $expiration_date, '', $row->event_type, $row->link_object_type, $row->widget_object_type, $row->status);
                }
                break;

            case 'print':
            default:
                $me = $this;
                $pageTitle = 'Event';
                require app_path() . '/views/printer/list-event-view.php';
        }
    }



    /**
     * Print expiration date type friendly name.
     *
     * @param $promotion $promotion
     * @return string
     */
    public function printExpirationDate($event)
    {
        $return = '';
        switch ($event->is_permanent) {
            case 'Y':
                $result = 'Permanent';
                break;

            case 'N':
            default:
                $date = $event->end_date;
                $date = explode(' ',$date);
                $time = strtotime($date[0]);
                $newformat = date('d M Y',$time);
                $result = $newformat;
        }

        return $result;
    }
}