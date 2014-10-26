<?php namespace OrbitShop\API\v1\Helper;
/**
 * Helper for replacing the Laravel's default Input::get(). We do not want the
 * Input::get() to parse both $_GET and $_POST.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class Input
{
    /**
     * Method to get the query string and provide default value if not exists
     *
     * @author Rio Astamal <me@riostamal.net>
     *
     * @param string $key                   The query string name
     * @param callback|mixed $callback      Callback which should be executed | Default value
     * @param mixed $default                The default value
     * @return mixed
     */
    public static function GET($key, $callback=NULL, $default=NULL)
    {
        if (is_callable($callback)) {

            if (isset($_GET[$key])) {
                return $callback($_GET[$key]);
            }

            return $default;
        }

        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        return $callback;
    }

    /**
     * Method to get the input data from post body and provide default value if not exists
     *
     * @author Rio Astamal <me@riostamal.net>
     *
     * @param string $key                   The key peer name of the post data
     * @param callback|mixed $callback      Callback which should be executed | Default value
     * @param mixed $default                The default value
     * @return mixed
     */
    public static function POST($key, $callback=NULL, $default=NULL)
    {
        if (is_callable($callback)) {

            if (isset($_POST[$key])) {
                return $callback($_POST[$key]);
            }

            return $default;
        }

        if (isset($_POST[$key])) {
            return $_POST[$key];
        }

        return $callback;
    }
}
