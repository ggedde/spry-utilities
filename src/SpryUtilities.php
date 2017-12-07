<?php

namespace Spry\SpryUtilities;

use Spry\Spry;

class SpryUtilities {

    public static function getRemoteResponse($url='', $request='')
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



    /**
	 * Extracts the Value from a multi-dementional array by string
     * format using "." as the array separator.
	 *
 	 * @param array $key_path
 	 * @param array $array
 	 *
 	 * @access 'public'
 	 * @return mixed
	 */

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



    /**
	 * Runs a test through the Remote Response
	 *
 	 * @param array $test
 	 *
 	 * @access 'public'
 	 * @return mixed
	 */

    public static function test($test='')
	{
		$response_code = 2050;

        $result = [];

        if(is_string($test))
        {
            if(empty(Spry::config()->tests))
    		{
    			Spry::stop(5052);
    		}

            if(!isset(Spry::config()->tests[$test]))
            {
                return Spry::response(5053, null);
            }

            $test = Spry::config()->tests[$test];
        }

		$result = [
            'status' => 'Passed',
            'params' => $test['params'],
            'expect' => [],
            'result' => [],
        ];

		$response = self::getRemoteResponse(json_encode($test['params']), Spry::config()->endpoint.$test['route']);
		$response = json_decode($response, true);

        $result['full_response'] = $response;

		if(!empty($test['expect']) && is_array($test['expect']))
		{
			$result['result'] = [];

            if(empty($test['expect']))
            {
                $result['status'] = 'Failed';
                $response_code = 5050;
            }
            else
            {
                $result['expect'] = $test['expect'];

                foreach ($test['expect'] as $expect_key => $expect)
				{
                    $expect_path = $expect_key;
                    $expect_compare = '=';

                    $comparisons = ['<=','>=','=<','=>','<','>','!==','===','!=','==','!','='];

                    foreach($comparisons as $compare_key => $compare_value)
                    {
                        if($pos = strrpos($expect_key, '['.$compare_value.']'))
                        {
                            $expect_path = rtrim(substr($expect_key, 0, $pos));
                            $expect_compare = trim(str_replace(['[',']'], '', $compare_value));
                            break;
                        }
                        else if($pos = strrpos($expect_key, $compare_value))
                        {
                            $expect_path = rtrim(substr($expect_key, 0, $pos));
                            $expect_compare = trim($compare_value);
                            break;
                        }
                    }

                    $response_value = self::extractKeyValue($expect_path, $response);

                    $result['result'][$expect_path] = $response_value;

					if(is_null($response_value))
					{
                        $result['status'] = 'Failed';
						$response_code = 5050;
					}

                    if($expect_compare && !is_null($response_value))
                    {
                        switch($expect_compare)
                        {
                            case '!==':

                                if($response_value === $expect)
                                {
                                    $result['status'] = 'Failed';
            						$response_code = 5050;
                                }

                            break;

                            case '!=':
                            case '!':

                                if($response_value == $expect)
                                {
                                    $result['status'] = 'Failed';
            						$response_code = 5050;
                                }

                            break;

                            case '<=':
                            case '=<':

                                if($response_value > $expect)
                                {
                                    $result['status'] = 'Failed';
            						$response_code = 5050;
                                }

                            break;

                            case '>=':
                            case '=>':

                                if($response_value < $expect)
                                {
                                    $result['status'] = 'Failed';
            						$response_code = 5050;
                                }

                            break;

                            case '<':

                                if($response_value >= $expect)
                                {
                                    $result['status'] = 'Failed';
            						$response_code = 5050;
                                }

                            break;

                            case '>':

                                if($response_value <= $expect)
                                {
                                    $result['status'] = 'Failed';
            						$response_code = 5050;
                                }

                            break;

                            case '==':
                            case '=':

                                if($response_value != $expect)
                                {
                                    $result['status'] = 'Failed';
            						$response_code = 5050;
                                }

                            break;

                            case '===':
                            default:

                                if($response_value !== $expect)
                                {
                                    $result['status'] = 'Failed';
            						$response_code = 5050;
                                }

                            break;
                        }
                    }
				}
            }
		}

		return Spry::response($response_code, $result);
	}
}
