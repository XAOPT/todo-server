<?php

/**
 * Abstract Controller
 * To be extended by every controller in application
 */
abstract class RestController {

    protected $request;
    protected $response;
    protected $responseStatus;
    protected $jsonbody;
    public    $loggedUser;
    public    $schema;

    public function __construct($request)
    {
        $this->request = $request;

        if ( isset( $this->request['body'] ) )
            $this->jsonbody = json_decode( $this->request['body'], true );
    }

    public function checkAuth()
    {
        $auth_token = mysql_real_escape_string($this->request['params']['auth_token']);
        $userid     = intval($this->request['params']['session_user']);

        if ( !isset( $auth_token ) || !isset($userid) )
            return false;

        $query = mysql_query( "SELECT * FROM `todo_session` WHERE auth_token='$auth_token' AND userid={$userid}" ) or $this->throwMySQLError();
        if ( $session = mysql_fetch_array( $query ) )
        {
            // юзер под которым вошли в систему.
            $this->loggedUser = User::createFromDatabase( (int)$session['userid'] );
            //if ( !isset( $this->loggedUser ) || !$this->loggedUser->hasPermission( 'system.access' ) )
            if ( !isset( $this->loggedUser ))
            {
                $this->dropSessionByAuthToken( $auth_token );
                return false;
            }

            return $this->loggedUser->getId();
        }

        return false;
    }

    protected function dropSessionByAuthToken( $auth_token )
    {
        mysql_query( "DELETE FROM `todo_session` WHERE auth_token='{$auth_token}'" );
    }

    protected function insertArrayIntoDatabase($table_name, $array = array())
    {
        if (empty($array))
            return false;

        $var = array();
        $val = array();

        foreach ($array AS $k=>$v) {
            $vars[] = "`".$k."`";

            if ($v != 'NOW()')
                $val[]  = "'".mysql_real_escape_string($v)."'";
            else
                $val[]  = $v;
        }

        $var    = implode(",", $vars);
        $values = implode(",", $val);

        $query = "INSERT INTO ".$table_name." (".$var.") VALUES (".$values.")";

        mysql_query( $query ) or $this->throwMySQLError();

        return mysql_insert_id();
    }

    /**
     * Обновляет строку в таблице $table, определённую условием $where.
     * @param string $table - имя таблицы
     * @param string $array - хеш-массив. Ключ элемента - имя столбца. Значение элемента - устанавливаемое значение.
     * @param string $where - условие выбора строки.
     */
    protected function UpdateDatabaseFromArray ($table, $array, $where) {
        $vars = array();

        foreach ($array AS $k=>$v) {
            if ($v != 'NOW()')
                $vars[]="`".$k."`='".$v."'";
            else
                $vars[]="`".$k."`=".$v."";
        }

        if (empty($vars))
            return false;

        $var = implode(",", $vars);

        $query = "UPDATE ".$table." SET ".$var." WHERE ".$where;
        if (defined("DB_PREFIX"))
            $query = preg_replace('/\#\#/', DB_PREFIX, $query);
        mysql_query( $query ) or $this->throwMySQLError();
    }

    protected function getResourceNamePartsCount()
    {
        return count( $this->request['resource'] );
    }

    protected function getResourceNamePart( $index )
    {
        return $this->request['resource'][$index];
    }

    // Returns a value of the request JSON object for the specified key.
    protected function getRequestBodyValue( $name, $expected = true )
    {
        $val = $this->jsonbody[$name];
        if ( !isset( $val ) )
        {
            if ( $expected )
                throw new Exception("Bad Request. ".$name." is undefined", 400);
            else
                return null;
        }

        if (!is_array($val))
            return mysql_real_escape_string($val);
        else
            return $val;
    }

    protected function getRequestParamValue( $name, $expected = true )
    {
        $val = $this->request['params'][$name];

        if ( !isset( $val ) )
        {
            if ( $expected )
                throw new Exception("Bad Request: param var '{$name}' is not set", 400);
            else
                return null;
        }

        if (!is_array($val))
            return mysql_real_escape_string($val);
        else {
            foreach ($val as &$v) {
                $v = mysql_real_escape_string($v);
            }
            return $val;
        }
    }

    protected function throwMySQLError()
    {
        throw new Exception( mysql_error(), 500 );
    }

    final public function getResponseStatus() {
        return $this->responseStatus;
    }

    final public function getResponse() {
        return $this->response;
    }

    protected function setSchema($scope = false)
    {
        if (!file_exists("schemas/".$scope.".json") || !$scope)
            throw new Exception("Unknown schema", 400);

        $schema = file_get_contents("schemas/".$scope.".json");
        $this->schema[$scope] = json_decode($schema, true);
    }

    protected function getSchema($scope = false)
    {
        if (!$scope)
            $scope = $this->request['controller'];

        if (empty($this->schema[$scope]))
            $this->setSchema($scope);

        return $this->schema[$scope];
    }

    protected function normalizeObject($data = array(), $scope = '')
    {
        $schema = $this->getSchema($scope);

        $output = array();

        if (count($data))
        foreach ($data as $key => $value)
        {
            if (!isset($schema['param'][$key]))
                continue;

            switch ($schema['param'][$key])
            {
                case "int":
                    $output[$key] = (int)$value;
                    break;
                case "string":
                case "date":
                    $output[$key] = (string)$value;
                    break;
                case "timestamp":
                    $output[$key] = strtotime($value);
                    break;
                case "bool":
                    $output[$key] = (bool)$value;
                    break;
                case "json":
                    if (is_array($value)) {
                        // если на входе массив - значит надо зажать
                        $output[$key] = json_encode($value);
                    }
                    else {
                        $temp = json_decode($value, true);
                        if (isset($temp))
                            $output[$key] = $temp;
                        else
                            $output[$key] = json_encode($value);
                    }
                    break;
            }
        }

        return $output;
    }

    protected function GetParamsFromRequestBody($action = '', $is_owner = false)
    {
        // получим схему
        $schema = $this->getSchema($scope);

        if (!$action || !isset($schema[$action]))
            throw new Exception("GetParamsFromRequestBody issue", 400);

        // если данное действие не разрешает править какие-то поля - убираем их
        $param = $schema['param'];

        if (isset($schema[$action]['forbidden_fields']) && count($schema[$action]['forbidden_fields']))
        foreach ($schema[$action]['forbidden_fields'] as $key) {
            unset($param[$key]);
        }

        // если есть разграничения по уровню доступа - проверим их. Если админ, то ему можно всё
        if (isset($schema[$action]['access']) && count($schema[$action]['access']) && !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER )) {

            $acceptable_fields = array();
            foreach ($schema[$action]['access'] as $permission => $fields)
            {
                if (($permission == "owner" && $is_owner == true) || $this->loggedUser->hasPermission( $permission ))
                {
                    if (count($fields) == 1 && $fields[0] == "*")
                        $fields = array_keys($param);

                    $acceptable_fields = array_merge($acceptable_fields, $fields);
                }
            }

            if (empty($acceptable_fields))
                $this->throwForbidden();

            foreach ($param as $key => $value) {
                if (!in_array($key, $acceptable_fields))
                    unset($param[$key]);
            }
        }

        // получим взодящие параметры
        $output = array();

        if (count($param))
        foreach ($param as $key => $value)
        {
            // узнаем из схемы, необходим ли параметр
            $expected = false;
            if (isset($schema[$action]['require']) && in_array($key, $schema[$action]['require']))
                $expected = true;

            $temp = $this->getRequestBodyValue( $key, $expected );

            if ($expected) {
                if (!isset($temp))
                    throw new Exception("Bad Request: param var '{$key}' is null", 400);
                else if ($schema['param'][$key] == 'string' && empty($temp))
                    throw new Exception("Bad Request: param var '{$key}'. Required strings can't be empty", 400);
            }

            if (isset($temp)) {
                $output[$key] = $temp;
            }
        }

        $output = $this->normalizeObject($output);

        return $output;
    }

    protected function throwForbidden()
    {
        throw new Exception( 'Forbidden action', 403 );
    }

    abstract public function routes();
}

?>
