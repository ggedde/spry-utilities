<?php

namespace Spry\SpryUtilities;

use Spry\Spry;

class SpryUtilities {



    public static function get_api_response($request='', $url='')
	{
		if(!empty($request))
		{
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request);

			$response = curl_exec($ch);
			curl_close($ch);

			return $response;
		}
	}



    /**
	 * Creates a one way Hash value used for Passwords and other authentication.
	 *
 	 * @param string $value
 	 *
 	 * @access 'public'
 	 * @return string
	 */

    public static function hash($value='')
    {
        $salt = '';

		if(isset(Spry::config()->salt))
		{
			$salt = Spry::config()->salt;
		}

		return md5(serialize($value).$salt);
    }



    /**
     * Return a formatted alphnumeric safe version of the string.
     *
     * @param string $string
     *
     * @access 'public'
     * @return string
     */

    public static function sanitize($string)
    {
        return preg_replace("/\W/", '', str_replace([' ', '-'], '_', strtolower(trim($string))));
    }



    /**
	 * Migrates the Database Scheme based on the configuration.
	 *
 	 * @param array $args
 	 *
 	 * @access 'public'
 	 * @return array
	 */

    public static function db_migrate($args=[])
	{
        if(empty(Spry::config()))
        {
            return Spry::response(5001, null);
        }

		if(empty(Spry::config()->db['username']) || empty(Spry::config()->db['database_name']))
        {
            return Spry::response(5032, null);
        }

        if(empty(Spry::config()->db['provider']) || !class_exists(Spry::config()->db['provider']))
        {
            return Spry::response(5033, null);
        }

		$logs = Spry::db()->migrate($args);

		return Spry::response(30, $logs);
	}



    public static function extractKeyValue($key_path='', $array=[])
    {
        $path = explode('.', $key_path);

        $param_value = $array;

        foreach($path as $key)
        {
            if(isset($param_value[$key]))
            {
                $param_value = $param_value[$key];
            }
            else
            {
                $param_value = null;
                break;
            }
        }

        return $param_value;
    }
}
