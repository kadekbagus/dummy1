<?php
/**
 * An API controller for managing Settings.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class SettingAPIController extends ControllerAPI
{
    /**
     * POST - Update Setting
     *
     * @author <Tian> <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `setting_name`         (required) - Setting name
     * @param integer    `setting_value`        (required) - Setting value
     * @param integer    `object_id`            (optional) - Object ID
     * @param integer    `object_type`          (optional) - Object type
     * @param string     `status`               (optional) - Status. Valid value: active, inactive, deleted.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateSetting()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedsetting = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.setting.postupdatesetting.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.setting.postupdatesetting.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.setting.postupdatesetting.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_setting')) {
                Event::fire('orbit.setting.postupdatesetting.authz.notallowed', array($this, $user));
                $updateSettingLang = Lang::get('validation.orbit.actionlist.update_setting');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateSettingLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.setting.postupdatesetting.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $setting_name = OrbitInput::post('setting_name');
            $setting_value = OrbitInput::post('setting_value');
            $object_id = OrbitInput::post('object_id');
            $object_type = OrbitInput::post('object_type');
            $status = OrbitInput::post('status');

            $validator = Validator::make(
                array(
                    'setting_name'     => $setting_name,
                    'setting_value'    => $setting_value,
                    'status'           => $status,
                ),
                array(
                    'setting_name'     => 'required',
                    'setting_value'    => 'required',
                    'status'           => 'orbit.empty.setting_status',
                )
            );

            Event::fire('orbit.setting.postupdatesetting.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.setting.postupdatesetting.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedsetting = Setting::excludeDeleted()->where('setting_name', $setting_name)->first();

            if (empty($updatedsetting)) {
                // do insert
                $updatedsetting = new Setting();
                $updatedsetting->setting_name = $setting_name;
                $updatedsetting->setting_value = $setting_value;
                $updatedsetting->object_id = $object_id;
                $updatedsetting->object_type = $object_type;
                if (trim($status) !== '') {
                    $updatedsetting->status = $status;
                }
                
                $updatedsetting->modified_by = $this->api->user->user_id;

                Event::fire('orbit.setting.postupdatesetting.before.save', array($this, $updatedsetting));

                $updatedsetting->save();

                Event::fire('orbit.setting.postupdatesetting.after.save', array($this, $updatedsetting));
            } else {
                // do update
                OrbitInput::post('setting_value', function($setting_value) use ($updatedsetting) {
                    $updatedsetting->setting_value = $setting_value;
                });

                OrbitInput::post('object_id', function($object_id) use ($updatedsetting) {
                    $updatedsetting->object_id = $object_id;
                });

                OrbitInput::post('object_type', function($object_type) use ($updatedsetting) {
                    $updatedsetting->object_type = $object_type;
                });

                OrbitInput::post('status', function($status) use ($updatedsetting) {
                    $updatedsetting->status = $status;
                });

                $updatedsetting->modified_by = $this->api->user->user_id;

                Event::fire('orbit.setting.postupdatesetting.before.save', array($this, $updatedsetting));

                $updatedsetting->save();

                Event::fire('orbit.setting.postupdatesetting.after.save', array($this, $updatedsetting));
            }

            $this->response->data = $updatedsetting;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Setting updated: %s', $updatedsetting->setting_name);
            $activity->setUser($user)
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting OK')
                    ->setObject($updatedsetting)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.setting.postupdatesetting.after.commit', array($this, $updatedsetting));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.setting.postupdatesetting.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting Failed')
                    ->setObject($updatedsetting)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.setting.postupdatesetting.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting Failed')
                    ->setObject($updatedsetting)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.setting.postupdatesetting.query.error', array($this, $e));

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
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting Failed')
                    ->setObject($updatedsetting)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.setting.postupdatesetting.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting Failed')
                    ->setObject($updatedsetting)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    protected function registerCustomValidation()
    {
        // Check the existence of the setting status
        Validator::extend('orbit.empty.setting_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

    }
}