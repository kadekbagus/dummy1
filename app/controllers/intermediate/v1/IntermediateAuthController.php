<?php
/**
 * Base Intermediate Controller for all controller which need authentication.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use OrbitShop\API\v1\ResponseProvider;

class IntermediateAuthController extends IntermediateBaseController
{
    /**
     * Check the authenticated user on constructor
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function __construct()
    {
        parent::__construct();

        $this->beforeFilter(function()
        {
            try
            {
                // $user = User::find(1);

                // Hard code the login as off 24-11-2014, since the frontend
                // having difficulty with AngularJS
                // Auth::login( $user );
                if (! Auth::check()) {
                    $message = Lang::get('validation.orbit.access.needtologin');
                    ACL::throwAccessForbidden($message);
                }
            } catch (ACLForbiddenException $e) {
                $response = new ResponseProvider();
                $response->code = $e->getCode();
                $response->status = 'error';
                $response->message = $e->getMessage();

                return $this->render($response);
            } catch (Exception $e) {
                $response = new ResponseProvider();
                $response->code = $e->getCode();
                $response->status = 'error';
                $response->message = $e->getMessage();

                return $this->render($response);
            }
        });
    }
}
