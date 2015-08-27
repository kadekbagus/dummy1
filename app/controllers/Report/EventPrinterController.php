<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
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

        // Builder object
        $events = EventModel::select(DB::raw($prefix . "events.*"),
                                     DB::raw('retailer.*'),
                                     DB::raw('product.*'),
                                     DB::raw('cat1.category_name as family_name1'),
                                     DB::raw('cat2.category_name as family_name2'),
                                     DB::raw('cat3.category_name as family_name3'),
                                     DB::raw('cat4.category_name as family_name4'),
                                     DB::raw('cat5.category_name as family_name5'),
                                     DB::raw("CASE link_object_type
                                            WHEN 'widget' THEN 'page'
                                                ELSE link_object_type
                                            END AS 'event_redirected_to'
                                        "),
                                     "promotions.promotion_name as promotion_name"
                                     )
                            ->leftJoin(DB::raw("( select e.event_id as event_id2, 
                                            CASE
                                            WHEN
                                                (e.is_all_retailer = 'Y')
                                            THEN
                                                'All Retailer' 
                                            ELSE 
                                                GROUP_CONCAT(r.name SEPARATOR ', ')
                                            END AS retailer_list
                                        from {$prefix}events e
                                        inner join {$prefix}event_retailer er on er.event_id = e.event_id
                                        inner join {$prefix}merchants r on r.merchant_id = er.retailer_id
                                        where e.merchant_id = 12 and e.status != 'deleted'
                                        group by e.event_id 
                                      )AS retailer"
                                      ), function ($q) {
                                        $q->on( DB::raw('retailer.event_id2'), '=', 'events.event_id' );
                                })
                            ->leftJoin(DB::raw("( select e.event_id as event_id3, 
                                            CASE
                                            WHEN
                                                (e.is_all_product = 'Y')
                                            THEN
                                                'All Product' 
                                            ELSE 
                                                GROUP_CONCAT(p.`product_name` SEPARATOR ', ')
                                            END AS product_name
                                        from {$prefix}events e
                                        inner join {$prefix}event_product ep on ep.event_id = e.event_id
                                        inner join {$prefix}products p on p.product_id = ep.product_id
                                        where e.merchant_id = 12 and e.status != 'deleted'
                                        group by e.event_id
                                        ) AS product"
                                       ), function ($q) {
                                            $q->on( DB::raw('product.event_id3'), '=', 'events.event_id' );
                                })
                            ->leftJoin('event_retailer', 'event_retailer.event_id', '=', 'events.event_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'event_retailer.retailer_id')
                            ->leftJoin(DB::raw("{$prefix}promotions"), function($join) {
                                $join->on('promotions.promotion_id', '=', 'events.link_object_id1');
                                $join->on('events.link_object_type', '=', DB::raw("'promotion'"));
                            })
                            ->leftJoin(DB::raw("{$prefix}products"), function($join) {
                                $join->on('products.product_id', '=', 'events.link_object_id1');
                                $join->on('events.link_object_type', '=', DB::raw("'product'"));
                            })
                            ->leftJoin(DB::raw("{$prefix}categories cat1"), function($join) {
                                $join->on(DB::raw('cat1.category_id'), '=', 'events.link_object_id1');
                                $join->on('events.link_object_type', '=', DB::raw("'family'"));
                            })
                            ->leftJoin(DB::raw("{$prefix}categories cat2"), function($join) {
                                $join->on(DB::raw('cat2.category_id'), '=', 'events.link_object_id2');
                                $join->on('events.link_object_type', '=', DB::raw("'family'"));
                            }) 
                            ->leftJoin(DB::raw("{$prefix}categories cat3"), function($join) {
                                $join->on(DB::raw('cat3.category_id'), '=', 'events.link_object_id3');
                                $join->on('events.link_object_type', '=', DB::raw("'family'"));
                            })
                            ->leftJoin(DB::raw("{$prefix}categories cat4"), function($join) {
                                $join->on(DB::raw('cat4.category_id'), '=', 'events.link_object_id4');
                                $join->on('events.link_object_type', '=', DB::raw("'family'"));
                            })
                            ->leftJoin(DB::raw("{$prefix}categories cat5"), function($join) {
                                $join->on(DB::raw('cat5.category_id'), '=', 'events.link_object_id5');
                                $join->on('events.link_object_type', '=', DB::raw("'family'"));
                            })
                            ->groupBy('events.event_id')
                            ->excludeDeleted('events');

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
            $events->where('events.end_date', '>=', $begindate)
                   ->where('events.is_permanent', 'N');
        });

        // Filter event by end_date for end
        OrbitInput::get('expiration_end_date', function($enddate) use ($events)
        {
            $events->where('events.end_date', '<=', $enddate)
                   ->where('events.is_permanent', 'N');
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

        // Clone the query builder which still does not include the take,
        // skip, and order by
        $_events = clone $events;

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
                'status'            => 'events.status',
                'event_redirected_to' => 'event_redirected_to'
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

        $pageTitle = 'Event';
        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle));

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Event List', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Event', $totalRec, '', '', '', '','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Name', 'Expiration Date & Time', 'Retailer', 'Event Type', 'Event Redirected To', 'Event Link', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $expiration_date = $this->printExpirationDate($row);
                    $event_link = $this->printEventLink($row);
                    $link_object_type = $this->printLinkObjectType($row);
                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n", '', $row->event_name, $expiration_date, $row->retailer_list, $row->event_type, $link_object_type, $event_link, $row->status);
                }
                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-event-view.php';
        }
    }



    /**
     * Print expiration date type friendly name.
     *
     * @param $event $event
     * @return string
     */
    public function printExpirationDate($event)
    {
        switch ($event->is_permanent) {
            case 'Y':
                $result = 'Permanent';
                break;

            case 'N':
            default:
                $date = $event->end_date;
                if($date==NULL || empty($date)){
                    $result = "";
                } else {
                    $date = explode(' ',$date);
                    $time = strtotime($date[0]);
                    $newformat = date('d F Y',$time);
                    $result = $newformat.' '.$date[1];
                }
        }

        return $result;
    }


    /**
     * Print event link friendly name.
     *
     * @param $event $event
     * @return string
     */
    public function printEventLink($event)
    {
        switch($event->link_object_type){
            case 'promotion':
                $result = $event->promotion_name;
                break;
            case 'product':
                $result = $event->product_name;
                break;
            case 'family':
                $families = [];
                $families[] = $event->family_name1;
                $families[] = $event->family_name2;
                $families[] = $event->family_name3;
                $families[] = $event->family_name4;
                $families[] = $event->family_name5;

                // Remove empty array
                $families = array_filter($families);

                // Join with comma separator
                $families = implode(', ', $families);

                $result = $families;
                break;
            default:
                $result = str_replace("_"," ",$event->widget_object_type); 
        }
        
        return $result;
    }


    /**
     * Print link object type friendly name.
     *
     * @param $event $event
     * @return string
     */
    public function printLinkObjectType($event)
    {
        if($event->link_object_type === 'widget'){
            $result = 'page';
        } else {
            $result = $event->link_object_type;
        }
        return $result;
    }

}