<?php
/**
 * An API controller mainly for get cashier details and reports
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;

class CashierAPIController extends ControllerAPI
{
    /**
     * GET - Report of cashier
     * @endpoint '/api/v1/cashier/time-list'
     *
     * @author Yudi Rahono <yudi@rahono.com>
     *
     * List of API Parameters
     * ----------------------
     * @param array     `merchant_id`               (required) - Merchant IDS
     * @param array     `cashier_id`                (optional) - Names of cashier
     * @param array     `cashier_name`              (optional) - Names of cashier
     * @param string    `cashier_name_like`         (optional) - Name of Cashier like
     * @param array     `customer_id`               (optional) - Customer ID
     * @param array     `customer_firstname`        (optional) - Name of Customer
     * @param array     `customer_lastname`         (optional) - Name of Customer
     * @param string    `customer_name_like`        (optional) - Name of Customer Like
     * @param string    `payment_method`            (optional) - payment type
     * @param string    `transactions_code`         (optional) - receipt number
     * @param date      `purchase_date_begin`       (optional) - Purchase Date Begin
     * @param date      `purchase_date_end`         (optional) - Purchase Date End
     * @param integer   `take`                      (optional) - limit
     * @param integer   `skip`                      (optional) - limit offset
     * @param string    `sort_by`                   (optional) - column order by name
     * @param string    `sort_mode`                 (optional) - asc or desc
     * @return Illuminate\Support\Facades\Response
     */
    public function getCashierTimeReport()
    {
        $activity = Activity::portal()
            ->setActivityType('cashier_time_table');
        $user        = 'Guest';

        try {
            $httpCode    = 200;
            $tablePrefix = DB::getTablePrefix();

            Event::fire('orbit.cashier.getcashiertimereport.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.cashier.getcashiertimereport.after.auth', array($this));

            $user = $this->api->user;

            Event::fire('orbit.cashier.getcashiertimereport.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_employee')) {
                Event::fire('orbit.cashier.getcashiertimereport.authz.notallowed', array($this, $user));
                $createCouponLang = Lang::get('validation.orbit.actionlist.get_cashier_time');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createCouponLang));
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.cashier.getcashiertimereport.after.authz', array($this, $user));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.coupon.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.coupon.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $activities  = Activity::select(
                    'activities.user_id as activity_user_id',
                    'activities.full_name as activity_full_name',
                    'activities.role as activity_role',
                    'activities.group as activity_group',
                    DB::raw("date({$tablePrefix}activities.created_at) as activity_date"),
                    DB::raw("min(case activity_name when 'login_ok' then {$tablePrefix}activities.created_at end) as login_at"),
                    DB::raw("date_format(min(case activity_name when 'login_ok' then {$tablePrefix}activities.created_at end), '%H:%i:%s') as login_at_hour"),
                    DB::raw("max(case activity_name when 'logout_ok' then {$tablePrefix}activities.created_at end) as logout_at"),
                    DB::raw("date_format(max(case activity_name when 'logout_ok' then {$tablePrefix}activities.created_at end), '%H:%i:%s')  as logout_at_hour"),
                    DB::raw("timestampdiff(MINUTE, min(case activity_name when 'login_ok' then {$tablePrefix}activities.created_at end), max(case activity_name when 'logout_ok' then {$tablePrefix}activities.created_at end)) as total_time"),
                    'retailer.merchant_id as retailer_id',
                    'retailer.parent_id as merchant_id'
                )
                ->where('role', 'like', 'cashier')
                ->where('group', '=', 'pos')
                ->where(function($q) {
                    $q->where('activity_name', 'like', 'login_ok');
                    $q->orWhere('activity_name', 'like', 'logout_ok');
                })
                ->leftJoin("merchants as {$tablePrefix}retailer", 'retailer.merchant_id', '=', 'activities.location_id')
                ->groupBy('activity_date', 'activity_user_id');
            $activitiesQuery = $activities->getQuery();

            $transactionByDate = Transaction::select(
                    DB::raw('count(distinct transaction_id) as transactions_count'),
                    DB::raw('sum(total_to_pay) as transactions_total'),
                    DB::raw('date(created_at) as transaction_date'),
                    'created_at',
                    'cashier_id',
                    'customer_id'
                )
                ->groupBy('transaction_date', 'cashier_id');
            $transactionByDateQuery = $transactionByDate->getQuery();

            $transactions = DB::table(DB::raw("({$activities->toSql()}) as {$tablePrefix}activities"))
                ->mergeBindings($activitiesQuery)
                ->leftJoin(DB::raw("({$transactionByDate->toSql()}) as {$tablePrefix}transactions"), function ($join) {
                    $join->on('activity_user_id', '=','cashier_id');
                    $join->on('activity_date', '=','transaction_date');
                })
                ->mergeBindings($transactionByDateQuery)
                ->groupBy('activity_date', 'cashier_id');


            OrbitInput::get('merchant_id', function ($merchantId) use ($transactions) {
               $transactions->whereIn('activities.merchant_id', $this->getArray($merchantId));
            });

            // Filter by date from
            OrbitInput::get('purchase_date_begin', function ($dateBegin) use ($transactions) {
                $transactions->where('transactions.created_at', '>', $dateBegin);
            });

            // Filter by date to
            OrbitInput::get('purchase_date_end', function ($dateEnd) use ($transactions) {
                $transactions->where('transactions.created_at', '<', $dateEnd);
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

            $transactions->groupBy(['transactions.cashier_id', 'activities.activity_date']);

            $_transactions = clone $transactions;

            // Default sort by
            $sortBy = 'activity_date';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                $sortByMapping = array(
                    'activity_date' => 'activity_date',
                    'cashier_id'    => 'activity_user_id',
                    'cashier_name'  => 'activity_full_name',
                    'login_at'      => 'login_at_hour',
                    'logout_at'     => 'logout_at_hour',
                    'transactions_count'  => 'transactions_count',
                    'transactions_total'  => 'transactions_total',
                    'total_time' => 'total_time'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });

            $transactions->orderBy($sortBy, $sortMode);

            if ($this->builderOnly)
            {
                return $this->builderObject($transactions, $_transactions);
            }
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
            $transactions->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $transactions)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $transactions->skip($skip);

            $totalTransactions = DB::table(DB::raw("({$_transactions->toSql()}) as sub"))
                ->mergeBindings($_transactions)
                ->count();
            $transactionList    = $transactions->get();

            $data = new stdclass();
            $data->total_records = $totalTransactions;
            $data->last_page     = false;
            $data->returned_records = count($transactionList);
            $data->records = $transactionList;

            // Consider last pages
            if (($totalTransactions - $take) <= $skip)
            {
                $subTotalQuery    = $_transactions->toSql();
                $subTotalBindings = $_transactions;
                $subTotal = DB::table(DB::raw("({$subTotalQuery}) as sub_total"))
                                ->mergeBindings($subTotalBindings)
                                ->select([
                                    DB::raw("sum(sub_total.transactions_count) as transactions_count"),
                                    DB::raw("sum(sub_total.transactions_total) as transactions_total")
                                ])->first();

                $data->last_page  = true;
                $data->sub_total  = $subTotal;
            }

            if ($transactionList === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.attribute');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Registration
            $activity->setUser($user)
                ->setActivityName('cashier_time_report_failed')
                ->setActivityNameLong('Cashier Time Report Failed')
                ->setModuleName('Application')
                ->setNotes($e->getMessage())
                ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Registration
            $activity->setUser($user)
                ->setActivityName('cashier_time_report_failed')
                ->setActivityNameLong('Cashier Time Report Failed')
                ->setModuleName('Application')
                ->setNotes($e->getMessage())
                ->responseFailed();
        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Failed Registration
            $activity->setUser($user)
                ->setActivityName('cashier_time_report_failed')
                ->setActivityNameLong('Cashier Time Report Failed')
                ->setModuleName('Application')
                ->setNotes($e->getMessage())
                ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Failed Registration
            $activity->setUser($user)
                ->setActivityName('cashier_time_report_failed')
                ->setActivityNameLong('Cashier Time Report Failed')
                ->setModuleName('Application')
                ->setNotes($e->getMessage())
                ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
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
