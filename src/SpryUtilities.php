<?php

/**
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

namespace Spry;

use Spry\Spry;

/**
 * Utility Functions for Spry Compononents
 */
class SpryUtilities
{

    /**
     * Creates a random unique key.
     *
     * @param string $salt
     *
     * @access public
     *
     * @return string
     */
    public static function getRandomKey($salt = '')
    {
        return md5(uniqid(rand(), true).$salt);
    }



    /**
     * Runs a remote request.
     *
     * @param string $url
     * @param array  $request
     * @param array  $headers
     * @param string $method
     *
     * @access public
     *
     * @return string
     */
    public static function getRemoteResponse($url = '', $request = '', $headers = [], $method = 'POST')
    {
        $method = trim(strtoupper($method));

        if (!empty($request)) {
            $ch = curl_init();

            if (strval($method) === 'GET') {
                $url .= '?'.http_build_query($request);
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);

            if (strval($method) !== 'GET') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
            }

            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }

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
     * @access public
     *
     * @return string
     */
    public static function hash($value = '')
    {
        $salt = '';

        if (isset(Spry::config()->salt)) {
            $salt = Spry::config()->salt;
        }

        return md5(serialize($value).$salt);
    }



    /**
     * Return a formatted alphnumeric safe version of the string.
     *
     * @param string $string
     *
     * @access public
     *
     * @return string
     */
    public static function sanitize($string)
    {
        return preg_replace("/\W/", '', str_replace([' ', '-'], '_', strtolower(trim($string))));
    }



    /**
     * Returns the Singlular version of the string.
     *
     * @param string $string
     *
     * @access public
     *
     * @return string
     */
    public static function single($string)
    {
        if (!$string || !is_string($string) || !trim($string) || stripos(substr(trim($string), -1), 's') === false) {
            return $string;
        }

        $string = trim($string);

        if (stripos(substr($string, -3), 'ies') !== false) {
            return substr($string, 0, -3).(ctype_upper($string) ? 'Y' : 'y');
        }

        if (stripos(substr($string, -3), 'ses') !== false) {
            return substr($string, 0, -2);
        }

        if (stripos(substr($string, -1), 's') !== false) {
            return substr($string, 0, -1);
        }

        return $string;
    }



    /**
     * Returns the Plural version of the string.
     *
     * @param string $string
     *
     * @access public
     *
     * @return string
     */
    public static function plural($string)
    {
        if (!$string || !is_string($string) || !trim($string) || stripos(substr(trim($string), -1), 's') !== false) {
            return $string;
        }

        $string = trim($string);

        if (stripos(substr($string, -1), 'y') !== false) {
            return substr($string, 0, -1).(ctype_upper($string) ? 'IES' : 'ies');
        }

        return $string.(ctype_upper($string) ? 'S' : 's');
    }



    /**
     * Migrates the Database Scheme based on the configuration.
     *
     * @param array $args
     *
     * @access public
     *
     * @return array
     */
    public static function dbMigrate($args = [])
    {
        if (empty(Spry::config())) {
            return Spry::response(5001, null);
        }

        if (empty(Spry::config()->db['username']) || empty(Spry::config()->db['database_name'])) {
            return Spry::response(5032, null);
        }

        if (empty(Spry::config()->dbProvider) || !class_exists(Spry::config()->dbProvider)) {
            return Spry::response(5033, null);
        }

        $logs = Spry::db()->migrate($args);

        return Spry::response(30, $logs);
    }



    /**
     * Creates a random unique key.
     *
     * @param array $params
     * @param array $allowedOrderFields
     * @param array $configFields
     *
     * @access public
     *
     * @return array
     */
    public static function dbPrepareWhere($params = [], $allowedOrderFields = ['id'], $configFields = [])
    {
        $where = [];

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                if (!in_array($key, $configFields)) {
                    $where[$key] = $value;
                }
            }
        }

        $where['ORDERBY'] = self::dbGetOrder($allowedOrderFields);

        return $where;
    }



    /**
     * Gets the SQL Order from the API Request.
     * Can specify Allowed fields to order by.
     *
     * @param array $allowedFields
     *
     * @access public
     *
     * @return array
     */
    public static function dbGetOrder($allowedFields = ['id'])
    {
        $order = Spry::validator()->validate('order');
        $orderby = Spry::validator()->validate('orderby');

        $first = 'id';

        if (is_array($allowedFields)) {
            if (!in_array($orderby, $allowedFields)) {
                foreach ($allowedFields as $field) {
                    if (stripos($field, '.')) {
                        $split = explode('.', $field);

                        if (!empty($split[1]) && $split[1] === $orderby) {
                            $orderby = $split[0].'.'.$split[1];
                            break;
                        }
                    }
                }
            }

            $first = $allowedFields[0];
        }

        $order = (is_string($order) && in_array($order, ['DESC', 'ASC']) ? $order : 'DESC');
        $orderby = (is_string($orderby) && is_array($allowedFields) && in_array($orderby, $allowedFields) ? $orderby : $first);

        return [$orderby => $order];
    }



    /**
     * Extracts the Value from a multi-dementional array by string
     * format using "." as the array separator.
     *
     * @param array $keyPath
     * @param array $array
     *
     * @access public
     *
     * @return mixed
     */
    public static function extractKeyValue($keyPath = '', $array = [])
    {
        $path = explode('.', $keyPath);

        $paramValue = $array;

        foreach ($path as $key) {
            if (isset($paramValue[$key])) {
                $paramValue = $paramValue[$key];
            } else {
                $paramValue = null;
                break;
            }
        }

        return $paramValue;
    }



    /**
     * Runs a test through the Remote Response
     *
     * @param array $test
     *
     * @access public
     *
     * @return mixed
     */
    public static function test($test = '')
    {
        $responseCode = 2050;

        $result = [];

        if (is_string($test)) {
            if (empty(Spry::config()->tests)) {
                Spry::stop(5052);
            }

            if (!isset(Spry::config()->tests[$test])) {
                return Spry::response(5053, null);
            }

            $test = Spry::config()->tests[$test];
        }

        $method = (!empty($test['method']) ? trim(strtoupper($test['method'])) : 'POST');

        $result = [
            'status' => 'Passed',
            'params' => $test['params'],
            'headers' => (!empty($test['headers']) ? $test['headers'] : []),
            'method' => $method,
            'expect' => [],
            'result' => [],
        ];

        $response = self::getRemoteResponse(
            Spry::config()->endpoint.$test['route'],
            array_merge($test['params'], ['test_data' => 1]),
            (!empty($test['headers']) ? $test['headers'] : false),
            $method
        );

        $response = json_decode($response, true);

        $result['full_response'] = $response;

        if (!empty($test['expect']) && is_array($test['expect'])) {
            $result['result'] = [];

            if (empty($test['expect'])) {
                $result['status'] = 'Failed';
                $responseCode = 5050;
            } else {
                $result['expect'] = $test['expect'];

                foreach ($test['expect'] as $expectKey => $expect) {
                    $expectPath = $expectKey;
                    $expectCompare = '=';

                    $comparisons = ['<=', '>=', '=<', '=>', '<', '>', '!==', '===', '!=', '==', '!', '='];

                    foreach ($comparisons as $compareKey => $compareValue) {
                        if ($pos = strrpos($expectKey, '['.$compareValue.']')) {
                            $expectPath = rtrim(substr($expectKey, 0, $pos));
                            $expectCompare = trim(str_replace(['[', ']'], '', $compareValue));
                            break;
                        } elseif ($pos = strrpos($expectKey, $compareValue)) {
                            $expectPath = rtrim(substr($expectKey, 0, $pos));
                            $expectCompare = trim($compareValue);
                            break;
                        }
                    }

                    $responseValue = self::extractKeyValue($expectPath, $response);

                    $result['result'][$expectPath] = $responseValue;

                    if (is_null($responseValue)) {
                        $result['status'] = 'Failed';
                        $responseCode = 5050;
                    }

                    if ($expectCompare && !is_null($responseValue)) {
                        switch ($expectCompare) {
                            case '!==':
                                if ($responseValue === $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 5050;
                                }

                                break;

                            case '!=':
                            case '!':
                                if ($responseValue === $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 5050;
                                }

                                break;

                            case '<=':
                            case '=<':
                                if ($responseValue > $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 5050;
                                }

                                break;

                            case '>=':
                            case '=>':
                                if ($responseValue < $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 5050;
                                }

                                break;

                            case '<':
                                if ($responseValue >= $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 5050;
                                }

                                break;

                            case '>':
                                if ($responseValue <= $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 5050;
                                }

                                break;

                            case '==':
                            case '=':
                                if ($responseValue !== $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 5050;
                                }

                                break;

                            case '===':
                            default:
                                if ($responseValue !== $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 5050;
                                }

                                break;
                        }
                    }
                }
            }
        }

        return Spry::response($responseCode, $result);
    }
}
