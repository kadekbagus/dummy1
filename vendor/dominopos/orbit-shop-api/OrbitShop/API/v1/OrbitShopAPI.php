<?php namespace OrbitShop\API\v1;
/**
 * OrbitShop Base API
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitAPI\v10\API;
use OrbitShop\API\v1\OrbitShopLookupResponse;

class OrbitShopAPI extends API
{
    /**
     * The user ID who's associated with this key
     *
     * @var int
     */
    protected $userId = NULL;

    /**
     * Save the user instance.
     *
     * @var User
     */
    protected $user;

    /**
     * The Signature header name
     *
     * @var string
     */
    protected $httpSignatureHeader = 'X-Orbit-Signature';

    /**
     * The query parameter name that API should accept on.
     *
     * @var array
     */
    protected $queryParamName = array(
        'clientid'  => 'apikey',
        'timestamp' => 'apitimestamp',
        'version'   => 'apiver'
    );

    /**
     * Class constructor
     */
    public function __construct($clientID)
    {
        parent::__construct($clientID);
    }

    /**
     * Search the secret key based on the client ID.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return Morra\Loyalty\API\LoyaltyLookupResponse
     */
    protected function lookupClientSecretKey($clientID)
    {
        $response = new OrbitShopLookupResponse($clientID);
        $this->userId = $response->getUserId();
        $this->user = $response->getUser();

        return $response;
    }

    /**
     * Get the user ID that associated with this key.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Get the User object based on the user ID.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return \User|NULL
     */
    public function getUser()
    {
        return $this->user;
    }
}
