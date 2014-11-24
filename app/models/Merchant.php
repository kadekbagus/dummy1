<?php

class Merchant extends Eloquent
{
    /**
     * Merchant Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Kadek <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Use Trait MerchantTypeTrait so we only displaying records with value
     * `object_type` = 'merchant'
     */
    use MerchantTypeTrait;

    /**
     * Column name which determine the type of Merchant or Retailer.
     */
    const OBJECT_TYPE = 'object_type';

    protected $primaryKey = 'merchant_id';

    protected $table = 'merchants';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function retailers()
    {
        return $this->hasMany('Retailer', 'parent_id', 'merchant_id');
    }

    public function children()
    {
        return $this->retailers();
    }

    public function getPhoneCodeArea($separator='|#|')
    {
        $phone = explode($separator, $this->phone);

        if (isset($phone[0])) {
            return $phone[0];
        }

        return NULL;
    }

    public function getPhoneNumber($separator='|#|')
    {
        $phone = explode($separator, $this->phone);

        if (isset($phone[1])) {
            return $phone[1];
        }

        return NULL;
    }

    public function getFullPhoneNumber($separator='|#|', $concat=' ')
    {
        $phone = explode($separator, $this->phone);

        if (isset($phone[1])) {
            return $phone[0] . $concat . $phone[1];
        }

        return $phone[0];
    }

    /**
     * Contact person phone.
     */
    public function getContactPhoneCodeArea($separator='|#|')
    {
        $contact_person_phone = explode($separator, $this->contact_person_phone);

        if (isset($contact_person_phone[0])) {
            return $contact_person_phone[0];
        }

        return NULL;
    }

    public function getContactPhoneNumber($separator='|#|')
    {
        $contact_person_phone = explode($separator, $this->contact_person_phone);

        if (isset($contact_person_phone[1])) {
            return $contact_person_phone[1];
        }

        return NULL;
    }

    public function getContactFullPhoneNumber($separator='|#|', $concat=' ')
    {
        $contact_person_phone = explode($separator, $this->contact_person_phone);

        if (isset($contact_person_phone[1])) {
            return $contact_person_phone[0] . $concat . $contact_person_phone[1];
        }

        return $contact_person_phone[0];
    }

    /**
     * Contact person phone2.
     */
    public function getContact2PhoneCodeArea($separator='|#|')
    {
        $contact_person_phone2 = explode($separator, $this->contact_person_phone2);

        if (isset($contact_person_phone2[0])) {
            return $contact_person_phone2[0];
        }

        return NULL;
    }

    public function getContact2PhoneNumber($separator='|#|')
    {
        $contact_person_phone2 = explode($separator, $this->contact_person_phone2);

        if (isset($contact_person_phone2[1])) {
            return $contact_person_phone2[1];
        }

        return NULL;
    }

    public function getContact2FullPhoneNumber($separator='|#|', $concat=' ')
    {
        $contact_person_phone2 = explode($separator, $this->contact_person_phone2);

        if (isset($contact_person_phone2[1])) {
            return $contact_person_phone2[0] . $concat . $contact_person_phone2[1];
        }

        return $contact_person_phone2[0];
    }
}
