<?php
/**
 * An API controller for managing retailers.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class RetailerAPIController extends ControllerAPI
{
    /**
     * POST - Delete Retailer
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the retailer
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.retailer.postdeleteretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.retailer.postdeleteretailer.after.auth', array($this));

            // Try to check access control list, does this retailer allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.retailer.postdeleteretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_retailer')) {
                Event::fire('orbit.retailer.postdeleteretailer.authz.notallowed', array($this, $user));
                $deleteRetailerLang = Lang::get('validation.orbit.actionlist.delete_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.postdeleteretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $retailer_id = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'retailer_id' => $retailer_id,
                ),
                array(
                    'retailer_id' => 'required|numeric|orbit.empty.retailer',
                )
            );

            Event::fire('orbit.retailer.postdeleteretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.retailer.postdeleteretailer.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deleteretailer = Retailer::find($retailer_id);
            $deleteretailer->status = 'deleted';
            $deleteretailer->modified_by = $this->api->user->user_id;

            Event::fire('orbit.retailer.postdeleteretailer.before.save', array($this, $deleteretailer));

            $deleteretailer->save();

            Event::fire('orbit.retailer.postdeleteretailer.after.save', array($this, $deleteretailer));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.retailer');

            // Commit the changes
            $this->commit();

            Event::fire('orbit.retailer.postdeleteretailer.after.commit', array($this, $deleteretailer));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.retailer.postdeleteretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.retailer.postdeleteretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.retailer.postdeleteretailer.query.error', array($this, $e));

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
            Event::fire('orbit.retailer.postdeleteretailer.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.retailer.postdeleteretailer.before.render', array($this, $output));

        return $output;
    }

     /**
     * POST - Add new retailer
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`               (required) - User id for the retailer
     * @param string     `email`                 (required) - Email address of the retailer
     * @param string     `name`                  (optional) - Name of the retailer
     * @param string     `description`           (optional) - Retailer description
     * @param string     `address_line1`         (optional) - Address 1
     * @param string     `address_line2`         (optional) - Address 2
     * @param string     `address_line3`         (optional) - Address 3
     * @param integer    `city_id`               (optional) - City id
     * @param string     `city`                  (optional) - Name of the city
     * @param integer    `country_id`            (optional) - Country id
     * @param string     `country`               (optional) - Name of the country
     * @param string     `phone`                 (optional) - Phone of the retailer
     * @param string     `fax`                   (optional) - Fax of the retailer
     * @param string     `start_date_activity`   (optional) - Start date activity of the retailer
     * @param string     `status`                (optional) - Status of the retailer
     * @param string     `logo`                  (optional) - Logo of the retailer
     * @param string     `currency`              (optional) - Currency used by the retailer
     * @param string     `currency_symbol`       (optional) - Currency symbol
     * @param string     `tax_code1`             (optional) - Tax code 1
     * @param string     `tax_code2`             (optional) - Tax code 2
     * @param string     `tax_code3`             (optional) - Tax code 3
     * @param string     `slogan`                (optional) - Slogan for the retailer
     * @param string     `vat_included`          (optional) - Vat included
     * @param string     `object_type`           (optional) - Object type
     * @param string     `parent_id`             (optional) - The retailer id
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.retailer.postnewretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.retailer.postnewretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.retailer.postnewretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('add_retailer')) {
                Event::fire('orbit.retailer.postnewretailer.authz.notallowed', array($this, $user));
                $createRetailerLang = Lang::get('validation.orbit.actionlist.new_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.postnewretailer.after.authz', array($this, $user));

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

            Event::fire('orbit.retailer.postnewretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.retailer.postnewretailer.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $newretailer = new Retailer();
            $newretailer->user_id = $user_id;
            $newretailer->email = $email;
            $newretailer->name = $name;
            $newretailer->description = $description;
            $newretailer->address_line1 = $address_line1;
            $newretailer->address_line2 = $address_line2;
            $newretailer->address_line3 = $address_line3;
            $newretailer->city_id = $city_id;
            $newretailer->city = $city;
            $newretailer->country_id = $country_id;
            $newretailer->country = $country;
            $newretailer->phone = $phone;
            $newretailer->fax = $fax;
            $newretailer->start_date_activity = $start_date_activity;
            $newretailer->status = $status;
            $newretailer->logo = $logo;
            $newretailer->currency = $currency;
            $newretailer->currency_symbol = $currency_symbol;
            $newretailer->tax_code1 = $tax_code1;
            $newretailer->tax_code2 = $tax_code2;
            $newretailer->tax_code3 = $tax_code3;
            $newretailer->slogan = $slogan;
            $newretailer->vat_included = $vat_included;
            $newretailer->object_type = $object_type;
            $newretailer->parent_id = $parent_id;
            $newretailer->modified_by = $this->api->user->user_id;

            Event::fire('orbit.retailer.postnewretailer.before.save', array($this, $newretailer));

            $newretailer->save();

            Event::fire('orbit.retailer.postnewretailer.after.save', array($this, $newretailer));
            $this->response->data = $newretailer->toArray();

            // Commit the changes
            $this->commit();

            Event::fire('orbit.retailer.postnewretailer.after.commit', array($this, $newretailer));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.retailer.postnewretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.retailer.postnewretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.retailer.postnewretailer.query.error', array($this, $e));

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
            Event::fire('orbit.retailer.postnewretailer.general.exception', array($this, $e));

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
     * POST - Update retailer
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
     * @param string     `parent_id`             (optional) - The merchant id
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateRetailer()
    {
        try {
            $httpCode=200;

            Event::fire('orbit.retailer.postupdateretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.retailer.postupdateretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.retailer.postupdateretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_retailer')) {
                Event::fire('orbit.retailer.postupdateretailer.authz.notallowed', array($this, $user));
                $updateRetailerLang = Lang::get('validation.orbit.actionlist.update_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.postupdateretailer.after.authz', array($this, $user));

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

            Event::fire('orbit.retailer.postupdateretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.retailer.postupdateretailer.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedretailer = Merchant::find($merchant_id);
            $updatedretailer->user_id = $user_id;
            $updatedretailer->email = $email;
            $updatedretailer->name = $name;
            $updatedretailer->description = $description;
            $updatedretailer->address_line1 = $address_line1;
            $updatedretailer->address_line2 = $address_line2;
            $updatedretailer->address_line3 = $address_line3;
            $updatedretailer->city_id = $city_id;
            $updatedretailer->city = $city;
            $updatedretailer->country_id = $country_id;
            $updatedretailer->country = $country;
            $updatedretailer->phone = $phone;
            $updatedretailer->fax = $fax;
            $updatedretailer->start_date_activity = $start_date_activity;
            $updatedretailer->status = $status;
            $updatedretailer->logo = $logo;
            $updatedretailer->currency = $currency;
            $updatedretailer->currency_symbol = $currency_symbol;
            $updatedretailer->tax_code1 = $tax_code1;
            $updatedretailer->tax_code2 = $tax_code2;
            $updatedretailer->tax_code3 = $tax_code3;
            $updatedretailer->slogan = $slogan;
            $updatedretailer->vat_included = $vat_included;
            $updatedretailer->parent_id = $parent_id;
            $updatedretailer->modified_by = $this->api->user->user_id;

            Event::fire('orbit.retailer.postupdateretailer.before.save', array($this, $updatedretailer));

            $updatedretailer->save();

            Event::fire('orbit.retailer.postupdateretailer.after.save', array($this, $updatedretailer));
            $this->response->data = $updatedretailer->toArray();

            // Commit the changes
            $this->commit();

            Event::fire('orbit.retailer.postupdateretailer.after.commit', array($this, $updatedretailer));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.retailer.postupdateretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.retailer.postupdateretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.retailer.postupdateretailer.query.error', array($this, $e));

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
            Event::fire('orbit.retailer.postupdateretailer.general.exception', array($this, $e));

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
     * GET - Search Retailer
     *
     * @author Kadek Bagus <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string `sort_by` (optional) - column order by
     * @param string `sort_mode` (optional) - asc or desc
     * @param integer `take` (optional) - limit
     * @param integer `skip` (optional) - limit offset
     * @param integer `merchant_id` (optional)
     * @param integer `user_id` (optional)
     * @param string `email` (optional)
     * @param string `name` (optional)
     * @param string `description` (optional)
     * @param string `address1` (optional)
     * @param string `address2` (optional)
     * @param string `address3` (optional)
     * @param string `city_id` (optional)
     * @param string `city` (optional)
     * @param string `country_id` (optional)
     * @param string `country` (optional)
     * @param string `phone` (optional)
     * @param string `fax` (optional)
     * @param string `status` (optional)
     * @param string `currency` (optional)
     * @param string `name_like` (optional)
     * @param string `email_like` (optional)
     * @param string `description_like` (optional)
     * @param string `address1_like` (optional)
     * @param string `address2_like` (optional)
     * @param string `address3_like` (optional)
     * @param string `city_like` (optional)
     * @param string `country_like` (optional)
     * @return Illuminate\Support\Facades\Response
     */

    public function getSearchRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.retailer.getsearchretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.retailer.getsearchretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.retailer.getsearchretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_user')) {
                Event::fire('orbit.retailer.getsearchretailer.authz.notallowed', array($this, $user));
                $viewRetailerLang = Lang::get('validation.orbit.actionlist.view_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.getsearchretailer.after.authz', array($this, $user));

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
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );

            Event::fire('orbit.retailer.getsearchretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.retailer.getsearchretailer.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            // Builder object
            $retailers = User::excludeDeleted();

            // Filter retailer by Ids
            OrbitInput::get('merchant_id', function($merchantIds) use ($retailers)
            {
                $retailers->whereIn('retailers.merchant_id', $merchantIds);
            });

            // Filter retailer by Ids
            OrbitInput::get('user_id', function($userIds) use ($retailers)
            {
                $retailers->whereIn('retailers.user_id', $userIds);
            });

            // Filter retailer by name
            OrbitInput::get('name', function($name) use ($retailers)
            {
                $retailers->whereIn('retailers.name', $name);
            });

            // Filter retailer by matching name pattern
            OrbitInput::get('name_like', function($name) use ($retailers)
            {
                $retailers->where('retailers.name', 'like', "%$name%");
            });

            // Filter retailer by description
            OrbitInput::get('description', function($description) use ($retailers)
            {
                $retailers->whereIn('retailers.description', $description);
            });

            // Filter retailer by description pattern
            OrbitInput::get('description_like', function($description) use ($retailers)
            {
                $description->where('retailers.description', 'like', "%$description%");
            });

            // Filter retailer by their email
            OrbitInput::get('email', function($email) use ($retailers)
            {
                $retailers->whereIn('retailers.email', $email);
            });

            // Filter retailer by address1
            OrbitInput::get('address1', function($address1) use ($retailers)
            {
                $retailers->where('retailers.address_line1', "%$address1%");
            });

            // Filter retailer by address1 pattern
            OrbitInput::get('address1', function($address1) use ($retailers)
            {
                $retailers->where('retailers.address_line1', 'like', "%$address1%");
            });

            // Filter retailer by address2
            OrbitInput::get('address2', function($address2) use ($retailers)
            {
                $retailers->where('retailers.address_line2', "%$address2%");
            });

            // Filter retailer by address2 pattern
            OrbitInput::get('address2', function($address2) use ($retailers)
            {
                $retailers->where('retailers.address_line2', 'like', "%$address2%");
            });
            
             // Filter retailer by address3
            OrbitInput::get('address3', function($address3) use ($retailers)
            {
                $retailers->where('retailers.address_line3', "%$address3%");
            });

             // Filter retailer by address3 pattern
            OrbitInput::get('address3', function($address3) use ($retailers)
            {
                $retailers->where('retailers.address_line3', 'like', "%$address3%");
            });

             // Filter retailer by cityID
            OrbitInput::get('city_id', function($cityIds) use ($retailers)
            {
                $retailers->whereIn('retailers.city_id', $cityIds);
            });

             // Filter retailer by city
            OrbitInput::get('city', function($city) use ($retailers)
            {
                $retailers->whereIn('retailers.city', $city);
            });

             // Filter retailer by city pattern
            OrbitInput::get('city_like', function($city) use ($retailers)
            {
                $retailers->where('retailers.city', 'like', "%$city%");
            });

             // Filter retailer by countryID
            OrbitInput::get('country_id', function($countryId) use ($retailers)
            {
                $retailers->whereIn('retailers.country_id', $countryId);
            });

             // Filter retailer by country
            OrbitInput::get('country', function($country) use ($retailers)
            {
                $retailers->whereIn('retailers.country', $country);
            });

             // Filter retailer by country pattern
            OrbitInput::get('country_like', function($country) use ($retailers)
            {
                $retailers->where('retailers.country', 'like', "%$country%");
            });

             // Filter retailer by phone
            OrbitInput::get('phone', function($phone) use ($retailers)
            {
                $retailers->whereIn('retailers.phone', $phone);
            });

             // Filter retailer by fax
            OrbitInput::get('fax', function($fax) use ($retailers)
            {
                $retailers->whereIn('retailers.fax', $fax);
            });

             // Filter retailer by phone
            OrbitInput::get('phone', function($phone) use ($retailers)
            {
                $retailers->whereIn('retailers.phone', $phone);
            });

             // Filter retailer by status
            OrbitInput::get('status', function($status) use ($retailers)
            {
                $retailers->whereIn('retailers.status', $status);
            });

            // Filter retailer by currency
            OrbitInput::get('currency', function($currency) use ($retailers)
            {
                $retailers->whereIn('retailers.currency', $currency);
            });
            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_users = clone $users;

            // Get the take args
            $take = $maxRecord;
            OrbitInput::get('take', function($_take) use (&$take, $maxRecord)
            {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;
            });
            $users->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $users)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $retailers->skip($skip);

            // Default sort by
            $sortBy = 'retailers.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                   // Map the sortby request to the real column name
                  $sortByMapping = array(
                  'registered_date' => 'retailers.created_at',
                  'retailer_name' => 'retailers.name',
                  'retailer_email' => 'retailers.email',
                  'retailer_userid' => 'retailers.user_id',
                  'retailer_description' => 'retailers.description',
                  'retailerid' => 'retailers.merchant_id',
                  'retailer_address1' => 'retailers.address_line1',
                  'retailer_address2' => 'retailers.address_line2',
                  'retailer_address3' => 'retailers.address_line3',
                  'retailer_cityid' => 'retailers.city_id',
                  'retailer_city' => 'retailers.city',
                  'retailer_countryid' => 'retailers.country_id',
                  'retailer_country' => 'retailers.country',
                  'retailer_phone' => 'retailers.phone',
                  'retailer_fax' => 'retailers.fax',
                  'retailer_status' => 'retailers.status',
                  'retailer_currency' => 'retailers.currency',
                  );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $retailers->orderBy($sortBy, $sortMode);

            $totalRetailers = $_retailers->count();
            $listOfRetailers = $retailers->get();

            $data = new stdclass();
            $data->total_records = $totalRetailers;
            $data->returned_records = count($listOfRetailers);
            $data->records = $listOfRetailers;

            if ($totalRetailers === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.user');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.retailer.getsearchretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.retailer.getsearchretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.retailer.getsearchretailer.query.error', array($this, $e));

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
            Event::fire('orbit.retailer.getsearchretailer.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.retailer.getsearchretailer.before.render', array($this, &$output));

        return $output;
    }


    protected function registerCustomValidation()
    {
        // Check the existance of retailer id
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) {
            $retailer = Retailer::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.empty.retailer', $retailer);

            return TRUE;
        });
    }
}
