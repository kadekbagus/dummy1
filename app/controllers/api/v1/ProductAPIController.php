<?php

/**
 * An API controller for managing products.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class ProductAPIController extends ControllerAPI
{

    /**
     * POST - Update Product
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Kadek <kadek@dominopos.com>
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_id`                    (required) - ID of the product
     * @param integer   `merchant_id`                   (optional) - ID of the merchant
     * @param string    `product_code`                  (optional) - Product code
     * @param string    `upc_code`                      (optional) - Product UPC code
     * @param string    `product_name`                  (optional) - Product name
     * @param string    `image`                         (optional) - Product image
     * @param string    `short_description`             (optional) - Product short description
     * @param string    `long_description`              (optional) - Product long description
     * @param string    `is_featured`                   (optional) - is featured
     * @param string    `new_from`                      (optional) - new from
     * @param string    `new_until`                     (optional) - new until
     * @param string    `in_store_localization`         (optional) - in store localization
     * @param string    `post_sales_url`                (optional) - post sales url
     * @param decimal   `price`                         (optional) - Price of the product
     * @param string    `merchant_tax_id1`              (optional) - Tax 1
     * @param string    `merchant_tax_id2`              (optional) - Tax 2
     * @param string    `status`                        (optional) - Status
     * @param integer   `created_by`                    (optional) - ID of the creator
     * @param integer   `modified_by`                   (optional) - Modify by
     * @param array     `retailer_ids`                  (optional) - ORID links
     * @param array     `no_retailer`                   (optional) - Flag to delete all ORID links
     * @param images    `images`                        (optional) - Product image
     * @param integer   `category_id1`                  (optional) - Category ID1.
     * @param integer   `category_id2`                  (optional) - Category ID2.
     * @param integer   `category_id3`                  (optional) - Category ID3.
     * @param integer   `category_id4`                  (optional) - Category ID4.
     * @param integer   `category_id5`                  (optional) - Category ID5.
     * @param string    `product_variants`              (optional) - JSON String for new product combination
     * @param string    `product_variants_update`       (optional) - JSON String for updated product combination
     * @param array     `product_variants_delete`       (optional) - Array of variant id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateProduct()
    {
        try {
            $httpCode=200;

            Event::fire('orbit.product.postupdateproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postupdateproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postupdateproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_product')) {
                Event::fire('orbit.product.postupdateproduct.authz.notallowed', array($this, $user));
                $updateProductLang = Lang::get('validation.orbit.actionlist.update_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateProductLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postupdateproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $product_id = OrbitInput::post('product_id');
            $merchant_id = OrbitInput::post('merchant_id');
            $category_id1 = OrbitInput::post('category_id1');
            $category_id2 = OrbitInput::post('category_id2');
            $category_id3 = OrbitInput::post('category_id3');
            $category_id4 = OrbitInput::post('category_id4');
            $category_id5 = OrbitInput::post('category_id5');

            // Product Variants Delete
            $product_combinations_delete = OrbitInput::post('product_variants_delete');

            $validator = Validator::make(
                array(
                    'product_id'        => $product_id,
                    'merchant_id'       => $merchant_id,
                    'category_id1'      => $category_id1,
                    'category_id2'      => $category_id2,
                    'category_id3'      => $category_id3,
                    'category_id4'      => $category_id4,
                    'category_id5'      => $category_id5,
                    'product_variants_delete'    => $product_combinations_delete
                ),
                array(
                    'product_id'        => 'required|numeric|orbit.empty.product',
                    'merchant_id'       => 'numeric|orbit.empty.merchant',
                    'category_id1'      => 'numeric|orbit.empty.category_id1',
                    'category_id2'      => 'numeric|orbit.empty.category_id2',
                    'category_id3'      => 'numeric|orbit.empty.category_id3',
                    'category_id4'      => 'numeric|orbit.empty.category_id4',
                    'category_id5'      => 'numeric|orbit.empty.category_id5',
                    'product_variants_delete'   => 'array|orbit.empty.product_variant_array'
                ),
                array(
                    'orbit.empty.product_variant_array' => Lang::get('validation.orbit.empty.product_attr.attribute.variant')
                )
            );

            Event::fire('orbit.product.postupdateproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postupdateproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedproduct = App::make('orbit.empty.product');
            App::instance('memory:current.updated.product', $updatedproduct);

            OrbitInput::post('product_code', function($product_code) use ($updatedproduct) {
                if (!empty($product_code)) {
                    $validator = Validator::make(
                        array(
                            'product_code'      => $product_code,
                        ),
                        array(
                            'product_code'      => 'product_code_exists_but_me',
                        ),
                        array('product_code_exists_but_me' => Lang::get('validation.orbit.productcode.exists'))
                    );

                    Event::fire('orbit.product.postupdateproduct.before.productcodevalidation', array($this, $validator));

                    // Run the productcodevalidation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                    Event::fire('orbit.product.postupdateproduct.after.productcodevalidation', array($this, $validator));

                    $updatedproduct->product_code = $product_code;
                } else {
                    $updatedproduct->product_code = NULL;
                }
            });

            OrbitInput::post('upc_code', function($upc_code) use ($updatedproduct) {
                if (!empty($upc_code)) {
                    $validator = Validator::make(
                        array(
                            'upc_code'      => $upc_code,
                        ),
                        array(
                            'upc_code'      => 'upc_code_exists_but_me',
                        ),
                        array('upc_code_exists_but_me' => Lang::get('validation.orbit.upccode.exists'))
                    );

                    Event::fire('orbit.product.postupdateproduct.before.upccodevalidation', array($this, $validator));

                    // Run the productcodevalidation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                    Event::fire('orbit.product.postupdateproduct.after.upccodevalidation', array($this, $validator));

                    $updatedproduct->upc_code = $upc_code;
                } else {
                    $updatedproduct->upc_code = NULL;
                }
            });

            OrbitInput::post('product_name', function($product_name) use ($updatedproduct) {
                $updatedproduct->product_name = $product_name;
            });

            OrbitInput::post('image', function($image) use ($updatedproduct) {
                $updatedproduct->image = $image;
            });

            OrbitInput::post('short_description', function($short_description) use ($updatedproduct) {
                $updatedproduct->short_description = $short_description;
            });

            OrbitInput::post('long_description', function($long_description) use ($updatedproduct) {
                $updatedproduct->long_description = $long_description;
            });

            OrbitInput::post('is_featured', function($is_featured) use ($updatedproduct) {
                $updatedproduct->is_featured = $is_featured;
            });

            OrbitInput::post('new_from', function($new_from) use ($updatedproduct) {
                $updatedproduct->new_from = $new_from;
            });

            OrbitInput::post('new_until', function($new_until) use ($updatedproduct) {
                $updatedproduct->new_until = $new_until;
            });

            OrbitInput::post('in_store_localization', function($in_store_localization) use ($updatedproduct) {
                $updatedproduct->in_store_localization = $in_store_localization;
            });

            OrbitInput::post('post_sales_url', function($post_sales_url) use ($updatedproduct) {
                $updatedproduct->post_sales_url = $post_sales_url;
            });

            OrbitInput::post('price', function($price) use ($updatedproduct) {
                $updatedproduct->price = $price;
            });

            OrbitInput::post('merchant_tax_id1', function($merchant_tax_id1) use ($updatedproduct) {
                $updatedproduct->merchant_tax_id1 = $merchant_tax_id1;
            });

            OrbitInput::post('merchant_tax_id2', function($merchant_tax_id2) use ($updatedproduct) {
                $updatedproduct->merchant_tax_id2 = $merchant_tax_id2;
            });

            OrbitInput::post('status', function($status) use ($updatedproduct) {
                $updatedproduct->status = $status;
            });

            OrbitInput::post('created_by', function($created_by) use ($updatedproduct) {
                $updatedproduct->created_by = $created_by;
            });

            OrbitInput::post('category_id1', function($category_id1) use ($updatedproduct) {
                if (trim($category_id1) === '') {
                    $category_id1 = NULL;
                }
                $updatedproduct->category_id1 = (int) $category_id1;
                $updatedproduct->load('category1');
            });

            OrbitInput::post('category_id2', function($category_id2) use ($updatedproduct) {
                if (trim($category_id2) === '') {
                    $category_id2 = NULL;
                }
                $updatedproduct->category_id2 = (int) $category_id2;
                $updatedproduct->load('category2');
            });

            OrbitInput::post('category_id3', function($category_id3) use ($updatedproduct) {
                if (trim($category_id3) === '') {
                    $category_id3 = NULL;
                }
                $updatedproduct->category_id3 = (int) $category_id3;
                $updatedproduct->load('category3');
            });

            OrbitInput::post('category_id4', function($category_id4) use ($updatedproduct) {
                if (trim($category_id4) === '') {
                    $category_id4 = NULL;
                }
                $updatedproduct->category_id4 = (int) $category_id4;
                $updatedproduct->load('category4');
            });

            OrbitInput::post('category_id5', function($category_id5) use ($updatedproduct) {
                if (trim($category_id5) === '') {
                    $category_id5 = NULL;
                }
                $updatedproduct->category_id5 = (int) $category_id5;
                $updatedproduct->load('category5');
            });

            OrbitInput::post('no_retailer', function($no_retailer) use ($updatedproduct) {
                if ($no_retailer == 'y') {
                    $deleted_retailer_ids = ProductRetailer::where('product_id', $updatedproduct->product_id)->get(array('retailer_id'))->toArray();
                    $updatedproduct->retailers()->detach($deleted_retailer_ids);
                    $updatedproduct->load('retailers');
                }
            });

            OrbitInput::post('retailer_ids', function($retailer_ids) use ($updatedproduct) {
                // validate retailer_ids
                $retailer_ids = (array) $retailer_ids;
                foreach ($retailer_ids as $retailer_id_check) {
                    $validator = Validator::make(
                        array(
                            'retailer_id'   => $retailer_id_check,

                        ),
                        array(
                            'retailer_id'   => 'numeric|orbit.empty.retailer',
                        )
                    );

                    Event::fire('orbit.product.postupdateproduct.before.retailervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.product.postupdateproduct.after.retailervalidation', array($this, $validator));
                }
                // sync new set of retailer ids
                $updatedproduct->retailers()->sync($retailer_ids);

                // reload retailers relation
                $updatedproduct->load('retailers');
            });

            $lastAttributeIndexNumber = $updatedproduct->getLastAttributeIndexNumber();

            // Save new product variants (combination)
            $variants = array();
            OrbitInput::post('product_variants', function($product_combinations)
            use ($user, $updatedproduct, &$variants, $lastAttributeIndexNumber)
            {
                $variant_decode = $this->JSONValidate($product_combinations);
                $attribute_values = $this->checkVariant($variant_decode);
                $merchant_id = $updatedproduct->merchant_id;

                foreach ($variant_decode as $variant) {
                    // Return the default price if the variant price is empty
                    $vprice = function() use ($variant, $updatedproduct) {
                        if (empty($variant->price)) {
                            return $updatedproduct->price;
                        }

                        return $variant->price;
                    };

                    // Return the default sku if the variant sku is empty
                    $vsku = function() use ($variant, $updatedproduct) {
                        if (empty($variant->sku)) {
                            return $updatedproduct->product_code;
                        }

                        return $variant->sku;
                    };

                    // Return the default upc if the variant upc is empty
                    $vupc = function() use ($variant, $updatedproduct) {
                        if (empty($variant->upc)) {
                            return $updatedproduct->upc;
                        }

                        return $variant->upc;
                    };

                    $product_variant = new ProductVariant();
                    $product_variant->product_id = $updatedproduct->product_id;
                    $product_variant->price = $vprice();
                    $product_variant->sku = $vsku();
                    $product_variant->upc = $vupc();
                    $product_variant->merchant_id = $merchant_id;
                    $product_variant->created_by = $user->user_id;
                    $product_variant->status = 'active';

                    // Check the validity of each attribute value sent
                    foreach ($variant->attribute_values as $i=>$value_id) {
                        $attributeIndex = $i + 1;

                        $field_value_id = 'product_attribute_value_id' . $attributeIndex;
                        $product_variant->{$field_value_id} = $value_id;

                        // We check the value of the old attribute_id on the products table
                        // then compare it with attribute_id of this new variant.
                        // If it is not same then we should not process it
                        $old_product_id = (string)$updatedproduct->{'attribute_id' . $attributeIndex};
                        if (empty($old_product_id)) {
                            continue;
                        }

                        if (empty($value_id)) {
                            continue;
                        }

                        // This ProductAttributeValue object is to get the product attribute_id
                        // of the new variant
                        $_attribute_value = ProductAttributeValue::where('product_attribute_value_id', $value_id)->first();
                        if (empty($_attribute_value)) {
                            $errorMessage = Lang::get('validation.orbit.empty.product_attr.attribute.value', array('id' => $value_id));
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        // Compare it
                        if ((string)$_attribute_value->product_attribute_id !== $old_product_id) {
                            $errorMessage = sprintf('Invalid attribute order. %s vs %s => %s',
                                                    $_attribute_value->product_attribute_id,
                                                    $old_product_id,
                                                    $attributeIndex
                            );
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                    }
                    $product_variant->save();

                    $variants[] = $product_variant;
                }

                $this->keepProductColumnUpToDate($updatedproduct);
            });

            // Save existing product variants (combination)
            OrbitInput::post('product_variants_update', function($product_combinations_update)
            use ($user, $updatedproduct, &$variants, $lastAttributeIndexNumber)
            {
                $variant_decode = $this->JSONValidate($product_combinations_update);
                $attribute_values = $this->checkVariant($variant_decode, 'update');
                $merchant_id = $updatedproduct->merchant_id;

                // Get current variant id
                $current_variant = function($_variant_id) use ($updatedproduct) {
                    foreach ($updatedproduct->variants as $tmp_variant) {
                        if ((string)$tmp_variant->product_variant_id !== (string)$_variant_id) {
                            continue;
                        }

                        return $tmp_variant;
                    }
                };

                foreach ($variant_decode as $variant) {
                    // Flag for particular product variant which should be edited
                    $has_transaction = FALSE;

                    $product_variant = $current_variant($variant->variant_id);
                    $product_variant->modified_by = $user->user_id;

                    $transaction_detail = TransactionDetail::TransactionJoin()
                                                          ->ExcludeDeletedTransaction()
                                                          ->where('transaction_details.product_variant_id', $product_variant->product_variant_id)
                                                          ->first();

                    if (is_object($transaction_detail)) {
                        $has_transaction = TRUE;
                    }

                    // Return the default price if the variant price is empty
                    $price = function() use ($variant, $has_transaction, $product_variant) {
                        if (empty($variant->price)) {
                            return $product_variant->price;
                        }

                        return $variant->price;
                    };

                    // Return the default sku if the variant sku is empty
                    $sku = function() use ($variant, $has_transaction, $product_variant) {
                        if ($has_transaction) {
                            // Reject the saving
                            $errorMessage = Lang::get('validation.orbit.exists.product.variant.transaction',
                                ['id' => $variant->variant_id]
                            );
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        if (empty($variant->sku)) {
                            return $product_variant->product_code;
                        }

                        return $variant->sku;
                    };

                    // Return the default upc if the variant upc is empty
                    $upc = function() use ($variant, $has_transaction, $product_variant) {
                        if ($has_transaction) {
                            // Reject the saving
                            $errorMessage = Lang::get('validation.orbit.exists.product.variant.transaction',
                                ['id' => $variant->variant_id]
                            );
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        if (empty($variant->upc)) {
                            return $product_variant->upc;
                        }

                        return $variant->upc;
                    };

                    $product_variant->price = $price();
                    $product_variant->sku = $sku();
                    $product_variant->upc = $upc();

                    // Check the validity of each attribute value sent
                    foreach ($variant->attribute_values as $i=>$value_id) {
                        $attributeIndex = $i + 1;

                        $field_value_id = 'product_attribute_value_id' . $attributeIndex;
                        $product_variant->{$field_value_id} = $value_id;

                        // We check the value of the old attribute_id on the products table
                        // then compare it with attribute_id of this new variant.
                        // If it is not same then we should not process it
                        $old_product_id = (string)$updatedproduct->{'attribute_id' . $attributeIndex};
                        if (empty($old_product_id)) {
                            continue;
                        }

                        if (empty($value_id)) {
                            continue;
                        }

                        // This ProductAttributeValue object is to get the product attribute_id
                        // of the new variant
                        $_attribute_value = ProductAttributeValue::where('product_attribute_value_id', $value_id)->first();
                        if (empty($_attribute_value)) {
                            $errorMessage = Lang::get('validation.orbit.empty.product_attr.attribute.value', array('id' => $value_id));
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        // Compare it
                        if ((string)$_attribute_value->product_attribute_id !== $old_product_id) {
                            $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.order', [
                                                    'expect' => $old_product_id,
                                                    'got' => $_attribute_value->product_attribute_id
                            ]);
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                    }
                    $product_variant->save();

                    $variants[] = $product_variant;
                }

                $this->keepProductColumnUpToDate($updatedproduct);
            });

            // Delete product variant
            OrbitInput::post('product_variants_delete', function($product_combinations_delete) use ($updatedproduct, $user, &$variants)
            {
                $_variants = App::make('memory:deleted.variants');
                foreach ($_variants as $variant) {
                    // Unset variant which has been deleted
                    foreach ($variants as $i=>$v) {
                        if ((string)$v->product_variant_id === (string)$variant->product_variant_id) {
                            unset($variants[$i]);
                        }
                    }

                    $variant->modified_by = $user->user_id;
                    $variant->delete();
                }

                $this->keepProductColumnUpToDate($updatedproduct);
            });

            $updatedproduct->modified_by = $this->api->user->user_id;

            Event::fire('orbit.product.postupdateproduct.before.save', array($this, $updatedproduct));

            $updatedproduct->save();

            $updatedproduct->setRelation('variants', $variants);
            $updatedproduct->variants = $variants;

            $updatedproduct->load('category1');
            $updatedproduct->load('category2');
            $updatedproduct->load('category3');
            $updatedproduct->load('category4');
            $updatedproduct->load('category5');

            Event::fire('orbit.product.postupdateproduct.after.save', array($this, $updatedproduct));
            $this->response->data = $updatedproduct;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postupdateproduct.after.commit', array($this, $updatedproduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postupdateproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postupdateproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            //$this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postupdateproduct.query.error', array($this, $e));

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
            Event::fire('orbit.product.postupdateproduct.general.exception', array($this, $e));

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
     * GET - Search Product
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `with`                     (optional) - Valid value: family.
     * @param array    `product_id`               (optional) - ID of the product
     * @param string     `product_code`             (optional)
     * @param string     `product_name`             (optional)
     * @param string     `short_description`        (optional)
     * @param string     `long_description`         (optional)
     * @param string     `product_name_like`        (optional)
     * @param string     `short_description_like`   (optional)
     * @param string     `long_description_like`    (optional)
     * @param integer    `merchant_id`              (optional)
     * @param integer    `status`                   (optional)
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.getsearchproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.getsearchproduct.after.auth', array($this));

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.getsearchproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.product.getsearchproduct.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.getsearchproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'product_id' => OrbitInput::get('product_id'),
                ),
                array(
                    'sort_by'       => 'in:registered_date,product_id,product_name,product_code,product_price,product_tax_code,product_short_description,product_long_description,product_is_new,product_new_until,product_merchant_id,product_status',
                    'product_id'    => 'array|min:1'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.product_sortby'),
                )
            );

            Event::fire('orbit.product.getsearchproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.product.getsearchproduct.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $products = Product::with('retailers')->excludeDeleted()->allowedForUser($user);

            // Filter product by Ids
            OrbitInput::get('product_id', function ($productIds) use ($products) {
                $products->whereIn('products.product_id', $productIds);
            });

            // Filter product by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($products) {
                $products->whereIn('products.merchant_id', $merchantIds);
            });

            // Filter product by product code
            OrbitInput::get('product_code', function ($product_code) use ($products) {
                $products->whereIn('products.product_code', $product_code);
            });

            // Filter product by name
            OrbitInput::get('product_name', function ($name) use ($products) {
                $products->whereIn('products.product_name', $name);
            });

            // Filter product by name pattern
            OrbitInput::get('product_name_like', function ($name) use ($products) {
                $products->where('products.product_name', 'like', "%$name%");
            });

            // Filter product by short description
            OrbitInput::get('short_description', function ($short_description) use ($products) {
                $products->whereIn('products.short_description', $short_description);
            });

            // Filter product by short description pattern
            OrbitInput::get('short_description_like', function ($short_description) use ($products) {
                $products->where('products.short_description', 'like', "%$short_description%");
            });

            // Filter product by long description
            OrbitInput::get('long_description', function ($long_description) use ($products) {
                $products->whereIn('products.long_description', $long_description);
            });

            // Filter product by long description pattern
            OrbitInput::get('long_description_like', function ($long_description) use ($products) {
                $products->where('products.long_description', 'like', "%$long_description%");
            });

            // Filter product by status
            OrbitInput::get('status', function ($status) use ($products) {
                $products->whereIn('products.status', $status);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($products) {
                $with = (array) $with;
                foreach ($with as $relation) {
                    if ($relation === 'family') {
                        $with = array_merge($with, array('category1', 'category2', 'category3', 'category4', 'category5'));
                        break;
                    }
                }
                $products->with($with);
            });

            $_products = clone $products;

            // Get the take args
            $take = $maxRecord;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;
            });
            $products->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $products) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $products->skip($skip);

            // Default sort by
            $sortBy = 'products.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'           => 'products.created_at',
                    'product_id'                => 'products.product_id',
                    'product_name'              => 'products.product_name',
                    'product_code'              => 'products.product_code',
                    'product_price'             => 'products.price',
                    'product_tax_code'          => 'products.tax_code',
                    'product_short_description' => 'products.short_description',
                    'product_long_description'  => 'products.long_description',
                    'product_is_new'            => 'products.is_new',
                    'product_new_until'         => 'products.new_until',
                    'product_merchant_id'       => 'products.merchant_id',
                    'product_status'            => 'products.status',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $products->orderBy($sortBy, $sortMode);

            $totalRec = $_products->count();
            $listOfRec = $products->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.getsearchproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.getsearchproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.product.getsearchproduct.query.error', array($this, $e));

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
            Event::fire('orbit.product.getsearchproduct.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        $output = $this->render($httpCode);
        Event::fire('orbit.product.getsearchproduct.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Add new product
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `merchant_id`               (required) - ID of the merchant
     * @param string    `product_code`              (optional) - Product code
     * @param string    `upc_code`                  (optional) - Merchant description
     * @param string    `product_name`              (required) - Product name
     * @param string    `image`                     (optional) - Product image
     * @param string    `short_description`         (optional) - Product short description
     * @param string    `long_description`          (optional) - Product long description
     * @param string    `is_featured`               (optional) - is featured
     * @param string    `new_from`                  (optional) - new from
     * @param string    `new_until`                 (optional) - new until
     * @param string    `in_store_localization`     (optional) - in store localization
     * @param string    `post_sales_url`            (optional) - post sales url
     * @param decimal   `price`                     (optional) - Price of the product
     * @param string    `merchant_tax_id1`          (optional) - Tax 1
     * @param string    `merchant_tax_id2`          (optional) - Tax 2
     * @param string    `status`                    (required) - Status
     * @param integer   `created_by`                (optional) - ID of the creator
     * @param integer   `modified_by`               (optional) - Modify by
     * @param file      `images`                    (optional) - Product Image
     * @param array     `retailer_ids`              (optional) - ORID links
     * @param integer   `category_id1`              (optional) - Category ID1.
     * @param integer   `category_id2`              (optional) - Category ID2.
     * @param integer   `category_id3`              (optional) - Category ID3.
     * @param integer   `category_id4`              (optional) - Category ID4.
     * @param integer   `category_id5`              (optional) - Category ID5.
     * @param integer   `product_variants`          (optional) - JSON String of Product Combination
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postnewproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postnewproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postnewproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_product')) {
                Event::fire('orbit.merchant.postnewproduct.authz.notallowed', array($this, $user));
                $createProductLang = Lang::get('validation.orbit.actionlist.new_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createProductLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postnewproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');
            $product_code = OrbitInput::post('product_code');
            $upc_code = OrbitInput::post('upc_code');
            $product_name = OrbitInput::post('product_name');
            $image = OrbitInput::post('image');
            $short_description = OrbitInput::post('short_description');
            $long_description = OrbitInput::post('long_description');
            $is_featured = OrbitInput::post('is_featured');
            $new_from = OrbitInput::post('new_from');
            $new_until = OrbitInput::post('new_until');
            $in_store_localization = OrbitInput::post('in_store_localization');
            $post_sales_url = OrbitInput::post('post_sales_url');
            $price = OrbitInput::post('price');
            $merchant_tax_id1 = OrbitInput::post('merchant_tax_id1');
            $merchant_tax_id2 = OrbitInput::post('merchant_tax_id2');
            $status = OrbitInput::post('status');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $category_id1 = OrbitInput::post('category_id1');
            $category_id2 = OrbitInput::post('category_id2');
            $category_id3 = OrbitInput::post('category_id3');
            $category_id4 = OrbitInput::post('category_id4');
            $category_id5 = OrbitInput::post('category_id5');

            $validator = Validator::make(
                array(
                    'merchant_id'       => $merchant_id,
                    'product_name'      => $product_name,
                    'status'            => $status,
                    'category_id1'      => $category_id1,
                    'category_id2'      => $category_id2,
                    'category_id3'      => $category_id3,
                    'category_id4'      => $category_id4,
                    'category_id5'      => $category_id5,
                ),
                array(
                    'merchant_id'           => 'required|numeric|orbit.empty.merchant',
                    'product_name'          => 'required',
                    'status'                => 'required|orbit.empty.product_status',
                    'category_id1'          => 'numeric|orbit.empty.category_id1',
                    'category_id2'          => 'numeric|orbit.empty.category_id2',
                    'category_id3'          => 'numeric|orbit.empty.category_id3',
                    'category_id4'          => 'numeric|orbit.empty.category_id4',
                    'category_id5'          => 'numeric|orbit.empty.category_id5',
                )
            );

            Event::fire('orbit.product.postnewproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            foreach ($retailer_ids as $retailer_id_check) {
                $validator = Validator::make(
                    array(
                        'retailer_id'   => $retailer_id_check,

                    ),
                    array(
                        'retailer_id'   => 'numeric|orbit.empty.retailer',
                    )
                );

                Event::fire('orbit.product.postnewproduct.before.retailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.product.postnewproduct.after.retailervalidation', array($this, $validator));
            }

            Event::fire('orbit.product.postnewproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $newproduct = new Product();
            $newproduct->merchant_id = $merchant_id;
            $newproduct->product_code = $product_code;
            $newproduct->upc_code = $upc_code;
            $newproduct->product_name = $product_name;
            $newproduct->image = $image;
            $newproduct->short_description = $short_description;
            $newproduct->long_description = $long_description;
            $newproduct->is_featured = $is_featured;
            $newproduct->new_from = $new_from;
            $newproduct->new_until = $new_until;
            $newproduct->in_store_localization = $in_store_localization;
            $newproduct->post_sales_url = $post_sales_url;
            $newproduct->price = $price;
            $newproduct->merchant_tax_id1 = $merchant_tax_id1;
            $newproduct->merchant_tax_id2 = $merchant_tax_id2;
            $newproduct->status = $status;
            $newproduct->created_by = $this->api->user->user_id;
            $newproduct->modified_by = $this->api->user->user_id;
            $newproduct->category_id1 = $category_id1;
            $newproduct->category_id2 = $category_id2;
            $newproduct->category_id3 = $category_id3;
            $newproduct->category_id4 = $category_id4;
            $newproduct->category_id5 = $category_id5;

            Event::fire('orbit.product.postnewproduct.before.save', array($this, $newproduct));

            $newproduct->save();

            $productretailers = array();

            foreach ($retailer_ids as $retailer_id) {
                $productretailer = new ProductRetailer();
                $productretailer->retailer_id = $retailer_id;
                $productretailer->product_id = $newproduct->product_id;
                $productretailer->save();
                $productretailers[] = $productretailer;
            }

            // Save product variants (combination)
            $variants = array();
            OrbitInput::post('product_variants', function($product_combinations)
            use ($price, $upc_code, $merchant_id, $user, $newproduct, $product_code, &$variants)
            {
                $variant_decode = $this->JSONValidate($product_combinations);
                $index = 1;
                $attribute_values = $this->checkVariant($variant_decode);

                foreach ($variant_decode as $variant) {
                    // Return the default price if the variant price is empty
                    $vprice = function() use ($variant, $price) {
                        if (empty($variant->price)) {
                            return $price;
                        }

                        return $variant->price;
                    };

                    // Return the default sku if the variant sku is empty
                    $vsku = function() use ($variant, $product_code) {
                        if (empty($variant->sku)) {
                            return $product_code;
                        }

                        return $variant->sku;
                    };

                    // Return the default upc if the variant upc is empty
                    $vupc = function() use ($variant, $upc_code) {
                        if (empty($variant->upc)) {
                            return $upc_code;
                        }

                        return $variant->upc;
                    };

                    $product_variant = new ProductVariant();
                    $product_variant->product_id = $newproduct->product_id;
                    $product_variant->price = $vprice();
                    $product_variant->sku = $vsku();
                    $product_variant->upc = $vupc();
                    $product_variant->merchant_id = $merchant_id;
                    $product_variant->created_by = $user->user_id;
                    $product_variant->status = 'active';

                    // Save the 5 attributes value id
                    foreach ($variant->attribute_values as $i=>$value_id) {
                        $field_value_id = 'product_attribute_value_id' . ($i + 1);
                        $product_variant->{$field_value_id} = $value_id;
                    }
                    $product_variant->save();

                    $variants[] = $product_variant;
                }

                // Save the product attribute id to the product table
                foreach ($attribute_values as $index_attr=>$value) {
                    $field_attribute_id = 'attribute_id' . ($index_attr + 1);
                    $newproduct->$field_attribute_id = $value->product_attribute_id;
                }
                $newproduct->save();
            });

            $newproduct->setRelation('retailers', $productretailers);
            $newproduct->retailers = $productretailers;

            $newproduct->setRelation('variants', $variants);
            $newproduct->variants = $variants;

            $newproduct->load('category1');
            $newproduct->load('category2');
            $newproduct->load('category3');
            $newproduct->load('category4');
            $newproduct->load('category5');

            Event::fire('orbit.product.postnewproduct.after.save', array($this, $newproduct));
            $this->response->data = $newproduct;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postnewproduct.after.commit', array($this, $newproduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postnewproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postnewproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postnewproduct.query.error', array($this, $e));

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
            Event::fire('orbit.product.postnewproduct.general.exception', array($this, $e));

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
     * POST - Delete Product
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `product_id`                  (required) - ID of the product
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postdeleteproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postdeleteproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postdeleteproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_product')) {
                Event::fire('orbit.product.postdeleteproduct.authz.notallowed', array($this, $user));
                $deleteProductLang = Lang::get('validation.orbit.actionlist.delete_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteProductLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postdeleteproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $product_id = OrbitInput::post('product_id');

            $validator = Validator::make(
                array(
                    'product_id' => $product_id,
                ),
                array(
                    'product_id' => 'required|numeric|orbit.empty.product',
                )
            );

            Event::fire('orbit.product.postdeleteproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postdeleteproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deleteproduct = Product::excludeDeleted()->allowedForUser($user)->where('product_id', $product_id)->first();
            $deleteproduct->status = 'deleted';
            $deleteproduct->modified_by = $this->api->user->user_id;

            Event::fire('orbit.product.postdeleteproduct.before.save', array($this, $deleteproduct));

            // get product-retailer for the product
            $deleteproductretailers = ProductRetailer::where('product_id', $deleteproduct->product_id)->get();

            foreach ($deleteproductretailers as $deleteproductretailer) {
                $deleteproductretailer->delete();
            }

            $deleteproduct->save();

            Event::fire('orbit.product.postdeleteproduct.after.save', array($this, $deleteproduct));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.product');

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postdeleteproduct.after.commit', array($this, $deleteproduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postdeleteproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postdeleteproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postdeleteproduct.query.error', array($this, $e));

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
            Event::fire('orbit.product.postdeleteproduct.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.product.postdeleteproduct.before.render', array($this, $output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of product id
        $user = $this->api->user;
        Validator::extend('orbit.empty.product', function ($attribute, $value, $parameters) use ($user) {
            $product = Product::with('retailers', 'category1', 'category2', 'category3', 'category4', 'category5', 'variants')
                                     ->excludeDeleted()
                                     ->allowedForUser($user)
                                     ->where('product_id', $value)
                                     ->first();

            if (empty($product)) {
                return FALSE;
            }

            App::instance('orbit.empty.product', $product);

            return TRUE;
        });

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

        // Check the existance of retailer id
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) {
            $merchant = App::make('orbit.empty.merchant');
            $retailer = Retailer::excludeDeleted()->allowedForUser($this->api->user)
                        ->where('merchant_id', $value)
                        ->where('parent_id', $merchant->merchant_id)
                        ->first();

            if (empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.empty.retailer', $retailer);

            return TRUE;
        });

        // Check product_code, it should not exists
        Validator::extend('orbit.exists.product_code', function ($attribute, $value, $parameters) {
            $product = Product::excludeDeleted()
                        ->where('product_code', $value)
                        ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.validation.product_code', $product);

            return TRUE;
        });

        // Check changed product_code
        Validator::extend('product_code_exists_but_me', function ($attribute, $value, $parameters) {
            $product_id = OrbitInput::post('product_id');
            $product = Product::excludeDeleted()
                        ->where('product_code', $value)
                        ->where('product_id', '!=', $product_id)
                        ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.validation.product', $product);

            return TRUE;
        });

        // Check upc_code, it should not exists
        Validator::extend('orbit.exists.upc_code', function ($attribute, $value, $parameters) {
            $product = Product::excludeDeleted()
                        ->where('upc_code', $value)
                        ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.validation.upc_code', $product);

            return TRUE;
        });

        // Check changed upc_code
        Validator::extend('upc_code_exists_but_me', function ($attribute, $value, $parameters) {
            $product_id = OrbitInput::post('product_id');
            $product = Product::excludeDeleted()
                        ->where('upc_code', $value)
                        ->where('product_id', '!=', $product_id)
                        ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.validation.product', $product);

            return TRUE;
        });

        // Check the existance of category_id1
        Validator::extend('orbit.empty.category_id1', function ($attribute, $value, $parameters) {
            $category_id1 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($category_id1)) {
                return FALSE;
            }

            App::instance('orbit.empty.category_id1', $category_id1);

            return TRUE;
        });

        // Check the existance of category_id2
        Validator::extend('orbit.empty.category_id2', function ($attribute, $value, $parameters) {
            $category_id2 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($category_id2)) {
                return FALSE;
            }

            App::instance('orbit.empty.category_id2', $category_id2);

            return TRUE;
        });

        // Check the existance of category_id3
        Validator::extend('orbit.empty.category_id3', function ($attribute, $value, $parameters) {
            $category_id3 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($category_id3)) {
                return FALSE;
            }

            App::instance('orbit.empty.category_id3', $category_id3);

            return TRUE;
        });

        // Check the existance of category_id4
        Validator::extend('orbit.empty.category_id4', function ($attribute, $value, $parameters) {
            $category_id4 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($category_id4)) {
                return FALSE;
            }

            App::instance('orbit.empty.category_id4', $category_id4);

            return TRUE;
        });

        // Check the existance of category_id5
        Validator::extend('orbit.empty.category_id5', function ($attribute, $value, $parameters) {
            $category_id5 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($category_id5)) {
                return FALSE;
            }

            App::instance('orbit.empty.category_id5', $category_id5);

            return TRUE;
        });

        // Check the existence of the product status
        Validator::extend('orbit.empty.product_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of each variant ID
        Validator::extend('orbit.empty.product_variant_array', function ($attribute, $value, $parameters) {
            $variants = App::make('orbit.empty.product')->variants;

            $variant_ids = [];
            $variant_objects = [];
            foreach ($variants as $i=>$variant) {
                $variant_ids[$i] = $variant->product_variant_id;
            }

            $valid_deleted = [];

            foreach ($value as $variant_id) {
                if (! in_array($variant_id, $variant_ids)) {
                    return FALSE;
                }

                foreach ($variants as $variant) {
                    if ((string)$variant->product_variant_id === (string)$variant_id) {
                        $variant_objects[] = $variant;
                    }
                }
            }

            App::instance('memory:deleted.variants', $variant_objects);

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
        $errorMessage = Lang::get('validation.orbit.jsonerror.field.format');

        if (! is_string($string)) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        $result = @json_decode($string);
        if (json_last_error() !== JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        $errorMessage = Lang::get('validation.orbit.jsonerror.field.array');
        if (! is_array($result)) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        return $result;
    }

    /**
     * Check the validity of variant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param object $variant
     * @return array - ProductAttributeValue
     */
    protected function checkVariant($variants, $mode='normal')
    {
        $values = array();

        foreach ($variants as $i=>$variant) {
            $neededProperties = array('attribute_values', 'price', 'sku', 'upc');

            if ($mode === 'update') {
                // Check the existence of 'variant_id'
                $neededProperties[] = 'variant_id';
            }

            foreach ($neededProperties as $property) {
                // It should have property specified
                if (! property_exists($variant, $property)) {
                    $errorMessage = Lang::get('validation.orbit.empty.product_attr.attribute.json_property',
                        array('property' => $property)
                    );
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            if (! is_array($variant->attribute_values)) {
                $errorMessage = Lang::get('validation.orbit.jsonerror.field', array('field' => 'attribute_values'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (count($variant->attribute_values) !== 5) {
                $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.count');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Check the price validity
            if (! empty($variant->price)) {
                if (! preg_match('/^[+-]?((\d+(\.\d*)?)|(\.\d+))$/', $variant->price)) {
                    $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.price');
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            if ($mode === 'update') {
                // Check the existence of the product variant id
                $product = App::make('orbit.empty.product');
            }

            // Check each of these product attribute value existence
            $merchantId = $this->getMerchantId();
            foreach ($variant->attribute_values as $value_id) {
                if (empty($value_id)) {
                    continue;
                }

                $productAttributeValue = ProductAttributeValue::excludeDeleted('product_attribute_values')
                                                              ->merchantIds(array($merchantId))
                                                              ->where('product_attribute_value_id', $value_id)
                                                              ->first();

                if (empty($productAttributeValue)) {
                    $errorMessage = Lang::get('validation.orbit.empty.product_attr.attribute.value', array('id' => $value_id));
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                // This code are executed inside a loop so, we only need the first
                // index to get the object of productAttributeValue to determine
                // which product_attribute_id this group belonging to
                if ($i === 0) {
                    $values[] = $productAttributeValue;
                }
            }
        }

        return $values;
    }

    /**
     * Helper for checking current merchant id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return int
     */
    protected function getMerchantId()
    {
        $currentProduct = NULL;

        $merchantId = OrbitInput::post('merchant_id', function($_merchantId) {
            return $_merchantId;
        });

        if (! $merchantId) {
            if (App::bound('memory:current.updated.product')) {
                $currentProduct = App::make('memory:current.updated.product');
            }

            if (is_object($currentProduct)) {
                $merchantId = $currentProduct->merchant_id;
            }
        }

        return $merchantId;
    }

    /**
     * Update product column attribute_id{1-5} to reflect the most up to date
     * changes.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Product $updatedproduct
     * @return void
     */
    protected function keepProductColumnUpToDate(&$updatedproduct)
    {
        // Get the most complete variant with all the product attribute
        // values which has been set up

        // @Todo
        // This is slow, it should be rewritten
        $with = array(
            'attributeValue1',
            'attributeValue2',
            'attributeValue3',
            'attributeValue4',
            'attributeValue5',
        );
        $complete_variant = ProductVariant::excludeDeleted()
                                          ->mostCompleteValue()
                                          ->where('product_id', $updatedproduct->product_id)
                                          ->with($with)
                                          ->first();

        // Flag to determine if the updated product has been changes
        $updated_product_changes = FALSE;

        if (empty($complete_variant)) {
            // This product does not have any variant anymore
            for ($i=5; $i>=1; $i--) {
                $updatedproduct->{'attribute_id' . $i} = NULL;
            }

            $updated_product_changes = TRUE;
        } else {
            // Update the product attribute id{1-5}
            for ($i=5; $i>=1; $i--) {
                if (is_null($complete_variant->{'attributeValue' . $i})) {
                    continue;
                }

                // If we goes here then particular attribute value is not empty
                // and also has attributeValue object
                $updatedproduct->{'attribute_id' . $i} = $complete_variant->{'attributeValue' . $i}->product_attribute_id;

                // Update the flag
                $updated_product_changes = TRUE;
            }
        }

        // Save the updated product
        if ($updated_product_changes) {
            $updatedproduct->save();
        }
    }
}
