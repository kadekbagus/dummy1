<?php
/**
 * An API controller for managing Promotion.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class PromotionAPIController extends ControllerAPI
{
    /**
     * POST - Create New Promotion
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`           (required) - Merchant ID
     * @param string     `promotion_name`         (required) - Promotion name
     * @param integer    `promotion_level`        (required) - Promotion Level
     * @param integer    `promotion_order`        (optional) - Promotion order
     * @param string     `description`           (optional) - Description
     * @param string     `status`                (optional) - Status
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

            if (! ACL::create($user)->isAllowed('create_promotion')) {
                Event::fire('orbit.promotion.postnewpromotion.authz.notallowed', array($this, $user));
                $createPromotionLang = Lang::get('validation.orbit.actionlist.new_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createPromotionLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.promotion.postnewpromotion.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');
            $promotion_name = OrbitInput::post('promotion_name');
            $promotion_level = OrbitInput::post('promotion_level');
            $promotion_order = OrbitInput::post('promotion_order');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status');
            
            $validator = Validator::make(
                array(
                    'merchant_id'    => $merchant_id,
                    'promotion_name'  => $promotion_name,
                    'promotion_level' => $promotion_level,
                    'status'         => $status,
                ),
                array(
                    'merchant_id'    => 'required|numeric|orbit.empty.merchant',
                    'promotion_name'  => 'required|orbit.exists.promotion_name',
                    'promotion_level' => 'required|numeric|between:1,5',
                    'status'         => 'required|orbit.empty.promotion_status',
                )
            );

            Event::fire('orbit.promotion.postnewpromotion.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Begin database transaction
            $this->beginTransaction();

            $newpromotion = new Promotion();
            $newpromotion->merchant_id = $merchant_id;
            $newpromotion->promotion_name = $promotion_name;
            $newpromotion->promotion_level = $promotion_level;
            $newpromotion->promotion_order = $promotion_order;
            $newpromotion->description = $description;
            $newpromotion->status = $status;
            $newpromotion->created_by = $this->api->user->user_id;
            $newpromotion->modified_by = $this->api->user->user_id;

            Event::fire('orbit.promotion.postnewpromotion.before.save', array($this, $newpromotion));

            $newpromotion->save();

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
     * @param integer    `promotion_id`           (required) - Promotion ID
     * @param integer    `merchant_id`           (optional) - Merchant ID
     * @param string     `promotion_name`         (optional) - Promotion name
     * @param integer    `promotion_level`        (optional) - Promotion level
     * @param integer    `promotion_order`        (optional) - Promotion order
     * @param string     `description`           (optional) - Description
     * @param string     `status`                (optional) - Status
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
            $promotion_level = OrbitInput::post('promotion_level');
            $promotion_order = OrbitInput::post('promotion_order');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status');

            $validator = Validator::make(
                array(
                    'promotion_id'       => $promotion_id,
                    'merchant_id'       => $merchant_id,
                    'promotion_name'     => $promotion_name,
                    'promotion_level'    => $promotion_level,
                    'status'            => $status,
                ),
                array(
                    'promotion_id'       => 'required|numeric|orbit.empty.promotion',
                    'merchant_id'       => 'numeric|orbit.empty.merchant',
                    'promotion_name'     => 'promotion_name_exists_but_me',
                    'promotion_level'    => 'numeric|between:1,5',
                    'status'            => 'orbit.empty.promotion_status',
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

            $updatedpromotion = Promotion::excludeDeleted()->allowedForUser($user)->where('promotion_id', $promotion_id)->first();

            OrbitInput::post('merchant_id', function($merchant_id) use ($updatedpromotion) {
                $updatedpromotion->merchant_id = $merchant_id;
            });

            OrbitInput::post('promotion_name', function($promotion_name) use ($updatedpromotion) {
                $updatedpromotion->promotion_name = $promotion_name;
            });

            OrbitInput::post('promotion_level', function($promotion_level) use ($updatedpromotion) {
                $updatedpromotion->promotion_level = $promotion_level;
            });

            OrbitInput::post('promotion_order', function($promotion_order) use ($updatedpromotion) {
                $updatedpromotion->promotion_order = $promotion_order;
            });
            
            OrbitInput::post('description', function($description) use ($updatedpromotion) {
                $updatedpromotion->description = $description;
            });

            OrbitInput::post('status', function($status) use ($updatedpromotion) {
                $updatedpromotion->status = $status;
            });

            $updatedpromotion->modified_by = $this->api->user->user_id;

            Event::fire('orbit.promotion.postupdatepromotion.before.save', array($this, $updatedpromotion));

            $updatedpromotion->save();

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
                    'promotion_id' => 'required|numeric|orbit.empty.promotion|orbit.exists.have_product_category',
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
     * GET - Search Promotion
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sort_by`               (optional) - column order by
     * @param string   `sort_mode`             (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `promotion_id`          (optional) - Promotion ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `promotion_name`        (optional) - Promotion name
     * @param string   `promotion_name_like`   (optional) - Promotion name like
     * @param string   `promotion_type`        (optional) - Promotion type
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date
     * @param datetime `end_date`              (optional) - End date
     * @param string   `is_permanent`          (optional) - Is permanent
     * @param string   `status`                (optional) - Status
     * @param string   `rule_type`             (optional) - Rule type
     * @param string   `discount_object_type`  (optional) - Discount object type
     * @param integer  `discount_object_id1`   (optional) - Discount object ID1
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchPromotion()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.promotion.getsearchpromotion.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotion.getsearchpromotion.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotion.getsearchpromotion.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_promotion')) {
                Event::fire('orbit.promotion.getsearchpromotion.authz.notallowed', array($this, $user));
                $viewPromotionLang = Lang::get('validation.orbit.actionlist.view_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewPromotionLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.promotion.getsearchpromotion.after.authz', array($this, $user));

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
                    'in' => Lang::get('validation.orbit.empty.promotion_sortby'),
                )
            );

            Event::fire('orbit.promotion.getsearchpromotion.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotion.getsearchpromotion.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 1;
            }

            // Builder object
            $promotions = Promotion::with('promotionrule', 'retailers', 'promotionrule.discountproduct', 'promotionrule.discountfamily1', 'promotionrule.discountfamily2', 'promotionrule.discountfamily3', 'promotionrule.discountfamily4', 'promotionrule.discountfamily5')
                ->excludeDeleted()
                ->allowedForUser($user);

            // Filter promotion by Ids
            OrbitInput::get('promotion_id', function($promotionIds) use ($promotions)
            {
                $promotions->whereIn('promotions.promotion_id', $promotionIds);
            });

            // Filter promotion by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($promotions) {
                $promotions->whereIn('promotions.merchant_id', $merchantIds);
            });

            // Filter promotion by promotion name
            OrbitInput::get('promotion_name', function($promotionname) use ($promotions)
            {
                $promotions->whereIn('promotions.promotion_name', $promotionname);
            });

            // Filter promotion by matching promotion name pattern
            OrbitInput::get('promotion_name_like', function($promotionname) use ($promotions)
            {
                $promotions->where('promotions.promotion_name', 'like', "%$promotionname%");
            });

            // Filter promotion by promotion type
            OrbitInput::get('promotion_type', function($promotionTypes) use ($promotions)
            {
                $promotions->whereIn('promotions.promotion_type', $promotionTypes);
            });

            // Filter promotion by description
            OrbitInput::get('description', function($description) use ($promotions)
            {
                $promotions->whereIn('promotions.description', $description);
            });

            // Filter promotion by matching description pattern
            OrbitInput::get('description_like', function($description) use ($promotions)
            {
                $promotions->where('promotions.description', 'like', "%$description%");
            });

            // Filter promotion by begin date
            OrbitInput::get('begin_date', function($begindate) use ($promotions)
            {
                $promotions->where('promotions.begin_date', '>=', $begindate);
            });

            // Filter promotion by end date
            OrbitInput::get('end_date', function($enddate) use ($promotions)
            {
                $promotions->where('promotions.end_date', '<=', $enddate);
            });

            // Filter promotion by is permanent
            OrbitInput::get('is_permanent', function ($ispermanent) use ($promotions) {
                $promotions->whereIn('promotions.is_permanent', $ispermanent);
            });

            // Filter promotion by status
            OrbitInput::get('status', function ($statuses) use ($promotions) {
                $promotions->whereIn('promotions.status', $statuses);
            });

            // Filter promotion rule by rule type
            OrbitInput::get('rule_type', function ($ruleTypes) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($ruleTypes) {
                    $q->whereIn('rule_type', $ruleTypes);
                });
            });

            // Filter promotion rule by discount object type
            OrbitInput::get('discount_object_type', function ($discountObjectTypes) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectTypes) {
                    $q->whereIn('discount_object_type', $discountObjectTypes);
                });
            });

            // Filter promotion rule by discount object id1
            OrbitInput::get('discount_object_id1', function ($discountObjectId1s) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectId1s) {
                    $q->whereIn('discount_object_id1', $discountObjectId1s);
                });
            });

            // Filter promotion rule by discount object id2
            OrbitInput::get('discount_object_id2', function ($discountObjectId2s) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectId2s) {
                    $q->whereIn('discount_object_id2', $discountObjectId2s);
                });
            });

            // Filter promotion rule by discount object id3
            OrbitInput::get('discount_object_id3', function ($discountObjectId3s) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectId3s) {
                    $q->whereIn('discount_object_id3', $discountObjectId3s);
                });
            });

            // Filter promotion rule by discount object id4
            OrbitInput::get('discount_object_id4', function ($discountObjectId4s) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectId4s) {
                    $q->whereIn('discount_object_id4', $discountObjectId4s);
                });
            });

            // Filter promotion rule by discount object id5
            OrbitInput::get('discount_object_id5', function ($discountObjectId5s) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectId5s) {
                    $q->whereIn('discount_object_id5', $discountObjectId5s);
                });
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_promotions = clone $promotions;

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
                $promotions->take($take);
            }

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $promotions)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $promotions->skip($skip);
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
            $promotions->orderBy($sortBy, $sortMode);

            $totalPromotions = $_promotions->count();
            $listOfPromotions = $promotions->get();

            $data = new stdclass();
            $data->total_records = $totalPromotions;
            $data->returned_records = count($listOfPromotions);
            $data->records = $listOfPromotions;

            if ($totalPromotions === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.promotion');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotion.getsearchpromotion.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotion.getsearchpromotion.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.promotion.getsearchpromotion.query.error', array($this, $e));

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
            Event::fire('orbit.promotion.getsearchpromotion.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.promotion.getsearchpromotion.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of promotion id
        Validator::extend('orbit.empty.promotion', function ($attribute, $value, $parameters) {
            $promotion = Promotion::excludeDeleted()
                        ->where('promotion_id', $value)
                        ->first();

            if (empty($promotion)) {
                return FALSE;
            }

            App::instance('orbit.empty.promotion', $promotion);

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

        // Check promotion name, it should not exists
        Validator::extend('orbit.exists.promotion_name', function ($attribute, $value, $parameters) {
            $promotionName = Promotion::excludeDeleted()
                        ->where('promotion_name', $value)
                        ->first();

            if (! empty($promotionName)) {
                return FALSE;
            }

            App::instance('orbit.validation.promotion_name', $promotionName);

            return TRUE;
        });

        // Check promotion name, it should not exists (for update)
        Validator::extend('promotion_name_exists_but_me', function ($attribute, $value, $parameters) {
            $promotion_id = trim(OrbitInput::post('promotion_id'));
            $promotion = Promotion::excludeDeleted()
                        ->where('promotion_name', $value)
                        ->where('promotion_id', '!=', $promotion_id)
                        ->first();

            if (! empty($promotion)) {
                return FALSE;
            }

            App::instance('orbit.validation.promotion', $promotion);

            return TRUE;
        });

        // Check if promotion have linked to product
        Validator::extend('orbit.exists.have_product_category', function ($attribute, $value, $parameters) {
            $productcategory = ProductCategory::where('promotion_id', $value)
                        ->first();

            if (! empty($productcategory)) {
                return FALSE;
            }

            App::instance('orbit.exists.have_product_category', $productcategory);

            return TRUE;
        });

        // Check the existence of the promotion status
        Validator::extend('orbit.empty.promotion_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

    }
}