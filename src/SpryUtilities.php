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
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
     * @param bool   $skipInitialHash
     *
     * @access public
     *
     * @return string
     */
    public static function hash($value = '', $skipInitialHash = false)
    {
        $salt = '';

        if (isset(Spry::config()->salt)) {
            $salt = Spry::config()->salt;
        }

        if ($value && !is_string($value)) {
            $value = serialize($value);
        }

        if (!$skipInitialHash) {
            $value = hash('sha256', $value);
        }

        $value = hash('sha256', serialize($value).$salt);

        return $value;
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
            return Spry::response(null, 1);
        }

        if (empty(Spry::config()->db['username']) || empty(Spry::config()->db['database_name'])) {
            return Spry::response(null, 32);
        }

        if (empty(Spry::config()->dbProvider) || !class_exists(Spry::config()->dbProvider)) {
            return Spry::response(null, 33);
        }

        $logs = Spry::db()->migrate($args);

        return Spry::response(null, 30, null, null, $logs);
    }



    /**
     * Prepares the Select Statment using meta fields for Search, Pagination, etc..
     *
     * @param string $table
     * @param array  $join
     * @param array  $where
     * @param array  $meta
     * @param array  $searchFields
     * @param array  $dbMeta
     *
     * @access public
     *
     * @return array
     */
    public static function dbPrepareSelect($table, $join = null, $where = [], $meta = [], $searchFields = [], $dbMeta = [])
    {
        $responseMeta = [];

        // Get Multiple - Set Default Totals
        $total = $searchTotal = Spry::db($dbMeta)->count($table, $join, $table.'.id', $where);

        // If has Orderby then set Order
        if (!empty($meta['orderby']) && !empty($meta['order'])) {
            $where['ORDER'] = [$meta['orderby'] => $meta['order']];
        } else {
            $where['ORDER'] = [$table.'.id' => 'DESC'];
        }

        // If has Search then set Search Parameter and Fields to search on
        if (!empty($meta['search']) && !empty($searchFields)) {
            $where['OR'] = [];
            foreach ($searchFields as $searchField) {
                $where['OR'][$searchField.'[~]'] = $meta['search'];
            }
            $searchTotal = Spry::db($dbMeta)->count($table, $join, $table.'.id', $where);
        }

        $pagination = self::dbGetPagination($meta, $total, $searchTotal);

        if (!empty($pagination->limit)) {
            $where['LIMIT'] = $pagination->limit;
            $responseMeta = [
                'pagination' => $pagination->meta,
            ];
        }

        return (object) ['where' => $where, 'meta' => $responseMeta, 'total' => $total, 'search_total' => $searchTotal];
    }



    /**
     * Creates the Pagination object.
     *
     * @param array $meta
     * @param array $total
     * @param array $searchTotal
     *
     * @access public
     *
     * @return array
     */
    public static function dbGetPagination($meta, $total, $searchTotal)
    {
        $pagination = (object) [];
        $pagination->page = !empty($meta['pagination_page']) ? $meta['pagination_page'] : 1;
        $pagination->pageLimit = !empty($meta['pagination_page_limit']) ? $meta['pagination_page_limit'] : 10;
        $pagination->count = !empty($meta['pagination_count']) ? $meta['pagination_count'] : 1000;

        $pagination->limit = null;
        if ($searchTotal && $searchTotal > $pagination->count) {
            $pagination->limit = [(($pagination->page - 1) * $pagination->pageLimit), $pagination->pageLimit];
        }

        $pagination->meta = [
            'total' => $total,
            'search_total' => $searchTotal,
            'page' => $pagination->page,
            'page_limit' => $pagination->pageLimit,
        ];

        return $pagination;
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
        $responseCode = 250;

        $result = [];

        if (is_string($test)) {
            if (empty(Spry::config()->tests)) {
                Spry::stop(52);
            }

            if (!isset(Spry::config()->tests[$test])) {
                Spry::stop(53);
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

        $headers = !empty($test['headers']) && is_array($test['headers']) ? $test['headers'] : [];
        $headers[] = 'SpryTest: 1';

        $params = !empty($test['params']) && is_array($test['params']) ? $test['params'] : [];

        $response = self::getRemoteResponse(
            Spry::config()->endpoint.$test['route'],
            array_merge($params, ['test_data' => 1]),
            $headers,
            $method
        );

        $response = json_decode($response, true);

        $result['full_response'] = $response;

        if (!empty($test['expect']) && is_array($test['expect'])) {
            $result['result'] = [];

            if (empty($test['expect'])) {
                $result['status'] = 'Failed';
                $responseCode = 50;
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
                        $responseCode = 50;
                    }

                    if ($expectCompare && !is_null($responseValue)) {
                        switch ($expectCompare) {
                            case '!==':
                                if ($responseValue === $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 50;
                                }

                                break;

                            case '!=':
                            case '!':
                                if ($responseValue === $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 50;
                                }

                                break;

                            case '<=':
                            case '=<':
                                if ($responseValue > $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 50;
                                }

                                break;

                            case '>=':
                            case '=>':
                                if ($responseValue < $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 50;
                                }

                                break;

                            case '<':
                                if ($responseValue >= $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 50;
                                }

                                break;

                            case '>':
                                if ($responseValue <= $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 50;
                                }

                                break;

                            case '==':
                            case '=':
                                if ($responseValue !== $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 50;
                                }

                                break;

                            case '===':
                            default:
                                if ($responseValue !== $expect) {
                                    $result['status'] = 'Failed';
                                    $responseCode = 50;
                                }

                                break;
                        }
                    }
                }
            }
        }

        return Spry::response($result, $responseCode, $result['status'] === 'Passed' ? 'success' : 'error');
    }
}
