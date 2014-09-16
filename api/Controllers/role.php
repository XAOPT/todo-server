<?php

class Controllers_role extends RestController 
{
 	public static function createRoleFromDatabaseObject( $dbobj )
	{
		if ( !isset( $dbobj ) ) 
			return null;
		
		$role = array( 
			'class'       => 'role',
			'id'          => (int)$dbobj['id'],
			'sysrole'     => (bool)$dbobj['sysrole'],
			'sysname'     => (string)$dbobj['sysname'],
			'name'        => (string)$dbobj['name'],
			'permissions' => json_decode( $dbobj['permissions'], true ),
		);
		
		return $role;
	}
        
	public function get() 
	{
		switch ( $this->getResourceNamePartsCount() )
		{
			case 1:
				## role [GET]: Returns list of all system roles.
				$roles = array( 'class' => 'userRoleList' );
	
				$items = array();
				$query = mysql_query( "SELECT * FROM `todo_role`" ) or $this->throwMySQLError();
				while( $db_role = mysql_fetch_array( $query ) )
				{
					$role = $this->createRoleFromDatabaseObject( $db_role );
					if ( $role != null )
						$items[] = $role;
				}

				$roles['items'] = $items;
				
				$this->response = $roles;
				$this->responseStatus = 200;
				break;
		}
			
		return null;
	}
        
	public function post() {
            switch ( $this->getResourceNamePartsCount() )
            {
                ## role [POST]: Creates new role with the specified system name.
                case 1:
                    if ( !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER ) )
                        $this->throwForbidden();
                    
                    $sysname = $this->getRequestBodyValue( 'sysname' );
                    $sysrole = (int)$this->getRequestBodyValue( 'sysrole', false );

					mysql_query( "INSERT INTO `todo_role` (`sysname`,`sysrole`) VALUES('$sysname',$sysrole)" ) or $this->throwMySQLError();
					$id = mysql_insert_id();

					$this->response = array('id'=>$id);
					$this->responseStatus = 201; // created

                    break;
            }
            
            return null;
	}
        
	public function put() 
        {
            switch ( $this->getResourceNamePartsCount() )
            {
                ## role/<id> [PUT]: Modifies the role.
                case 2:
                    if ( !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER ) )
                        $this->throwForbidden();
                    
					$role_id = (int)$this->getResourceNamePart( 1 );
					
					$editable_columns = array(
						'permissions' => 'permissions', 
						'name'        => 'name'
					);
				
					$sets = $this->GetEditablesFromBody($editable_columns);
					$sets = implode(',', $sets);
					
					if ($sets)
						mysql_query( "UPDATE `todo_role` SET {$sets} WHERE role_id='{$role_id}'" ) or $this->throwMySQLError();
					
					$this->response = 'ok'; // accepted
					$this->responseStatus = 202; // accepted
					break;
            }
            
            return null;
	}
        
	public function delete() {
		
		## role/<id> [DELETE] system role delete
		$role_id = (int)$this->getResourceNamePart( 1 );
				
		$query = mysql_query("SELECT * FROM `todo_role` WHERE role_id='{$role_id}'");
		$role = mysql_fetch_array($query);
		
		if ( !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER ) || $role['sysname'] == 'admin')
            $this->throwForbidden();
		
		if ($role['sysrole'] == 1)
			mysql_query("UPDATE `todo_user` SET role='' WHERE role='{$role['sysname']}'");
		else
		{
			mysql_query("UPDATE `todo_user` SET def_prole='' WHERE def_prole='{$role['sysname']}'");
			mysql_query("DELETE FROM `todo_prole` WHERE role='{$role['sysname']}'");			
		}
		
		mysql_query("DELETE FROM `todo_role` WHERE role='{$role['sysname']}'");
		
		$this->response = 'ok';
		$this->responseStatus = 202;
	}
}

?>
