<?php
/**
 * An API controller for managing history which happens on Orbit.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;

class ActivityAPIController extends ControllerAPI
{
    /**
     * GET - List of Activities history
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array     `activity_names[]`      (optional) - Activity name
     * @param array     `activity_name_longs[]` (optional) - Activity name (friendly name)
     * @param array     `user_ids[]`            (optional) - IDs of the user
     * @param array     `user_emails[]`         (optional) - Emails of the user
     * @param array     `groups[]`              (optional) - Name of the group, e.g: 'portal', 'mobile-ci', 'pos'
     * @param array     `role_ids[]`            (optional) - IDs of user role
     * @param array     `object_ids[]`          (optional) - IDs of the object, could be the IDs of promotion, coupon, etc
     * @param array     `object_names[]`        (optional) - Name of the object, could be 'promotion', 'coupon', etc
     * @param array     `location_ids[]`        (optional) - IDs of retailer
     * @param array     `ip_address[]`          (optional) - List of IP Address
     * @param string    `ip_address_like`       (optional) - Pattern of the IP address. e.g: '192.168' or '220.'
     * @param string    `user_agent_like`       (optional) - User agent like
     * @param array     `staff_ids`             (optional) - User IDs of Cashier
     * @param array     `response_statuses`     (optional) - Response status, e.g: 'OK' or 'Failed'
     * @param string    `sort_by`               (optional) - column order by
     * @param string    `sort_mode`             (optional) - asc or desc
     * @param integer   `take`                  (optional) - limit
     * @param integer   `skip`                  (optional) - limit offset
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
            $maxRecord = (int) Config::get('orbit.pagination.product_attribute.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
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
}
