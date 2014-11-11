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

            Event::fire('orbit.user.postnewretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postnewretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postnewretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('add_retailer')) {
                Event::fire('orbit.user.postnewretailer.authz.notallowed', array($this, $user));
                $createRetailerLang = Lang::get('validation.orbit.actionlist.new_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.user.postnewretailer.after.authz', array($this, $user));

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

            Event::fire('orbit.user.postnewretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.postnewretailer.after.validation', array($this, $validator));

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

            Event::fire('orbit.user.postnewretailer.before.save', array($this, $newretailer));

            $newretailer->save();

            Event::fire('orbit.user.postnewretailer.after.save', array($this, $newretailer));
            $this->response->data = $newretailer->toArray();

            // Commit the changes
            $this->commit();

            Event::fire('orbit.user.postnewretailer.after.commit', array($this, $newretailer));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postnewretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postnewretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postnewretailer.query.error', array($this, $e));

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
            Event::fire('orbit.user.postnewretailer.general.exception', array($this, $e));

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

            Event::fire('orbit.user.postupdateretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postupdateretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postupdateretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_retailer')) {
                Event::fire('orbit.user.postupdateretailer.authz.notallowed', array($this, $user));
                $updateRetailerLang = Lang::get('validation.orbit.actionlist.update_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.user.postupdateretailer.after.authz', array($this, $user));

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

            Event::fire('orbit.user.postupdateretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.postupdateretailer.after.validation', array($this, $validator));

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

            Event::fire('orbit.user.postupdateretailer.before.save', array($this, $updatedretailer));

            $updatedretailer->save();

            Event::fire('orbit.user.postupdateretailer.after.save', array($this, $updatedretailer));
            $this->response->data = $updatedretailer->toArray();

            // Commit the changes
            $this->commit();

            Event::fire('orbit.user.postupdateretailer.after.commit', array($this, $updatedretailer));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postupdateretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postupdateretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postupdateretailer.query.error', array($this, $e));

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
            Event::fire('orbit.user.postupdateretailer.general.exception', array($this, $e));

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
