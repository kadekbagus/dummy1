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
     * GET - Search retailer
     *
     * @author Kadek <kadek@dominopos.com>
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

    public function getSearchRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.retailer.getsearchretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.retailer.getsearchretailer.after.auth', array($this));

            // Try to check access control list, does this merchant allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.retailer.getsearchretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_retailer')) {
                Event::fire('orbit.retailer.getsearchretailer.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.getsearchretailer.after.authz', array($this, $user));

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

            Event::fire('orbit.retailer.getsearchretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.retailer.getsearchretailer.after.validation', array($this, $validator));

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
                $error = Lang::get('statuses.orbit.nodata.retailer');
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

            Event::fire('orbit.retailer.getsearchretailer.after.commit', array($this, $result));

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.retailer.getsearchretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
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

            // Rollback the changes
            $this->rollBack();
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

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.retailer.getsearchretailer.general.exception', array($this, $e));

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
