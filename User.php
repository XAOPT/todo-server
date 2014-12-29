<?php

class User
{
    private $dbuser;
    private $permissions;
    private $project_permissions = array();

    ## sysname = 1
    const PERMISSION_ADMINISTER         = 'administer';
    const PERMISSION_PROJECT_MANAGEMENT = 'project.management';
    const PERMISSION_PEOPLE_MANAGEMENT  = 'people.management';

    ## sysname = 0
    const PERMISSION_TASK_MANAGEMENT    = 'task.management';
    const PERMISSION_COMMENT_MANAGEMENT = 'comment.management';
    const PERMISSION_TESTER             = 'tester';


    public function __construct( $object )
    {
        $this->dbuser = $object;

        $this->permissions = isset($object['permissions'])?json_decode( $object['permissions'], true ):array();
    }

    public static function createFromDatabase( $id )
    {
        $q = "
        SELECT u.*, r.permissions
        FROM `todo_user` AS u
        LEFT JOIN `todo_role` AS r ON (r.id = u.role)
        WHERE u.id='{$id}'
        ";

        $query = mysql_query( $q );
        if ( !isset($query) )
            return;

        if ( $object = mysql_fetch_array( $query ) )
            return new User( $object );
    }

    public function getPermissions()
    {
        return $this->permissions;
    }

    public function getId()
    {
         return $this->dbuser['id'];
    }

    public function hasProjectPermission($perm, $project)
    {
        if ( in_array( User::PERMISSION_ADMINISTER, $this->permissions ) )
            return true;

        if (!empty($this->project_permissions))
            return in_array($perm, $this->project_permissions);

        $q = "
        SELECT permissions
        FROM `todo_prole` AS pr
        LEFT JOIN `todo_role` AS r ON (r.sysname = pr.role)
        WHERE pr.user_id='{$this->dbuser['id']}' AND pr.project='{$project}' AND r.sysrole=0";

        $query = mysql_query( $q );
        $temp = mysql_fetch_array($query, MYSQL_NUM);

        ## если не найдено упоминаний о правах для указанного проекта, то проверяем не заданы ли права по-умолчанию для юзера
        if (empty($temp))
        {
            if (!$this->dbuser['def_prole'])
                return false;

            $query = mysql_query( "SELECT permissions FROM `todo_role` WHERE sysrole=0 AND sysname='{$this->dbuser['def_prole']}'" );
            $temp  = mysql_fetch_array($query, MYSQL_NUM);

            $this->project_permissions = json_decode($temp[0]);
            return in_array($perm, $this->project_permissions);
        }
        else
        {
            $this->project_permissions = json_decode($temp[0]);
            return in_array($perm, $this->project_permissions);
        }
    }

    public function hasPermission($perm = '')
    {
        // если есть админские права, то это разрешает всё.
        if ( in_array( User::PERMISSION_ADMINISTER, $this->permissions ) )
            return true;

        return in_array( $perm, $this->permissions );
    }
}
?>
