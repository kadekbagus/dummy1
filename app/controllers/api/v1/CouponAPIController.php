<?php
/**
 * An API controller for managing Coupon.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class CouponAPIController extends ControllerAPI
{

    /**
     * POST - Create New Promotion
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`           (required) - Merchant ID
     * @param string     `promotion_name`        (required) - Promotion name
     * @param string     `promotion_type`        (required) - Promotion type. Valid value: product, cart.
     * @param string     `status`                (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param datetime   `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string     `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param file       `images`                (optional) - Promotion image
     * @param string     `rule_type`             (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage.
     * @param decimal    `rule_value`            (optional) - Rule value
     * @param string     `discount_object_type`  (optional) - Discount object type. Valid value: product, family.
     * @param integer    `discount_object_id1`   (optional) - Discount object ID1 (product_id or category_id1).
     * @param integer    `discount_object_id2`   (optional) - Discount object ID2 (category_id2).
     * @param integer    `discount_object_id3`   (optional) - Discount object ID3 (category_id3).
     * @param integer    `discount_object_id4`   (optional) - Discount object ID4 (category_id4).
     * @param integer    `discount_object_id5`   (optional) - Discount object ID5 (category_id5).
     * @param decimal    `discount_value`        (optional) - Discount value
     * @param array      `retailer_ids`          (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewPromotion()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.promotion.postnewpromotion.before.auth', array($this));

            $this->checkAuth();
            
            Event::fire('orbit.promotion.postnewpromotion.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotion.postnewpromotion.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('new_promotion')) {
                Event::fire('orbit.promotion.postnewpromotion.authz.notallowed', array($this, $user));
                $createPromotionLang = Lang::get('validation.orbit.actionlist.new_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createPromotionLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.promotion.postnewpromotion.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');
            $promotion_name = OrbitInput::post('promotion_name');
            $promotion_type = OrbitInput::post('promotion_type');
            $status = OrbitInput::post('status');
            $description = OrbitInput::post('description');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $is_permanent = OrbitInput::post('is_permanent');
            $image = OrbitInput::post('image');
            $rule_type = OrbitInput::post('rule_type');
            $rule_value = OrbitInput::post('rule_value');
            $discount_object_type = OrbitInput::post('discount_object_type');
            $discount_object_id1 = OrbitInput::post('discount_object_id1');
            $discount_object_id2 = OrbitInput::post('discount_object_id2');
            $discount_object_id3 = OrbitInput::post('discount_object_id3');
            $discount_object_id4 = OrbitInput::post('discount_object_id4');
            $discount_object_id5 = OrbitInput::post('discount_object_id5');
            $discount_value = OrbitInput::post('discount_value');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;

            $validator = Validator::make(
                array(
                    'merchant_id'          => $merchant_id,
                    'promotion_name'       => $promotion_name,
                    'promotion_type'       => $promotion_type,
                    'status'               => $status,
                    'rule_type'            => $rule_type,
                    'discount_object_type' => $discount_object_type,
                    'discount_object_id1'  => $discount_object_id1,
                    'discount_object_id2'  => $discount_object_id2,
                    'discount_object_id3'  => $discount_object_id3,
                    'discount_object_id4'  => $discount_object_id4,
                    'discount_object_id5'  => $discount_object_id5,
                ),
                array(
                    'merchant_id'          => 'required|numeric|orbit.empty.merchant',
                    'promotion_name'       => 'required|orbit.exists.promotion_name',
                    'promotion_type'       => 'required|orbit.empty.promotion_type',
                    'status'               => 'required|orbit.empty.promotion_status',
                    'rule_type'            => 'orbit.empty.rule_type',
                    'discount_object_type' => 'orbit.empty.discount_object_type',
                    'discount_object_id1'  => 'orbit.empty.discount_object_id1',
                    'discount_object_id2'  => 'orbit.empty.discount_object_id2',
                    'discount_object_id3'  => 'orbit.empty.discount_object_id3',
                    'discount_object_id4'  => 'orbit.empty.discount_object_id4',
                    'discount_object_id5'  => 'orbit.empty.discount_object_id5',
                )
            );

            Event::fire('orbit.promotion.postnewpromotion.before.validation', array($this, $validator));

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

                Event::fire('orbit.promotion.postnewpromotion.before.retailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.promotion.postnewpromotion.after.retailervalidation', array($this, $validator));
            }

            Event::fire('orbit.promotion.postnewpromotion.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // save Promotion.
            $newpromotion = new Promotion();
            $newpromotion->merchant_id = $merchant_id;
            $newpromotion->promotion_name = $promotion_name;
            $newpromotion->promotion_type = $promotion_type;
            $newpromotion->status = $status;
            $newpromotion->description = $description;
            $newpromotion->begin_date = $begin_date;
            $newpromotion->end_date = $end_date;
            $newpromotion->is_permanent = $is_permanent;
            $newpromotion->image = $image;
            $newpromotion->created_by = $this->api->user->user_id;

            Event::fire('orbit.promotion.postnewpromotion.before.save', array($this, $newpromotion));

            $newpromotion->save();

            // save PromotionRule.
            $promotionrule = new PromotionRule();
            $promotionrule->rule_type = $rule_type;
            $promotionrule->rule_value = $rule_value;
            $promotionrule->discount_object_type = $discount_object_type;
            $promotionrule->discount_object_id1 = $discount_object_id1;
            $promotionrule->discount_object_id2 = $discount_object_id2;
            $promotionrule->discount_object_id3 = $discount_object_id3;
            $promotionrule->discount_object_id4 = $discount_object_id4;
            $promotionrule->discount_object_id5 = $discount_object_id5;
            $promotionrule->discount_value = $discount_value;
            $promotionrule = $newpromotion->promotionrule()->save($promotionrule);
            $newpromotion->promotionrule = $promotionrule;

            // save PromotionRetailer.
            $promotionretailers = array();
            foreach ($retailer_ids as $retailer_id) {
                $promotionretailer = new PromotionRetailer();
                $promotionretailer->retailer_id = $retailer_id;
                $promotionretailer->promotion_id = $newpromotion->promotion_id;
                $promotionretailer->save();
                $promotionretailers[] = $promotionretailer;
            }
            $newpromotion->retailers = $promotionretailers;

            Event::fire('orbit.promotion.postnewpromotion.after.save', array($this, $newpromotion));
            $this->response->data = $newpromotion;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.promotion.postnewpromotion.after.commit', array($this, $newpromotion));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotion.postnewpromotion.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotion.postnewpromotion.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.promotion.postnewpromotion.query.error', array($this, $e));

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
            Event::fire('orbit.promotion.postnewpromotion.general.exception', array($this, $e));

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
     * POST - Update Promotion
     *
     * @author <Tian> <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`          (required) - Promotion ID
     * @param integer    `merchant_id`           (optional) - Merchant ID
     * @param string     `promotion_name`        (optional) - Promotion name
     * @param string     `promotion_type`        (optional) - Promotion type. Valid value: product, cart.
     * @param string     `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param datetime   `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string     `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param file       `images`                (optional) - Promotion image
     * @param string     `rule_type`             (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage.
     * @param decimal    `rule_value`            (optional) - Rule value
     * @param string     `discount_object_type`  (optional) - Discount object type. Valid value: product, family.
     * @param integer    `discount_object_id1`   (optional) - Discount object ID1 (product_id or category_id1).
     * @param integer    `discount_object_id2`   (optional) - Discount object ID2 (category_id2).
     * @param integer    `discount_object_id3`   (optional) - Discount object ID3 (category_id3).
     * @param integer    `discount_object_id4`   (optional) - Discount object ID4 (category_id4).
     * @param integer    `discount_object_id5`   (optional) - Discount object ID5 (category_id5).
     * @param decimal    `discount_value`        (optional) - Discount value
     * @param string     `no_retailer`           (optional) - Flag to delete all ORID links. Valid value: Y.
     * @param array      `retailer_ids`          (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdatePromotion()
    {
        try {
            $httpCode=200;

            Event::fire('orbit.promotion.postupdatepromotion.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotion.postupdatepromotion.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotion.postupdatepromotion.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_promotion')) {
                Event::fire('orbit.promotion.postupdatepromotion.authz.notallowed', array($this, $user));
                $updatePromotionLang = Lang::get('validation.orbit.actionlist.update_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updatePromotionLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.promotion.postupdatepromotion.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $promotion_id = OrbitInput::post('promotion_id');
            $merchant_id = OrbitInput::post('merchant_id');
            $promotion_name = OrbitInput::post('promotion_name');
            $promotion_type = OrbitInput::post('promotion_type');
            $status = OrbitInput::post('status');
            $rule_type = OrbitInput::post('rule_type');
            $discount_object_type = OrbitInput::post('discount_object_type');
            $discount_object_id1 = OrbitInput::post('discount_object_id1');
            $discount_object_id2 = OrbitInput::post('discount_object_id2');
            $discount_object_id3 = OrbitInput::post('discount_object_id3');
            $discount_object_id4 = OrbitInput::post('discount_object_id4');
            $discount_object_id5 = OrbitInput::post('discount_object_id5');

            $validator = Validator::make(
                array(
                    'promotion_id'         => $promotion_id,
                    'merchant_id'          => $merchant_id,
                    'promotion_name'       => $promotion_name,
                    'promotion_type'       => $promotion_type,
                    'status'               => $status,
                    'rule_type'            => $rule_type,
                    'discount_object_type' => $discount_object_type,
                    'discount_object_id1'  => $discount_object_id1,
                    'discount_object_id2'  => $discount_object_id2,
                    'discount_object_id3'  => $discount_object_id3,
                    'discount_object_id4'  => $discount_object_id4,
                    'discount_object_id5'  => $discount_object_id5,
                ),
                array(
                    'promotion_id'         => 'required|numeric|orbit.empty.promotion',
                    'merchant_id'          => 'numeric|orbit.empty.merchant',
                    'promotion_name'       => 'promotion_name_exists_but_me',
                    'promotion_type'       => 'orbit.empty.promotion_type',
                    'status'               => 'orbit.empty.promotion_status',
                    'rule_type'            => 'orbit.empty.rule_type',
                    'discount_object_type' => 'orbit.empty.discount_object_type',
                    'discount_object_id1'  => 'orbit.empty.discount_object_id1',
                    'discount_object_id2'  => 'orbit.empty.discount_object_id2',
                    'discount_object_id3'  => 'orbit.empty.discount_object_id3',
                    'discount_object_id4'  => 'orbit.empty.discount_object_id4',
                    'discount_object_id5'  => 'orbit.empty.discount_object_id5',
                ),
                array(
                   'promotion_name_exists_but_me' => Lang::get('validation.orbit.exists.promotion_name'),
                )
            );

            Event::fire('orbit.promotion.postupdatepromotion.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotion.postupdatepromotion.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedpromotion = Promotion::with('promotionrule', 'retailers')->excludeDeleted()->allowedForUser($user)->where('promotion_id', $promotion_id)->first();

            // save Promotion.
            OrbitInput::post('merchant_id', function($merchant_id) use ($updatedpromotion) {
                $updatedpromotion->merchant_id = $merchant_id;
            });

            OrbitInput::post('promotion_name', function($promotion_name) use ($updatedpromotion) {
                $updatedpromotion->promotion_name = $promotion_name;
            });

            OrbitInput::post('promotion_type', function($promotion_type) use ($updatedpromotion) {
                $updatedpromotion->promotion_type = $promotion_type;
            });

            OrbitInput::post('status', function($status) use ($updatedpromotion) {
                $updatedpromotion->status = $status;
            });

            OrbitInput::post('description', function($description) use ($updatedpromotion) {
                $updatedpromotion->description = $description;
            });

            OrbitInput::post('begin_date', function($begin_date) use ($updatedpromotion) {
                $updatedpromotion->begin_date = $begin_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedpromotion) {
                $updatedpromotion->end_date = $end_date;
            });

            OrbitInput::post('is_permanent', function($is_permanent) use ($updatedpromotion) {
                $updatedpromotion->is_permanent = $is_permanent;
            });

            OrbitInput::post('image', function($image) use ($updatedpromotion) {
                $updatedpromotion->image = $image;
            });

            $updatedpromotion->modified_by = $this->api->user->user_id;

            Event::fire('orbit.promotion.postupdatepromotion.before.save', array($this, $updatedpromotion));

            $updatedpromotion->save();


            // save PromotionRule.
            $promotionrule = PromotionRule::where('promotion_id', '=', $promotion_id)->first();
            OrbitInput::post('rule_type', function($rule_type) use ($promotionrule) {
                if (trim($rule_type) === '') {
                    $rule_type = NULL;
                }
                $promotionrule->rule_type = $rule_type;
            });

            OrbitInput::post('rule_value', function($rule_value) use ($promotionrule) {
                $promotionrule->rule_value = $rule_value;
            });

            OrbitInput::post('discount_object_type', function($discount_object_type) use ($promotionrule) {
                if (trim($discount_object_type) === '') {
                    $discount_object_type = NULL;
                }
                $promotionrule->discount_object_type = $discount_object_type;
            });

            OrbitInput::post('discount_object_id1', function($discount_object_id1) use ($promotionrule) {
                if (trim($discount_object_id1) === '') {
                    $discount_object_id1 = NULL;
                }
                $promotionrule->discount_object_id1 = $discount_object_id1;
            });

            OrbitInput::post('discount_object_id2', function($discount_object_id2) use ($promotionrule) {
                if (trim($discount_object_id2) === '') {
                    $discount_object_id2 = NULL;
                }
                $promotionrule->discount_object_id2 = $discount_object_id2;
            });

            OrbitInput::post('discount_object_id3', function($discount_object_id3) use ($promotionrule) {
                if (trim($discount_object_id3) === '') {
                    $discount_object_id3 = NULL;
                }
                $promotionrule->discount_object_id3 = $discount_object_id3;
            });

            OrbitInput::post('discount_object_id4', function($discount_object_id4) use ($promotionrule) {
                if (trim($discount_object_id4) === '') {
                    $discount_object_id4 = NULL;
                }
                $promotionrule->discount_object_id4 = $discount_object_id4;
            });

            OrbitInput::post('discount_object_id5', function($discount_object_id5) use ($promotionrule) {
                if (trim($discount_object_id5) === '') {
                    $discount_object_id5 = NULL;
                }
                $promotionrule->discount_object_id5 = $discount_object_id5;
            });

            OrbitInput::post('discount_value', function($discount_value) use ($promotionrule) {
                $promotionrule->discount_value = $discount_value;
            });
            $promotionrule->save();
            $updatedpromotion->setRelation('promotionrule', $promotionrule);
            $updatedpromotion->promotionrule = $promotionrule;


            // save PromotionRetailer.
            OrbitInput::post('no_retailer', function($no_retailer) use ($updatedpromotion) {
                if ($no_retailer == 'Y') {
                    $deleted_retailer_ids = PromotionRetailer::where('promotion_id', $updatedpromotion->promotion_id)->get(array('retailer_id'))->toArray();
                    $updatedpromotion->retailers()->detach($deleted_retailer_ids);
                    $updatedpromotion->load('retailers');
                }
            });

            OrbitInput::post('retailer_ids', function($retailer_ids) use ($updatedpromotion) {
                // validate retailer_ids
                $retailer_ids = (array) $retailer_ids;
                foreach ($retailer_ids as $retailer_id_check) {
                    $validator = Validator::make(
                        array(
                            'retailer_id'   => $retailer_id_check,
                        ),
                        array(
                            'retailer_id'   => 'orbit.empty.retailer',
                        )
                    );

                    Event::fire('orbit.promotion.postupdatepromotion.before.retailervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.promotion.postupdatepromotion.after.retailervalidation', array($this, $validator));
                }
                // sync new set of retailer ids
                $updatedpromotion->retailers()->sync($retailer_ids);

                // reload retailers relation
                $updatedpromotion->load('retailers');
            });
            

            Event::fire('orbit.promotion.postupdatepromotion.after.save', array($this, $updatedpromotion));
            $this->response->data = $updatedpromotion;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.promotion.postupdatepromotion.after.commit', array($this, $updatedpromotion));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotion.postupdatepromotion.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotion.postupdatepromotion.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.promotion.postupdatepromotion.query.error', array($this, $e));

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
            Event::fire('orbit.promotion.postupdatepromotion.general.exception', array($this, $e));

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
     * POST - Delete Promotion
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                  (required) - ID of the promotion
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeletePromotion()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.promotion.postdeletepromotion.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotion.postdeletepromotion.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotion.postdeletepromotion.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_promotion')) {
                Event::fire('orbit.promotion.postdeletepromotion.authz.notallowed', array($this, $user));
                $deletePromotionLang = Lang::get('validation.orbit.actionlist.delete_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deletePromotionLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.promotion.postdeletepromotion.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $promotion_id = OrbitInput::post('promotion_id');

            $validator = Validator::make(
                array(
                    'promotion_id' => $promotion_id,
                ),
                array(
                    'promotion_id' => 'required|numeric|orbit.empty.promotion',
                )
            );

            Event::fire('orbit.promotion.postdeletepromotion.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotion.postdeletepromotion.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deletepromotion = Promotion::excludeDeleted()->allowedForUser($user)->where('promotion_id', $promotion_id)->first();
            $deletepromotion->status = 'deleted';
            $deletepromotion->modified_by = $this->api->user->user_id;

            Event::fire('orbit.promotion.postdeletepromotion.before.save', array($this, $deletepromotion));

            // hard delete promotion-retailer.
            $deletepromotionretailers = PromotionRetailer::where('promotion_id', $deletepromotion->promotion_id)->get();
            foreach ($deletepromotionretailers as $deletepromotionretailer) {
                $deletepromotionretailer->delete();
            }

            $deletepromotion->save();

            Event::fire('orbit.promotion.postdeletepromotion.after.save', array($this, $deletepromotion));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.promotion');

            // Commit the changes
            $this->commit();

            Event::fire('orbit.promotion.postdeletepromotion.after.commit', array($this, $deletepromotion));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotion.postdeletepromotion.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotion.postdeletepromotion.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.promotion.postdeletepromotion.query.error', array($this, $e));

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
            Event::fire('orbit.promotion.postdeletepromotion.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.promotion.postdeletepromotion.before.render', array($this, $output));

        return $output;
    }

    /**
     * GET - Search Coupon
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: retailers, product, family.
     * @param string   `sortby`                (optional) - column order by
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `promotion_id`          (optional) - Coupon ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `promotion_name`        (optional) - Coupon name
     * @param string   `promotion_name_like`   (optional) - Coupon name like
     * @param string   `promotion_type`        (optional) - Coupon type. Valid value: product, cart.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string   `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param string   `coupon_notification`   (optional) - Coupon notification. Valid value: Y, N.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `rule_type`             (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage, cash_rebate_discount_by_value, cash_rebate_discount_by_percentage.
     * @param string   `rule_object_type`      (optional) - Rule object type. Valid value: product, family.
     * @param integer  `rule_object_id1`       (optional) - Rule object ID1 (product_id or category_id1).
     * @param integer  `rule_object_id2`       (optional) - Rule object ID2 (category_id2).
     * @param integer  `rule_object_id3`       (optional) - Rule object ID3 (category_id3).
     * @param integer  `rule_object_id4`       (optional) - Rule object ID4 (category_id4).
     * @param integer  `rule_object_id5`       (optional) - Rule object ID5 (category_id5).
     * @param string   `discount_object_type`  (optional) - Discount object type. Valid value: product, family, cash_rebate.
     * @param integer  `discount_object_id1`   (optional) - Discount object ID1 (product_id or category_id1).
     * @param integer  `discount_object_id2`   (optional) - Discount object ID2 (category_id2).
     * @param integer  `discount_object_id3`   (optional) - Discount object ID3 (category_id3).
     * @param integer  `discount_object_id4`   (optional) - Discount object ID4 (category_id4).
     * @param integer  `discount_object_id5`   (optional) - Discount object ID5 (category_id5).
     * @param integer  `issue_retailer_id`     (optional) - Issue Retailer IDs
     * @param integer  `redeem_retailer_id`    (optional) - Redeem Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchCoupon()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.getsearchcoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.getsearchcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.getsearchcoupon.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_coupon')) {
                Event::fire('orbit.coupon.getsearchcoupon.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.coupon.getsearchcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,promotion_name,promotion_type,description,begin_date,end_date,is_permanent,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.coupon_sortby'),
                )
            );

            Event::fire('orbit.coupon.getsearchcoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.coupon.getsearchcoupon.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            // Builder object
            $coupons = Coupon::with('couponrule')
                             ->excludeDeleted();

            // Filter coupon by Ids
            OrbitInput::get('promotion_id', function($promotionIds) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_id', $promotionIds);
            });

            // Filter coupon by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($coupons) {
                $coupons->whereIn('promotions.merchant_id', $merchantIds);
            });

            // Filter coupon by promotion name
            OrbitInput::get('promotion_name', function($promotionName) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_name', $promotionName);
            });

            // Filter coupon by matching promotion name pattern
            OrbitInput::get('promotion_name_like', function($promotionName) use ($coupons)
            {
                $coupons->where('promotions.promotion_name', 'like', "%$promotionName%");
            });

            // Filter coupon by promotion type
            OrbitInput::get('promotion_type', function($promotionTypes) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_type', $promotionTypes);
            });

            // Filter coupon by description
            OrbitInput::get('description', function($description) use ($coupons)
            {
                $coupons->whereIn('promotions.description', $description);
            });

            // Filter coupon by matching description pattern
            OrbitInput::get('description_like', function($description) use ($coupons)
            {
                $coupons->where('promotions.description', 'like', "%$description%");
            });

            // Filter coupon by begin date
            OrbitInput::get('begin_date', function($beginDate) use ($coupons)
            {
                $coupons->where('promotions.begin_date', '<=', $beginDate);
            });

            // Filter coupon by end date
            OrbitInput::get('end_date', function($endDate) use ($coupons)
            {
                $coupons->where('promotions.end_date', '>=', $endDate);
            });

            // Filter coupon by is permanent
            OrbitInput::get('is_permanent', function ($isPermanent) use ($coupons) {
                $coupons->whereIn('promotions.is_permanent', $isPermanent);
            });

            // Filter coupon by coupon notification
            OrbitInput::get('coupon_notification', function ($couponNotification) use ($coupons) {
                $coupons->whereIn('promotions.coupon_notification', $couponNotification);
            });

            // Filter coupon by status
            OrbitInput::get('status', function ($statuses) use ($coupons) {
                $coupons->whereIn('promotions.status', $statuses);
            });

            // Filter coupon rule by rule type
            OrbitInput::get('rule_type', function ($ruleTypes) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleTypes) {
                    $q->whereIn('rule_type', $ruleTypes);
                });
            });

             // Filter coupon rule by rule object type
            OrbitInput::get('rule_object_type', function ($ruleObjectTypes) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectTypes) {
                    $q->whereIn('rule_object_type', $ruleObjectTypes);
                });
            });

            // Filter coupon rule by rule object id1
            OrbitInput::get('rule_object_id1', function ($ruleObjectId1s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId1s) {
                    $q->whereIn('rule_object_id1', $ruleObjectId1s);
                });
            });

            // Filter coupon rule by rule object id2
            OrbitInput::get('rule_object_id2', function ($ruleObjectId2s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId2s) {
                    $q->whereIn('rule_object_id2', $ruleObjectId2s);
                });
            });

            // Filter coupon rule by rule object id3
            OrbitInput::get('rule_object_id3', function ($ruleObjectId3s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId3s) {
                    $q->whereIn('rule_object_id3', $ruleObjectId3s);
                });
            });

            // Filter coupon rule by rule object id4
            OrbitInput::get('rule_object_id4', function ($ruleObjectId4s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId4s) {
                    $q->whereIn('rule_object_id4', $ruleObjectId4s);
                });
            });

            // Filter coupon rule by rule object id5
            OrbitInput::get('rule_object_id5', function ($ruleObjectId5s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId5s) {
                    $q->whereIn('rule_object_id5', $ruleObjectId5s);
                });
            });

            // Filter coupon rule by discount object type
            OrbitInput::get('discount_object_type', function ($discountObjectTypes) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectTypes) {
                    $q->whereIn('discount_object_type', $discountObjectTypes);
                });
            });

            // Filter coupon rule by discount object id1
            OrbitInput::get('discount_object_id1', function ($discountObjectId1s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId1s) {
                    $q->whereIn('discount_object_id1', $discountObjectId1s);
                });
            });

            // Filter coupon rule by discount object id2
            OrbitInput::get('discount_object_id2', function ($discountObjectId2s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId2s) {
                    $q->whereIn('discount_object_id2', $discountObjectId2s);
                });
            });

            // Filter coupon rule by discount object id3
            OrbitInput::get('discount_object_id3', function ($discountObjectId3s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId3s) {
                    $q->whereIn('discount_object_id3', $discountObjectId3s);
                });
            });

            // Filter coupon rule by discount object id4
            OrbitInput::get('discount_object_id4', function ($discountObjectId4s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId4s) {
                    $q->whereIn('discount_object_id4', $discountObjectId4s);
                });
            });

            // Filter coupon rule by discount object id5
            OrbitInput::get('discount_object_id5', function ($discountObjectId5s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId5s) {
                    $q->whereIn('discount_object_id5', $discountObjectId5s);
                });
            });

            // Filter coupon by issue retailer id
            OrbitInput::get('issue_retailer_id', function ($issueRetailerIds) use ($coupons) {
                $coupons->whereHas('issueretailers', function($q) use ($issueRetailerIds) {
                    $q->whereIn('retailer_id', $issueRetailerIds);
                });
            });

            // Filter coupon by redeem retailer id
            OrbitInput::get('redeem_retailer_id', function ($redeemRetailerIds) use ($coupons) {
                $coupons->whereHas('redeemretailers', function($q) use ($redeemRetailerIds) {
                    $q->whereIn('retailer_id', $redeemRetailerIds);
                });
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($coupons) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'retailers') {
                        $coupons->with('issueretailers', 'redeemretailers');
                    } elseif ($relation === 'product') {
                        $coupons->with('couponrule.ruleproduct', 'couponrule.discountproduct');
                    } elseif ($relation === 'family') {
                        $coupons->with('couponrule.rulecategory1', 'couponrule.rulecategory2', 'couponrule.rulecategory3', 'couponrule.rulecategory4', 'couponrule.rulecategory5', 'couponrule.discountcategory1', 'couponrule.discountcategory2', 'couponrule.discountcategory3', 'couponrule.discountcategory4', 'couponrule.discountcategory5');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupons = clone $coupons;

            // Get the take args
            if (trim(OrbitInput::get('take')) === '') {
                $take = $maxRecord;
            } else {
                OrbitInput::get('take', function($_take) use (&$take, $maxRecord)
                {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                });
            }
            if ($take > 0) {
                $coupons->take($take);
            }

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $coupons)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $coupons->skip($skip);
            }

            // Default sort by
            $sortBy = 'promotions.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'promotions.created_at',
                    'promotion_name'    => 'promotions.promotion_name',
                    'promotion_type'    => 'promotions.promotion_type',
                    'description'       => 'promotions.description',
                    'begin_date'        => 'promotions.begin_date',
                    'end_date'          => 'promotions.end_date',
                    'is_permanent'      => 'promotions.is_permanent',
                    'status'            => 'promotions.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $coupons->orderBy($sortBy, $sortMode);

            $totalCoupons = $_coupons->count();
            $listOfCoupons = $coupons->get();

            $data = new stdclass();
            $data->total_records = $totalCoupons;
            $data->returned_records = count($listOfCoupons);
            $data->records = $listOfCoupons;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.query.error', array($this, $e));

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
            Event::fire('orbit.coupon.getsearchcoupon.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.coupon.getsearchcoupon.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of coupon id
        Validator::extend('orbit.empty.coupon', function ($attribute, $value, $parameters) {
            $coupon = Coupon::excludeDeleted()
                        ->where('promotion_id', $value)
                        ->first();

            if (empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.empty.coupon', $coupon);

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

        // Check coupon name, it should not exists
        Validator::extend('orbit.exists.coupon_name', function ($attribute, $value, $parameters) {
            $couponName = Coupon::excludeDeleted()
                        ->where('promotion_name', $value)
                        ->first();

            if (! empty($couponName)) {
                return FALSE;
            }

            App::instance('orbit.validation.coupon_name', $couponName);

            return TRUE;
        });

        // Check coupon name, it should not exists (for update)
        Validator::extend('coupon_name_exists_but_me', function ($attribute, $value, $parameters) {
            $coupon_id = trim(OrbitInput::post('coupon_id'));
            $coupon = Coupon::excludeDeleted()
                        ->where('promotion_name', $value)
                        ->where('promotion_id', '!=', $coupon_id)
                        ->first();

            if (! empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.validation.coupon', $coupon);

            return TRUE;
        });

        // Check the existence of the coupon status
        Validator::extend('orbit.empty.coupon_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the coupon type
        Validator::extend('orbit.empty.coupon_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $couponTypes = array('product', 'cart');
            foreach ($couponTypes as $couponType) {
                if($value === $couponType) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the rule type
        Validator::extend('orbit.empty.rule_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $ruletypes = array('cart_discount_by_value', 'cart_discount_by_percentage', 'new_product_price', 'product_discount_by_value', 'product_discount_by_percentage', 'cash_rebate_discount_by_value', 'cash_rebate_discount_by_percentage');
            foreach ($ruletypes as $ruletype) {
                if($value === $ruletype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the discount object type
        Validator::extend('orbit.empty.discount_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $discountobjecttypes = array('product', 'family');
            foreach ($discountobjecttypes as $discountobjecttype) {
                if($value === $discountobjecttype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existance of discount_object_id1
        Validator::extend('orbit.empty.discount_object_id1', function ($attribute, $value, $parameters) {
            $discountobjecttype = trim(OrbitInput::post('discount_object_type'));
            if ($discountobjecttype === 'product') {
                $discount_object_id1 = Product::excludeDeleted()
                        ->where('product_id', $value)
                        ->first();
            } elseif ($discountobjecttype === 'family') {
                $discount_object_id1 = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();
            }

            if (empty($discount_object_id1)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id1', $discount_object_id1);

            return TRUE;
        });

        // Check the existance of discount_object_id2
        Validator::extend('orbit.empty.discount_object_id2', function ($attribute, $value, $parameters) {
            $discount_object_id2 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id2)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id2', $discount_object_id2);

            return TRUE;
        });

        // Check the existance of discount_object_id3
        Validator::extend('orbit.empty.discount_object_id3', function ($attribute, $value, $parameters) {
            $discount_object_id3 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id3)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id3', $discount_object_id3);

            return TRUE;
        });

        // Check the existance of discount_object_id4
        Validator::extend('orbit.empty.discount_object_id4', function ($attribute, $value, $parameters) {
            $discount_object_id4 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id4)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id4', $discount_object_id4);

            return TRUE;
        });

        // Check the existance of discount_object_id5
        Validator::extend('orbit.empty.discount_object_id5', function ($attribute, $value, $parameters) {
            $discount_object_id5 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id5)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id5', $discount_object_id5);

            return TRUE;
        });

        // Check the existance of retailer id
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) {
            $retailer = Retailer::excludeDeleted()->allowedForUser($this->api->user)
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