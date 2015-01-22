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
     * @param string    `employee_id`           (required) - Employee ID, i.e: 'EMP001', 'CASHIER001`
     * @param string    `username`              (required) - Username used to login
     * @param string    `password`              (required) - Password for the account
     * @param string    `password_confirmation` (required) - Confirmation password
     * @param string    `employee_role`         (required) - Role of the employee, i.e: 'cashier', 'manager', 'supervisor'
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
            $employeeId = OrbitInput::post('employee_id');
            $firstName = OrbitInput::post('firstname');
            $lastName = OrbitInput::post('lastname');
            $employeeRole = OrbitInput::post('employee_role');

            $validator = Validator::make(
                array(
                    'firstname'             => $firstName,
                    'lastname'              => $lastName,
                    'birtdate'              => $birthdate,
                    'position'              => $position,
                    'employee_id'           => $employeeId,
                    'username'              => $loginId,
                    'password'              => $password,
                    'password_confirmation' => $password2,
                    'employee_role'         => $employeeRole
                ),
                array(
                    'firstname'     => 'required',
                    'lastname'      => 'required',
                    'birthdate'     => 'required|date_format:Y-m-d',
                    'position'      => 'required',
                    'employee_id'   => 'required|orbit.exists.employeeid',
                    'username'      => 'required|orbit.exists.username',
                    'password'      => 'required|min:5|confirmed',
                    'employee_role' => 'required|orbit.empty.employee.role'
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

            $newEmployee = new User();
            $newEmployee->username = $loginId;
            $newEmployee->user_email = sprintf('%@local.localdomain', $loginId);
            $newEmployee->user_password = Hash::make($password);
            $newEmployee->status = 'active';
            $newEmployee->user_role_id = $user_role_id;
            $newEmployee->user_ip = $_SERVER['REMOTE_ADDR'];
            $newEmployee->modified_by = $this->api->user->user_id;

            Event::fire('orbit.employee.postnewemployee.before.save', array($this, $newEmployee));

            $newEmployee->save();

            $apikey = new Apikey();
            $apikey->api_key = Apikey::genApiKey($newEmployee);
            $apikey->api_secret_key = Apikey::genSecretKey($newEmployee);
            $apikey->status = 'active';
            $apikey->user_id = $newEmployee->user_id;
            $apikey = $newEmployee->apikey()->save($apikey);

            $newEmployee->setRelation('apikey', $apikey);
            $newEmployee->apikey = $apikey;
            $newEmployee->setHidden(array('user_password'));

            Event::fire('orbit.employee.postnewemployee.after.save', array($this, $newEmployee));
            $this->response->data = $newEmployee;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.employee.postnewemployee.after.commit', array($this, $newEmployee));
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
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * POST - Delete user
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`                 (required) - ID of the user
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteUser()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.user.postdeleteuser.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postdeleteuser.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postdeleteuser.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_user')) {
                Event::fire('orbit.user.postdeleteuser.authz.notallowed', array($this, $user));
                $deleteUserLang = Lang::get('validation.orbit.actionlist.delete_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.user.postdeleteuser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $user_id = OrbitInput::post('user_id');

            // Error message when access is forbidden
            $deleteYourSelf = Lang::get('validation.orbit.actionlist.delete_your_self');
            $message = Lang::get('validation.orbit.access.forbidden',
                                 array('action' => $deleteYourSelf));

            $validator = Validator::make(
                array(
                    'user_id' => $user_id,
                ),
                array(
                    'user_id' => 'required|numeric|orbit.empty.user|no_delete_themself',
                ),
                array(
                    'no_delete_themself' => $message,
                )
            );

            Event::fire('orbit.user.postdeleteuser.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.postdeleteuser.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deleteuser = User::with(array('apikey'))->find($user_id);
            $deleteuser->status = 'deleted';
            $deleteuser->modified_by = $this->api->user->user_id;

            $deleteapikey = Apikey::where('apikey_id', '=', $deleteuser->apikey->apikey_id)->first();
            $deleteapikey->status = 'deleted';

            Event::fire('orbit.user.postdeleteuser.before.save', array($this, $deleteuser));

            $deleteuser->save();
            $deleteapikey->save();

            Event::fire('orbit.user.postdeleteuser.after.save', array($this, $deleteuser));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.user');

            // Commit the changes
            $this->commit();

            Event::fire('orbit.user.postdeleteuser.after.commit', array($this, $deleteuser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postdeleteuser.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postdeleteuser.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postdeleteuser.query.error', array($this, $e));

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
            Event::fire('orbit.user.postdeleteuser.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.user.postdeleteuser.before.render', array($this, $output));

        return $output;
    }

    /**
     * POST - Update user (currently only basic info)
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `user_id`               (required) - ID of the user
     * @param string    `email`                 (optional) - User email address
     * @param string    `username`              (optional) - Username
     * @param integer   `role_id`               (optional) - Role ID
     * @param string    `firstname`             (optional) - User first name
     * @param string    `lastname`              (optional) - User last name
     * @param string    `status`                (optional) - Status of the user 'active', 'pending', 'blocked', or 'deleted'
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateUser()
    {
        try {
            $httpCode=200;

            Event::fire('orbit.user.postupdateuser.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postupdateuser.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postupdateuser.before.authz', array($this, $user));

            $user_id = OrbitInput::post('user_id');
            if (! ACL::create($user)->isAllowed('update_user')) {
                // No need to check if it is the user itself
                if ((string)$user->user_id !== (string)$user_id)
                {
                    Event::fire('orbit.user.postupdateuser.authz.notallowed', array($this, $user));
                    $updateUserLang = Lang::get('validation.orbit.actionlist.update_user');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateUserLang));
                    ACL::throwAccessForbidden($message);
                }
            }
            Event::fire('orbit.user.postupdateuser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $email = OrbitInput::post('email');
            $username = OrbitInput::post('username');
            $user_firstname = OrbitInput::post('firstname');
            $user_lastname = OrbitInput::post('lastname');
            $status = OrbitInput::post('status');
            $user_role_id = OrbitInput::post('role_id');

            // Details
            $birthdate = OrbitInput::post('birthdate');
            $gender = OrbitInput::post('gender');
            $address1 = OrbitInput::post('address_line1');
            $address2 = OrbitInput::post('address_line2');
            $address3 = OrbitInput::post('address_line3');
            $city = OrbitInput::post('city');
            $province = OrbitInput::post('province');
            $postal_code = OrbitInput::post('postal_code');
            $country = OrbitInput::post('postal_code');
            $phone1 = OrbitInput::post('phone');
            $phone2 = OrbitInput::post('phone2');
            $relationship_status = OrbitInput::post('relationship_status');
            $number_of_children = OrbitInput::post('number_of_children');
            $occupation = OrbitInput::post('occupation');
            $sector_of_activity = OrbitInput::post('sector_of_activity');
            $company_name = OrbitInput::post('company_name');
            $education_level = OrbitInput::post('education_level');
            $preferred_lang = OrbitInput::post('preferred_language');
            $avg_annual_income1 = OrbitInput::post('avg_annual_income1');
            $avg_annual_income2 = OrbitInput::post('avg_annual_income2');
            $avg_monthly_spent1 = OrbitInput::post('avg_monthly_spent1');
            $avg_monthly_spent2 = OrbitInput::post('avg_monthly_spent2');
            $personal_interests = OrbitInput::post('personal_interests');

            $validator = Validator::make(
                array(
                    'user_id'               => $user_id,
                    'username'              => $username,
                    'email'                 => $email,
                    'role_id'               => $user_role_id,
                    'status'                => $status,

                    'firstname'             => $user_firstname,
                    'lastname'              => $user_lastname,

                    'birthdate'             => $birthdate,
                    'gender'                => $gender,
                    'address_line1'         => $address1,
                    'city'                  => $city,
                    'province'              => $province,
                    'postal_code'           => $postal_code,
                    'country'               => $country,
                    'phone'                 => $phone1,
                    'phone2'                => $phone2,
                    'relationship_status'   => $relationship_status,
                    'number_of_children'    => $number_of_children,
                    'education_level'       => $education_level,
                    'preferred_language'    => $preferred_lang,
                    'avg_annual_income1'    => $avg_annual_income1,
                    'avg_annual_income2'    => $avg_annual_income2,
                    'avg_monthly_spent1'    => $avg_monthly_spent1,
                    'avg_monthly_spent2'    => $avg_monthly_spent2,
                    'personal_interests'    => $personal_interests
                ),
                array(
                    'user_id'               => 'required|numeric',
                    'username'              => 'orbit.exists.username',
                    'email'                 => 'email|email_exists_but_me',
                    'role_id'               => 'numeric|orbit.empty.role',
                    'status'                => 'orbit.empty.user_status',

                    'firstname'             => 'required',
                    'lastname'              => 'required',

                    'birthdate'             => 'required|date_format:Y-m-d',
                    'gender'                => 'required|in:m,f',
                    'address_line1'         => 'required',
                    'city'                  => 'required',
                    'province'              => 'required',
                    'postal_code'           => 'required|numeric',
                    'country'               => 'required',
                    'phone'                 => 'required',
                    'relationship_status'   => 'in:none,single,in a relationship,engaged,married,divorced,widowed',
                    'number_of_children'    => 'numeric|min:0',
                    'education_level'       => 'in:none,junior high school,diploma,bachelor,master,ph.d,doctor,other',
                    'preferred_language'    => 'in:en,id',
                    'avg_annual_income1'    => 'numeric',
                    'avg_annual_income2'    => 'numeric',
                    'avg_monthly_spent1'    => 'numeric',
                    'avg_monthly_spent2'    => 'numeric',
                    'personal_interests'    => 'array|orbit.empty.personal_interest'
                ),
                array('email_exists_but_me' => Lang::get('validation.orbit.email.exists'))
            );

            Event::fire('orbit.user.postupdateuser.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.postupdateuser.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updateduser = User::with('userdetail')
                               ->excludeDeleted()
                               ->find($user_id);

            OrbitInput::post('username', function($username) use ($updateduser) {
                $updateduser->username = $username;
            });

            OrbitInput::post('email', function($email) use ($updateduser) {
                $updateduser->user_email = $email;
            });

            OrbitInput::post('firstname', function($firstname) use ($updateduser) {
                $updateduser->user_firstname = $firstname;
            });

            OrbitInput::post('lastname', function($lastname) use ($updateduser) {
                $updateduser->user_lastname = $lastname;
            });

            OrbitInput::post('status', function($status) use ($updateduser) {
                $updateduser->status = $status;
            });

            OrbitInput::post('role_id', function($role_id) use ($updateduser) {
                $updateduser->user_role_id = $role_id;
            });

            // Details

            OrbitInput::post('birthdate', function($date) use ($updateduser) {
                $updateduser->userdetail->birthdate = $date;
            });

            OrbitInput::post('gender', function($gender) use ($updateduser) {
                $updateduser->userdetail->gender = $gender;
            });

            OrbitInput::post('address_line1', function($addr1) use ($updateduser) {
                $updateduser->userdetail->address_line1 = $addr1;
            });

            OrbitInput::post('address_line2', function($addr2) use ($updateduser) {
                $updateduser->userdetail->address_line2 = $addr2;
            });

            OrbitInput::post('address_line3', function($addr3) use ($updateduser) {
                $updateduser->userdetail->address_line3 = $addr3;
            });

            OrbitInput::post('city', function($city) use ($updateduser) {
                $updateduser->userdetail->city = $city;
            });

            OrbitInput::post('province', function($province) use ($updateduser) {
                $updateduser->userdetail->province = $province;
            });

            OrbitInput::post('postal_code', function($postal) use ($updateduser) {
                $updateduser->userdetail->postal_code = $postal;
            });

            OrbitInput::post('country', function($country) use ($updateduser) {
                $updateduser->userdetail->country = $country;
            });

            OrbitInput::post('phone', function($phone1) use ($updateduser) {
                $updateduser->userdetail->phone = $phone1;
            });

            OrbitInput::post('phone2', function($phone2) use ($updateduser) {
                $updateduser->userdetail->phone2 = $phone2;
            });

            OrbitInput::post('relationship_status', function($status) use ($updateduser) {
                $updateduser->userdetail->relationship_status = $status;
            });

            OrbitInput::post('number_of_children', function($number) use ($updateduser) {
                $updateduser->userdetail->number_of_children = $number;

                if ($number > 0) {
                    $updateduser->userdetail->has_children = 'Y';
                }
            });

            OrbitInput::post('education_level', function($level) use ($updateduser) {
                $updateduser->userdetail->last_education_degree = $level;
            });

            OrbitInput::post('preferred_language', function($lang) use ($updateduser) {
                $updateduser->userdetail->preferred_lang = $lang;
            });

            OrbitInput::post('avg_annual_income1', function($income1) use ($updateduser) {
                $updateduser->userdetail->avg_annual_income1 = $income1;
            });

            OrbitInput::post('avg_annual_income2', function($income2) use ($updateduser) {
                $updateduser->userdetail->avg_annual_income2 = $income2;
            });

            OrbitInput::post('avg_monthly_spent1', function($spent1) use ($updateduser) {
                $updateduser->userdetail->avg_monthly_spent1 = $spent1;
            });

            OrbitInput::post('avg_monthly_spent2', function($spent2) use ($updateduser) {
                $updateduser->userdetail->avg_monthly_spent2 = $spent2;
            });

            OrbitInput::post('occupation', function($occupation) use ($updateduser) {
                $updateduser->userdetail->occupation = $occupation;
            });

            OrbitInput::post('sector_of_activity', function($soc) use ($updateduser) {
                $updateduser->userdetail->sector_of_activity = $soc;
            });

            OrbitInput::post('company_name', function($company) use ($updateduser) {
                $updateduser->userdetail->company_name = $company;
            });

            OrbitInput::post('personal_interests', function($interests) use ($updateduser) {
                $updateduser->interests()->sync($interests);
            });

            $updateduser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.user.postupdateuser.before.save', array($this, $updateduser));

            $updateduser->save();
            $updateduser->userdetail->modified_by = $user->user_id;
            $updateduser->userdetail->save();

            $apikey = Apikey::where('user_id', '=', $updateduser->user_id)->first();
            if ($status != 'pending') {
                $apikey->status = $status;
            } else {
                $apikey->status = 'blocked';
            }
            $apikey->save();
            $updateduser->setRelation('apikey', $apikey);

            $updateduser->apikey = $apikey;

            Event::fire('orbit.user.postupdateuser.after.save', array($this, $updateduser));
            $this->response->data = $updateduser;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.user.postupdateuser.after.commit', array($this, $updateduser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postupdateuser.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postupdateuser.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postupdateuser.query.error', array($this, $e));

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
            Event::fire('orbit.user.postupdateuser.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * GET - Search user (currently only basic info)
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Kadek Bagus <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sort_by`               (optional) - column order by
     * @param string   `sort_mode`             (optional) - asc or desc
     * @param integer  `user_id`               (optional)
     * @param integer  `role_id`               (optional)
     * @param string   `username`              (optional)
     * @param string   `email`                 (optional)
     * @param string   `firstname`             (optional)
     * @param string   `lastname`              (optional)
     * @param string   `status`                (optional)
     * @param string   `username_like`         (optional)
     * @param string   `email_like`            (optional)
     * @param string   `firstname_like`        (optional)
     * @param string   `lastname_like`         (optional)
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @return Illuminate\Support\Facades\Response
     */

    public function getSearchUser()
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
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:username,email,firstname,lastname,registered_date',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
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
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            // Builder object
            $users = User::with(array('userdetail'))->excludeDeleted();

            // Filter user by Ids
            OrbitInput::get('user_id', function ($userIds) use ($users) {
                $users->whereIn('users.user_id', $userIds);
            });

            // Filter user by username
            OrbitInput::get('username', function ($username) use ($users) {
                $users->whereIn('users.username', $username);
            });

            // Filter user by matching username pattern
            OrbitInput::get('username_like', function ($username) use ($users) {
                $users->where('users.username', 'like', "%$username%");
            });

            // Filter user by their firstname
            OrbitInput::get('firstname', function ($firstname) use ($users) {
                $users->whereIn('users.user_firstname', $firstname);
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

            // Filter user by their email
            OrbitInput::get('email', function ($email) use ($users) {
                $users->whereIn('users.user_email', $email);
            });

            // Filter user by their status
            OrbitInput::get('status', function ($status) use ($users) {
                $users->whereIn('users.status', $status);
            });

            // Filter user by their role id
            OrbitInput::get('role_id', function ($roleId) use ($users) {
                $users->whereIn('users.user_role_id', $roleId);
            });

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
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'users.created_at',
                    'username'          => 'users.username',
                    'email'             => 'users.user_email',
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

    /**
     * GET - Search Consumer (currently only basic info)
     *
     * @author Kadek Bagus <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string        `sort_by`           (optional) - column order by
     * @param string        `sort_mode`         (optional) - asc or desc
     * @param integer       `user_id`           (optional)
     * @param string        `username`          (optional)
     * @param string        `email`             (optional)
     * @param string        `firstname`         (optional)
     * @param string        `lastname`          (optional)
     * @param string        `status`            (optional)
     * @param string        `username_like`     (optional)
     * @param string        `email_like`        (optional)
     * @param string        `firstname_like`    (optional)
     * @param string        `lastname_like`     (optional)
     * @param array|string  `merchant_id`       (optional) - Id of the merchant, could be array or string with comma separated value
     * @param array|string  `retailer_id`       (optional) - Id of the retailer (Shop), could be array or string with comma separated value
     * @param integer       `take`              (optional) - limit
     * @param integer       `skip`              (optional) - limit offset
     * @return Illuminate\Support\Facades\Response
     */
    public function getConsumerListing()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.user.getconsumer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.getconsumer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.getconsumer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_user')) {
                Event::fire('orbit.user.getconsumer.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.user.getconsumer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:username,email,firstname,lastname,registered_date',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );

            Event::fire('orbit.user.getconsumer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.getconsumer.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            // Builder object
            $users = User::Consumers()
                         ->with(array('userDetail', 'userDetail.lastVisitedShop'))
                         ->excludeDeleted();

            // Filter by merchant ids
            OrbitInput::get('merchant_id', function($merchantIds) use ($users) {
                $users->merchantIds($merchantIds);
            });

            // Filter by retailer (shop) ids
            OrbitInput::get('retailer_id', function($retailerIds) use ($users) {
                $users->retailerIds($retailerIds);
            });

            // Filter user by Ids
            OrbitInput::get('user_id', function ($userIds) use ($users) {
                $users->whereIn('users.user_id', $userIds);
            });

            // Filter user by username
            OrbitInput::get('username', function ($username) use ($users) {
                $users->whereIn('users.username', $username);
            });

            // Filter user by matching username pattern
            OrbitInput::get('username_like', function ($username) use ($users) {
                $users->where('users.username', 'like', "%$username%");
            });

            // Filter user by their firstname
            OrbitInput::get('firstname', function ($firstname) use ($users) {
                $users->whereIn('users.user_firstname', $firstname);
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
            OrbitInput::get('lastname_like', function ($lastname) use ($users) {
                $users->where('users.user_lastname', 'like', "%$lastname%");
            });

            // Filter user by their email
            OrbitInput::get('email', function ($email) use ($users) {
                $users->whereIn('users.user_email', $email);
            });

            // Filter user by their email pattern
            OrbitInput::get('email_like', function ($email) use ($users) {
                $users->where('users.user_email', 'like', "%$email%");
            });

            // Filter user by their status
            OrbitInput::get('status', function ($status) use ($users) {
                $users->whereIn('users.status', $status);
            });

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
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'users.created_at',
                    'username'          => 'users.username',
                    'email'             => 'users.user_email',
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
            Event::fire('orbit.user.getconsumer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.getconsumer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.user.getconsumer.query.error', array($this, $e));

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
            Event::fire('orbit.user.getconsumer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.user.getconsumer.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Change password user
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`                 (required) - ID of the user
     * @param string     `old_password`            (required) - user's old password
     * @param string     `new_password`            (required) - user's new password
     * @param string     `confirm_password`            (required) - confirmation user's new password
     * @return Illuminate\Support\Facades\Response
     */
    public function postChangePassword()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.user.postchangepassword.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postchangepassword.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postchangepassword.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('change_password')) {
                Event::fire('orbit.user.postchangepassword.authz.notallowed', array($this, $user));
                $changePasswordUserLang = Lang::get('validation.orbit.actionlist.change_password');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $changePasswordUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.user.postchangepassword.after.authz', array($this, $user));

            $this->registerCustomValidation();

            // $user_id = OrbitInput::post('user_id');
            $old_password = OrbitInput::post('old_password');
            $new_password = OrbitInput::post('new_password');
            $new_password_confirmation = OrbitInput::post('confirm_password');

            // Error message when old password is not correct
            $message = Lang::get('validation.orbit.access.old_password_not_match');

            $validator = Validator::make(
                array(
                    // 'user_id' => $user_id,
                    'old_password' => $old_password,
                    'new_password' => $new_password,
                    'new_password_confirmation' => $new_password_confirmation,
                ),
                array(
                    // 'user_id' => 'required|numeric|orbit.empty.user',
                    'old_password' => 'required|min:5|valid_user_password',
                    'new_password' => 'required|min:5|confirmed',
                ),
                array(
                    'valid_user_password' => $message,
                )
            );

            Event::fire('orbit.user.postchangepassword.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.postchangepassword.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $passupdateduser = User::excludeDeleted()->find($this->api->user->user_id);
            $passupdateduser->user_password = Hash::make($new_password);
            $passupdateduser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.user.postchangepassword.before.save', array($this, $passupdateduser));

            $passupdateduser->save();

            Event::fire('orbit.user.postchangepassword.after.save', array($this, $passupdateduser));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.updated.user');

            // Commit the changes
            $this->commit();

            Event::fire('orbit.user.postchangepassword.after.commit', array($this, $passupdateduser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postchangepassword.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postchangepassword.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postchangepassword.query.error', array($this, $e));

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
            Event::fire('orbit.user.postchangepassword.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.user.postchangepassword.before.render', array($this, $output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check user old password
        Validator::extend('valid_user_password', function ($attribute, $value, $parameters) {
            if (Hash::check($value, $this->api->user->user_password)) {
                return TRUE;
            }

            return FALSE;
        });

        // Check user email address, it should not exists
        Validator::extend('orbit.email.exists', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->where('user_email', $value)
                        ->first();

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.user', $user);

            return TRUE;
        });

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

        // Check the existance of the Role
        Validator::extend('orbit.empty.user_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check user email address, it should not exists
        Validator::extend('email_exists_but_me', function ($attribute, $value, $parameters) {
            $user_id = OrbitInput::post('user_id');
            $user = User::excludeDeleted()
                        ->where('user_email', $value)
                        ->where('user_id', '!=', $user_id)
                        ->first();

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.user', $user);

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
            $invalidRoles = array('super admin', 'administrator', 'consumer', 'customer');
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
    }
}
