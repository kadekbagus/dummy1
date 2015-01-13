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
     * GET - List of Product Attributes.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string        `sort_by`               (optional) - column order by
     * @param string        `sort_mode`             (optional) - asc or desc
     * @param string        `attribute_name`        (optional) - attribute name
     * @param string        `attribute_name_like`   (optional) - attribute name like
     * @param array         `with`                  (optional) - relationship included, e.g: 'values', 'merchant'
     * @param array|string  `merchant_id`           (optional) - Id of the merchant, could be array or string with comma separated value
     * @param integer       `take`                  (optional) - limit
     * @param integer       `skip`                  (optional) - limit offset
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchAttribute()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.getattribute.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.getattribute.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.getattribute.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product_attribute')) {
                Event::fire('orbit.product.getattribute.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_user');
                $message = Lang::get('validation.orbit.access.view_product_attribute', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.getattribute.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:id,name,created',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.attribute_sortby'),
                )
            );

            Event::fire('orbit.product.getattribute.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.getattribute.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            // Builder object
            $attributes = ProductAttribute::excludeDeleted();

            // Include other relationship
            OrbitInput::get('with', function($with) use ($attributes) {
                $attributes->with($with);
            });

            // Filter by ids
            OrbitInput::get('id', function($productIds) use ($attributes) {
                $attributes->whereIn('product_attributes.product_attribute_id', $productIds);
            });

            // Filter by merchant ids
            OrbitInput::get('merchant_id', function($merchantIds) use ($attributes) {
                $attributes->whereIn('product_attributes.merchant_id', $merchantIds);
            });

            // Filter by attribute name
            OrbitInput::get('attribute_name', function ($attributeName) use ($attributes) {
                $attributes->whereIn('product_attributes.product_attribute_name', $attributeName);
            });

            // Filter like attribute name
            OrbitInput::get('attribute_name_like', function ($attributeName) use ($attributes) {
                $attributes->whereIn('product_attributes.product_attribute_name', $attributeName);
            });

            // Filter user by their status
            OrbitInput::get('status', function ($status) use ($attributes) {
                $$attributes->whereIn('product_attributes.status', $status);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_attributes = clone $attributes;

            // Get the take args
            $take = $maxRecord;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;
            });
            $attributes->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $attributes) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $attributes->skip($skip);

            // Default sort by
            $sortBy = 'product_attributes.product_attribute_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'id'            => 'product_attributes.product_attribute_id',
                    'name'          => 'product_attributes.product_attribute_name',
                    'created'       => 'product_attributes.created_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $attributes->orderBy($sortBy, $sortMode);

            $totalAttributes = $_attributes->count();
            $listOfAttributes = $attributes->get();

            $data = new stdclass();
            $data->total_records = $totalAttributes;
            $data->returned_records = count($listOfAttributes);
            $data->records = $listOfAttributes;

            if ($listOfAttributes === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.attribute');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.getattribute.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.getattribute.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.product.getattribute.query.error', array($this, $e));

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
            Event::fire('orbit.product.getattribute.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.product.getattribute.before.render', array($this, &$output));

        return $output;
    }

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
                    'merchant_id'           => $merchantId,
                    'attribute_name'        => $attributeName,
                    'attribute_value'       => $attributeValue
                ),
                array(
                    'merchant_id'       => 'required|numeric|orbit.empty.merchant',
                    'attribute_name'    => 'required|orbit.attribute.unique',
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

            // Insert attribute values if specified by the caller
            OrbitInput::post('attribute_value', function($attributeValue) use ($attribute, &$values, $user)
            {
                // Parse JSON
                $attributeValue = $this->JSONValidate($attributeValue);

                foreach ($attributeValue as $value) {
                    $attrValue = new ProductAttributeValue();
                    $attrValue->product_attribute_id = $attribute->product_attribute_id;
                    $attrValue->value = $value->attribute_value;
                    $attrValue->status = 'active';
                    $attrValue->created_by = $user->user_id;
                    $attrValue->save();

                    $values[] = $attrValue;
                }
            });

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

     /**
     * POST - Update product attribute
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_attribute_id`      (required) - ID of the product attribute
     * @param integer   `merchant_id`               (required) - ID of the merchant
     * @param string    `attribute_name             (required) - Name of the attribute
     * @param array     `attribute_value_new`       (optional) - The value of attribute (new)
     * @param array     `attribute_value_update`    (optional) - The value of attribute (update)
     * @param array     `attribute_value_delete`    (optional) - The value of attribute (delete)
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateAttribute()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postupdateattribute.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postupdateattribute.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postupdateattribute.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_product_attribute')) {
                Event::fire('orbit.product.postupdateattribute.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.new_product_attribute');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postupdateattribute.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $attributeId = OrbitInput::post('product_attribute_id');
            $merchantId = OrbitInput::post('merchant_id');
            $attributeName = OrbitInput::post('attribute_name');

            $messageAttributeUnique = Lang::get('validation.orbit.exists.product.attribute.unique', array(
                'attrname' => $attributeName
            ));

            $validator = Validator::make(
                array(
                    'product_attribute_id'  => $attributeId,
                    'merchant_id'           => $merchantId,
                    'attribute_name'        => $attributeName,
                ),
                array(
                    'product_attribute_id'      => 'required|numeric|orbit.empty.attribute',
                    'merchant_id'               => 'numeric|orbit.empty.merchant',
                    'attribute_name'            => 'orbit.attribute.unique.butme',
                    'attribute_value_deleted'   => 'array',
                ),
                array(
                    'orbit.attribute.unique.butme'    => $messageAttributeUnique
                )
            );

            Event::fire('orbit.product.postupdateattribute.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postupdateattribute.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $attribute = App::make('orbit.empty.attribute');

            // Check if product attribute name has been changed
            OrbitInput::post('attribute_name', function($name) use ($attribute)
            {
                $attribute->product_attribute_name = $name;
            });

            Event::fire('orbit.product.postupdateattribute.before.save', array($this, $attribute));

            $attribute->save();

            // Hold the attribute values both old and new which has been saved
            $values = array();
            $newValues = array();
            $updatedValues = array();
            $deletedValues = array();

            // Delete attribute value
            OrbitInput::post('attribute_value_delete', function($attributeValueDelete) use ($attribute, &$deletedValues, $user)
            {
                foreach ($attributeValueDelete as $valueId) {
                    $attrValue = ProductAttributeValue::excludeDeleted()->find($valueId);

                    if (empty($attrValue)) {
                        continue;   // Skip deleting
                    }

                    $attrValue->status = 'deleted';
                    $attrValue->modified_by = $user->user_id;
                    $attrValue->save();

                    $deletedValues[] = $attrValue->product_attribute_value_id;
                }
            });

            // Update attribute value
            OrbitInput::post('attribute_value_update', function($attributeValueOld) use ($attribute, &$updatedValues, $user)
            {
                $existence = array();

                // Parse JSON
                $attributeValueOld = $this->JSONValidate($attributeValueOld);

                // List of new value in array format
                $oldValueArray = array();
                foreach ($attributeValueOld as $newValue) {
                    $oldValueArray[$newValue->value_id] = $newValue->attribute_value;
                }

                // Does the new attribute value already exists
                foreach ($attribute->values as $attrValue) {
                    foreach ($oldValueArray as $valueId=>$newValue) {
                        // Make sure we only save the attribute value id which
                        // already on database
                        $attrId = (string)$attrValue->product_attribute_value_id;
                        if ((string)$valueId !== $attrId) {
                            continue;
                        }

                        // Only insert into the existence when the value is not
                        // same, means there's a changes.
                        $_newValue = strtolower($newValue);
                        $oldValue = strtolower($attrValue->value);

                        if ($oldValue !== $_newValue) {
                            $existence[$attrId] = $newValue;
                        }
                    }
                }

                foreach ($existence as $valueId=>$value) {
                    $attrValue = ProductAttributeValue::excludeDeleted()->find($valueId);

                    if (empty($attrValue)) {
                        continue;   // Skip saving
                    }

                    $attrValue->value = $value;
                    $attrValue->modified_by = $user->user_id;
                    $attrValue->save();

                    $updatedValues[] = $attrValue;
                }
            });

            // Insert new attribute value
            OrbitInput::post('attribute_value_new', function($attributeValueNew) use ($attribute, &$newValues, $user)
            {
                $existence = array();

                // Parse JSON
                $attributeValueNew = $this->JSONValidate($attributeValueNew);

                // List of new value in array format
                $newValueArray = array();
                foreach ($attributeValueNew as $newValue) {
                    $newValueArray[] = $newValue->attribute_value;
                }

                // Does the new attribute value already exists
                foreach ($attribute->values as $attrValue) {
                    foreach ($newValueArray as $newValue) {
                        $_newValue = strtolower($newValue);
                        $oldValue = strtolower($attrValue->value);

                        if ($oldValue === $_newValue) {
                            $existence[] = $newValue;
                        }
                    }
                }

                // Calculate the difference of new value from the existings one
                $attributeDifference = array_diff($newValueArray, $existence);

                foreach ($attributeDifference as $value) {
                    $attrValue = new ProductAttributeValue();
                    $attrValue->product_attribute_id = $attribute->product_attribute_id;
                    $attrValue->value = $value;
                    $attrValue->status = 'active';
                    $attrValue->created_by = $user->user_id;
                    $attrValue->save();

                    $newValues[] = $attrValue;
                }
            });

            $values = array_merge($newValues + $updatedValues);
            if (! empty($values)) {
                $attribute->setRelation('values', $values);
                $attribute->values = $values;
            }

            // Unset the attribute value which has been deleted
            foreach ($deletedValues as $valueId) {
                foreach ($attribute->values as $key=>$origValue) {
                    $origId = (string)$origValue->product_attribute_value_id;
                    if ($origId === (string)$valueId) {
                        $attribute->values->forget($key);
                    }
                }
            }

            Event::fire('orbit.product.postupdateattribute.after.save', array($this, $attribute));
            $this->response->data = $attribute;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postupdateattribute.after.commit', array($this, $attribute));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postupdateattribute.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postupdateattribute.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postupdateattribute.query.error', array($this, $e));

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
            Event::fire('orbit.product.postupdateattribute.general.exception', array($this, $e));

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
     * POST - Delete product attribute
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_attribute_id`      (required) - ID of the product attribute
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteAttribute()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postdeleteattribute.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postdeleteattribute.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postdeleteattribute.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_product_attribute')) {
                Event::fire('orbit.product.postdeleteattribute.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.new_product_attribute');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postdeleteattribute.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $attributeId = OrbitInput::post('product_attribute_id');

            $validator = Validator::make(
                array(
                    'product_attribute_id'  => $attributeId,
                ),
                array(
                    'product_attribute_id'  => 'required|numeric|orbit.empty.attribute',
                )
            );

            Event::fire('orbit.product.postdeleteattribute.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postdeleteattribute.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $attribute = App::make('orbit.empty.attribute');

            Event::fire('orbit.product.postdeleteattribute.before.save', array($this, $attribute));

            // Change the status to deleted
            $attribute->status = 'deleted';
            $attribute->save();

            Event::fire('orbit.product.postdeleteattribute.after.save', array($this, $attribute));
            $this->response->data = $attribute;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postdeleteattribute.after.commit', array($this, $attribute));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postdeleteattribute.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postdeleteattribute.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postdeleteattribute.query.error', array($this, $e));

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
            Event::fire('orbit.product.postdeleteattribute.general.exception', array($this, $e));

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

        // Make sure product attribute exists
        Validator::extend('orbit.empty.attribute', function ($attribute, $value, $parameters) {
            $attribute = ProductAttribute::excludeDeleted()
                                         ->with('values')
                                         ->find($value);

            if (empty($attribute)) {
                return FALSE;
            }

            App::instance('orbit.empty.attribute', $attribute);

            return TRUE;
        });

        // Make sure the name of the attribute is unique on this merchant only
        Validator::extend('orbit.attribute.unique', function ($attribute, $value, $parameters) {
            // Use the value of attribute if post merchant_id is null
            $merchantId = OrbitInput::post('merchant_id', NULL);
            if (empty($merchantId)) {
                $merchantId = $attribute->merchant_id;
            }

            $attribute = ProductAttribute::excludeDeleted()
                                         ->with('values')
                                         ->where('product_attribute_name', $value)
                                         ->where('merchant_id', $merchantId)
                                         ->first();

            if (! empty($attribute)) {
                return FALSE;
            }

            App::instance('orbit.attribute.unique', $attribute);

            return TRUE;
        });

        // Make sure the name of the attribute is unique on this merchant only
        Validator::extend('orbit.attribute.unique.butme', function ($attribute, $value, $parameters) {
            // Get the instance of ProductAttribute object
            $attribute = App::make('orbit.empty.attribute');

            // Use the value of attribute if post merchant_id is null
            $merchantId = OrbitInput::post('merchant_id', NULL);
            if (empty($merchantId)) {
                $merchantId = $attribute->merchant_id;
            }

            $attribute = ProductAttribute::excludeDeleted()
                                         ->with('values')
                                         ->where('product_attribute_name', $value)
                                         ->where('merchant_id', $merchantId)
                                         ->where('product_attribute_id', '!=', $attribute->product_attribute_id)
                                         ->first();

            if (! empty($attribute)) {
                return FALSE;
            }

            App::instance('orbit.attribute.unique.butme', $attribute);

            return TRUE;
        });
    }

    /**
     * Validate a JSON.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $string - JSON string to parse.
     * @return mixed
     */
    protected function JSONValidate($string) {
        $errorMessage = Lang::get('validation.orbit.jsonerror.format');

        if (! is_string($string)) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        $result = @json_decode($string);
        if (json_last_error() !== JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        $errorMessage = Lang::get('validation.orbit.jsonerror.array');
        if (! is_array($result)) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        return $result;
    }
}
