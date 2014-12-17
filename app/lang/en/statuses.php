<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Statuses Language Lines
    |--------------------------------------------------------------------------
    |
    | Mostly used to return the success status of some operation.
    |
    */

    'orbit' => array(
        'deleted'   => array(
            'user'      => 'User has been successfully deleted.',
            'merchant'  => 'Merchant has been successfully deleted.',
            'retailer'  => 'Retailer has been successfully deleted.',
            'product'   => 'Product has been successfully deleted.',
            'category'  => 'Category has been successfully deleted.',
        ),
        'updated'   => array(
            'user'      => 'User has been successfully updated.',
        ),
        'nodata'    => array(
            'user'         => 'There is no user found that matched your criteria.',
            'merchant'     => 'There is no merchant found that matched your criteria.',
            'retailer'     => 'There is no retailer found that matched your criteria.',
            'product'      => 'There is no product found that matched your criteria.',
            'tax'          => 'There is no tax found that matched your criteria.',
            'categories'   => 'There is no category found that matched your criteria.',
        ),
        'uploaded'  => array(
            'merchant' => array(
                'logo'  => 'Merchant logo has been successfully uploaded.',
            ),
            'retailer' => array(
                'logo'  => 'Retailer logo has been successfully uploaded.',
            ),
            'product' => array(
                'main'          => 'Product image has been successfully uploaded.',
                'delete_image'  => 'Product images has been successfully deleted.'
            ),
        )
    )
);
