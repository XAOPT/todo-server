<?php

class Controllers_auth extends RestController 
{
	function getUserIdFromDatabase( $username, $password = '' )
	{
		if (!empty($password))
			$password = md5($password);
                
		$query = mysql_query( "SELECT id FROM `todo_user` WHERE username='$username' AND password='{$password}'" ) or $this->throwMySQLError();

		if ( $user = mysql_fetch_array( $query ) )
			return (int)$user['id'];
		else
			return 0;
	}
        
	public function get() {
		return null;
	}
        
	public function post() 
	{    
		if ( $this->getResourceNamePartsCount() != 1 )
			return null;
		
		$username = $this->getRequestBodyValue('username');
		$password = $this->getRequestBodyValue('password', false );
		
		$user_id = $this->getUserIdFromDatabase( $username, $password );
		if ( $user_id <= 0 )
			throw new Exception( 'Unauthorized', 401 );

		// Проверим наличие доступа к системе.
		$this->loggedUser = User::createFromDatabase( $user_id );
		if ( !isset( $this->loggedUser ) || !$this->loggedUser->hasPermission( 'system.access' ) )
			throw new Exception( 'Forbidden', 403 );
		
		// создаем новую сессию для данного юзера.
		mysql_query( "DELETE FROM `session` WHERE user_id=$user_id" ) or $this->throwMySQLError();
	
		$auth_token = md5( microtime() );
		$ipaddr = (string)$_SERVER['REMOTE_ADDR'];
		$user_agent = (string)$_SERVER['HTTP_USER_AGENT'];
	
		mysql_query( "INSERT INTO `session` (auth_token,user_id,ipaddr,user_agent)
				 VALUES('$auth_token',$user_id,'$ipaddr','$user_agent')" ) or $this->throwMySQLError();
		
		$this->response = array(
			'class'      => 'session', 
			'auth_token' => $auth_token,
			'user_id'    => $user_id 
		);
		
		$this->responseStatus = 200;
	}
        
	public function put() {
            return null;
	}
        
	public function delete() {
		return null;
	}
        
        
        public function checkAuth() {
		return true;
	}
}

?>
