<?php

class Controllers_calendar extends RestController
{
    function DeleteCalendarRowByUser($worker = 0)
    {
        $day = intval($_REQUEST['day']);

        mysql_query("DELETE FROM `todo_calendar` WHERE day='{$day}' AND worker='{$worker}'") or $this->throwMySQLError();

        return 'ok';
    }

    function GetCalendarByUser($worker = 0)
    {
        $from  = intval($_GET['from']);
        $count = intval($_GET['count']);

        if (!$count) $count = 500;

        if (!$from)
            $from = 0;

        $days = array();
        $query = mysql_query( "SELECT day, kind FROM `todo_calendar` WHERE `worker`='{$worker}' AND day>={$from} LIMIT 0, {$count}" ) or $this->throwMySQLError();
        while( $dbtask = mysql_fetch_array( $query, MYSQL_ASSOC ) )
        {
            $days[] = $dbtask;
        }

        return $days;
    }

    function PutCalendarRow($worker = 0)
    {
        $available_kinds = array('workday','dayoff');

        $day  = intval($_REQUEST['day']);
        $kind = $this->getRequestBodyValue('kind');

        if (!in_array($kind, $available_kinds) || $day <= 14000)
            throw new Exception( 'Bad Request', 400 );

        mysql_query("INSERT INTO `todo_calendar` (day, worker, kind) VALUES ({$day}, {$worker}, '{$kind}') ON DUPLICATE KEY UPDATE kind='{$kind}';") or $this->throwMySQLError();

        return 'ok';
    }

    public function get()
    {
        switch ( $this->getResourceNamePartsCount() )
        {
            ## calendar?from=15275&count=100 [GET]: Returns full task description.
            case 1:
                $this->response = $this->GetCalendarByUser();
                $this->responseStatus = 200;
                break;
        }

        return null;
    }

    public function put()
    {
        switch ( $this->getResourceNamePartsCount() )
        {
            case 1:
                ## calendar?day=15275 [PUT]
                if ( !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER )  )
                    $this->throwForbidden();

                $this->response = $this->PutCalendarRow();
                $this->responseStatus = 202; // accepted
                break;
        }

        return null;
    }

    public function post() {
        return null;
    }

    public function delete() {
        return null;
    }
}

?>
