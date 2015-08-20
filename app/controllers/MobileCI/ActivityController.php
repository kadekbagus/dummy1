<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Activity;
use Config;
use DB;
use EventModel;
use Exception;
use Lang;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Transaction;
use Validator;
use View;
use Widget;

class ActivityController extends MobileCIAPIController
{
    /**
     * POST - Event pop up display activity
     *
     * @param integer    `eventdata`        (optional) - The event ID
     *
     * @return void
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postDisplayEventPopUpActivity()
    {
        $activity = Activity::mobileci()
            ->setActivityType('view');
        $user = null;
        $event_id = null;
        $event = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $event_id = OrbitInput::post('eventdata');
            $event = EventModel::active()->where('event_id', $event_id)->first();

            $activityNotes = sprintf('Event View. Event Id : %s', $event_id);
            $activity->setUser($user)
                ->setActivityName('event_view')
                ->setActivityNameLong('Event View (Pop Up)')
                ->setObject($event)
                ->setModuleName('Event')
                ->setEvent($event)
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (Exception $e) {
            $this->rollback();
            $activityNotes = sprintf('Event Click Failed. Event Id : %s', $event_id);
            $activity->setUser($user)
                ->setActivityName('event_click')
                ->setActivityNameLong('Event Click Failed')
                ->setObject(null)
                ->setModuleName('Event')
                ->setEvent($event)
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Widget click activity
     *
     * @param integer    `widgetdata`        (optional) - The widget ID
     *
     * @return void
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postClickWidgetActivity()
    {
        $activity = Activity::mobileci()
            ->setActivityType('click');
        $user = null;
        $widget_id = null;
        $widget = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $widget_id = OrbitInput::post('widgetdata');
            $widget = Widget::active()->where('widget_id', $widget_id)->first();

            $activityNotes = sprintf('Widget Click. Widget Id : %s', $widget_id);
            $activity->setUser($user)
                ->setActivityName('widget_click')
                ->setActivityNameLong('Widget Click ' . ucwords(str_replace('_', ' ', $widget->widget_type)))
                ->setObject($widget)
                ->setModuleName('Widget')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (Exception $e) {
            $activityNotes = sprintf('Widget Click Failed. Widget Id : %s', $widget_id);
            $activity->setUser($user)
                ->setActivityName('widget_click')
                ->setActivityNameLong('Widget Click Failed')
                ->setObject(null)
                ->setModuleName('Widget')
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Save receipt activity
     *
     * @param integer    `transactiondata`        (optional) - The transaction ID
     *
     * @return void
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postClickSaveReceiptActivity()
    {
        $activity = Activity::mobileci()
            ->setActivityType('click');
        $user = null;
        $transaction_id = null;
        $transaction = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $transaction_id = OrbitInput::post('transactiondata');
            $transaction = Transaction::where('transaction_id', $transaction_id)
                ->where('customer_id', $user->user_id)
                ->where('status', 'paid')
                ->first();

            $activityNotes = sprintf('Save Receipt Click. Transaction Id : %s', $transaction_id);
            $activity->setUser($user)
                ->setActivityName('save_receipt_click')
                ->setActivityNameLong('Save Receipt Click')
                ->setObject($transaction)
                ->setModuleName('Cart')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (Exception $e) {
            $activityNotes = sprintf('Save Receipt Click Failed. Transaction Id : %s', $transaction_id);
            $activity->setUser($user)
                ->setActivityName('save_receipt_click')
                ->setActivityNameLong('Save Receipt Click Failed')
                ->setObject(null)
                ->setModuleName('Cart')
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Checkout click activity
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return void
     */
    public function postClickCheckoutActivity()
    {
        $activity = Activity::mobileci()
            ->setActivityType('click');
        $user = null;
        $cart_id = null;
        $cart = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartdata = $this->getCartData();
            $cart = $cartdata->cart;
            $cart_id = $cart->cart_id;

            $activityNotes = sprintf('Checkout. Cart Id : %s', $cart_id);
            $activity->setUser($user)
                ->setActivityName('checkout')
                ->setActivityNameLong('Checkout')
                ->setObject($cart)
                ->setModuleName('Cart')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (Exception $e) {
            $activityNotes = sprintf('Checkout Failed. Cart Id : %s', $cart_id);
            $activity->setUser($user)
                ->setActivityName('checkout')
                ->setActivityNameLong('Checkout Failed')
                ->setObject(null)
                ->setModuleName('Cart')
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }
}
