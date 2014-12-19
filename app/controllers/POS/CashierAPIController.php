<?php namespace POS;

/**
 * An API controller for managing Cashier QUICK AND DIRTY
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use \View;
use \User;
use \UserDetail;
use \Token;
use \Role;
use \Lang;
use \Apikey;
use \Validator;
use \Product;

class CashierAPIController extends ControllerAPI
{  

    /**
     * POST - Login cashier in shop
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `username`  (required) - username of the cashier
     * @param string    `password`  (required) - password of the cashier
     * @return Illuminate\Support\Facades\Response
     */
    public function postLoginCashier()
    {
        try {
            $username = trim(OrbitInput::post('username'));
            $password = trim(OrbitInput::post('password'));

            if (trim($username) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'username'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (trim($password) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'password'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = User::with('apikey', 'userdetail', 'role')
                        ->active()
                        ->where('username', $username)
                        ->where('user_role_id', Role::where('role_name','Cashier')->first()->role_id)
                        ->first();           

            if (! is_object($user)) {
                $message = \Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            if (! \Hash::check($password, $user->user_password)) {
                $message = \Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            \Auth::login($user);

            $this->response->data = $user;
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

        return $this->render();
    }


    /**
     * POST - Logout cashier
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postLogoutCashier()
    {
        \Auth::logout();
        $this->response->code = 0;
        $this->response->message = 'success';
        $this->response->data = 'sukses atuh kang';
        return $this->render();
    }


    /**
     * POST - Scan Barcode
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postScanBarcode()
    {
        try {
            
            $cmd = "sudo ./barcode /dev/domino/scanner";
            //$barcode = shell_exec($cmd);
            $barcode = trim(OrbitInput::post('barcode'));

            $product = Product::where('upc_code', $barcode)
                        ->active()
                        ->first();           

            if (! is_object($product)) {
                $message = \Lang::get('validation.orbit.empty.product');
                ACL::throwAccessForbidden($message);
            }

            $this->response->data = $product;
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

        return $this->render();
    }
}
