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
	}
	
	protected function GetEditablesFromBody($editable_columns)
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
	}

	final public function getResponseStatus() {
		return $this->responseStatus;
	}

	final public function getResponse() {
		return $this->response;
	}

	public function checkAuth() 
	{
		//return true;
		
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
				throw new Exception('Bad Request', 400);
			else
				return null;
		}
		
		return mysql_real_escape_string($val);
	}
	
	protected function throwMySQLError()
	{
		throw new Exception( mysql_error(), 500 );
	}
	
	protected function throwForbidden()
	{
		throw new Exception( 'Forbidden', 403 );
	}

	// @codeCoverageIgnoreStart
	abstract public function get();
	abstract public function post();
	abstract public function put();
	abstract public function delete();
	// @codeCoverageIgnoreEnd
	
}

?>
