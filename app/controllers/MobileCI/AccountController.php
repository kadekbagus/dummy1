<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Activity;
use Carbon\Carbon as Carbon;
use Cart;
use Config;
use DB;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Exception;
use Lang;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use Transaction;
use User;
use UserDetail;
use Validator;
use View;

class AccountController extends MobileCIAPIController
{
    /**
     * GET - Sign in page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return \Illuminate\View\View
     */
    public function getSignInView()
    {
        try {
            $user = $this->getLoggedInUser();

            return \Redirect::to('/customer/welcome');
        } catch (Exception $e) {
            $retailer = $this->getRetailerInfo();

            // Get email from query string
            $user_email = OrbitInput::get('email', '');
            if (! empty($user_email)) {
                \DummyAPIController::create()->getUserSignInNetwork();
            }

            if ($e->getMessage() === 'Session error: user not found.' ||
                $e->getMessage() === 'Invalid session data.' ||
                $e->getMessage() === 'IP address miss match.' ||
                $e->getMessage() === 'User agent miss match.') {

                    return View::make('mobile-ci.signin', array(
                        'retailer' => $retailer,
                        'user_email' => htmlentities($user_email)
                    ));
            } else {

                return View::make('mobile-ci.signin', array(
                    'retailer' => $retailer,
                    'user_email' => htmlentities($user_email)
                ));
            }
        }
    }

    /**
     * TODO: method listed on routes but no method found within namespace
     * @return mixed
     */
    public function getSignUpView()
    {
        return View::make("errors/404");
    }

    /**
     * TODO: method listed on routes but no method found within namespace
     * @return mixed
     */
    public function postSignUpView()
    {
        return View::make("errors/404");
    }

    /**
     * TODO: method listed on routes but no method found within namespace
     * @return mixed
     */
    public function getActivationView()
    {
        return View::make("errors/404");
    }

    /**
     * POST - Send receipt to user email
     *
     * @param integer    `ticketdata`             (optional) - The receipt image on base 64 encoded
     * @param integer    `transactionid`          (optional) - The transaction ID
     *
     * @return void
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postSendTicket()
    {
        try {
            $user = $this->getLoggedInUser();

            $ticketdata = OrbitInput::post('ticketdata');
            $transactionid = OrbitInput::post('transactionid');

            $transaction = Transaction::with('details.product')->where('transaction_id', $transactionid)->where('customer_id', $user->user_id)->first();

            $ticketdata = str_replace('data:image/png;base64,', '', $ticketdata);

            $image = base64_decode($ticketdata);

            $date = str_replace(' ', '_', $transaction->created_at);

            $filename = 'receipt-' . $date . ' . png';

            $mailviews = array(
                'html' => 'emails.receipt.receipt-html',
                'text' => 'emails.receipt.receipt-text'
            );

            $retailer = $this->getRetailerInfo();

            \Mail::send(
                $mailviews,
                array('user' => $user, 'retailer' => $retailer, 'transactiondetails' => $transaction->details, 'transaction' => $transaction),
                function ($message) use ($user, $image, $filename) {
                    $message->to($user->user_email, $user->getFullName())->subject('Orbit Receipt');
                    $message->attachData($image, $filename, array('mime' => 'image/png'));
                }
            );

        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * POST - Login customer in shop
     *
     * @param string    `email`          (required) - Email address of the user
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postLoginInShop()
    {
        try {
            $email = trim(OrbitInput::post('email'));

            if (trim($email) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            $retailer = $this->getRetailerInfo();

            DB::connection()->getPdo()->beginTransaction();

            $user = User::with('apikey', 'userdetail', 'role')
                ->excludeDeleted()
                ->where('user_email', $email)
                ->whereHas(
                    'role',
                    function ($query) {
                        $query->where('role_name', 'Consumer');
                    }
                )->sharedLock()
                ->first();

            if (! is_object($user)) {
                $response = \LoginAPIController::create('raw')->setUseTransaction(false)->postRegisterUserInShop();
                if ($response->code !== 0) {
                    throw new Exception($response->message, $response->code);
                }
                $user = $response->data;
            }

            $user_detail = UserDetail::where('user_id', $user->user_id)->first();
            $user_detail->last_visit_shop_id = $retailer->merchant_id;
            $user_detail->last_visit_any_shop = Carbon::now();
            $user_detail->save();

            if ($retailer->parent->enable_shopping_cart == 'yes') {
                $cart = Cart::where('status', 'active')->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->first();
                if (is_null($cart)) {
                    $cart = new Cart();
                    $cart->customer_id = $user->user_id;
                    $cart->merchant_id = $retailer->parent_id;
                    $cart->retailer_id = $retailer->merchant_id;
                    $cart->status = 'active';
                    $cart->save();
                    $cart->cart_code = Cart::CART_INCREMENT + $cart->cart_id;
                    $cart->save();
                }
            }

            $user->setHidden(array('user_password', 'apikey'));
            $this->response->data = $user;

            DB::connection()->getPdo()->commit();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            DB::connection()->getPdo()->rollback();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            DB::connection()->getPdo()->rollback();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            DB::connection()->getPdo()->rollback();
        }

        return $this->render();
    }

    /**
     * GET - Logout customer in shop
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\Support\Facades\Redirect
     */
    public function getLogoutInShop()
    {
        try {
            $this->prepareSession();
            $this->session->start(array(), 'no-session-creation');
            $this->session->destroy();
        } catch (Exception $e) {
        }

        return \Redirect::to('/customer');
    }

    /**
     * GET - Recognize me page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\View\View
     */
    public function getMeView()
    {
        $user = null;
        $activityPage = Activity::mobileci()
            ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartitems = $this->getCartForToolbar();

            $cartdata = $this->getCartData();

            $activityPageNotes = sprintf('Page viewed: %s', 'Recognize Me');
            $activityPage->setUser($user)
                ->setActivityName('view_recognize_me')
                ->setActivityNameLong('View Recognize Me')
                ->setObject($user)
                ->setModuleName('User')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.recognizeme', array('page_title'=>Lang::get('mobileci.page_title.recognize_me'), 'user' => $user, 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata));
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view: %s', 'Recognize Me');
            $activityPage->setUser($user)
                ->setActivityName('view_recognize_me')
                ->setActivityNameLong('View Recognize Me')
                ->setObject(null)
                ->setModuleName('User')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }
}
