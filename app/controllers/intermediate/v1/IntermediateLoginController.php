<?php
/**
 * Intermediate Controller for handling user login
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ResponseProvider;
use MobileCI\MobileCIAPIController;
use Net\Security\Firewall;

class IntermediateLoginController extends IntermediateBaseController
{
    /**
     * @author Rio Astamal <me@rioastamal.net>
     * @param @see LoginAPIController::postLogin
     * @return Response
     */
    public function postLogin()
    {
        $response = LoginAPIController::create('raw')->postLogin();
        if ($response->code === 0)
        {
            $user = $response->data;
            $user->setHidden(array('user_password', 'apikey'));
            // Auth::login($user);

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
            );
            $this->session->enableForceNew()->start($data);

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();
        }

        return $this->render($response);
    }

    /**
     * Clear the session
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Response
     */
    public function getLogout()
    {
        $response = new ResponseProvider();

        try {
            $this->session->start(array(), 'no-session-creation');
            $this->session->destroy();
            $response->data = NULL;
        } catch (Exception $e) {
            $response->code = $e->getCode();
            $response->status = 'error';
            $response->message = $e->getMessage();
        }

        return $this->render($response);
    }

    /**
     * Check the session value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Response
     */
    public function getSession()
    {
        $response = new ResponseProvider();

        try {
            $this->session->start(array(), 'no-session-creation');

            if (Config::get('app.debug')) {
                $response->data = $this->session->getSession();
            } else {
                $response->data = 'Not in debug mode.';
            }
        } catch (Exception $e) {
            $response->code = $e->getCode();
            $response->status = 'error';
            $response->message = $e->getMessage();
        }

        return $this->render($response);
    }

    /**
     * Check and activate token.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param @see LoginAPIController::getRegisterTokenCheck
     * @return response
     */
    public function postRegisterTokenCheck()
    {
        $response = LoginAPIController::create('raw')->postRegisterTokenCheck();
        if ($response->code === 0)
        {
            $user = $response->data;

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
            );
            $this->session->enableForceNew()->start($data);

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();
        }

        return $this->render($response);
    }

    /**
     * Get token list
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Response
     */
    public function getTokenList()
    {
        return $this->render(TokenAPIController::create('raw')->getSearchToken());
    }

    /**
     * Mobile-CI Intermediate call by registering client mac address when login
     * succeed.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param @see MobileCIAPIController::postLoginInShop()
     * @return Response
     */
    public function postLoginMobileCI()
    {
        $response = MobileCIAPIController::create('raw')->postLoginInShop();
        if ($response->code === 0)
        {
            // Register User Mac Address to the Router
            $registerMac = Firewall::create()->grantMacByIP($_SERVER['REMOTE_ADDR']);
            if (! $registerMac['status']) {
                $exitCode = 1;
                if (isset($registerMac['object'])) {
                    $exitCode = $registerMac['object']->getExitCode();
                }
                $response->message = $registerMac['message'];

                // Call logout to clear session
                MobileCIAPIController::create('raw')->getLogoutInShop();

                return $this->render($response);
            }

            $user = $response->data;
            $user->setHidden(array('user_password', 'apikey'));

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
            );
            $this->session->enableForceNew()->start($data);

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();
        }

        return $this->render($response);
    }

    /**
     * Mobile-CI Intermediate call by revoking client mac address when logout
     * succeed.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param @see MobileCIAPIController::postLoginInShop()
     * @return Response
     */
    public function getLogoutMobileCI()
    {
        $response = json_decode($this->getLogout()->getContent());
        try {
            if ($response->code !== 0) {
                throw new Exception ($response->message, $response->code);
            }

            // De-register User Mac Address to the Router
            $deRegisterMac = Firewall::create()->revokeMacByIP($_SERVER['REMOTE_ADDR']);
            if (! $deRegisterMac['status']) {
                $exitCode = 1;
                if (isset($deRegisterMac['object'])) {
                    $exitCode = $deRegisterMac['object']->getExitCode();
                }
                throw new Exception ($deRegisterMac['message'], $exitCode);
            }
        } catch (Exception $e) {
        }

        // Redirect back to /customer
        return Redirect::to('/customer');
    }
}
