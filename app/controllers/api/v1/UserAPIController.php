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
     * @author <Ahmad Anshori> <ahmad@dominopos.com>
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
        try {
            $httpCode = 200;

            Event::fire('orbit.user.postnewuser.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postnewuser.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postnewuser.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_user')) {
                Event::fire('orbit.user.postnewuser.authz.notallowed', array($this, $user));
                $createUserLang = Lang::get('validation.orbit.actionlist.add_new_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.user.postnewuser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $email = OrbitInput::post('email');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');
            $user_role_id = OrbitInput::post('role_id');

            $validator = Validator::make(
                array(
                    'email'     => $email,
                    'password'  => $password,
                    'password_confirmation' => $password2,
                    'role_id' => $user_role_id,
                ),
                array(
                    'email'     => 'required|email|orbit.email.exists',
                    'password'  => 'required|min:5|confirmed',
                    'role_id' => 'required|numeric|orbit.empty.role',
                )
            );

            Event::fire('orbit.user.postnewuser.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.postnewuser.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $newuser = new User();
            $newuser->username = $email;
            $newuser->user_email = $email;
            $newuser->user_password = Hash::make($password);
            $newuser->status = 'pending';
            $newuser->user_role_id = $user_role_id;
            $newuser->user_ip = $_SERVER['REMOTE_ADDR'];
            $newuser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.user.postnewuser.before.save', array($this, $newuser));

            $newuser->save();

            $userdetail = new UserDetail();
            $userdetail = $newuser->userdetail()->save($userdetail);

            $newuser->setRelation('userdetail', $userdetail);
            $newuser->userdetail = $userdetail;

            $apikey = new Apikey();
            $apikey->api_key = Apikey::genApiKey($newuser);
            $apikey->api_secret_key = Apikey::genSecretKey($newuser);
            $apikey->status = 'active';
            $apikey->user_id = $newuser->user_id;
            $apikey = $newuser->apikey()->save($apikey);

            $newuser->setRelation('apikey', $apikey);
            $newuser->apikey = $apikey;
            $newuser->setHidden(array('user_password'));

            Event::fire('orbit.user.postnewuser.after.save', array($this, $newuser));
            $this->response->data = $newuser->toArray();

            // Commit the changes
            $this->commit();

            Event::fire('orbit.user.postnewuser.after.commit', array($this, $newuser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postnewuser.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postnewuser.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
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
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.user.postnewuser.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
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

            $this->response->code = $e->getCode();
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
     * @author <Ahmad Anshori> <ahmad@dominopos.com>
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

            if (! ACL::create($user)->isAllowed('update_user')) {
                Event::fire('orbit.user.postupdateuser.authz.notallowed', array($this, $user));
                $updateUserLang = Lang::get('validation.orbit.actionlist.update_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.user.postupdateuser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $user_id = OrbitInput::post('user_id');
            $email = OrbitInput::post('email');
            $username = OrbitInput::post('username');
            $user_firstname = OrbitInput::post('firstname');
            $user_lastname = OrbitInput::post('lastname');
            $status = OrbitInput::post('status');
            $user_role_id = OrbitInput::post('role_id');

            $validator = Validator::make(
                array(
                    'user_id'           => $user_id,
                    'username'          => $username,
                    'email'             => $email,
                    'role_id'           => $user_role_id,
                    'status'            => $status,
                ),
                array(
                    'user_id'           => 'required|numeric',
                    'username'          => 'required|orbit.exists.username',
                    'email'             => 'required|email|email_exists_but_me',
                    'role_id'           => 'required|numeric|orbit.empty.role',
                    'status'            => 'orbit.empty.user_status',
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

            $updateduser = User::find($user_id);
            $updateduser->username = $username;
            $updateduser->user_email = $email;
            $updateduser->user_firstname = $user_firstname;
            $updateduser->user_lastname = $user_lastname;
            $updateduser->status = $status;
            $updateduser->user_role_id = $user_role_id;
            $updateduser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.user.postupdateuser.before.save', array($this, $updateduser));

            $updateduser->save();

            $userdetail = UserDetail::where('user_id', '=', $user_id)->first();
            $updateduser->setRelation('userdetail', $userdetail);

            $updateduser->userdetail = $userdetail;

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
            $this->response->data = $updateduser->toArray();

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

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);

    }

    /**
     * GET - Search user (currently only basic info)
     *
     * @author <Ahmad Anshori> <ahmad@dominopos.com>
     * @author <Kadek Bagus> <kadek@dominopos.com>
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

            $userid = OrbitInput::get('user_id');
            $roleid = OrbitInput::get('role_id');
            $username = OrbitInput::get('username');
            $username_like = OrbitInput::get('username_like');
            $firstname = OrbitInput::get('firstname');
            $firstname_like = OrbitInput::get('firstname_like');
            $lastname = OrbitInput::get('lastname');
            $lastname_like = OrbitInput::get('lastname_like');
            $email = OrbitInput::get('email');
            $email_like = OrbitInput::get('email_like');
            $status = OrbitInput::get('status');
            $sort_mode = OrbitInput::get('sortmode');
            $sort_by = OrbitInput::get('sortby');
            $take = OrbitInput::get('take');
            $skip = OrbitInput::get('skip');
            $sortByUserLang = Lang::get('validation.orbit.actionlist.');
            $message = Lang::get('validation.orbit.access.forbidden', array('action' => $sortByUserLang));
            $operator = '=';

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:username,user_email,user_firstname,user_lastname,registered_date',
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

            $this->beginTransaction();

            if (! empty($username)) {
                $field = 'username';
                $keyword = $username;
            } elseif (! empty($username_like)) {
                $field = 'username';
                $keyword = array();
                if (is_array($username_like)) {
                    foreach ($username_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$username_like.'%';
                }
            } elseif (! empty($firstname)) {
                $field = 'user_firstname';
                $keyword = $firstname;
            } elseif (! empty($firstname_like)) {
                $field = 'user_firstname';
                $keyword = array();
                if (is_array($firstname_like)) {
                    foreach ($firstname_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$firstname_like.'%';
                }
            } elseif (! empty($lastname)) {
                $field = 'user_lastname';
                $keyword = $lastname;
            } elseif (! empty($lastname_like)) {
                $field = 'user_lastname';
                $keyword = array();
                if (is_array($lastname_like)) {
                    foreach ($lastname_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$lastname_like.'%';
                }
            } elseif (! empty($email)) {
                $field = 'user_email';
                $keyword = $email;
            } elseif (! empty($email_like)) {
                $field = 'user_email';
                $keyword = array();
                if (is_array($email_like)) {
                    foreach ($email_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$email_like.'%';
                }
            } elseif (! empty($status)) {
                $field = 'status';
                $keyword = $status;
            } elseif (! empty($userid)) {
                $field = 'user_id';
                $keyword = $userid;
            } elseif (! empty($roleid)) {
                $field = 'user_role_id';
                $keyword = $roleid;
            } else {
                $field = '';
                $keyword = '';
            }

            // if using 'LIKE' operator change $operator from '=' to 'LIKE'
            if (! empty($username_like) || ! empty($firstname_like) || ! empty($lastname_like) || ! empty($email_like)) {
                $operator = 'LIKE';
            }

            // if sort_by is not defined then use registered_date
            if (empty($sort_by) || $sort_by=='registered_date') {
                $sort_by = 'created_at';
            }

            // if sort_mode is not defined then use 'desc' as default sort mode
            if (empty($sort_mode)) {
                $sort_mode = 'desc';
            }

            // if Config::get('orbit.pagination.max_record') is not defined then set default max_record to 10
            if (!empty(Config::get('orbit.pagination.max_record'))) {
                $maxrecord = Config::get('orbit.pagination.max_record');
            } else {
                $maxrecord = 10;
            }

            // if take exist then set max_record to $take
            if (! empty($take)) {
                $maxrecord = $take;
            }

            // if skip is not defined then set default skip to 0
            if (empty($skip)) {
                $skip = 0;
            }

            // if there is no arguments passed then select all records
            if (empty($field) && empty($keyword)) {
                $hit = User::count();
                if ($hit<=$maxrecord) {
                    $maxrecord = $hit;
                }
                $queryresult = User::with('apikey', 'userdetail')->where('status', '!=', 'deleted')->orderBy($sort_by, $sort_mode)->take($maxrecord)->skip($skip)->get();
            } else {
                $queryresult = User::with('apikey', 'userdetail')->where('status', '!=', 'deleted')->where(function ($query) use ($keyword, $field, $operator) {
                    foreach ($keyword as $key) {
                        $query->orWhere($field, $operator, $key);
                    }
                })->orderBy($sort_by, $sort_mode)->take($maxrecord)->skip($skip)->get();
            }

            $count = count($queryresult);

            if ($count <= $maxrecord) {
                $maxrecord = $count;
            }

            if ($count == 0) {
                $error = Lang::get('statuses.orbit.nodata.user');
                $result['total_records'] = 0;
                $result['returned_records'] = 0;
                $result['records'] = null;

                $this->response->status = 'success';
                $this->response->message = $error;
                $this->response->data = $result;
            } else {
                if (! empty($take)) {
                    $result['total_records'] = $count;
                    $result['returned_records'] = $take;
                } else {
                    $result['total_records'] = $maxrecord;
                    $result['returned_records'] = $count;
                }
                $result['records'] = $queryresult->toArray();

                $this->response->data = $result;
            }

            // Commit the changes
            $this->commit();

            Event::fire('orbit.user.getsearchuser.after.commit', array($this, $result));

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.getsearchuser.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
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

            // Rollback the changes
            $this->rollBack();
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

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.user.getsearchuser.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
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

    }
}
