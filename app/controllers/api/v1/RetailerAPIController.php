<?php
/**
 * An API controller for managing retailers.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class RetailerAPIController extends ControllerAPI
{
    /**
     * POST - Delete Retailer
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the retailer
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.retailer.postdeleteretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.retailer.postdeleteretailer.after.auth', array($this));

            // Try to check access control list, does this retailer allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.retailer.postdeleteretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_retailer')) {
                Event::fire('orbit.retailer.postdeleteretailer.authz.notallowed', array($this, $user));
                $deleteRetailerLang = Lang::get('validation.orbit.actionlist.delete_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.postdeleteretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $retailer_id = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'retailer_id' => $retailer_id,
                ),
                array(
                    'retailer_id' => 'required|numeric|orbit.empty.retailer',
                )
            );

            Event::fire('orbit.retailer.postdeleteretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.retailer.postdeleteretailer.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deleteretailer = Retailer::find($retailer_id);
            $deleteretailer->status = 'deleted';
            $deleteretailer->modified_by = $this->api->user->user_id;

            Event::fire('orbit.retailer.postdeleteretailer.before.save', array($this, $deleteretailer));

            $deleteretailer->save();

            Event::fire('orbit.retailer.postdeleteretailer.after.save', array($this, $deleteretailer));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.retailer');

            // Commit the changes
            $this->commit();

            Event::fire('orbit.retailer.postdeleteretailer.after.commit', array($this, $deleteretailer));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.retailer.postdeleteretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.retailer.postdeleteretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.retailer.postdeleteretailer.query.error', array($this, $e));

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
            Event::fire('orbit.retailer.postdeleteretailer.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.retailer.postdeleteretailer.before.render', array($this, $output));

        return $output;
    }

    protected function registerCustomValidation()
    {
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
