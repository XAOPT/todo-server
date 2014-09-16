<?php

class Controllers_user extends RestController
{
    public static function getUserFromDatabase( $id )
    {
        $query = mysql_query( "SELECT * FROM `todo_user` WHERE `id`=$id" ) or $this->throwMySQLError();
        if ( $object = mysql_fetch_array( $query ) )
            return $object;

        throw new Exception( 'Not Found', 404 );
    }

    public static function createUserFromDatabaseObject( $dbobj )
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
    }

    public function get()
    {
        switch ( $this->getResourceNamePartsCount() )
        {
            case 3:
                ## user/<id>/timesheet?day=<day> [GET]: Возвращает таймшит указанного юзера на указанный день по всем задачам над которыми он работал.
                if ( $this->getResourceNamePart( 2 ) == 'timesheet' )
                {
                    $day    = intval($_GET['day']);
                    $worker = $this->getResourceNamePart( 1 );

                    $controllers_timesheet = new Controllers_timesheet($this->request);
                    $sheets = $controllers_timesheet->_getUserTimesheet($worker, $day);

                    $this->response = $sheets;
                    $this->responseStatus = 200;
                }
                ## user/<id>/calendar?from=15275&count=100 [GET] Получить отличия в календаре юзера от дефолтного календаря.
                else if ( $this->getResourceNamePart( 2 ) == 'calendar' )
                {
                    $worker = $this->getResourceNamePart( 1 );

                    $calendar_controller = new Controllers_calendar($this->request);

                    $this->response = $calendar_controller->GetCalendarByUser($worker);
                    $this->responseStatus = 200;
                }
                ## user/<id>/clientSettings [GET]: получаем клиентские настройки пользователя
                ## user/<id>/vars [GET]:           получаем доп. инфо о пользователе
                else if ( $this->getResourceNamePart( 2 ) == 'clientSettings' ||  $this->getResourceNamePart( 2 ) == 'vars')
                {
                    $user_id = $this->getResourceNamePart( 1 );

                    $query = mysql_query( "SELECT ".$this->getResourceNamePart( 2 )." FROM `todo_user` WHERE id='{$user_id}'" ) or $this->throwMySQLError();
                    $temp  = mysql_fetch_array($query, MYSQL_NUM);

                    if (empty($temp))
                        throw new Exception( 'Not Found', 404 );

                    $this->response       = $temp[0];
                    $this->responseStatus = 200;
                }
                break;
            case 2:
                ## user/<id> [GET]: Returns full user info.
                $user_id = $this->getResourceNamePart( 1 );

                $query = mysql_query( "SELECT * FROM `todo_user` WHERE id='{$user_id}'" ) or $this->throwMySQLError();
                $db_user = mysql_fetch_array( $query );

                $this->response       = $this->createUserFromDatabaseObject( $db_user );
                $this->responseStatus = 200;
                break;
            case 1:
                ## user [GET]: Returns all users.
                $users = array( 'class' => 'userList' );

                $items = array();
                $query = mysql_query( "SELECT * FROM `todo_user`" ) or $this->throwMySQLError();
                while( $db_user = mysql_fetch_array( $query ) )
                {
                    $user = $this->createUserFromDatabaseObject( $db_user );
                    if ( $user != null )
                        $items[] = $user;
                }

                $users['items'] = $items;

                $this->response = $users;
                $this->responseStatus = 200;
                break;
        }

        return null;
    }

    public function post()
    {
        switch ( $this->getResourceNamePartsCount() )
        {
            ## user [POST]: Creates new user.
            case 1:
                if ( !$this->loggedUser->hasPermission( User::PERMISSION_PEOPLE_MANAGEMENT ) )
                    $this->throwForbidden();

                $username = $this->getRequestBodyValue( 'username' );

                $data = array(
                    'username'  => $username,
                    'created'   => 'NOW()',
                    'createdby' => $this->loggedUser->getId()
                );

                $this->insertArrayIntoDatabase('todo_user', $data);
                $id = mysql_insert_id();

                $this->response = array( 'id' => $id );
                $this->responseStatus = 201; // created

                break;
        }

        return null;
    }

    public function put()
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

                ## user/<id>/password [PUT]: Change password
                if ( $this->getResourceNamePart( 2 ) == 'password' )
                {
                    $user_id = intval($this->getResourceNamePart( 1 ));

                    if ( $user_id != $this->loggedUser->getId() && !$this->loggedUser->hasPermission( User::PERMISSION_PEOPLE_MANAGEMENT ) )
                        $this->throwForbidden();

                    $password = $this->getRequestBodyValue( 'password' );

                    $query = mysql_query( "SELECT role FROM `todo_user` WHERE id='{$user_id}'" ) or $this->throwMySQLError();
                    $row   = mysql_fetch_array($query, MYSQL_NUM);
                    $role  = $row[0];

                    if ($role == 'admin' && $user_id != $this->loggedUser->getId() && !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER ))
                        $this->throwForbidden();

                    mysql_query( "UPDATE `todo_user` SET password='{$password}' WHERE id='{$user_id}'" ) or $this->throwMySQLError();

                    $this->response = 'ok';
                    $this->responseStatus = 200;
                }

                ## user/<id>/calendar?day=15275
                if ( $this->getResourceNamePart( 2 ) == 'calendar' )
                {
                    if (
                    !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER )
                    && !$this->loggedUser->hasPermission( User::PERMISSION_PEOPLE_MANAGEMENT )
                    )
                        $this->throwForbidden();

                    $worker = intval($this->getResourceNamePart( 1 ));

                    $calendar_controller = new Controllers_calendar($this->request);

                    $this->response = $calendar_controller->PutCalendarRow($worker);
                    $this->responseStatus = 200;
                }

                ## user/<id>/clientSettings [PUT]: переопределяет клиентские настройки пользователя
                if ( $this->getResourceNamePart( 2 ) == 'clientSettings' )
                {
                    $user_id = $this->getResourceNamePart( 1 );

                    if ( $user_id != $this->loggedUser->getId())
                        $this->throwForbidden();

                    mysql_query( "UPDATE `todo_user` SET clientSettings='{$this->request['body']}' WHERE id='{$user_id}'" ) or $this->throwMySQLError();

                    $this->response = 'ok';
                    $this->responseStatus = 200;
                }

                ## user/<id>/vars [PUT]:           переопределяет доп. инфо о пользователе
                if ( $this->getResourceNamePart( 2 ) == 'vars' )
                {
                    $user_id = $this->getResourceNamePart( 1 );

                    if ( $user_id != $this->loggedUser->getId() && !$this->loggedUser->hasPermission( User::PERMISSION_PEOPLE_MANAGEMENT ) )
                        $this->throwForbidden();

                    mysql_query( "UPDATE `todo_user` SET vars='{$this->request['body']}' WHERE id='{$user_id}'" ) or $this->throwMySQLError();

                    $this->response = 'ok';
                    $this->responseStatus = 200;
                }
                break;
            ## user/<id> [PUT]: Modifies the specified user.
            case 2:
                if ( !$this->loggedUser->hasPermission( User::PERMISSION_PEOPLE_MANAGEMENT ))
                    $this->throwForbidden();

                $user_id = $this->getResourceNamePart( 1 );

                $this->getUserFromDatabase($user_id);

                if ($this->loggedUser->hasPermission( User::PERMISSION_PEOPLE_MANAGEMENT ))
                    $editable_columns = array(
                        'deleted'    => 'deleted',
                        'username'   => 'username',
                        'role'       => 'role',
                        'def_role'   => 'def_role',
                        'group'      => 'group',
                        'firstname'  => 'firstname',
                        'lastname'   => 'lastname',
                    );

                $role = $this->getRequestBodyValue( 'role', false );

                if ($role == 'admin' && !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER ))
                    $this->throwForbidden();

                $sets = $this->GetEditablesFromBody($editable_columns);
                $sets = implode(',', $sets);

                mysql_query( "UPDATE `todo_user` SET {$sets} WHERE id='{$user_id}'" ) or $this->throwMySQLError();

                $this->response = 'ok';
                $this->responseStatus = 202; // accepted
                break;
        }

        return null;
    }

    public function delete()
    {
        ## user/<id>/calendar?day=15275 [DELETE]
        if ( $this->getResourceNamePart( 2 ) == 'calendar' )
        {
            if (
            !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER )
            && !$this->loggedUser->hasPermission( User::PERMISSION_PEOPLE_MANAGEMENT )
            )
                $this->throwForbidden();

            $user_id = intval($this->getResourceNamePart( 1 ));

            $calendar_controller = new Controllers_calendar($this->request);
            $this->response = $calendar_controller->DeleteCalendarRowByUser($user_id);
            $this->responseStatus = 200;
        }
    }
}

?>
