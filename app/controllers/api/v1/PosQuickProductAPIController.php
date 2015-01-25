<?php
/**
 * An API controller for managing POS Quick Product.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;

class PosQuickProductAPIController extends ControllerAPI
{
    /**
     * POST - Create pos quick product
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_id`    (required) - ID of the product
     * @param integer   `merchant_id`   (required) - ID of the merchant
     * @param integer   `product_order` (requird)  - Order of the Pos Quick Product Order
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewPosQuickProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postnewposquickproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postnewposquickproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postnewposquickproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_pos_quick_product')) {
                Event::fire('orbit.product.postnewposquickproduct.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.add_new_pos_quick_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postnewposquickproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $productId = OrbitInput::post('product_id');
            $merchantId = OrbitInput::post('merchant_id');
            $order = OrbitInput::post('product_order');

            $validator = Validator::make(
                array(
                    'product_id'        => $productId,
                    'merchant_id'       => $merchantId,
                    'product_order'     => $order,
                ),
                array(
                    'product_id'        => 'required|numeric|orbit.empty.product',
                    'merchant_id'       => 'required|numeric|orbit.empty.merchant',
                    'product_order'     => 'required|numeric|min:0'
                )
            );

            Event::fire('orbit.product.postnewposquickproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postnewposquickproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $posQuickProduct = PosQuickProduct::excludeDeleted()
                                              ->where('product_id', $productId)
                                              ->where('merchant_id', $merchantId)
                                              ->first();
            if (empty($posQuickProduct)) {
                $posQuickProduct = new PosQuickProduct();
            }
            $posQuickProduct->product_id = $productId;
            $posQuickProduct->merchant_id = $merchantId;
            $posQuickProduct->product_order = $order;

            Event::fire('orbit.product.postnewposquickproduct.before.save', array($this, $posQuickProduct));

            $posQuickProduct->save();

            Event::fire('orbit.product.postnewposquickproduct.after.save', array($this, $posQuickProduct));
            $this->response->data = $posQuickProduct;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postnewposquickproduct.after.commit', array($this, $posQuickProduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postnewposquickproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postnewposquickproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postnewposquickproduct.query.error', array($this, $e));

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
            Event::fire('orbit.product.postnewposquickproduct.general.exception', array($this, $e));

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
     * POST - Update pos quick product
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_id`    (required) - ID of the product
     * @param integer   `merchant_id`   (required) - ID of the merchant
     * @param integer   `product_order` (requird)  - Order of the Pos Quick Product Order
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdatePosQuickProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postupdateposquickproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postupdateposquickproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postupdateposquickproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_pos_quick_product')) {
                Event::fire('orbit.product.postupdateposquickproduct.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.update_pos_quick_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postupdateposquickproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $productId = OrbitInput::post('product_id');
            $merchantId = OrbitInput::post('merchant_id');
            $order = OrbitInput::post('product_order');

            $validator = Validator::make(
                array(
                    'product_id'        => $productId,
                    'merchant_id'       => $merchantId,
                    'product_order'     => $order,
                ),
                array(
                    'product_id'        => 'required|numeric|orbit.empty.product',
                    'merchant_id'       => 'required|numeric|orbit.empty.merchant',
                    'product_order'     => 'required|numeric|min:0'
                )
            );

            Event::fire('orbit.product.postupdateposquickproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postupdateposquickproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $posQuickProduct = PosQuickProduct::excludeDeleted()
                                              ->where('product_id', $productId)
                                              ->where('merchant_id', $merchantId)
                                              ->first();
            if (empty($posQuickProduct)) {
                $posQuickProduct = new PosQuickProduct();
            }
            $posQuickProduct->product_id = $productId;
            $posQuickProduct->merchant_id = $merchantId;
            $posQuickProduct->product_order = $order;

            Event::fire('orbit.product.postupdateposquickproduct.before.save', array($this, $posQuickProduct));

            $posQuickProduct->save();

            Event::fire('orbit.product.postupdateposquickproduct.after.save', array($this, $posQuickProduct));
            $this->response->data = $posQuickProduct;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postupdateposquickproduct.after.commit', array($this, $posQuickProduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postupdateposquickproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postupdateposquickproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postupdateposquickproduct.query.error', array($this, $e));

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
            Event::fire('orbit.product.postupdateposquickproduct.general.exception', array($this, $e));

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
     * POST - Delete pos quick product
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_id`    (required) - ID of the product
     * @param integer   `merchant_id`   (required) - ID of the merchant
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeletePosQuickProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postupdateposquickproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postupdateposquickproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postupdateposquickproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_pos_quick_product')) {
                Event::fire('orbit.product.postupdateposquickproduct.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.delete_pos_quick_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postupdateposquickproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $productId = OrbitInput::post('product_id');
            $merchantId = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'product_id'        => $productId,
                    'merchant_id'       => $merchantId,
                ),
                array(
                    'product_id'        => 'required|numeric|orbit.empty.product',
                    'merchant_id'       => 'required|numeric|orbit.empty.merchant',
                )
            );

            Event::fire('orbit.product.postupdateposquickproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postupdateposquickproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $posQuickProduct = PosQuickProduct::excludeDeleted()
                                              ->where('product_id', $productId)
                                              ->where('merchant_id', $merchantId)
                                              ->first();
            if (empty($posQuickProduct)) {
                $errorMessage = Lang::get('validation.orbit.empty.posquickproduct');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.product.postupdateposquickproduct.before.save', array($this, $posQuickProduct));

            $posQuickProduct->delete();

            Event::fire('orbit.product.postupdateposquickproduct.after.save', array($this, $posQuickProduct));
            $this->response->data = $posQuickProduct;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.product.postupdateposquickproduct.after.commit', array($this, $posQuickProduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postupdateposquickproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postupdateposquickproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postupdateposquickproduct.query.error', array($this, $e));

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
            Event::fire('orbit.product.postupdateposquickproduct.general.exception', array($this, $e));

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
     * GET - List of POS Quick Product
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array         `product_ids`           (optional) - IDs of the product
     * @param array         `merchant_ids`          (optional) - IDs of the merchant
     * @param array         `retailer_ids`          (optional) - IDs of the
     * @param string        `sort_by`               (optional) - column order by
     * @param string        `sort_mode`             (optional) - asc or desc
     * @param integer       `take`                  (optional) - limit
     * @param integer       `skip`                  (optional) - limit offset
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchPosQuickProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.getposquickproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.getposquickproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.getposquickproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product_attribute')) {
                Event::fire('orbit.product.getposquickproduct.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_user');
                $message = Lang::get('validation.orbit.access.view_product_attribute', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.getposquickproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:id,price,name,product_order',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.posquickproduct_sortby'),
                )
            );

            Event::fire('orbit.product.getposquickproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.getposquickproduct.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.product_attribute.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }

            // Builder object
            $posQuickProducts = PosQuickProduct::joinRetailer()
                                               ->excludeDeleted('pos_quick_products')
                                               ->with('product');

            // Filter by ids
            OrbitInput::get('id', function($posQuickIds) use ($posQuickProducts) {
                $posQuickProducts->whereIn('pos_quick_products.pos_quick_product_id', $posQuickIds);
            });

            // Filter by merchant ids
            OrbitInput::get('merchant_ids', function($merchantIds) use ($posQuickProducts) {
                $posQuickProducts->whereIn('pos_quick_products.merchant_id', $merchantIds);
            });

            // Filter by retailer ids
            OrbitInput::get('retailer_ids', function($retailerIds) use ($posQuickProducts) {
                $posQuickProducts->whereIn('product_retailer.retailer_id', $retailerIds);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_posQuickProducts = clone $posQuickProducts;

            // Get the take args
            $take = $maxRecord;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;
            });
            $posQuickProducts->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $posQuickProducts) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $posQuickProducts->skip($skip);

            // Default sort by
            $sortBy = 'pos_quick_products.product_order';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'id'            => 'pos_quick_products.pos_quick_product_id',
                    'name'          => 'products.product_name',
                    'product_order' => 'pos_quick_products.product_prder',
                    'price'         => 'products.price',
                    'created'       => 'pos_quick_products.created_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $posQuickProducts->orderBy($sortBy, $sortMode);

            $totalPosQuickProducts = $_posQuickProducts->count();
            $listOfPosQuickProducts = $posQuickProducts->get();

            $data = new stdclass();
            $data->total_records = $totalPosQuickProducts;
            $data->returned_records = count($listOfPosQuickProducts);
            $data->records = $listOfPosQuickProducts;

            if ($listOfPosQuickProducts === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.attribute');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.getposquickproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.getposquickproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.product.getposquickproduct.query.error', array($this, $e));

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
            Event::fire('orbit.product.getposquickproduct.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.product.getposquickproduct.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        $user = $this->api->user;
        Validator::extend('orbit.empty.merchant', function ($attribute, $merchantId, $parameters) use ($user) {
            $merchant = Merchant::allowedForUser($user)
                                ->excludeDeleted()
                                ->where('merchant_id', $merchantId)
                                ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check the existance of product id
        Validator::extend('orbit.empty.product', function ($attribute, $value, $parameters) use ($user) {
            $product = Product::excludeDeleted()
                                ->allowedForUser($user)
                                ->where('product_id', $value)
                                ->first();

            if (empty($product)) {
                return FALSE;
            }

            App::instance('orbit.empty.product', $product);

            return TRUE;
        });
    }
}