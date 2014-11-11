<?php
/**
 * An API controller for managing merchants.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class MerchantAPIController extends ControllerAPI
{
    /**
     * POST - Delete Merchant
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMerchant()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.merchant.postdeletemerchant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.merchant.postdeletemerchant.after.auth', array($this));

            // Try to check access control list, does this merchant allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.merchant.postdeletemerchant.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_merchant')) {
                Event::fire('orbit.merchant.postdeletemerchant.authz.notallowed', array($this, $user));
                $deleteMerchantLang = Lang::get('validation.orbit.actionlist.delete_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteMerchantLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.merchant.postdeletemerchant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                ),
                array(
                    'merchant_id' => 'required|numeric|orbit.empty.merchant',
                )
            );

            Event::fire('orbit.merchant.postdeletemerchant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.merchant.postdeletemerchant.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deletemerchant = Merchant::find($merchant_id);
            $deletemerchant->status = 'deleted';
            $deletemerchant->modified_by = $this->api->user->user_id;

            Event::fire('orbit.merchant.postdeletemerchant.before.save', array($this, $deletemerchant));

            $deletemerchant->save();

            Event::fire('orbit.merchant.postdeletemerchant.after.save', array($this, $deletemerchant));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.merchant');

            // Commit the changes
            $this->commit();

            Event::fire('orbit.merchant.postdeletemerchant.after.commit', array($this, $deletemerchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.merchant.postdeletemerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.merchant.postdeletemerchant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.merchant.postdeletemerchant.query.error', array($this, $e));

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
            Event::fire('orbit.merchant.postdeletemerchant.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.merchant.postdeletemerchant.before.render', array($this, $output));

        return $output;
    }

     /**
     * POST - Add new merchant
     *
     * @author <kadek> <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`               (required) - User id for the merchant
     * @param string     `email`                 (required) - Email address of the merchant
     * @param string     `name`                  (optional) - Name of the merchant
     * @param string     `description`           (optional) - Merchant description
     * @param string     `address_line1`         (optional) - Address 1
     * @param string     `address_line2`         (optional) - Address 2
     * @param string     `address_line3`         (optional) - Address 3
     * @param integer    `city_id`               (optional) - City id
     * @param string     `city`                  (optional) - Name of the city
     * @param integer    `country_id`            (optional) - Country id
     * @param string     `country`               (optional) - Name of the country
     * @param string     `phone`                 (optional) - Phone of the merchant
     * @param string     `fax`                   (optional) - Fax of the merchant
     * @param string     `start_date_activity`   (optional) - Start date activity of the merchant
     * @param string     `status`                (optional) - Status of the merchant
     * @param string     `logo`                  (optional) - Logo of the merchant
     * @param string     `currency`              (optional) - Currency used by the merchant
     * @param string     `currency_symbol`       (optional) - Currency symbol
     * @param string     `tax_code1`             (optional) - Tax code 1
     * @param string     `tax_code2`             (optional) - Tax code 2
     * @param string     `tax_code3`             (optional) - Tax code 3
     * @param string     `slogan`                (optional) - Slogan for the merchant
     * @param string     `vat_included`          (optional) - Vat included
     * @param string     `object_type`           (optional) - Object type
     * @param string     `parent_id`             (optional) - The merchant id
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewMerchant()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.merchant.postnewmerchant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.merchant.postnewmerchant.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.merchant.postnewmerchant.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('new_merchant')) {
                Event::fire('orbit.merchant.postnewmerchant.authz.notallowed', array($this, $user));
                $createMerchantLang = Lang::get('validation.orbit.actionlist.new_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createMerchantLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.merchant.postnewmerchant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $user_id = OrbitInput::post('user_id');
            $email = OrbitInput::post('email');
            $name = OrbitInput::post('name');
            $description = OrbitInput::post('description');
            $address_line1 = OrbitInput::post('address_line1');
            $address_line2 = OrbitInput::post('address_line2');
            $address_line3 = OrbitInput::post('address_line3');
            $city_id = OrbitInput::post('city_id');
            $city = OrbitInput::post('city');
            $country_id = OrbitInput::post('country_id');
            $country = OrbitInput::post('country');
            $phone = OrbitInput::post('phone');
            $fax = OrbitInput::post('fax');
            $start_date_activity = OrbitInput::post('start_date_activity');
            $status = OrbitInput::post('status');
            $logo = OrbitInput::post('logo');
            $currency = OrbitInput::post('currency');
            $currency_symbol = OrbitInput::post('currency_symbol');
            $tax_code1 = OrbitInput::post('tax_code1');
            $tax_code2 = OrbitInput::post('tax_code2');
            $tax_code3 = OrbitInput::post('tax_code3');
            $slogan = OrbitInput::post('slogan');
            $vat_included = OrbitInput::post('vat_included');
            $object_type = OrbitInput::post('object_type');
            $parent_id = OrbitInput::post('parent_id');

            $validator = Validator::make(
                array(
                    'user_id'   => $user_id,
                    'email'     => $email,
                ),
                array(
                    'user_id'   => 'required|numeric',
                    'email'     => 'required|email|orbit.email.exists',
                )
            );

            Event::fire('orbit.merchant.postnewmerchant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.merchant.postnewmerchant.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $newmerchant = new Merchant();
            $newmerchant->user_id = $user_id;
            $newmerchant->email = $email;
            $newmerchant->name = $name;
            $newmerchant->description = $description;
            $newmerchant->address_line1 = $address_line1;
            $newmerchant->address_line2 = $address_line2;
            $newmerchant->address_line3 = $address_line3;
            $newmerchant->city_id = $city_id;
            $newmerchant->city = $city;
            $newmerchant->country_id = $country_id;
            $newmerchant->country = $country;
            $newmerchant->phone = $phone;
            $newmerchant->fax = $fax;
            $newmerchant->start_date_activity = $start_date_activity;
            $newmerchant->status = $status;
            $newmerchant->logo = $logo;
            $newmerchant->currency = $currency;
            $newmerchant->currency_symbol = $currency_symbol;
            $newmerchant->tax_code1 = $tax_code1;
            $newmerchant->tax_code2 = $tax_code2;
            $newmerchant->tax_code3 = $tax_code3;
            $newmerchant->slogan = $slogan;
            $newmerchant->vat_included = $vat_included;
            $newmerchant->object_type = $object_type;
            $newmerchant->parent_id = $parent_id;
            $newmerchant->modified_by = $this->api->user->user_id;

            Event::fire('orbit.merchant.postnewmerchant.before.save', array($this, $newmerchant));

            $newmerchant->save();

            Event::fire('orbit.merchant.postnewmerchant.after.save', array($this, $newmerchant));
            $this->response->data = $newmerchant->toArray();

            // Commit the changes
            $this->commit();

            Event::fire('orbit.merchant.postnewmerchant.after.commit', array($this, $newmerchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.merchant.postnewmerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.merchant.postnewmerchant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.merchant.postnewmerchant.query.error', array($this, $e));

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
            Event::fire('orbit.merchant.postnewmerchant.general.exception', array($this, $e));

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
     * GET - Search merchant
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sort_by`               (optional) - column order by
     * @param string   `sort_mode`             (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `merchant_id`           (optional)
     * @param integer  `user_id`               (optional)
     * @param string   `email`                 (optional)
     * @param string   `name`                  (optional)
     * @param string   `description`           (optional)
     * @param string   `address1`              (optional)
     * @param string   `address2`              (optional)
     * @param string   `address3`              (optional)
     * @param string   `city_id`               (optional)
     * @param string   `city`                  (optional)
     * @param string   `country_id`            (optional)
     * @param string   `country`               (optional)
     * @param string   `phone`                 (optional)
     * @param string   `fax`                   (optional)
     * @param string   `status`                (optional)
     * @param string   `currency`              (optional)
     * @param string   `name_like`             (optional)
     * @param string   `email_like`            (optional)
     * @param string   `description_like`      (optional)
     * @param string   `address1_like`         (optional)
     * @param string   `address2_like`         (optional)
     * @param string   `address3_like`         (optional)
     * @param string   `city_like`             (optional)
     * @param string   `country_like`          (optional)
     * @return Illuminate\Support\Facades\Response
     */

    public function getSearchMerchant()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.merchant.getsearchmerchant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.merchant.getsearchmerchant.after.auth', array($this));

            // Try to check access control list, does this merchant allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.merchant.getsearchmerchant.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_merchant')) {
                Event::fire('orbit.merchant.getsearchmerchant.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.merchant.getsearchmerchant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::get('merchant_id');
            $user_id = OrbitInput::get('user_id');
            $name = OrbitInput::get('name');
            $name_like = OrbitInput::get('name_like');
            $description = OrbitInput::get('description');
            $description_like = OrbitInput::get('description_like');
            $address1 = OrbitInput::get('address1');
            $address2 = OrbitInput::get('address2');
            $address3 = OrbitInput::get('address3');
            $address1_like = OrbitInput::get('address1_like');
            $address2_like = OrbitInput::get('address2_like');
            $address3_like = OrbitInput::get('address3_like');
            $city = OrbitInput::get('city');
            $city_id = OrbitInput::get('city_id');
            $city_like = OrbitInput::get('city_like');
            $country = OrbitInput::get('country');
            $country_id = OrbitInput::get('country_id');
            $country_like = OrbitInput::get('country_like');
            $email = OrbitInput::get('email');
            $email_like = OrbitInput::get('email_like');
            $phone = OrbitInput::get('phone');
            $fax = OrbitInput::get('fax');
            $status = OrbitInput::get('status');
            $currency = OrbitInput::get('currency');
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
                    'sort_by' => 'in:merchant_id,user_id,email,name,registered_date,description,address1,address2,address3,city_id,city,country_id,country,phone,fax,status,currency',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );

            Event::fire('orbit.merchant.getsearchmerchant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.merchant.getsearchmerchant.after.validation', array($this, $validator));

            $this->beginTransaction();

            if (! empty($name)) {
                $field = 'name';
                $keyword = $name;
            } elseif (! empty($name_like)) {
                $field = 'name';
                $keyword = array();
                if (is_array($name_like)) {
                    foreach ($name_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$name_like.'%';
                }
            } elseif (! empty($description)) {
                $field = 'description';
                $keyword = $description;
            } elseif (! empty($description_like)) {
                $field = 'description';
                $keyword = array();
                if (is_array($description_like)) {
                    foreach ($description_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$description_like.'%';
                }
            } elseif (! empty($email)) {
                $field = 'email';
                $keyword = $email;
            } elseif (! empty($email_like)) {
                $field = 'email';
                $keyword = array();
                if (is_array($email_like)) {
                    foreach ($email_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$email_like.'%';
                }
            } elseif (! empty($address1)) {
                $field = 'address_line1';
                $keyword = $address1;
            } elseif (! empty($address1_like)) {
                $field = 'address_line1';
                $keyword = array();
                if (is_array($address1_like)) {
                    foreach ($address1_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$address1_like.'%';
                }
            } elseif (! empty($address2)) {
                $field = 'address_line2';
                $keyword = $address2;
            } elseif (! empty($address2_like)) {
                $field = 'address_line2';
                $keyword = array();
                if (is_array($address2_like)) {
                    foreach ($address2_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$address2_like.'%';
                }
            } elseif (! empty($address3)) {
                $field = 'address_line3';
                $keyword = $address3;
            } elseif (! empty($address3_like)) {
                $field = 'address_line3';
                $keyword = array();
                if (is_array($address3_like)) {
                    foreach ($address3_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$address3_like.'%';
                }
            } elseif (! empty($city)) {
                $field = 'city';
                $keyword = $city;
            } elseif (! empty($city_like)) {
                $field = 'city';
                $keyword = array();
                if (is_array($city_like)) {
                    foreach ($city_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$city_like.'%';
                }
            } elseif (! empty($country)) {
                $field = 'country';
                $keyword = $country;
            } elseif (! empty($country_like)) {
                $field = 'country';
                $keyword = array();
                if (is_array($country_like)) {
                    foreach ($country_like as $keylike) {
                        $keylike = '%'.$keylike;
                        $keyword[] = $keylike;
                    }
                } else {
                    $keyword[] = '%'.$country_like.'%';
                }
            } elseif (! empty($phone)) {
                $field = 'phone';
                $keyword = $phone;
            } elseif (! empty($fax)) {
                $field = 'fax';
                $keyword = $fax;
            } elseif (! empty($currency)) {
                $field = 'currency';
                $keyword = $currency;
            } elseif (! empty($status)) {
                $field = 'status';
                $keyword = $status;
            } elseif (! empty($merchant_id)) {
                $field = 'merchant_id';
                $keyword = $merchant_id;
            } elseif (! empty($userid)) {
                $field = 'user_id';
                $keyword = $userid;
            } elseif (! empty($city_id)) {
                $field = 'city_id';
                $keyword = $city_id;
            } elseif (! empty($country_id)) {
                $field = 'country_id';
                $keyword = $country_id;
            } else {
                $field = '';
                $keyword = '';
            }

            // if using 'LIKE' operator change $operator from '=' to 'LIKE'
            if (! empty($email_like) || ! empty($name_like) || ! empty($description_like) || ! empty($address1_like) || ! empty($address2_like) || ! empty($address3_like) || ! empty($city_like) || ! empty($country_like)) {
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
                $hit = Merchant::count();
                if ($hit<=$maxrecord) {
                    $maxrecord = $hit;
                }
                $queryresult = Merchant::where('status', '!=', 'deleted')->orderBy($sort_by, $sort_mode)->take($maxrecord)->skip($skip)->get();
            } else {
                $queryresult = Merchant::where('status', '!=', 'deleted')->where(function ($query) use ($keyword, $field, $operator) {
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
                $error = Lang::get('statuses.orbit.nodata.merchant');
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

            Event::fire('orbit.merchant.getsearchmerchant.after.commit', array($this, $result));

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.merchant.getsearchmerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.merchant.getsearchmerchant.invalid.arguments', array($this, $e));

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
            Event::fire('orbit.merchant.getsearchmerchant.query.error', array($this, $e));

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
            Event::fire('orbit.merchant.getsearchmerchant.general.exception', array($this, $e));

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
     * POST - Update merchant
     *
     * @author <Kadek> <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`           (required) - ID of the merchant
     * @param integer    `user_id`               (required) - User id for the merchant
     * @param string     `email`                 (required) - Email address of the merchant
     * @param string     `name`                  (optional) - Name of the merchant
     * @param string     `description`           (optional) - Merchant description
     * @param string     `address_line1`         (optional) - Address 1
     * @param string     `address_line2`         (optional) - Address 2
     * @param string     `address_line3`         (optional) - Address 3
     * @param integer    `city_id`               (optional) - City id
     * @param string     `city`                  (optional) - Name of the city
     * @param integer    `country_id`            (optional) - Country id
     * @param string     `country`               (optional) - Name of the country
     * @param string     `phone`                 (optional) - Phone of the merchant
     * @param string     `fax`                   (optional) - Fax of the merchant
     * @param string     `start_date_activity`   (optional) - Start date activity of the merchant
     * @param string     `status`                (optional) - Status of the merchant
     * @param string     `logo`                  (optional) - Logo of the merchant
     * @param string     `currency`              (optional) - Currency used by the merchant
     * @param string     `currency_symbol`       (optional) - Currency symbol
     * @param string     `tax_code1`             (optional) - Tax code 1
     * @param string     `tax_code2`             (optional) - Tax code 2
     * @param string     `tax_code3`             (optional) - Tax code 3
     * @param string     `slogan`                (optional) - Slogan for the merchant
     * @param string     `vat_included`          (optional) - Vat included
     * @param string     `object_type`           (optional) - Object type
     * @param string     `parent_id`             (optional) - The merchant id
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateMerchant()
    {
        try {
            $httpCode=200;

            Event::fire('orbit.merchant.postupdatemerchant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.merchant.postupdatemerchant.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.merchant.postupdatemerchant.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_merchant')) {
                Event::fire('orbit.merchant.postupdatemerchant.authz.notallowed', array($this, $user));
                $updateMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateMerchantLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.merchant.postupdatemerchant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');
            $user_id = OrbitInput::post('user_id');
            $email = OrbitInput::post('email');
            $name = OrbitInput::post('name');
            $description = OrbitInput::post('description');
            $address_line1 = OrbitInput::post('address_line1');
            $address_line2 = OrbitInput::post('address_line2');
            $address_line3 = OrbitInput::post('address_line3');
            $city_id = OrbitInput::post('city_id');
            $city = OrbitInput::post('city');
            $country_id = OrbitInput::post('country_id');
            $country = OrbitInput::post('country');
            $phone = OrbitInput::post('phone');
            $fax = OrbitInput::post('fax');
            $start_date_activity = OrbitInput::post('start_date_activity');
            $status = OrbitInput::post('status');
            $logo = OrbitInput::post('logo');
            $currency = OrbitInput::post('currency');
            $currency_symbol = OrbitInput::post('currency_symbol');
            $tax_code1 = OrbitInput::post('tax_code1');
            $tax_code2 = OrbitInput::post('tax_code2');
            $tax_code3 = OrbitInput::post('tax_code3');
            $slogan = OrbitInput::post('slogan');
            $vat_included = OrbitInput::post('vat_included');
            $object_type = OrbitInput::post('object_type');
            $parent_id = OrbitInput::post('parent_id');

            $validator = Validator::make(
                array(
                    'merchant_id'       => $merchant_id,
                    'user_id'           => $user_id,
                    'email'             => $email,
                ),
                array(
                    'merchant_id'       => 'required|numeric',
                    'user_id'           => 'required|numeric',
                    'email'             => 'required|email|orbit.email.exists',
                )
            );

            Event::fire('orbit.merchant.postupdatemerchant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.merchant.postupdatemerchant.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedmerchant = Merchant::find($merchant_id);
            $updatedmerchant->user_id = $user_id;
            $updatedmerchant->email = $email;
            $updatedmerchant->name = $name;
            $updatedmerchant->description = $description;
            $updatedmerchant->address_line1 = $address_line1;
            $updatedmerchant->address_line2 = $address_line2;
            $updatedmerchant->address_line3 = $address_line3;
            $updatedmerchant->city_id = $city_id;
            $updatedmerchant->city = $city;
            $updatedmerchant->country_id = $country_id;
            $updatedmerchant->country = $country;
            $updatedmerchant->phone = $phone;
            $updatedmerchant->fax = $fax;
            $updatedmerchant->start_date_activity = $start_date_activity;
            $updatedmerchant->status = $status;
            $updatedmerchant->logo = $logo;
            $updatedmerchant->currency = $currency;
            $updatedmerchant->currency_symbol = $currency_symbol;
            $updatedmerchant->tax_code1 = $tax_code1;
            $updatedmerchant->tax_code2 = $tax_code2;
            $updatedmerchant->tax_code3 = $tax_code3;
            $updatedmerchant->slogan = $slogan;
            $updatedmerchant->vat_included = $vat_included;
            $updatedmerchant->object_type = $object_type;
            $updatedmerchant->parent_id = $parent_id;
            $updatedmerchant->modified_by = $this->api->user->user_id;

            Event::fire('orbit.merchant.postupdatemerchant.before.save', array($this, $updatedmerchant));

            $updatedmerchant->save();

            Event::fire('orbit.merchant.postupdatemerchant.after.save', array($this, $updatedmerchant));
            $this->response->data = $updatedmerchant->toArray();

            // Commit the changes
            $this->commit();

            Event::fire('orbit.merchant.postupdatemerchant.after.commit', array($this, $updatedmerchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.merchant.postupdatemerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.merchant.postupdatemerchant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.merchant.postupdatemerchant.query.error', array($this, $e));

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
            Event::fire('orbit.merchant.postupdatemerchant.general.exception', array($this, $e));

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
        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Merchant::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });
    }
}
