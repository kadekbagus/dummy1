<?php
/**
 * API for managing product attributes and its values.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class ProductAttributeAPIController extends ControllerAPI
{
     /**
     * POST - Add new product attribute
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `merchant_id`           (required) - ID of the merchant
     * @param string    `attribute_name         (required) - Name of the attribute
     * @param array     'attribute_value`       (optional) - The value of attribute
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewAttribute()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postnewattribute.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postnewattribute.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postnewattribute.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_product_attribute')) {
                Event::fire('orbit.product.postnewattribute.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.new_product_attribute');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postnewattribute.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchantId = OrbitInput::post('merchant_id');
            $attributeName = OrbitInput::post('attribute_name');
            $attributeValue = OrbitInput::post('attribute_value');

            $messageAttributeUnique = Lang::get('validation.orbit.exists.product.attribute.unique', array(
                'attrname' => $attributeName
            ));

            $validator = Validator::make(
                array(
                    'merchant_id'       => $merchantId,
                    'attribute_name'    => $attributeName,
                    'attribute_value'   => $attributeValue
                ),
                array(
                    'merchant_id'       => 'required|numeric|orbit.empty.merchant',
                    'attribute_name'    => 'required|orbit.attribute.unique',
                    'attribute_value'   => 'array'
                ),
                array(
                    'orbit.attribute.unique'    => $messageAttributeUnique
                )
            );

            Event::fire('orbit.product.postnewattribute.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postnewattribute.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $attribute = new ProductAttribute();
            $attribute->merchant_id = $merchantId;
            $attribute->product_attribute_name = $attributeName;
            $attribute->created_by = $user->user_id;

            Event::fire('orbit.product.postnewattribute.before.save', array($this, $attribute));

            $attribute->save();

            $values = array();
            foreach ($attributeValue as $value) {
                $attrValue = new ProductAttributeValue();
                $attrValue->product_attribute_id = $attribute->product_attribute_id;
                $attrValue->value = $value;
                $attrValue->status = 'active';
                $attrValue->created_by = $user->user_id;
                $attrValue->save();

                $values[] = $attrValue;
            }
            $attribute->setRelation('values', $values);
            $attribute->values = $values;

            Event::fire('orbit.product.postnewattribute.after.save', array($this, $attribute));
            $this->response->data = $attribute;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postnewattribute.after.commit', array($this, $attribute));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postnewattribute.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postnewattribute.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postnewattribute.query.error', array($this, $e));

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
            Event::fire('orbit.product.postnewattribute.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
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
        $user = $this->api->user;
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) use ($user){
            $merchant = Merchant::excludeDeleted()
                        ->allowedForUser($user)
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Make sure the name of the attribute is unique on this merchant only
        Validator::extend('orbit.attribute.unique', function ($attribute, $value, $parameters) {
            $merchantId = OrbitInput::post('merchant_id');
            $attribute = ProductAttribute::excludeDeleted()
                                         ->where('product_attribute_name', $value)
                                         ->where('merchant_id', $merchantId)
                                         ->first();

            if (! empty($attribute)) {
                return FALSE;
            }

            App::instance('orbit.attribute.unique', $attribute);

            return TRUE;
        });
    }
}
