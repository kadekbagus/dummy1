<?php
/**
 * A dummy route file
 */

Route::get('/api/dummy', function()
{
    $data = array(
        'code'      => 0,
        'status'    => 'ok',
        'message'   => 'Hello World',
        'data'      => null
    );

    return Response::json($data);
});
