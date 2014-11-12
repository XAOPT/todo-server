<?php
/*
 * Copyright 2011 <http://voidweb.com>.
 * Author: Deepesh Malviya <https://github.com/deepeshmalviya>.
 *
 * Simple-REST - Lightweight PHP REST Library
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

/**
 * Class implements RESTfulness
 */
class Rest {

    private $request = array(); // Array storing request
    private $response; // Array storing response

    const DEFAULT_RESPONSE_FORMAT = 'json'; // Default response format

    public function __construct() {
        $this->processRequest();
    }

    private function processRequest()
    {
        $resource = parse_url($_SERVER['REQUEST_URI']);

        $this->request['route']    = trim($resource['path'], '/');
        $this->request['resource'] = explode( '/', $this->request['route'] );
        $this->request['method']   = strtolower($_SERVER['REQUEST_METHOD']);
        $this->request['headers']  = $this->getHeaders();
        $this->request['format']   = isset($_REQUEST['format']) ? trim($_REQUEST['format']) : null;

        switch($this->request['method']) {
            case 'get':
                $this->request['params'] = $_GET;
                break;
            case 'post':
                $this->request['params'] = array_merge($_GET);
                $this->request['body'] = file_get_contents('php://input');
                break;
            case 'put':
                $this->request['params'] = array_merge($_GET);
                $this->request['body'] = file_get_contents('php://input');
                break;
            case 'delete':
                $this->request['params'] = $_GET;
                $this->request['body'] = file_get_contents('php://input');
                break;
            default:
                break;
        }

        $this->request['content-type'] = $this->getResponseFormat($this->request['format']);
        if(!function_exists('trim_value')) {
            function trim_value(&$value) {
                $value = trim($value);
            }
        }
        array_walk_recursive($this->request, 'trim_value');

        $this->process();
    }

    public function process()
    {
        try {
            $this->request['controller'] = $this->request['resource'][0];

            if ($this->request['method'] == 'options')
                throw new Exception('Options', 200);

            $controllerName = $this->getController();

            if(null == $controllerName) {
                throw new Exception('Method not allowed', 405);
            }

            $controller = new ReflectionClass($controllerName);
            if(!$controller->isInstantiable()) {
                throw new Exception('Bad Request', 400);
            }

            $controller_object = $controller->newInstance($this->request);

            $user_id = $controller_object->checkAuth();

            if (!$user_id) {
                throw new Exception('Unauthorized', 401);
            }

            $routes = $controller_object->routes();
            if (!isset($routes[$this->request['method']]))
            {
                throw new Exception('Method not allowed', 405);
            }
            else
            {
                foreach ($routes[$this->request['method']] as $regex => $func_name)
                {
                    $regex = preg_replace('/\//','\/',$regex);
                    if (preg_match('/^'.$regex.'$/', $this->request['route']))
                    {
                        $method = $controller->getMethod($func_name);
                    }
                }
            }

            if (!isset($method))
                throw new Exception('Route not found', 500);

            $method->invoke($controller_object);
            $this->response = $controller_object->getResponse();
            $this->responseStatus = $controller_object->getResponseStatus();

            //$this->writeRequestLog($user_id);

            if(is_null($this->response)) {
                throw new Exception('Answer is empty', 405);
            }
        } catch (Exception $re) {
            $this->responseStatus = $re->getCode();
            $this->response = array('ErrorCode' => $re->getCode(), 'ErrorMessage' => $re->getMessage());
        }

        $this->response()->send();
    }

    /**
     * Function to resolve constroller from the Controllers
     * directory based on resource name request.
     */
    private function getController() {
        $expected = $this->request['controller'];

        if (file_exists(APPLICATION_PATH . '/Controllers/'.$expected.'.php'))
            return 'Controllers_' . $expected;
        else
            return null;
    }

    /**
     * Function implementating json response helper.
     * Converts response array to json.
     */
    private function jsonResponse() {
        //return json_encode($this->response);
        return preg_replace_callback(
            '/\\\u([0-9a-fA-F]{4})/',
            create_function('$match', 'return mb_convert_encoding("&#" . intval($match[1], 16) . ";", "UTF-8", "HTML-ENTITIES");'),
            json_encode($this->response)
        );
    }

    /**
     * Function implementing querystring response helper
     * Converts response array to querystring.
     */
    private function qsResponse() {
        return http_build_query($this->response);
    }

    private function response() {
        if(!empty($this->response)) {
            $method = $this->request['content-type'] . 'Response';
            $this->response = array('status' => $this->responseStatus, 'body' => $this->$method());
        } else {
            $this->request['content-type'] = 'qs';
            $this->response = array('status' => $this->responseStatus, 'body' => $this->response);
        }

        return $this;
    }

    /**
     * Function to get HTTP headers
     */
    private function getHeaders() {
        if(function_exists('apache_request_headers')) {
            return apache_request_headers();
        }
        $headers = array();
        $keys = preg_grep('{^HTTP_}i', array_keys($_SERVER));
        foreach($keys as $val) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($val, 5)))));
                $headers[$key] = $_SERVER[$val];
            }
        return $headers;
    }

    private static $codes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
    );

    /**
     * Function returns HTTP response message based on HTTP response status code
     */
    private function getStatusMessage($status) {
        return (isset(self::$codes[$status])) ? self::$codes[$status] : self::$codes[500];
    }

    private static $formats = array('json', 'qs');

    /**
     * Function returns response format from allowed list
     * else the default response format
     */
    private function getResponseFormat($format) {
        return (in_array($format, self::$formats)) ? $format : self::DEFAULT_RESPONSE_FORMAT;
    }

    private static $contentTypes = array(
        'json' => 'application/json',
        'qs'   => 'text/plain'
    );

    /**
     * Function returns response content type.
     */
    private function getResponseContentType($type = null) {
        return self::$contentTypes[$type];
    }

    private function send() {
        $status = (isset($this->response['status'])) ? $this->response['status'] : 200;
        $contentType = $this->getResponseContentType($this->request['content-type']);
        $body = (empty($this->response['body'])) ? '' : $this->response['body'];

        $headers = 'HTTP/1.1 ' . $status . ' ' . $this->getStatusMessage($status);
        header($headers);
        header('Content-Type: ' . $contentType);
        echo $body;
    }

    protected function throwMySQLError($method = '', $query = '')
    {
        if ($method && $query)
        {
            file_put_contents('throwMySQLError.log', "[".date('Y-m-d H:i:s')." {$method}] {$query}\n", FILE_APPEND);
            file_put_contents('throwMySQLError.log', mysql_errno() . ": " . mysql_error() . "\n", FILE_APPEND);
        }
        else
        {
            file_put_contents('debug_backtrace.log', mysql_errno() . ": " . mysql_error() . "\n", FILE_APPEND);
            file_put_contents('debug_backtrace.log', print_r(debug_backtrace(), true), FILE_APPEND);
        }

        throw new Exception( DEVMODE ? mysql_error() : "DB error", 500 );
    }

    protected function writeRequestLog($userid = 0)
    {
        if (in_array($this->request['method'], array('post', 'put', 'delete')))
            mysql_query("INSERT INTO `todo_log` (user_id, method, request_uri, body) VALUES ('{$userid}', '{$this->request['method']}', '{$_SERVER['REQUEST_URI']}', '{$this->request['body']}')") or $this->throwMySQLError();
    }
}

