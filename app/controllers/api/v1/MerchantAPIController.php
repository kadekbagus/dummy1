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
    public function postAddMerchant()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.user.postaddmerchant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postaddmerchant.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postaddmerchant.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('add_merchant')) {
                Event::fire('orbit.user.postaddmerchant.authz.notallowed', array($this, $user));
                $createMerchantLang = Lang::get('validation.orbit.actionlist.add_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createMerchantLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.user.postaddmerchant.after.authz', array($this, $user));

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

            Event::fire('orbit.user.postaddmerchant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.postaddmerchant.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $addmerchant = new Merchant();
            $addmerchant->user_id = $user_id;
            $addmerchant->email = $email;
            $addmerchant->name = $name;
            $addmerchant->description = $description;
            $addmerchant->address_line1 = $address_line1;
            $addmerchant->address_line2 = $address_line2;
            $addmerchant->address_line3 = $address_line3;
            $addmerchant->city_id = $city_id;
            $addmerchant->city = $city;
            $addmerchant->country_id = $country_id;
            $addmerchant->country = $country;
            $addmerchant->phone = $phone;
            $addmerchant->fax = $fax;
            $addmerchant->start_date_activity = $start_date_activity;
            $addmerchant->status = $status;
            $addmerchant->logo = $logo;
            $addmerchant->currency = $currency;
            $addmerchant->currency_symbol = $currency_symbol;
            $addmerchant->tax_code1 = $tax_code1;
            $addmerchant->tax_code2 = $tax_code2;
            $addmerchant->tax_code3 = $tax_code3;
            $addmerchant->slogan = $slogan;
            $addmerchant->vat_included = $vat_included;
            $addmerchant->object_type = $object_type;
            $addmerchant->parent_id = $parent_id;

            Event::fire('orbit.user.postaddmerchant.before.save', array($this, $addmerchant));

            $addmerchant->save();

            Event::fire('orbit.user.postaddmerchant.after.save', array($this, $addmerchant));
            $this->response->data = $addmerchant->toArray();

            // Commit the changes
            $this->commit();

            Event::fire('orbit.user.postaddmerchant.after.commit', array($this, $addmerchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postaddmerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postaddmerchant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postaddmerchant.query.error', array($this, $e));

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
            Event::fire('orbit.user.postaddmerchant.general.exception', array($this, $e));

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
