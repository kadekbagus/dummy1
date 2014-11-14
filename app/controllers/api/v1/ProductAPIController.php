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
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `product_id`               (required) - ID of the product
     * @param string     `product_code`             (required)
     * @param string     `product_name`             (required)
     * @param decimal    `price`                    (optional)
     * @param string     `tax_code`                 (optional)
     * @param string     `short_description`        (optional)
     * @param string     `long_description`         (optional)
     * @param string     `image`                    (optional)
     * @param string     `is_new`                   (optional)
     * @param string     `new_until`                (optional)
     * @param integer    `stock`                    (optional)
     * @param string     `depend_on_stock`          (optional)
     * @param integer    `retailer_id`              (optional)
     * @param integer    `merchant_id`              (optional)
     * @param integer    `modified_by`              (optional)
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
            $retailer_id = OrbitInput::post('retailer_id');
            $product_code = OrbitInput::post('product_code');
            $product_name = OrbitInput::post('product_name');
            $price = OrbitInput::post('price');
            $tax_code = OrbitInput::post('tax_code');
            $short_description = OrbitInput::post('short_description');
            $long_description = OrbitInput::post('long_description');
            $image = OrbitInput::post('image');
            $is_new = OrbitInput::post('is_new');
            $new_until = date('Y-m-d', strtotime(OrbitInput::post('new_until')));
            $stock = OrbitInput::post('stock');
            $depend_on_stock = OrbitInput::post('depend_on_stock');

            $validator = Validator::make(
                array(
                    'product_id'        => $product_id,
                    'merchant_id'       => $merchant_id,
                    'retailer_id'       => $retailer_id,
                    'retailer_id'       => $retailer_id,
                ),
                array(
                    'product_id'        => 'required|numeric|orbit.empty.product',
                    'merchant_id'       => 'numeric|orbit.empty.merchant',
                    'retailer_id'       => 'numeric|orbit.empty.retailer',
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

            $updatedproduct = Product::find($product_id);
            $updatedproduct->product_code = $product_code;
            $updatedproduct->product_name = $product_name;
            $updatedproduct->price = $price;
            $updatedproduct->tax_code = $tax_code;
            $updatedproduct->short_description = $short_description;
            $updatedproduct->long_description = $long_description;
            $updatedproduct->image = $image;
            $updatedproduct->is_new = $is_new;
            $updatedproduct->new_until = $new_until;
            $updatedproduct->stock = $stock;
            $updatedproduct->depend_on_stock = $depend_on_stock;
            $updatedproduct->retailer_id = $retailer_id;
            $updatedproduct->merchant_id = $merchant_id;
            $updatedproduct->modified_by = $this->api->user->user_id;

            Event::fire('orbit.product.postupdateproduct.before.save', array($this, $updatedproduct));

            $updatedproduct->save();

            Event::fire('orbit.product.postupdateproduct.after.save', array($this, $updatedproduct));
            $this->response->data = $updatedproduct->toArray();

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
            $this->response->data = null;
            $httpCode = 403;

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
        // Check the existance of product id
        Validator::extend('orbit.empty.product', function ($attribute, $value, $parameters) {
            $product = Product::excludeDeleted()
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