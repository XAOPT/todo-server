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

    protected function insertArrayIntoDatabase($table_name, $array)
    {
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

/*    protected function GetEditablesFromBody($editable_columns)
    {
        $sets = array();

        foreach ($this->jsonbody as $key => $value)
        {
            if (in_array($key, $editable_columns))
            {
                $val = $this->getRequestBodyValue( $key );
                $sets[] = "{$editable_columns[$key]}='{$val}'";
            }
        }

        return $sets;
    }*/

    public function checkAuth()
    {
        return true;

        $auth_token = $this->request['params']['auth_token'];
        if ( !isset( $auth_token ) )
            return FALSE;

        $query = mysql_query( "SELECT * FROM `session` WHERE auth_token='$auth_token'" ) or $this->throwMySQLError();
        if ( $session = mysql_fetch_array( $query ) )
        {
            // если сменился айпишник, то оборвем сессию.
            $ipaddr = (string)$_SERVER['REMOTE_ADDR'];
            if ( $ipaddr != $session['ipaddr'] )
            {
                $this->dropSessionByAuthToken( $auth_token );
                return false;
            }

            // юзер под которым вошли в систему.
            $this->loggedUser = User::createFromDatabase( (int)$session['user_id'] );
            if ( !isset( $this->loggedUser ) || !$this->loggedUser->hasPermission( 'system.access' ) )
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
        mysql_query( "DELETE FROM `session` WHERE auth_token='$auth_token'" );
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

        return mysql_real_escape_string($val);
    }

    protected function getRequestParamValue( $name, $expected = true )
    {
        $val = $this->request['params'][$name];

        if ( !isset( $val ) )
        {
            if ( $expected )
                throw new Exception("Bad Request: param var '{$name}' is empty", 400);
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

    protected function setSchema($scope)
    {
        if (!file_exists("../schemas/".$scope.".json") || !$scope)
            throw new Exception("Unknown schema", 400);

        $schema = file_get_contents("../schemas/".$scope.".json");
        $this->schema = json_decode($schema, true);
    }

    protected function getSchema($scope)
    {
        if (!$scope)
            $scope = $this->request['controller'];

        if (empty($this->schema))
            $this->setSchema($scope);

        return $this->schema;
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
            }
        }

        return $output;
    }

    protected function GetParamsFromRequestBody($action = '', $scope = '')
    {
        // получим схему
        $schema = $this->getSchema($scope);

        if (!$action || !isset($schema[$action]))
            throw new Exception("GetParamsFromRequestBody issue", 400);

        // если данное действие не разрешает правиь какие-то поля - убираем их
        $param = $schema['param'];

        if (isset($schema[$action]['forbidden_fields']) && count($schema[$action]['forbidden_fields']))
        foreach ($schema[$action]['forbidden_fields'] as $key) {
            unset($param[$key]);
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
            if ($temp)
                $output[$key] = $temp;
        }

        $output = $this->normalizeObject($output);

        return $output;
    }

    abstract public function routes();
}

?>
