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
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`                 (required) - User id for the merchant
     * @param string     `email`                   (required) - Email address of the merchant
     * @param string     `name`                    (optional) - Name of the merchant
     * @param string     `description`             (optional) - Merchant description
     * @param string     `address_line1`           (optional) - Address 1
     * @param string     `address_line2`           (optional) - Address 2
     * @param string     `address_line3`           (optional) - Address 3
     * @param integer    `postal_code`             (optional) - Postal code
     * @param integer    `city_id`                 (optional) - City id
     * @param string     `city`                    (optional) - Name of the city
     * @param integer    `country_id`              (optional) - Country id
     * @param string     `country`                 (optional) - Name of the country
     * @param string     `phone`                   (optional) - Phone of the merchant
     * @param string     `fax`                     (optional) - Fax of the merchant
     * @param string     `start_date_activity`     (optional) - Start date activity of the merchant
     * @param string     `end_date_activity`       (optional) - End date activity of the merchant
     * @param string     `status`                  (optional) - Status of the merchant
     * @param string     `logo`                    (optional) - Logo of the merchant
     * @param string     `currency`                (optional) - Currency used by the merchant
     * @param string     `currency_symbol`         (optional) - Currency symbol
     * @param string     `tax_code1`               (optional) - Tax code 1
     * @param string     `tax_code2`               (optional) - Tax code 2
     * @param string     `tax_code3`               (optional) - Tax code 3
     * @param string     `slogan`                  (optional) - Slogan for the merchant
     * @param string     `vat_included`            (optional) - Vat included
     * @param string     `contact_person_firstname`(optional) - Contact person first name
     * @param string     `contact_person_lastname` (optional) - Contact person last name
     * @param string     `contact_person_position` (optional) - Contact person position
     * @param string     `contact_person_phone`    (optional) - Contact person phone
     * @param string     `contact_person_phone2`   (optional) - Contact person second phone
     * @param string     `contact_person_email`    (optional) - Contact person email
     * @param string     `sector_of_activity`      (optional) - Sector of activity
     * @param string     `vat_included`            (optional) - Vat included
     * @param string     `url`                     (optional) - Url
     * @param string     `masterbox_number`        (optional) - Masterbox number
     * @param string     `slavebox_number`         (optional) - Slavebox number
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

            if (! ACL::create($user)->isAllowed('create_merchant')) {
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
            $postal_code = OrbitInput::post('postal_code');
            $city_id = OrbitInput::post('city_id');
            $city = OrbitInput::post('city');
            $country_id = OrbitInput::post('country_id');
            $country = OrbitInput::post('country');
            $phone = OrbitInput::post('phone');
            $fax = OrbitInput::post('fax');
            $start_date_activity = OrbitInput::post('start_date_activity');
            $end_date_activity = OrbitInput::post('end_date_activity');
            $status = OrbitInput::post('status');
            $logo = OrbitInput::post('logo');
            $currency = OrbitInput::post('currency');
            $currency_symbol = OrbitInput::post('currency_symbol');
            $tax_code1 = OrbitInput::post('tax_code1');
            $tax_code2 = OrbitInput::post('tax_code2');
            $tax_code3 = OrbitInput::post('tax_code3');
            $slogan = OrbitInput::post('slogan');
            $vat_included = OrbitInput::post('vat_included');
            $contact_person_firstname = OrbitInput::post('contact_person_firstname');
            $contact_person_lastname = OrbitInput::post('contact_person_lastname');
            $contact_person_position = OrbitInput::post('contact_person_position');
            $contact_person_phone = OrbitInput::post('contact_person_phone');
            $contact_person_phone2 = OrbitInput::post('contact_person_phone2');
            $contact_person_email = OrbitInput::post('contact_person_email');
            $sector_of_activity = OrbitInput::post('sector_of_activity');
            $object_type = OrbitInput::post('object_type');
            $parent_id = OrbitInput::post('parent_id');
            $url = OrbitInput::post('url');
            $masterbox_number = OrbitInput::post('masterbox_number');
            $slavebox_number = OrbitInput::post('slavebox_number');

            $validator = Validator::make(
                array(
                    'user_id'   => $user_id,
                    'email'     => $email,
                    'name'      => $name,
                    'status'    => $status,
                ),
                array(
                    'user_id'   => 'required|numeric|orbit.empty.user',
                    'email'     => 'required|email',
                    'name'      => 'required',
                    'status'    => 'required|orbit.empty.merchant_status',
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
            $newmerchant->postal_code = $postal_code;
            $newmerchant->city_id = $city_id;
            $newmerchant->city = $city;
            $newmerchant->country_id = $country_id;
            $newmerchant->country = $country;
            $newmerchant->phone = $phone;
            $newmerchant->fax = $fax;
            $newmerchant->start_date_activity = $start_date_activity;
            $newmerchant->end_date_activity = $end_date_activity;
            $newmerchant->status = $status;
            $newmerchant->logo = $logo;
            $newmerchant->currency = $currency;
            $newmerchant->currency_symbol = $currency_symbol;
            $newmerchant->tax_code1 = $tax_code1;
            $newmerchant->tax_code2 = $tax_code2;
            $newmerchant->tax_code3 = $tax_code3;
            $newmerchant->slogan = $slogan;
            $newmerchant->vat_included = $vat_included;
            $newmerchant->contact_person_firstname = $contact_person_firstname;
            $newmerchant->contact_person_lastname = $contact_person_lastname;
            $newmerchant->contact_person_position = $contact_person_position;
            $newmerchant->contact_person_phone = $contact_person_phone;
            $newmerchant->contact_person_phone2 = $contact_person_phone2;
            $newmerchant->contact_person_email = $contact_person_email;
            $newmerchant->sector_of_activity = $sector_of_activity;
            $newmerchant->object_type = $object_type;
            $newmerchant->parent_id = $parent_id;
            $newmerchant->url = $url;
            $newmerchant->masterbox_number = $masterbox_number;
            $newmerchant->slavebox_number = $slavebox_number;
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
     * @param string   `sort_by`                       (optional) - column order by
     * @param string   `sort_mode`                     (optional) - asc or desc
     * @param integer  `take`                          (optional) - limit
     * @param integer  `skip`                          (optional) - limit offset
     * @param integer  `merchant_id`                   (optional)
     * @param integer  `user_id`                       (optional)
     * @param string   `email`                         (optional)
     * @param string   `name`                          (optional)
     * @param string   `description`                   (optional)
     * @param string   `address1`                      (optional)
     * @param string   `address2`                      (optional)
     * @param string   `address3`                      (optional)
     * @param integer  `postal_code`                   (optional) - Postal code
     * @param string   `city_id`                       (optional)
     * @param string   `city`                          (optional)
     * @param string   `country_id`                    (optional)
     * @param string   `country`                       (optional)
     * @param string   `phone`                         (optional)
     * @param string   `fax`                           (optional)
     * @param string   `status`                        (optional)
     * @param string   `currency`                      (optional)
     * @param string   `name_like`                     (optional)
     * @param string   `email_like`                    (optional)
     * @param string   `description_like`              (optional)
     * @param string   `address1_like`                 (optional)
     * @param string   `address2_like`                 (optional)
     * @param string   `address3_like`                 (optional)
     * @param string   `city_like`                     (optional)
     * @param string   `country_like`                  (optional)
     * @param string   `contact_person_firstname`      (optional) - Contact person firstname
     * @param string   `contact_person_firstname_like` (optional) - Contact person firstname like
     * @param string   `contact_person_lastname`       (optional) - Contact person lastname
     * @param string   `contact_person_lastname_like`  (optional) - Contact person lastname like
     * @param string   `contact_person_position`       (optional) - Contact person position
     * @param string   `contact_person_position_like`  (optional) - Contact person position like
     * @param string   `contact_person_phone`          (optional) - Contact person phone
     * @param string   `contact_person_phone2`         (optional) - Contact person phone2
     * @param string   `contact_person_email`          (optional) - Contact person email
     * @param string   `url`                           (optional) - Url
     * @param string   `masterbox_number`              (optional) - Masterbox number
     * @param string   `slavebox_number`               (optional) - Slavebox number
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

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,merchant_name,merchant_email,merchant_userid,merchant_description,merchantid,merchant_address1,merchant_address2,merchant_address3,merchant_cityid,merchant_city,merchant_countryid,merchant_country,merchant_phone,merchant_fax,merchant_status,merchant_currency',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.merchant_sortby'),
                )
            );

            Event::fire('orbit.merchant.getsearchmerchant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.merchant.getsearchmerchant.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $merchants = Merchant::excludeDeleted();

            // Filter merchant by Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($merchants) {
                $merchants->whereIn('merchants.merchant_id', $merchantIds);
            });

            // Filter merchant by Ids
            OrbitInput::get('user_id', function ($userIds) use ($merchants) {
                $merchants->whereIn('merchants.user_id', $userIds);
            });

            // Filter merchant by name
            OrbitInput::get('name', function ($name) use ($merchants) {
                $merchants->whereIn('merchants.name', $name);
            });

            // Filter merchant by name pattern
            OrbitInput::get('name_like', function ($name) use ($merchants) {
                $merchants->where('merchants.name', 'like', "%$name%");
            });

            // Filter merchant by description
            OrbitInput::get('description', function ($description) use ($merchants) {
                $merchants->whereIn('merchants.description', $description);
            });

            // Filter merchant by description pattern
            OrbitInput::get('description_like', function ($description) use ($merchants) {
                $merchants->where('merchants.description', 'like', "%$description%");
            });

            // Filter merchant by email
            OrbitInput::get('email', function ($email) use ($merchants) {
                $merchants->whereIn('merchants.email', $email);
            });

            // Filter merchant by email pattern
            OrbitInput::get('email_like', function ($email) use ($merchants) {
                $merchants->where('merchants.email', 'like', "%$email%");
            });

            // Filter merchant by address1
            OrbitInput::get('address1', function ($address1) use ($merchants) {
                $merchants->whereIn('merchants.address_line1', $address1);
            });

            // Filter merchant by address1 pattern
            OrbitInput::get('address1_like', function ($address1) use ($merchants) {
                $merchants->where('merchants.address_line1', 'like', "%$address1%");
            });

            // Filter merchant by address2
            OrbitInput::get('address2', function ($address2) use ($merchants) {
                $merchants->whereIn('merchants.address_line2', $address2);
            });

            // Filter merchant by address2 pattern
            OrbitInput::get('address2_like', function ($address2) use ($merchants) {
                $merchants->where('merchants.address_line2', 'like', "%$address2%");
            });

            // Filter merchant by address3
            OrbitInput::get('address3', function ($address3) use ($merchants) {
                $merchants->whereIn('merchants.address_line3', $address3);
            });

            // Filter merchant by address3 pattern
            OrbitInput::get('address3_like', function ($address3) use ($merchants) {
                $merchants->where('merchants.address_line3', 'like', "%$address3%");
            });

            // Filter merchant by postal code
            OrbitInput::get('postal_code', function ($postalcode) use ($merchants) {
                $merchants->whereIn('merchants.postal_code', $postalcode);
            });

            // Filter merchant by cityID
            OrbitInput::get('city_id', function ($cityIds) use ($merchants) {
                $merchants->whereIn('merchants.city_id', $cityIds);
            });

            // Filter merchant by city
            OrbitInput::get('city', function ($city) use ($merchants) {
                $merchants->whereIn('merchants.city', $city);
            });

            // Filter merchant by city pattern
            OrbitInput::get('city_like', function ($city) use ($merchants) {
                $merchants->where('merchants.city', 'like', "%$city%");
            });

            // Filter merchant by countryID
            OrbitInput::get('country_id', function ($countryId) use ($merchants) {
                $merchants->whereIn('merchants.country_id', $countryId);
            });

            // Filter merchant by country
            OrbitInput::get('country', function ($country) use ($merchants) {
                $merchants->whereIn('merchants.country', $country);
            });

            // Filter merchant by country pattern
            OrbitInput::get('country_like', function ($country) use ($merchants) {
                $merchants->where('merchants.country', 'like', "%$country%");
            });

            // Filter merchant by phone
            OrbitInput::get('phone', function ($phone) use ($merchants) {
                $merchants->whereIn('merchants.phone', $phone);
            });

            // Filter merchant by fax
            OrbitInput::get('fax', function ($fax) use ($merchants) {
                $merchants->whereIn('merchants.fax', $fax);
            });

            // Filter merchant by status
            OrbitInput::get('status', function ($status) use ($merchants) {
                $merchants->whereIn('merchants.status', $status);
            });

            // Filter merchant by currency
            OrbitInput::get('currency', function ($currency) use ($merchants) {
                $merchants->whereIn('merchants.currency', $currency);
            });

            // Filter merchant by contact person firstname
            OrbitInput::get('contact_person_firstname', function ($contact_person_firstname) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_firstname', $contact_person_firstname);
            });

            // Filter merchant by contact person firstname like
            OrbitInput::get('contact_person_firstname_like', function ($contact_person_firstname) use ($merchants) {
                $merchants->where('merchants.contact_person_firstname', 'like', "%$contact_person_firstname%");
            });

            // Filter merchant by contact person lastname
            OrbitInput::get('contact_person_lastname', function ($contact_person_lastname) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_lastname', $contact_person_lastname);
            });

            // Filter merchant by contact person lastname like
            OrbitInput::get('contact_person_lastname_like', function ($contact_person_lastname) use ($merchants) {
                $merchants->where('merchants.contact_person_lastname', 'like', "%$contact_person_lastname%");
            });

            // Filter merchant by contact person position
            OrbitInput::get('contact_person_position', function ($contact_person_position) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_position', $contact_person_position);
            });

            // Filter merchant by contact person position like
            OrbitInput::get('contact_person_position_like', function ($contact_person_position) use ($merchants) {
                $merchants->where('merchants.contact_person_position', 'like', "%$contact_person_position%");
            });

            // Filter merchant by contact person phone
            OrbitInput::get('contact_person_phone', function ($contact_person_phone) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_phone', $contact_person_phone);
            });

            // Filter merchant by contact person phone2
            OrbitInput::get('contact_person_phone2', function ($contact_person_phone2) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_phone2', $contact_person_phone2);
            });

            // Filter merchant by contact person email
            OrbitInput::get('contact_person_email', function ($contact_person_email) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_email', $contact_person_email);
            });

            // Filter merchant by sector of activity
            OrbitInput::get('sector_of_activity', function ($sector_of_activity) use ($merchants) {
                $merchants->whereIn('merchants.sector_of_activity', $sector_of_activity);
            });

            // Filter merchant by url
            OrbitInput::get('url', function ($url) use ($merchants) {
                $merchants->whereIn('merchants.url', $url);
            });

            // Filter merchant by masterbox_number
            OrbitInput::get('masterbox_number', function ($masterbox_number) use ($merchants) {
                $merchants->whereIn('merchants.masterbox_number', $masterbox_number);
            });

            // Filter merchant by slavebox_number
            OrbitInput::get('slavebox_number', function ($slavebox_number) use ($merchants) {
                $merchants->whereIn('merchants.slavebox_number', $slavebox_number);
            });

            $_merchants = clone $merchants;

            // Get the take args
            $take = $maxRecord;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;
            });
            $merchants->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $merchants) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $merchants->skip($skip);

            // Default sort by
            $sortBy = 'merchants.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'      => 'merchants.created_at',
                    'merchant_name'        => 'merchants.name',
                    'merchant_email'       => 'merchants.email',
                    'merchant_userid'      => 'merchants.user_id',
                    'merchant_description' => 'merchants.description',
                    'merchantid'           => 'merchants.merchant_id',
                    'merchant_address1'    => 'merchants.address_line1',
                    'merchant_address2'    => 'merchants.address_line2',
                    'merchant_address3'    => 'merchants.address_line3',
                    'merchant_cityid'      => 'merchants.city_id',
                    'merchant_city'        => 'merchants.city',
                    'merchant_countryid'   => 'merchants.country_id',
                    'merchant_country'     => 'merchants.country',
                    'merchant_phone'       => 'merchants.phone',
                    'merchant_fax'         => 'merchants.fax',
                    'merchant_status'      => 'merchants.status',
                    'merchant_currency'    => 'merchants.currency',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $merchants->orderBy($sortBy, $sortMode);

            $totalRec = $_merchants->count();
            $listOfRec = $merchants->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.merchant');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.merchant.getsearchmerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
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
        } catch (Exception $e) {
            Event::fire('orbit.merchant.getsearchmerchant.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        $output = $this->render($httpCode);
        Event::fire('orbit.merchant.getsearchmerchant.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Update merchant
     *
     * @author <Kadek> <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`              (required) - ID of the merchant
     * @param integer    `user_id`                  (required) - User id for the merchant
     * @param string     `email`                    (required) - Email address of the merchant
     * @param string     `name`                     (optional) - Name of the merchant
     * @param string     `description`              (optional) - Merchant description
     * @param string     `address_line1`            (optional) - Address 1
     * @param string     `address_line2`            (optional) - Address 2
     * @param string     `address_line3`            (optional) - Address 3
     * @param integer    `postal_code`              (optional) - Postal code
     * @param integer    `city_id`                  (optional) - City id
     * @param string     `city`                     (optional) - Name of the city
     * @param integer    `country_id`               (optional) - Country id
     * @param string     `country`                  (optional) - Name of the country
     * @param string     `phone`                    (optional) - Phone of the merchant
     * @param string     `fax`                      (optional) - Fax of the merchant
     * @param string     `start_date_activity`      (optional) - Start date activity of the merchant
     * @param string     `status`                   (optional) - Status of the merchant
     * @param string     `logo`                     (optional) - Logo of the merchant
     * @param string     `currency`                 (optional) - Currency used by the merchant
     * @param string     `currency_symbol`          (optional) - Currency symbol
     * @param string     `tax_code1`                (optional) - Tax code 1
     * @param string     `tax_code2`                (optional) - Tax code 2
     * @param string     `tax_code3`                (optional) - Tax code 3
     * @param string     `slogan`                   (optional) - Slogan for the merchant
     * @param string     `vat_included`             (optional) - Vat included
     * @param string     `contact_person_firstname` (optional) - Contact person firstname
     * @param string     `contact_person_lastname`  (optional) - Contact person lastname
     * @param string     `contact_person_position`  (optional) - Contact person position
     * @param string     `contact_person_phone`     (optional) - Contact person phone
     * @param string     `contact_person_phone2`    (optional) - Contact person phone2
     * @param string     `contact_person_email`     (optional) - Contact person email
     * @param string     `sector_of_activity`       (optional) - Sector of activity
     * @param string     `object_type`              (optional) - Object type
     * @param string     `parent_id`                (optional) - The merchant id
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
            $postal_code = OrbitInput::post('postal_code');
            $city_id = OrbitInput::post('city_id');
            $city = OrbitInput::post('city');
            $country_id = OrbitInput::post('country_id');
            $country = OrbitInput::post('country');
            $phone = OrbitInput::post('phone');
            $fax = OrbitInput::post('fax');
            $start_date_activity = OrbitInput::post('start_date_activity');
            $end_date_activity = OrbitInput::post('end_date_activity');
            $status = OrbitInput::post('status');
            $logo = OrbitInput::post('logo');
            $currency = OrbitInput::post('currency');
            $currency_symbol = OrbitInput::post('currency_symbol');
            $tax_code1 = OrbitInput::post('tax_code1');
            $tax_code2 = OrbitInput::post('tax_code2');
            $tax_code3 = OrbitInput::post('tax_code3');
            $slogan = OrbitInput::post('slogan');
            $vat_included = OrbitInput::post('vat_included');
            $contact_person_firstname = OrbitInput::post('contact_person_firstname');
            $contact_person_lastname = OrbitInput::post('contact_person_lastname');
            $contact_person_position = OrbitInput::post('contact_person_position');
            $contact_person_phone = OrbitInput::post('contact_person_phone');
            $contact_person_phone2 = OrbitInput::post('contact_person_phone2');
            $contact_person_email = OrbitInput::post('contact_person_email');
            $sector_of_activity = OrbitInput::post('sector_of_activity');
            $object_type = OrbitInput::post('object_type');
            $parent_id = OrbitInput::post('parent_id');
            $url = OrbitInput::post('url');
            $masterbox_number = OrbitInput::post('masterbox_number');
            $slavebox_number = OrbitInput::post('slavebox_number');

            $validator = Validator::make(
                array(
                    'merchant_id'       => $merchant_id,
                    'user_id'           => $user_id,
                    'email'             => $email,
                    'status'            => $status,
                    'name'              => $name,
                ),
                array(
                    'merchant_id'       => 'required|numeric|orbit.empty.merchant',
                    'user_id'           => 'required|numeric|orbit.empty.user',
                    'email'             => 'required|email',
                    'status'            => 'required|orbit.empty.merchant_status',
                    'name'              => 'required',
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
            $updatedmerchant->postal_code = $postal_code;
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
            $updatedmerchant->contact_person_firstname = $contact_person_firstname;
            $updatedmerchant->contact_person_lastname = $contact_person_lastname;
            $updatedmerchant->contact_person_position = $contact_person_position;
            $updatedmerchant->contact_person_phone = $contact_person_phone;
            $updatedmerchant->contact_person_phone2 = $contact_person_phone2;
            $updatedmerchant->contact_person_email = $contact_person_email;
            $updatedmerchant->sector_of_activity = $sector_of_activity;
            $updatedmerchant->object_type = $object_type;
            $updatedmerchant->parent_id = $parent_id;
            $updatedmerchant->url = $url;
            $updatedmerchant->masterbox_number = $masterbox_number;
            $updatedmerchant->slavebox_number = $slavebox_number;
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

        // Check user email address, it should not exists
        Validator::extend('orbit.email.exists', function ($attribute, $value, $parameters) {
            $merchant = Merchant::excludeDeleted()
                        ->where('email', $value)
                        ->first();

            if (! empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.validation.merchant', $merchant);

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

        // Check the existance of the merchant status
        Validator::extend('orbit.empty.merchant_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });
    }
}
