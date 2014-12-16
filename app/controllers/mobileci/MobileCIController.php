<?php namespace MobileCI;

/**
 * An API controller for managing products.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Illuminate\View\View as View;

class MobileCIController extends ControllerAPI
{
    public function postSignUpView()
    {
        $email = OrbitInput::get('emailSignUp');
        return View::make('mobile-ci.signup', array('email' => $email));
    }
}