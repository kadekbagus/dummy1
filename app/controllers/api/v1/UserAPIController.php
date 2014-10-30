<?php
/**
 * An API controller for managing user.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class UserAPIController extends ControllerAPI
{
    /**
     * POST - Create new user
     *
     * @author <YOUR_NAME> <YOUR@EMAIL>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     * @param string    `password_confirmation` (required) - Confirmation password
     * @param string    `role_id`               (required) - Role ID
     * @param string    `firstname`             (optional) - User first name
     * @param string    `lastname`              (optional) - User last name
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewUser()
    {
        $httpCode = 200;
        try {
            // Put your code in here, see DummyAPIController for reference
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postnewuser.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postnewuser.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postnewuser.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = NULL;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.user.postnewuser.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check user email address, it should not exists
        Validator::extend('orbit.email.exists', function($attribute, $value, $parameters)
        {
            $user = User::excludeDeleted()
                        ->where('user_email', $value)
                        ->first();

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.user', $user);

            return TRUE;
        });

        // Check the existance of the Role
        Validator::extend('orbit.empty.role', function($attribute, $value, $parameters)
        {
            $role = Role::find($value);

            if (empty($role)) {
                return FALSE;
            }

            App::instance('orbit.validation.role', $role);

            return TRUE;
        });
    }
}
