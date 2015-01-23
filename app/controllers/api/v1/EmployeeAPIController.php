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
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;

class EmployeeAPIController extends ControllerAPI
{
    /**
     * POST - Create New Employee
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `firstname`             (required) - Employee first name
     * @param string    `lastname`              (required) - Employee last name
     * @param string    `birthdate`             (required) - Employee birthdate
     * @param string    `position`              (required) - Employee position, i.e: 'Cashier 1', 'Supervisor'
     * @param string    `employee_id_char`      (required) - Employee ID, i.e: 'EMP001', 'CASHIER001`
     * @param string    `username`              (required) - Username used to login
     * @param string    `password`              (required) - Password for the account
     * @param string    `password_confirmation` (required) - Confirmation password
     * @param string    `employee_role`         (required) - Role of the employee, i.e: 'cashier', 'manager', 'supervisor'
     * @param array     `retailer_ids`          (optional) - List of Retailer IDs
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewEmployee()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.employee.postnewemployee.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.employee.postnewemployee.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.employee.postnewemployee.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_employee')) {
                Event::fire('orbit.employee.postnewemployee.authz.notallowed', array($this, $user));

                $createUserLang = Lang::get('validation.orbit.actionlist.add_new_employee');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createUserLang));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.employee.postnewemployee.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $loginId = OrbitInput::post('username');
            $birthdate = OrbitInput::post('birthdate');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');
            $position = OrbitInput::post('position');
            $employeeId = OrbitInput::post('employee_id_char');
            $firstName = OrbitInput::post('firstname');
            $lastName = OrbitInput::post('lastname');
            $employeeRole = OrbitInput::post('employee_role');
            $retailerIds = OrbitInput::post('retailer_ids');

            $errorMessage = [
                'orbit.empty.employee.role' => Lang::get('validation.orbit.empty.employee.role', array(
                    'role' => $employeeRole
                ))
            ];
            $validator = Validator::make(
                array(
                    'firstname'             => $firstName,
                    'lastname'              => $lastName,
                    'birthdate'             => $birthdate,
                    'position'              => $position,
                    'employee_id_char'      => $employeeId,
                    'username'              => $loginId,
                    'password'              => $password,
                    'password_confirmation' => $password2,
                    'employee_role'         => $employeeRole,
                    'retailer_ids'          => $retailerIds
                ),
                array(
                    'firstname'         => 'required',
                    'lastname'          => 'required',
                    'birthdate'         => 'required|date_format:Y-m-d',
                    'position'          => 'required',
                    'employee_id_char'  => 'required|orbit.exists.employeeid',
                    'username'          => 'required|orbit.exists.username',
                    'password'          => 'required|min:5|confirmed',
                    'employee_role'     => 'required|orbit.empty.employee.role',
                    'retailer_ids'      => 'array|min:1|orbit.empty.retailer'
                ),
                array(
                    'orbit.empty.employee.role' => $errorMessage['orbit.empty.employee.role']
                )
            );

            Event::fire('orbit.employee.postnewemployee.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.employee.postnewemployee.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $role = App::make('orbit.empty.employee.role');

            $newUser = new User();
            $newUser->username = $loginId;
            $newUser->user_email = sprintf('%s@local.localdomain', $loginId);
            $newUser->user_password = Hash::make($password);
            $newUser->status = 'active';
            $newUser->user_role_id = $role->role_id;
            $newUser->user_ip = $_SERVER['REMOTE_ADDR'];
            $newUser->modified_by = $this->api->user->user_id;
            $newUser->user_firstname = $firstName;
            $newUser->user_lastname = $lastName;

            Event::fire('orbit.employee.postnewemployee.before.save', array($this, $newUser));

            $newUser->save();

            $apikey = new Apikey();
            $apikey->api_key = Apikey::genApiKey($newUser);
            $apikey->api_secret_key = Apikey::genSecretKey($newUser);
            $apikey->status = 'active';
            $apikey->user_id = $newUser->user_id;
            $apikey = $newUser->apikey()->save($apikey);

            $newUser->setRelation('apikey', $apikey);
            $newUser->setHidden(array('user_password'));

            $userdetail = new UserDetail();
            $userdetail->birthdate = $birthdate;
            $userdetail = $newUser->userdetail()->save($userdetail);

            $newUser->setRelation('userDetail', $userdetail);

            $newEmployee = new Employee();
            $newEmployee->employee_id_char = $employeeId;
            $newEmployee->position = $position;
            $newEmployee->status = $newUser->status;
            $newEmployee = $newUser->employee()->save($newEmployee);

            $newUser->setRelation('employee', $newEmployee);

            if ($retailerIds) {
                $newEmployee->retailers()->sync($retailerIds);
            }

            Event::fire('orbit.employee.postnewemployee.after.save', array($this, $newUser));
            $this->response->data = $newUser;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.employee.postnewemployee.after.commit', array($this, $newUser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.employee.postnewemployee.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.employee.postnewemployee.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.employee.postnewemployee.query.error', array($this, $e));

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
        } catch (Exception $e) {
            Event::fire('orbit.employee.postnewemployee.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * POST - Update Existing Employee
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `user_id`               (required) - User ID of the employee
     * @param string    `firstname`             (required) - Employee first name
     * @param string    `lastname`              (required) - Employee last name
     * @param string    `birthdate`             (required) - Employee birthdate
     * @param string    `position`              (required) - Employee position, i.e: 'Cashier 1', 'Supervisor'
     * @param string    `employee_id_char`      (required) - Employee ID, i.e: 'EMP001', 'CASHIER001' (Unchangable)
     * @param string    `username`              (required) - Username used to login (Unchangable)
     * @param string    `password`              (required) - Password for the account
     * @param string    `password_confirmation` (required) - Confirmation password
     * @param string    `employee_role`         (required) - Role of the employee, i.e: 'cashier', 'manager', 'supervisor'
     * @param array     `retailer_ids`          (optional) - List of Retailer IDs
     * @param array     `retailer_ids_delete    (optional) - List of Retailer IDs need to be deleted
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateEmployee()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.employee.postupdateemployee.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.employee.postupdateemployee.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.employee.postupdateemployee.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_employee')) {
                Event::fire('orbit.employee.postupdateemployee.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.update_employee');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.employee.postupdateemployee.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $userId = OrbitInput::post('user_id');
            $loginId = OrbitInput::post('username');
            $birthdate = OrbitInput::post('birthdate');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');
            $position = OrbitInput::post('position');
            $employeeId = OrbitInput::post('employee_id_char');
            $employeeRole = OrbitInput::post('employee_role');
            $retailerIds = OrbitInput::post('retailer_ids');
            $status = OrbitInput::post('status');

            $errorMessage = [
                'orbit.empty.employee.role' => Lang::get('validation.orbit.empty.employee.role', array(
                    'role' => $employeeRole
                ))
            ];
            $validator = Validator::make(
                array(
                    'user_id'               => $userId,
                    'birthdate'             => $birthdate,
                    'password'              => $password,
                    'password_confirmation' => $password2,
                    'employee_role'         => $employeeRole,
                    'retailer_ids'          => $retailerIds,
                    'status'                => $status
                ),
                array(
                    'user_id'               => 'orbit.empty.user',
                    'birthdate'             => 'date_format:Y-m-d',
                    'password'              => 'min:5|confirmed',
                    'employee_role'         => 'orbit.empty.employee.role',
                    'retailer_ids'          => 'array|min:1|orbit.empty.retailer',
                    'status'                => 'orbit.empty.user_status',
                ),
                array(
                    'orbit.empty.employee.role' => $errorMessage['orbit.empty.employee.role']
                )
            );

            Event::fire('orbit.employee.postupdateemployee.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.employee.postupdateemployee.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $role = App::make('orbit.empty.employee.role');

            $updatedUser = App::make('orbit.empty.user');

            OrbitInput::post('password', function($password) use ($updatedUser) {
                $updatedUser->user_password = Hash::make($password);
            });

            OrbitInput::post('status', function($status) use ($updatedUser) {
                $updatedUser->status = 'active';
            });

            OrbitInput::post('employee_role', function($_role) use ($updatedUser, $role) {
                $updatedUser->user_role_id = $role->role_id;
            });

            OrbitInput::post('firstname', function($_firstname) use ($updatedUser) {
                $updatedUser->user_firstname = $_firstname;
            });

            OrbitInput::post('lastname', function($_lastname) use ($updatedUser) {
                $updatedUser->user_lastname = $_lastname;
            });

            $updatedUser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.employee.postupdateemployee.before.save', array($this, $updatedUser));

            $updatedUser->save();
            $updatedUser->apikey;

            // Get the relation
            $employee = $updatedUser->employee;
            $userDetail = $updatedUser->userDetail;

            OrbitInput::post('position', function($_position) use ($employee) {
                $employee->position = $_position;
            });

            $employee->status = $updatedUser->status;
            $employee->save();

            OrbitInput::post('birthdate', function($_birthdate) use ($userDetail) {
                $userDetail->birthdate = $_birthdate;
            });

            $userDetail->save();

            if ($retailerIds) {
                $employee->retailers()->sync($retailerIds);
            }

            Event::fire('orbit.employee.postupdateemployee.after.save', array($this, $updatedUser));
            $this->response->data = $updatedUser;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.employee.postupdateemployee.after.commit', array($this, $updatedUser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.employee.postupdateemployee.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.employee.postupdateemployee.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.employee.postupdateemployee.query.error', array($this, $e));

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
        } catch (Exception $e) {
            Event::fire('orbit.employee.postupdateemployee.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * POST - Delete Existing Employee
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`               (required) - User ID of the employee
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteEmployee()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.employee.postdeleteemployee.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.employee.postdeleteemployee.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.employee.postdeleteemployee.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_employee')) {
                Event::fire('orbit.employee.postdeleteemployee.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.delete_employee');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.employee.postdeleteemployee.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $userId = OrbitInput::post('user_id');

            $validator = Validator::make(
                array(
                    'user_id'               => $userId,
                ),
                array(
                    'user_id'               => 'required|numeric|orbit.empty.user'
                )
            );

            Event::fire('orbit.employee.postdeleteemployee.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.employee.postdeleteemployee.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deletedUser = App::make('orbit.empty.user');
            $deletedUser->status = 'deleted';
            $deletedUser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.employee.postdeleteemployee.before.save', array($this, $deletedUser));

            $deletedUser->save();

            // Get the relation
            $employee = $deletedUser->employee;
            $employee->status = $deletedUser->status;
            $employee->save();

            Event::fire('orbit.employee.postdeleteemployee.after.save', array($this, $deletedUser));
            $this->response->data = $deletedUser;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.employee.postdeleteemployee.after.commit', array($this, $deletedUser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.employee.postdeleteemployee.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.employee.postdeleteemployee.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.employee.postdeleteemployee.query.error', array($this, $e));

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
        } catch (Exception $e) {
            Event::fire('orbit.employee.postdeleteemployee.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * GET - Search employees
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `sort_by`               (optional) - column order by
     * @param string    `sort_mode`             (optional) - asc or desc
     * @param array     `user_ids`              (optional)
     * @param array     `role_ids`              (optional)
     * @param array     `retailer_ids`          (optional)
     * @param array     `merchant_ids`          (optional)
     * @param array     `usernames`             (optional)
     * @param array     `firstnames`            (optional)
     * @param array     `lastname`              (optional)
     * @param array     `statuses`              (optional)
     * @param array     `employee_id_chars`     (optional)
     * @param string    `username_like`         (optional)
     * @param string    `firstname_like`        (optional)
     * @param string    `lastname_like`         (optional)
     * @param array     `employee_id_char_like` (optional)
     * @param integer   `take`                  (optional) - limit
     * @param integer   `skip`                  (optional) - limit offset
     * @param array     `with`                  (optional) - default to ['employee.retailers', 'userDetail']
     * @return Illuminate\Support\Facades\Response
     */

    public function getSearchEmployee()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.user.getsearchuser.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.getsearchuser.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.getsearchuser.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_user')) {
                Event::fire('orbit.user.getsearchuser.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.user.getsearchuser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $role_ids = OrbitInput::post('role_ids');
            $validator = Validator::make(
                array(
                    'sort_by'   => $sort_by,
                    'role_ids'  => $role_ids,
                    'with'      => OrbitInput::get('with')
                ),
                array(
                    'sort_by'   => 'in:username,firstname,lastname,registered_date,employee_id_char,',
                    'role_ids'  => 'array|orbit.employee.role.limited',
                    'with'      => 'array|min:1'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.employee_sortby'),
                )
            );

            Event::fire('orbit.user.getsearchuser.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.getsearchuser.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.employee.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }

            // Builder object
            $joined = FALSE;
            $users = User::excludeDeleted('users');
            $defaultWith = array('employee.retailers');

            // Filter user by Ids
            OrbitInput::get('user_ids', function ($userIds) use ($users) {
                $users->whereIn('users.user_id', $userIds);
            });

            // Filter user by Retailer Ids
            OrbitInput::get('retailer_ids', function ($retailerIds) use ($users, $joined) {
                $joined = TRUE;
                $users->employeeRetailerIds($retailerIds);
            });

            // Filter user by Merchant Ids
            OrbitInput::get('merchant_ids', function ($merchantIds) use ($users, $joined) {
                $joined = TRUE;
                $users->employeeMerchantIds($retailerIds);
            });

            // Filter user by username
            OrbitInput::get('usernames', function ($username) use ($users) {
                $users->whereIn('users.username', $username);
            });

            // Filter user by matching username pattern
            OrbitInput::get('username_like', function ($username) use ($users) {
                $users->where('users.username', 'like', "%$username%");
            });

            // Filter user by their firstname
            OrbitInput::get('firstnames', function ($firstname) use ($users) {
                $users->whereIn('users.user_firstname', $firstname);
            });

            // Filter user by their employee_id_char
            OrbitInput::get('employee_id_char', function ($idChars) use ($users, $joined) {
                $joined = TRUE;
                $users->employeeIdChars($idChars);
            });

           // Filter user by their employee_id_char pattern
            OrbitInput::get('employee_id_char_like', function ($idCharLike) use ($users, $joined) {
                $joined = TRUE;
                $users->employeeIdCharLike($idCharLike);
            });

            // Filter user by their firstname pattern
            OrbitInput::get('firstname_like', function ($firstname) use ($users) {
                $users->where('users.user_firstname', 'like', "%$firstname%");
            });

            // Filter user by their lastname
            OrbitInput::get('lastname', function ($lastname) use ($users) {
                $users->whereIn('users.user_lastname', $lastname);
            });

            // Filter user by their lastname pattern
            OrbitInput::get('lastname_like', function ($firstname) use ($users) {
                $users->where('users.user_lastname', 'like', "%$firstname%");
            });

            // Filter user by their status
            OrbitInput::get('statuses', function ($status) use ($users) {
                $users->whereIn('users.status', $status);
            });

            // Filter user by their role id
            OrbitInput::get('role_id', function ($roleId) use ($users) {
                $users->whereIn('users.user_role_id', $roleId);
            });

            if (empty(OrbitInput::get('role_id'))) {
                $invalidRoles = ['super admin', 'administrator', 'consumer', 'customer', 'merchant-owner', 'guest'];
                $roles = Role::whereIn('role_name', $invalidRoles)->get();

                $ids = array();
                foreach ($roles as $role) {
                    $ids[] = $role->role_id;
                }
                $users->whereNotIn('users.user_role_id', $ids);
            }

            // Include Relationship
            $with = $defaultWith;
            OrbitInput::get('with', function ($_with) use ($users, &$with) {
                $with = array_merge($with, $_with);
            });
            $users->with($with);

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_users = clone $users;

            // Get the take args
            $take = $maxRecord;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;
            });
            $users->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $users) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $users->skip($skip);

            // Default sort by
            $sortBy = 'users.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                if ($_sortBy === 'employee_id_char') {
                    $users->prepareEmployeeRetailerCalled();
                }

                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'users.created_at',
                    'username'          => 'users.username',
                    'employee_id_char'  => 'employee.user_email',
                    'lastname'          => 'users.user_lastname',
                    'firstname'         => 'users.user_firstname'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $users->orderBy($sortBy, $sortMode);

            $totalUsers = $_users->count();
            $listOfUsers = $users->get();

            $data = new stdclass();
            $data->total_records = $totalUsers;
            $data->returned_records = count($listOfUsers);
            $data->records = $listOfUsers;

            if ($totalUsers === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.user');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.getsearchuser.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.getsearchuser.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.user.getsearchuser.query.error', array($this, $e));

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
        } catch (Exception $e) {
            Event::fire('orbit.user.getsearchuser.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.user.getsearchuser.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check username, it should not exists
        Validator::extend('orbit.exists.username', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->where('username', $value)
                        ->first();

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.username', $user);

            return TRUE;
        });

        // Check the existance of user id
        Validator::extend('orbit.empty.user', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->where('user_id', $value)
                        ->first();

            if (empty($user)) {
                return FALSE;
            }

            App::instance('orbit.empty.user', $user);

            return TRUE;
        });

        // Check self
        Validator::extend('no_delete_themself', function ($attribute, $value, $parameters) {
            if ((string) $value === (string) $this->api->user->user_id) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of the Role
        Validator::extend('orbit.empty.role', function ($attribute, $value, $parameters) {
            $role = Role::find($value);

            if (empty($role)) {
                return FALSE;
            }

            App::instance('orbit.validation.role', $role);

            return TRUE;
        });

        // Check the existance of the Status
        Validator::extend('orbit.empty.user_status', function ($attribute, $value, $parameters) {
            $statuses = array('active', 'pending', 'blocked', 'deleted');
            if (! in_array($value, $statuses)) {
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('orbit.empty.personal_interest', function ($attribute, $value, $parameters) {
            $personal_interest_ids = $value;
            $number = count($personal_interest_ids);
            $real_number = PersonalInterest::ExcludeDeleted()
                                           ->whereIn('personal_interest_id', $personal_interest_ids)
                                           ->count();

            if ((string)$real_number !== (string)$number) {
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('orbit.empty.employee.role', function ($attribute, $value, $parameters) {
            $invalidRoles = array('super admin', 'administrator', 'consumer', 'customer', 'merchant-owner');
            if (in_array(strtolower($value), $invalidRoles)) {
                return FALSE;
            }

            $role = Role::where('role_name', $value)->first();

            if (empty($role)) {
                return FALSE;
            }

            App::instance('orbit.empty.employee.role', $role);

            return TRUE;
        });

        Validator::extend('orbit.employee.role.limited', function ($attribute, $value, $parameters) {
            $invalidRoles = ['super admin', 'administrator', 'consumer', 'customer', 'merchant-owner', 'guest'];
            $roles = Role::whereIn('role_name', $invalidRoles)->get();

            if ($roles) {
                foreach ($roles as $role) {
                    foreach ($value as $roleId) {
                        if ((string)$roleId === (string)$role->role_id) {
                            // This role Id is not allowed
                            return FALSE;
                        }
                    }
                }
            }

            App::instance('orbit.employee.role.limited', $value);

            return TRUE;
        });

        Validator::extend('orbit.exists.employeeid', function ($attribute, $value, $parameters) {
            $employee = Employee::excludeDeleted()
                                ->where('employee_id_char', $value)
                                ->first();

            if (! empty($employee)) {
                App::instance('orbit.exists.employeeid', $employee);
                return FALSE;
            }

            return TRUE;
        });


        Validator::extend('orbit.empty.retailer', function ($attribute, $retailerIds, $parameters) {
            if (! is_array($retailerIds)) {
                return FALSE;
            }

            $number = count($retailerIds);
            $user = $this->api->user;
            $realNumber = Retailer::allowedForUser($user)
                                  ->excludeDeleted()
                                  ->whereIn('merchant_id', $retailerIds)
                                  ->count();

            if ((string)$realNumber !== (string)$number) {
                return FALSE;
            }

            return TRUE;
        });
    }
}
