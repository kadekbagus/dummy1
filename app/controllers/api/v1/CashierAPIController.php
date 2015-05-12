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
     *
     * @author Yudi Rahono <yudi@rahono.com>
     *
     * List of API Parameters
     * ----------------------
     * @param array     `user_id`                   (optional) - IDs of country
     * @param array     `cashier_id`                (optional) - Names of cashier
     * @param array     `cashier_name`              (optional) - Names of cashier
     * @param string    `cashier_name_like`         (optional) - Name of Cashier like
     * @param array     `customer_firstname`        (optional) - Name of Customer
     * @param array     `customer_lastname`         (optional) - Name of Customer
     * @param string    `customer_name_like`        (optional) - Name of Customer Like
     * @param string    `payment_method`            (optional) - payment type
     * @param string    `purchase_code`             (optional) - receipt number
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

            if (! ACL::create($user)->isAllowed('view_cashier')) {
                Event::fire('orbit.cashier.getcashiertimereport.authz.notallowed', array($this, $user));
                $createCouponLang = Lang::get('validation.orbit.actionlist.get_cashier_time');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createCouponLang));
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.cashier.getcashiertimereport.after.authz', array($this, $user));

            $activityQuery = DB::raw("
                SELECT
                    `user_id` as activity_user_id,
                    `full_name` as activity_full_name,
                    `role` as activity_role,
                    `group` as activity_group,
                    `activity_type`,
                    date(`created_at`) as activity_date,
                    min(`created_at`) as login_at,
                    max(`created_at`) as logout_at
                FROM {$tablePrefix}activities
                WHERE `role`          like 'cashier'
                AND   `group`         = 'pos'
                AND   `activity_type` IN ('login', 'logout')
                GROUP BY `activity_date`, `activity_user_id`");

            $transactions = Transaction::select([
                    'cashier_activities.*',
                    DB::raw("count(distinct transaction_id) as transactions_count"),
                    DB::raw('sum(total_to_pay) as transactions_total')])

                ->leftJoin("users as {$tablePrefix}customer", function ($join) {
                    $join->on('customer.user_id', '=', 'transactions.customer_id');
                })

                ->leftJoin(DB::raw("({$activityQuery}) as {$tablePrefix}cashier_activities"), function ($join) {
                    $join->on('cashier_activities.user_id', '=', 'transactions.cashier_id');
                });

            OrbitInput::get('cashier_id', function ($cashierId) use ($transactions) {
                $transactions->where("activity_user_id", '=', $cashierId);
            });

            OrbitInput::get('cashier_name', function ($cashierName) use ($transactions) {
                $transactions->where("activity_full_name", 'like', $cashierName);
            });

            OrbitInput::get('cashier_name_like', function ($cashierName) use ($transactions) {
                $transactions->where("activity_full_name", 'like', "%{$cashierName}%");
            });

            OrbitInput::get('customer_id', function ($customerId) use ($transactions) {
                $transactions->where("customer.user_id", '=', $customerId);
            });

            OrbitInput::get('customer_firstname', function ($customerName) use ($transactions) {
                $transactions->where("customer.user_firstname", 'like', $customerName);
            });

            OrbitInput::get('customer_lastname', function ($customerName) use ($transactions) {
                $transactions->where("customer.user_lastname", 'like', $customerName);
            });

            OrbitInput::get('customer_name_like', function ($customerName) use ($transactions) {
                $transactions->where("customer.user_firstname", 'like', "%{$customerName}%")
                             ->whereOr("customer.user_lastname", 'like', "%{$customerName}%");
            });

            OrbitInput::get('payment_method', function ($paymentType) use ($transactions) {
                $transactions->where('transactions.payment_method', '=', $paymentType);
            });

            OrbitInput::get('purchase_code', function ($purchaseCode) use ($transactions) {
               $transactions->where('transactions.transaction_code', '=', $purchaseCode);
            });

            $transactions->groupBy(['transactions.cashier_id', 'cashier_activities.activity_date'])
                       ->orderBy('transactions.created_at', 'ASC');

            $_transactions = clone $transactions;

            $totalTransactions = RecordCounter::create($_transactions)->count();
            $transactionList    = $transactions->get();

            $data = new stdclass();
            $data->total_records = $totalTransactions;
            $data->returned_records = count($transactionList);
            $data->records = $transactionList;

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
}
