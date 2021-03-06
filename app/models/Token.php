<?php
class Token extends Eloquent
{
    use GeneratedUuidTrait;
    /**
    * Token Model
    *
    * @author Tian <tian@dominopos.com>
    * @author Kadek <kadek@dominopos.com>
    */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'tokens';
    
    protected $primaryKey = 'token_id';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    // generate token
    public function generateToken($input)
    {
         $string = $input . str_random(32) . microtime(TRUE);

         return sha1($string);
    }

    // check token expiration
    public function scopeNotExpire($query)
    {
        return $query->where('expire', '>=', DB::raw('NOW()'));
    }

    /**
     * Registration token used in activation email.
     */
    const NAME_USER_REGISTRATION_MOBILE = "user_registration_mobile";

    /**
     * Reset password token used in reset password email.
     */
    const NAME_RESET_PASSWORD = "reset_password";

}
