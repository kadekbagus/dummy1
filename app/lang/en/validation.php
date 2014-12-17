<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    "accepted"             => "The :attribute must be accepted.",
    "active_url"           => "The :attribute is not a valid URL.",
    "after"                => "The :attribute must be a date after :date.",
    "alpha"                => "The :attribute may only contain letters.",
    "alpha_dash"           => "The :attribute may only contain letters, numbers, and dashes.",
    "alpha_num"            => "The :attribute may only contain letters and numbers.",
    "array"                => "The :attribute must be an array.",
    "before"               => "The :attribute must be a date before :date.",
    "between"              => array(
        "numeric" => "The :attribute must be between :min and :max.",
        "file"    => "The :attribute must be between :min and :max kilobytes.",
        "string"  => "The :attribute must be between :min and :max characters.",
        "array"   => "The :attribute must have between :min and :max items.",
    ),
    "boolean"              => "The :attribute field must be true or false",
    "confirmed"            => "The :attribute confirmation does not match.",
    "date"                 => "The :attribute is not a valid date.",
    "date_format"          => "The :attribute does not match the format :format.",
    "different"            => "The :attribute and :other must be different.",
    "digits"               => "The :attribute must be :digits digits.",
    "digits_between"       => "The :attribute must be between :min and :max digits.",
    "email"                => "The :attribute must be a valid email address.",
    "exists"               => "The selected :attribute is invalid.",
    "image"                => "The :attribute must be an image.",
    "in"                   => "The selected :attribute is invalid.",
    "integer"              => "The :attribute must be an integer.",
    "ip"                   => "The :attribute must be a valid IP address.",
    "max"                  => array(
        "numeric" => "The :attribute may not be greater than :max.",
        "file"    => "The :attribute may not be greater than :max kilobytes.",
        "string"  => "The :attribute may not be greater than :max characters.",
        "array"   => "The :attribute may not have more than :max items.",
    ),
    "mimes"                => "The :attribute must be a file of type: :values.",
    "min"                  => array(
        "numeric" => "The :attribute must be at least :min.",
        "file"    => "The :attribute must be at least :min kilobytes.",
        "string"  => "The :attribute must be at least :min characters.",
        "array"   => "The :attribute must have at least :min items.",
    ),
    "not_in"               => "The selected :attribute is invalid.",
    "numeric"              => "The :attribute must be a number.",
    "regex"                => "The :attribute format is invalid.",
    "required"             => "The :attribute field is required.",
    "required_if"          => "The :attribute field is required when :other is :value.",
    "required_with"        => "The :attribute field is required when :values is present.",
    "required_with_all"    => "The :attribute field is required when :values is present.",
    "required_without"     => "The :attribute field is required when :values is not present.",
    "required_without_all" => "The :attribute field is required when none of :values are present.",
    "same"                 => "The :attribute and :other must match.",
    "size"                 => array(
        "numeric" => "The :attribute must be :size.",
        "file"    => "The :attribute must be :size kilobytes.",
        "string"  => "The :attribute must be :size characters.",
        "array"   => "The :attribute must contain :size items.",
    ),
    "unique"               => "The :attribute has already been taken.",
    "url"                  => "The :attribute format is invalid.",
    "timezone"             => "The :attribute must be a valid zone.",

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'orbit' => array(
        // This will moved soon to the 'exists' key
        'email' => array(
            'exists' => 'The email address has already taken by someone else.',
        ),
        'exists' => array(
            'username'      => 'The username has already taken by someone else.',
            'email'         => 'Email address has been taken.',
            'omid'          => 'OMID has already taken by another Merchant.',
            'orid'          => 'ORID has already taken by another Retailer.',
            'category_name' => 'The category name has already been used.',
            'have_product_category' => 'The category have product(s).'
        ),
        'access' => array(
            'forbidden'              => 'You do not have permission to :action.',
            'needtologin'            => 'You need to login to view this page.',
            'loginfailed'            => 'Email or password was wrong.',
            'tokenmissmatch'         => 'CSRF protection token missmatch.',
            'wrongpassword'          => 'Password was wrong.',
            'old_password_not_match' => 'Old password was wrong'
        ),
        'empty' => array(
            'role'              => 'The Role ID you specify is not found.',
            'token'             => 'The Token you specify is not found.',
            'user'              => 'The User ID you specify is not found.',
            'merchant'          => 'The Merchant ID you specify is not found.',
            'retailer'          => 'The Retailer ID you specify is not found.',
            'product'           => 'The Product ID you specify is not found.',
            'category'          => 'The Category ID you specify is not found.',
            'tax'               => 'The Tax ID you specify is not found.',
            'user_status'       => 'The user status you specify is not found.',
            'user_sortby'       => 'The sort by argument you specify is not valid, the valid value are: username, email, firstname, lastname, and registered_date.',
            'merchant_status'   => 'The merchant status you specify is not found.',
            'merchant_sortby'   => 'The sort by argument you specify is not valid, the valid value are: registered_date, merchant_name, merchant_email, merchant_userid, merchant_description, merchantid, merchant_address1, merchant_address2, merchant_address3, merchant_cityid, merchant_city, merchant_countryid, merchant_country, merchant_phone, merchant_fax, merchant_status, merchant_currency.',
            'retailer_sortby'   => 'The sort by argument for retailer you specify is not valid, the valid value are: registered_date, retailer_name, retailer_email, and orid.',
            'retailer_status'   => 'The retailer status you specify is not found.',
            'tax_sortby'        => 'The sort by argument for tax you specify is not valid, the valid value are: registered_date, merchant_tax_id, tax_name, tax_value.',
            'tax_status'        => 'The tax status you specify is not found.',
        ),
        'queryerror' => 'Database query error, turn on debug mode to see the full query.',
        'actionlist' => array(
            'change_password'       => 'change password',
            'add_new_user'          => 'add new user',
            'delete_user'           => 'delete user',
            'delete_your_self'      => 'delete your self',
            'update_user'           => 'update user',
            'view_user'             => 'view user',
            'new_merchant'          => 'add new merchant',
            'update_merchant'       => 'update merchant',
            'delete_merchant'       => 'delete merchant',
            'view_merchant'         => 'view merchant',
            'new_retailer'          => 'add new retailer',
            'update_retailer'       => 'update retailer',
            'delete_retailer'       => 'delete retailer',
            'view_retailer'         => 'view retailer',
            'new_product'           => 'add new product',
            'update_product'        => 'update product',
            'delete_product'        => 'delete product',
            'view_product'          => 'view product',
            'new_tax'               => 'add new tax',
            'update_tax'            => 'update tax',
            'delete_tax'            => 'delete tax',
            'view_tax'              => 'view tax',
            'new_category'          => 'add new category',
            'update_category'       => 'update category',
            'delete_category'       => 'delete category',
            'view_category'         => 'view category',
        ),
    ),

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes' => array(
    ),

);
