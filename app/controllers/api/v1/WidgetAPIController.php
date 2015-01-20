<?php
/**
 * An API controller for managing widget.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;

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

            $validator = Validator::make(
                array(
                    'widget_type'           => $widgetType,
                    'object_id'             => $widgetObjectId,
                    'merchant_id'           => $merchantId,
                    'retailer_ids'          => $retailerIds,
                    'slogan'                => $slogan,
                    'animation'             => $animation,
                    'widget_order'          => $widgetOrder
                ),
                array(
                    'widget_type'           => 'required|in:catalogue,new_product,promotion,coupon',
                    'object_id'             => 'required|numeric',
                    'merchant_id'           => 'required|numeric|orbit.empty.merchant',
                    'slogan'                => 'required',
                    'animation'             => 'required|in:none,horizontal,vertical',
                    'widget_order'          => 'required|numeric',
                    'retailer_ids'          => 'array|orbit.empty.retailer',
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

            Event::fire('orbit.widget.postnewwidget.after.save', array($this, $widget));
            $this->response->data = $widget;

            // Commit the changes
            $this->commit();

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
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.postnewwidget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
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
        } catch (Exception $e) {
            Event::fire('orbit.widget.postnewwidget.general.exception', array($this, $e));

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
    }
}
