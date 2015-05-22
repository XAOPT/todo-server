<?php

class Controllers_role extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'role' => 'GetRole',
                'role/\d+' => 'GetRole',
            ),
            'post' => array(
                'role' => 'CreateRole'
            ),
            'put' => array(
                'role/\d+' => 'EditRole'
            ),
            'delete' => array(
                'role/\d+' => 'DeleteRole'
            )
        );
    }

    public function checkRoleExists($id = 0)
    {
        $result = mysql_query( "SELECT * FROM `todo_role` WHERE id={$id}" ) or $this->throwMySQLError();
        if (!mysql_num_rows($result))
            throw new Exception( 'Not Found', 404 );
    }

    public function isSysRole($id = 0)
    {
        $result = mysql_query( "SELECT sysrole FROM `todo_role` WHERE id={$id}" ) or $this->throwMySQLError();
        $role = mysql_fetch_array($result);

        if (isset($role[0]) && $role[0] == 0)
            return false;
        else
            return true;
    }

    public function GetRole($role_id = null)
    {
        if (!isset($role_id))
            $role_id = intval($this->getResourceNamePart( 1 ));

        $where = $role_id > 0?" WHERE id={$role_id}":'';

        $items = array();
        $query = mysql_query("SELECT * FROM `todo_role` {$where}") or $this->throwMySQLError();

        while( $obj = mysql_fetch_array( $query ) )
        {
            $items[] = $this->normalizeObject( $obj );
        }

        $this->response = array(
            "status" => 0,
            "items" => $items
        );
        $this->responseStatus = 200;
    }

    public function CreateRole()
    {
        $data = $this->GetParamsFromRequestBody('create');

        $this->insertArrayIntoDatabase('todo_role', $data);
        $id = mysql_insert_id();

        $this->response = array(
            "status" => 0,
            "id" => $id
        );
        $this->responseStatus = 201;
    }

    public function EditRole()
    {
        $id = $this->getResourceNamePart( 1 );

        $this->checkRoleExists($id);

        if ($this->isSysRole($id))
            $this->throwForbidden();

        $data = $this->GetParamsFromRequestBody('edit');

        $this->UpdateDatabaseFromArray('todo_role', $data, "id={$id}");

        $this->response = array(
            "status" => 0,
            "id" => $id
        );

        $this->responseStatus = 202; // accepted
    }

    public function DeleteRole()
    {
        if (!$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER ))
            $this->throwForbidden();

        $id = $this->getResourceNamePart( 1 );

        $this->checkRoleExists($id);

        // системные роли нельзя редактировать
        if ($this->isSysRole($id))
            $this->throwForbidden();

        mysql_query("DELETE FROM `todo_role` WHERE id={$id} AND sysrole=0") or $this->throwMySQLError();

        mysql_query("UPDATE `user` SET role=0 WHERE role=0") or $this->throwMySQLError();

        $this->response = array(
            "status" => 0,
            "id" => $id
        );
    }
}

?>
