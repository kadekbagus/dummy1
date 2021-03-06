<?php
/**
 * An API controller for managing widget.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;

class WidgetAPIController extends ControllerAPI
{
    /**
     * POST - Create new widget
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `type`                  (required) - Widget type, 'catalogue', 'new_product', 'promotion', 'coupon'
     * @param integer   `object_id`             (required) - The object ID
     * @param integer   `merchant_id`           (required) - Merchant ID
     * @param integer   `retailer_ids`          (required) - Retailer IDs
     * @param string    `animation`             (required) - Animation type, 'none', 'horizontal', 'vertical'
     * @param string    `slogan`                (required) - Widget slogan
     * @param integer   `widget_order`          (required) - Order of the widget
     * @param array     `images`                (optional)
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewWidget()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.postnewwidget.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.postnewwidget.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.postnewwidget.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_widget')) {
                Event::fire('orbit.widget.postnewwidget.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.add_new_widget');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.postnewwidget.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $widgetType = OrbitInput::post('widget_type');
            $widgetObjectId = OrbitInput::post('object_id');
            $merchantId = OrbitInput::post('merchant_id');
            $retailerIds = OrbitInput::post('retailer_ids');
            $slogan = OrbitInput::post('slogan');
            $animation = OrbitInput::post('animation');
            $widgetOrder = OrbitInput::post('widget_order');
            $images = OrbitInput::files('images');

            $validator = Validator::make(
                array(
                    'object_id'             => $widgetObjectId,
                    'merchant_id'           => $merchantId,
                    'widget_type'           => $widgetType,
                    'retailer_ids'          => $retailerIds,
                    'slogan'                => $slogan,
                    'animation'             => $animation,
                    'widget_order'          => $widgetOrder,
                    'images'                => $images
                ),
                array(
                    'object_id'             => 'required',
                    'merchant_id'           => 'required|orbit.empty.merchant',
                    'widget_type'           => 'required|in:catalogue,new_product,promotion,coupon|orbit.exists.widget_type:' . $merchantId,
                    'slogan'                => 'required',
                    'animation'             => 'required|in:none,horizontal,vertical',
                    'widget_order'          => 'required|numeric',
                    'images'                => 'required_if:animation,none',
                    'retailer_ids'          => 'array|orbit.empty.retailer'
                ),
                array(
                    'orbit.exists.widget_type' => Lang::get('validation.orbit.exists.widget_type'),
                )
            );

            Event::fire('orbit.widget.postnewwidget.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.widget.postnewwidget.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $widget = new Widget();
            $widget->widget_type = $widgetType;
            $widget->widget_object_id = $widgetObjectId;
            $widget->widget_slogan = $slogan;
            $widget->widget_order = $widgetOrder;
            $widget->merchant_id = $merchantId;
            $widget->animation = $animation;
            $widget->status = 'active';
            $widget->created_by = $user->user_id;

            Event::fire('orbit.widget.postnewwidget.before.save', array($this, $widget));

            $widget->save();

            // Insert attribute values if specified by the caller
            OrbitInput::post('retailer_ids', function($retailerIds) use ($widget) {
                $widget->retailers()->sync($retailerIds);
            });

            // If widget is empty then it should be applied to all retailers
            if (empty(OrbitInput::post('retailer_ids', NULL))) {
                $merchant = App::make('orbit.empty.merchant');
                $listOfRetailerIds = $merchant->getMyRetailerIds();
                $widget->retailers()->sync($listOfRetailerIds);
            }

            Event::fire('orbit.widget.postnewwidget.after.save', array($this, $widget));
            $this->response->data = $widget;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Widget Created: %s', $widget->widget_slogan);
            $activity->setUser($user)
                    ->setActivityName('create_widget')
                    ->setActivityNameLong('Create Widget OK')
                    ->setObject($widget)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.widget.postnewwidget.after.commit', array($this, $widget));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.postnewwidget.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_widget')
                    ->setActivityNameLong('Create Widget Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.postnewwidget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_widget')
                    ->setActivityNameLong('Create Widget Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.widget.postnewwidget.query.error', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_widget')
                    ->setActivityNameLong('Create Widget Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.widget.postnewwidget.general.exception', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_widget')
                    ->setActivityNameLong('Create Widget Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update widget
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `wiget_id`              (required) - The Widget ID
     * @param string    `type`                  (optional) - Widget type, 'catalogue', 'new_product', 'promotion', 'coupon'
     * @param integer   `object_id`             (optional) - The object ID
     * @param integer   `merchant_id`           (optional) - Merchant ID
     * @param integer   `retailer_ids`          (optional) - Retailer IDs
     * @param string    `animation`             (optional) - Animation type, 'none', 'horizontal', 'vertical'
     * @param string    `slogan`                (optional) - Widget slogan
     * @param integer   `widget_order`          (optional) - Order of the widget
     * @param array     `images`                (optional)
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateWidget()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.postupdatewidget.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.postupdatewidget.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.postupdatewidget.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_widget')) {
                Event::fire('orbit.widget.postupdatewidget.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.update_widget');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.postupdatewidget.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $widgetId = OrbitInput::post('widget_id');
            $widgetType = OrbitInput::post('widget_type');
            $widgetObjectId = OrbitInput::post('object_id');
            $merchantId = OrbitInput::post('merchant_id');
            $retailerIds = OrbitInput::post('retailer_ids');
            $slogan = OrbitInput::post('slogan');
            $animation = OrbitInput::post('animation');
            $widgetOrder = OrbitInput::post('widget_order');
            $images = OrbitInput::files('images');

            $validator = Validator::make(
                array(
                    'widget_id'             => $widgetId,
                    'object_id'             => $widgetObjectId,
                    'merchant_id'           => $merchantId,
                    'widget_type'           => $widgetType,
                    'retailer_ids'          => $retailerIds,
                    'slogan'                => $slogan,
                    'animation'             => $animation,
                    'widget_order'          => $widgetOrder,
                    'images'                => $images
                ),
                array(
                    'widget_id'             => 'required|orbit.empty.widget',
                    'object_id'             => '',
                    'merchant_id'           => 'orbit.empty.merchant',
                    'widget_type'           => ['required', 'in:catalogue,new_product,promotion,coupon', ['orbit.exists.widget_type_but_me', $merchantId, $widgetId]],
                    'animation'             => 'in:none,horizontal,vertical',
                    'images'                => 'required_if:animation,none',
                    'widget_order'          => 'numeric',
                    'retailer_ids'          => 'array|orbit.empty.retailer',
                ),
                array(
                    'orbit.exists.widget_type_but_me' => Lang::get('validation.orbit.exists.widget_type'),
                )
            );

            Event::fire('orbit.widget.postupdatewidget.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.widget.postupdatewidget.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $widget = App::make('orbit.empty.widget');

            OrbitInput::post('widget_type', function($type) use ($widget) {
                $widget->widget_type = $type;
            });

            OrbitInput::post('object_id', function($objectId) use ($widget) {
                $widget->widget_object_id = $objectId;
            });

            OrbitInput::post('merchant_id', function($merchantId) use ($widget) {
                $widget->merchant_id = $merchantId;
            });

            OrbitInput::post('slogan', function($slogan) use ($widget) {
                $widget->widget_slogan = $slogan;
            });

            OrbitInput::post('widget_order', function($order) use ($widget) {
                $widget->widget_order = $order;
            });

            OrbitInput::post('animation', function($animation) use ($widget) {
                $widget->animation = $animation;
            });

            Event::fire('orbit.widget.postupdatewidget.before.save', array($this, $widget));

            $widget->modified_by = $user->user_id;
            $widget->save();

            // Insert attribute values if specified by the caller
            OrbitInput::post('retailer_ids', function($retailerIds) use ($widget) {
                $widget->retailers()->sync($retailerIds);
            });

            // If widget is empty then it should be applied to all retailers
            if (empty(OrbitInput::post('retailer_ids', NULL))) {
                $merchant = App::make('orbit.empty.merchant');
                $listOfRetailerIds = $merchant->getMyRetailerIds();
                $widget->retailers()->sync($listOfRetailerIds);
            }

            Event::fire('orbit.widget.postupdatewidget.after.save', array($this, $widget));
            $this->response->data = $widget;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Widget updated: %s', $widget->widget_slogan);
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget OK')
                    ->setObject($widget)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.widget.postupdatewidget.after.commit', array($this, $widget));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.postupdatewidget.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.postupdatewidget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.widget.postupdatewidget.query.error', array($this, $e));

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

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.widget.postupdatewidget.general.exception', array($this, $e));

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

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete widget
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `wiget_id`              (required) - The Widget ID
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteWidget()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.postdeletewiget.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.postdeletewiget.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.postdeletewiget.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_widget')) {
                Event::fire('orbit.widget.postdeletewiget.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.delete_widget');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.postdeletewiget.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $widgetId = OrbitInput::post('widget_id');
            $validator = Validator::make(
                array(
                    'widget_id'             => $widgetId,
                ),
                array(
                    'widget_id'             => 'required|orbit.empty.widget',
                )
            );

            Event::fire('orbit.widget.postdeletewiget.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.widget.postdeletewiget.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $widget = App::make('orbit.empty.widget');
            $widget->status = 'deleted';
            $widget->modified_by = $user->user_id;
            $widget->save();

            Event::fire('orbit.widget.postdeletewiget.after.save', array($this, $widget));
            $this->response->data = $widget;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Widget Deleted: %s', $widget->widget_slogan);
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget OK')
                    ->setObject($widget)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.widget.postdeletewiget.after.commit', array($this, $widget));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.postdeletewiget.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.postdeletewiget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.widget.postdeletewiget.query.error', array($this, $e));

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

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.widget.postdeletewiget.general.exception', array($this, $e));

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

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * GET - List of Widgets.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array         `widget_ids`            (optional) - List of widget IDs
     * @param array         `widget_type`           (optional) - Type of the widget, e.g: 'catalogue', 'new_product', 'promotion', 'coupon'
     * @param array         `merchant_ids`          (optional) - List of Merchant IDs
     * @param array         `retailer_ids`          (optional) - List of Retailer IDs
     * @param array         `animations`            (optional) - Filter by animation
     * @param array         `types`                 (optional) - Filter by widget types
     * @param array         `with`                  (optional) - relationship included
     * @param integer       `take`                  (optional) - limit
     * @param integer       `skip`                  (optional) - limit offset
     * @param string        `sort_by`               (optional) - column order by
     * @param string        `sort_mode`             (optional) - asc or desc
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchWidget()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.getwidget.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.getwidget.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.getwidget.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_widget')) {
                Event::fire('orbit.widget.getwidget.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_widget');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.getwidget.after.authz', array($this, $user));

            $validator = Validator::make(
                array(
                    'widget_ids'    => OrbitInput::get('widget_ids'),
                    'merchant_ids'  => OrbitInput::get('merchant_ids'),
                    'retailer_ids'  => OrbitInput::get('retailer_ids'),
                    'animations'    => OrbitInput::get('animations'),
                    'types'         => OrbitInput::get('types')
                ),
                array(
                    'widget_ids'    => 'array|min:1',
                    'merchant_ids'  => 'array|min:1',
                    'retailer_ids'  => 'array|min:1',
                    'animations'    => 'array|min:1',
                    'types'         => 'array|min:1'
                )
            );

            Event::fire('orbit.widget.postdeletewiget.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.widget.postdeletewiget.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.widget.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.widget.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Available merchant to query
            $listOfMerchantIds = [];

            // Available retailer to query
            $listOfRetailerIds = [];

            // Builder object
            $widgets = Widget::joinRetailer()
                            ->excludeDeleted('widgets');

            // Include other relationship
            OrbitInput::get('with', function($with) use ($widgets) {
                $widgets->with($with);
            });

            // Filter by ids
            OrbitInput::get('widget_ids', function($widgetIds) use ($widgets) {
                $widgets->whereIn('widgets.widget_id', $widgetIds);
            });

            // Filter by merchant ids
            OrbitInput::get('merchant_ids', function($merchantIds) use ($widgets) {
                $listOfMerchantIds = (array)$merchantIds;
            });

            // Filter by retailer ids
            OrbitInput::get('retailer_ids', function($retailerIds) use ($widgets) {
                $listOfRetailerIds = (array)$retailerIds;
            });

            // Filter by animation
            OrbitInput::get('animations', function($animation) use ($widgets) {
                $widgets->whereIn('widgets.animation', $animation);
            });

            // Filter by widget type
            OrbitInput::get('types', function($types) use ($widgets) {
                $widgets->whereIn('widgets.widget_type', $types);
            });

            // @To do: Replace this hacks
            if (! $user->isSuperAdmin()) {
                $listOfMerchantIds = $user->getMyMerchantIds();

                if (empty($listOfMerchantIds)) {
                    $listOfMerchantIds = [-1];
                }
                $widgets->whereIn('widgets.merchant_id', $listOfMerchantIds);
            } else {
                if (! empty($listOfMerchantIds)) {
                    $widgets->whereIn('widgets.merchant_id', $listOfMerchantIds);
                }
            }

            // @To do: Replace this hacks
            if (! $user->isSuperAdmin()) {
                $listOfRetailerIds = $user->getMyRetailerIds();

                if (empty($listOfRetailerIds)) {
                    $listOfRetailerIds = [-1];
                }
                $widgets->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
            } else {
                if (! empty($listOfRetailerIds)) {
                    $widgets->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
                }
            }

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_widgets = clone $widgets;

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $widgets->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $widgets) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $widgets->skip($skip);

            // Default sort by
            $sortBy = 'widgets.widget_order';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'widget_order'  => 'widgets.widget_order',
                    'id'            => 'widgets.widget_id',
                    'created'       => 'widgets.created_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $widgets->orderBy($sortBy, $sortMode);

            $totalWidgets = RecordCounter::create($_widgets)->count();
            $listOfWidgets = $widgets->get();

            $data = new stdclass();
            $data->total_records = $totalWidgets;
            $data->returned_records = count($listOfWidgets);
            $data->records = $listOfWidgets;

            if ($totalWidgets === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.widget');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.getwidget.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.getwidget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.widget.getwidget.query.error', array($this, $e));

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
            Event::fire('orbit.widget.getwidget.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.widget.getwidget.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of widget id
        $user = $this->api->user;
        Validator::extend('orbit.empty.widget', function ($attribute, $value, $parameters) use ($user) {
            $widget = Widget::excludeDeleted()
                        ->where('widget_id', $value)
                        ->first();

            if (empty($widget)) {
                return FALSE;
            }

            App::instance('orbit.empty.widget', $widget);

            return TRUE;
        });

        // Check the existance of merchant id
        $user = $this->api->user;
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) use ($user) {
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

        // Check the existstance of each retailer ids
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) use ($user) {
            $expectedNumber = count($value);
            $merchant = App::make('orbit.empty.merchant');
            $retailerNumber = Retailer::excludeDeleted()
                        ->allowedForUser($user)
                        ->whereIn('merchant_id', $value)
                        ->where('parent_id', $merchant->merchant_id)
                        ->count();

            if ($expectedNumber !== $retailerNumber) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existstance of each widget type
        Validator::extend('orbit.exists.widget_type', function ($attribute, $value, $parameters) use ($user) {
            // Available retailer to query
            $listOfRetailerIds = [];
            $user = $this->api->user;

            $widget = Widget::joinRetailer()
                        ->excludeDeleted()
                        ->where('widgets.widget_type', $value)
                        ->where('widgets.merchant_id', $parameters[0]);

            // @To do: Replace this hacks
            if (! $user->isSuperAdmin()) {
                $listOfRetailerIds = $user->getMyRetailerIds();

                if (empty($listOfRetailerIds)) {
                    $listOfRetailerIds = [-1];
                }
                $widget->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
            } else {
                if (! empty($listOfRetailerIds)) {
                    $widget->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
                }
            }

            $widget = $widget->first();

            if (!empty($widget)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existstance of each widget type on update
        Validator::extend('orbit.exists.widget_type_but_me', function ($attribute, $value, $parameters) use ($user) {
            // Available retailer to query
            $listOfRetailerIds = [];
            $user = $this->api->user;

            $widget = Widget::joinRetailer()
                        ->excludeDeleted()
                        ->where('widgets.widget_type', $value)
                        ->where('widgets.merchant_id', $parameters[0])
                        ->where('widgets.widget_id', '!=', $parameters[1]);

            // @To do: Replace this hacks
            if (! $user->isSuperAdmin()) {
                $listOfRetailerIds = $user->getMyRetailerIds();

                if (empty($listOfRetailerIds)) {
                    $listOfRetailerIds = [-1];
                }
                $widget->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
            } else {
                if (! empty($listOfRetailerIds)) {
                    $widget->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
                }
            }

            $widget = $widget->first();

            if (!empty($widget)) {
                return FALSE;
            }

            return TRUE;
        });
    }
}
