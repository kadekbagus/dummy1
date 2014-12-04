<?php
/**
 * An API controller for managing merchant taxes.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class MerchantTaxAPIController extends ControllerAPI
{
    /**
     * GET - Search merchant tax
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `sort_by`                       (optional) - column order by
     * @param string            `sort_mode`                     (optional) - asc or desc
     * @param integer           `take`                          (optional) - limit
     * @param integer           `skip`                          (optional) - limit offset
     * @param integer           `merchant_tax_id`               (optional)
     * @param string            `tax_name`                      (optional)
     * @param string            `tax_name_like`                 (optional)
     * @param decimal           `tax_value`                     (optional)
     */

    public function getSearchMerchantTax()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.merchanttax.getsearchmerchanttax.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.merchanttax.getsearchmerchanttax.after.auth', array($this));
 
            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.merchanttax.getsearchmerchanttax.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_tax')) {
                Event::fire('orbit.merchanttax.getsearchmerchanttax.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_tax');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.merchanttax.getsearchmerchanttax.after.authz', array($this, $user));

            // $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,merchant_tax_id,tax_name,tax_value',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.tax_sortby'),
                )
            );

            Event::fire('orbit.merchanttax.getsearchmerchanttax.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.merchanttax.getsearchmerchanttax.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $taxes = MerchantTax::AllowedForUser($user);

            // Filter Merchant Tax by Ids
            OrbitInput::get('merchant_tax_id', function($merchantTaxId) use ($taxes)
            {
                $taxes->whereIn('merchant_taxes.merchant_tax_id', $merchantTaxId);
            });

            // Filter Merchant Tax by Tax Name
            OrbitInput::get('tax_name', function($tax_name) use ($taxes)
            {
                $taxes->whereIn('merchant_taxes.tax_name', $tax_name);
            });

            // Filter Merchant Tax by Tax Name Pattern
            OrbitInput::get('tax_name_like', function($tax_name_like) use ($taxes)
            {
                $taxes->where('merchant_taxes.tax_name', 'like', "%$tax_name_like%");
            });

            // Filter Merchant Tax by Tax Value
            OrbitInput::get('tax_value', function($tax_value) use ($taxes)
            {
                $taxes->whereIn('merchant_taxes.tax_value', $tax_value);
            });

            $_taxes = clone $taxes;

            // Get the take args
            $take = $maxRecord;
            OrbitInput::get('take', function($_take) use (&$take, $maxRecord)
            {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;
            });
            $taxes->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $taxes)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $taxes->skip($skip);

            // Default sort by
            $sortBy = 'merchant_taxes.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'           => 'merchant_taxes.created_at',
                    'merchant_tax_id'           => 'merchant_taxes.merchant_tax_id',
                    'tax_name'                  => 'merchant_taxes.tax_name',
                    'tax_value'                 => 'merchant_taxes.tax_value',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $taxes->orderBy($sortBy, $sortMode);

            $totalRec = $_taxes->count();
            $listOfRec = $taxes->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.tax');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.merchanttax.getsearchmerchanttax.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.merchanttax.getsearchmerchanttax.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.merchanttax.getsearchmerchanttax.query.error', array($this, $e));

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
            Event::fire('orbit.merchanttax.getsearchmerchanttax.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        $output = $this->render($httpCode);
        Event::fire('orbit.merchanttax.getsearchmerchanttax.before.render', array($this, &$output));

        return $output;
    }
}