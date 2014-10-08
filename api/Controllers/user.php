<?php

class Controllers_user extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'user' => 'GetUsers'
            ),
            'post' => array(
                'user' => 'CreateUser'
            ),
            'put' => array(
                'user/\d+'   => 'EditUser'
            )
        );
    }

    public function checkUserExists($user_id = 0)
    {
        $result = mysql_query( "SELECT * FROM `todo_user` WHERE id='{$user_id}'" ) or $this->throwMySQLError();
        if (!mysql_num_rows($result))
            throw new Exception( 'Not Found', 404 );
    }

    public function GetUsers()
    {
        $id        = intval($this->getRequestParamValue('id', false));

        $from      = intval($this->getRequestParamValue('from', false));
        $count     = intval($this->getRequestParamValue('count', false));

        if (!$from) $from = 0;
        if (!$count) $count = 100;

        $where = array();

        if ($id) {
            $where[] = "`id`={$id}";
        }

        if (empty($where))
            $where[] = "1=1";

        $where = implode(" AND ", $where);

        $items = array();
        $query = mysql_query("SELECT * FROM `todo_user` WHERE {$where} LIMIT {$from}, {$count}") or $this->throwMySQLError();

        while( $obj = mysql_fetch_array( $query ) )
        {
            $items[] = $this->normalizeObject( $obj );
        }

        $this->response = array(
            "status" => 0,
            "from" => $from,
            "count" => $count,
            "items" => $items
        );
        $this->responseStatus = 200;
    }

    public function CreateUser()
    {
        ## user [POST]: Creates new user.
        /*if ( !$this->loggedUser->hasPermission( User::PERMISSION_PEOPLE_MANAGEMENT ) )
            $this->throwForbidden();*/

        $data = $this->GetParamsFromRequestBody('create');

        $this->insertArrayIntoDatabase('todo_user', $data);
        $id = mysql_insert_id();

        $this->response = array(
            "status" => 0,
            "id" => $id
        );
        $this->responseStatus = 201;
    }

    public function EditUser()
    {
        ## user/<id> [PUT]: Modifies the specified user.
        /*if ( !$this->loggedUser->hasPermission( User::PERMISSION_PEOPLE_MANAGEMENT ))
            $this->throwForbidden();*/

        $user_id = $this->getResourceNamePart( 1 );

        $this->checkUserExists($user_id);

        /*$role = $this->getRequestBodyValue( 'role', false );

        if ($role == 'admin' && !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER ))
            $this->throwForbidden();*/

        $data = $this->GetParamsFromRequestBody('edit');

        $this->UpdateDatabaseFromArray('todo_user', $data, "id='{$user_id}'");

        $this->response = array(
            "status" => 0,
            "id" => $user_id
        );

        $this->responseStatus = 202; // accepted
    }

    /* ------------------------------------------------------ */

    /*public static function getUserFromDatabase( $id )
    {
        $query = mysql_query( "SELECT * FROM `todo_user` WHERE `id`=$id" ) or $this->throwMySQLError();
        if ( $object = mysql_fetch_array( $query ) )
            return $object;

        throw new Exception( 'Not Found', 404 );
    }*/

    /*public static function createUserFromDatabaseObject( $dbobj )
    {
        if ( !isset( $dbobj ) || empty($dbobj) )
            throw new Exception( 'Not Found', 404 );

        $user = array(
            'class'       => 'user',
            'id'          => (int)$dbobj['id'],
            'deleted'     => (bool)$dbobj['deleted'],
            'username'    => (string)$dbobj['username'],
            'role'        => (string)$dbobj['role'],
            'def_prole'   => (string)$dbobj['def_prole'],
            'group'       => (string)$dbobj['group'],
            'firstname'   => (string)$dbobj['firstname'],
            'lastname'    => (string)$dbobj['lastname']
            );

        return $user;
    }*/

    /*public function put()
    {
        switch ( $this->getResourceNamePartsCount() )
        {
            case 3:
                ## user/<id>/projectrole
                if ( $this->getResourceNamePart( 2 ) == 'projectrole' )
                {
                    if ( !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER ) && !$this->loggedUser->hasPermission( User::PERMISSION_PEOPLE_MANAGEMENT ) )
                        $this->throwForbidden();

                    $user_id = intval($this->getResourceNamePart( 1 ));
                    $project = (int)$this->getRequestBodyValue( 'project' );
                    $role    = $this->getRequestBodyValue( 'role', false );

                    if (!$project)
                        throw new Exception( 'Bad Request', 400 );

                    if ($role)
                        mysql_query( "INSERT INTO `todo_prole` (user_id, project, role) VALUES('{$user_id}','{$project}','{$role}') ON DUPLICATE KEY UPDATE role='{$role}'" ) or $this->throwMySQLError();
                    else
                        mysql_query( "DELETE FROM `todo_prole` WHERE user_id='{$user_id}' AND project='{$project}'" ) or $this->throwMySQLError();

                    $this->response       = 'ok';
                    $this->responseStatus = 202;
                }
        }

        return null;
    }*/
}

?>
