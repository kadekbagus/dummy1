<?php DominoPOS\OrbitSession;
/**
 * Structure of Session Data
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class SessionData
{
    /**
     * Session ID
     *
     * @var string
     */
    public $id = '';

    /**
     * Created time
     *
     * @var int
     */
    public $createdAt = 0;

    /**
     * Expire time
     *
     * @var int
     */
    public $expireAt = 0;

    /**
     * Session value
     *
     * @var string
     */
    public $value = '';

    /**
     * Client User Agent
     *
     * @var string
     */
    public $userAgent = '';

    /**
     * Client IP Address
     *
     * @var string
     */
    public $ipAddress = '';
}
