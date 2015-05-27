<?php

/*

Пример хекса таска:
70000000 - количество ячеек с часами (00000070 = 112)
190ADD07 - дата 07DD - год, 0A - месяц, 19 - день = 19 октября 2013
0000C040 - количество часов (float)
01 - разделитель
1E0ADD070000004001050BDD070000004001060BDD070000004001140BDD070000404001160BDD0700004040011A0BDD0700004040011B0BDD0700004040011C0BDD0700004040011D0BDD070000803F01060CDD0700000040010B0CDD0700000040010D0CDD070000004001100CDD070000004001110CDD070000004001120CDD070000803F011A0CDD0700000040011B0CDD0700000040011C0CDD0700000040010901DE0700008040010E01DE0700000040011801DE0700008040010B02DE0700008040010303DE0700008040011A03DE070000A040011704DE070000803F011804DE070000803F011904DE070000803F011C04DE0700000040011D04DE070000803F011E04DE0700008040010505DE070000803F010605DE0700000040010705DE0700000040010805DE070000803F010C05DE0700000040010D05DE0700000040010E05DE0700000040010F05DE0700008040011005DE0700008040011305DE0700008040011405DE0700004040011505DE0700000040011605DE0700004040011705DE0700000000011A05DE0700000040011D05DE070000803F010506DE0700000040010606DE070000803F010307DE070000A040010407DE070000E040010707DE070000C040010807DE0700008040010907DE0700000040010A07DE070000803F010B07DE0700004040010E07DE0700000040011007DE0700000040011207DE070000803F011607DE070000803F011707DE0700008040011807DE070000803F010108DE0700004040010408DE070000C040010508DE0700000040010608DE070000A040010708DE070000803F010808DE0700008040010B08DE0700000040010C08DE0700000040010D08DE0700000040010E08DE070000803F011208DE0700000040011308DE0700004040011408DE0700000040011508DE070000803F011608DE070000803F010F09DE070000803F011009DE0700000040011109DE070000803F011209DE0700000040011609DE070000803F011809DE0700004040011909DE0700000040011A09DE0700000040011E09DE070000004001010ADE070000004001080ADE0700004040010E0ADE070000C040010F0ADE070000404001110ADE070000404001150ADE070000004001160ADE070000004001170ADE070000004001180ADE0700000040011F0ADE070000004001050BDE070000004001180BDE070000004001190BDE0700000040011C0BDE0700000040011802DF0700000041011902DF0700000041011A02DF0700000041011B02DF0700000041010203DF0700008040010303DF0700008040010403DF0700008040010503DF0700008040010E04DF070000803F011004DF0700000040011104DF0700000040011505DF070000004001
13000000 - количество рабочих дней (19)
1E0ADD07 - дата одного рабочего дня
120BDD07130BDD07140BDD07150BDD07160BDD070E01DE070F01DE071001DE071101DE071B02DE071C02DE070103DE070203DE070303DE070403DE070503DE070603DE070703DE07
70000000 - конец записи о часах и рабочих днях
00 - два символа на каждый отмеченный часами день
000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000
07 - длина сообщения (7)
74657374657374
5E - длина следующего коммента (94)
D0BFD180D0B8D0B2D0B5D182203232D0BFD180D0B8D0B2D0B5D182203232D0BFD180D0B8D0B2D0B5D182203232D0BF3232D0BFD180D0B8D0B2D0B5D182203232D0BFD180D0B8D0B2D0B5D182203232D0BFD180D0B8D0B2D0B5D182203232
0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000
*/

class Controllers_timesheet extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'timesheet' => 'GetTimesheet',
                'timesheet/\d+/summary' => 'GetSummaryTimesheet'
            ),
            'put' => array(
                'timesheet' => 'EditTimesheet'
            )
        );
    }

    private function getCommentsPointer($binary)
    {
        $parts_string = substr($binary, 0, 8);
        $parts = hexdec(implode('', array_reverse(str_split($parts_string, 2))));

        $workingday_length = hexdec(implode('', array_reverse(str_split(substr($binary, 8+$parts*18, 8), 2))));

        return 24+$parts*18+$workingday_length*8;
    }

    private function parseTimesheet($binary, $task_id = 0, $userid = 0)
    {
        $parts_string = substr($binary, 0, 8);
        $parts = hexdec(implode('', array_reverse(str_split($parts_string, 2))));

        $comments_pointer = $this->getCommentsPointer($binary);

        $data = array();

        for ($i = 0; $i < $parts; $i++) {
            $start = 8 + $i*18;
            $date  = substr($binary, $start, 8);
            $hours = substr($binary, $start+8, 8);

            $date = str_split($date, 2);
            $date = hexdec($date[3].$date[2])."-".sprintf("%02d", hexdec($date[1]))."-".sprintf("%02d", hexdec($date[0]));
            if (strcmp($hours, "0000000")) {
                $hours = round($this->hexTo32Float("0x".substr($hours, 6, 2).substr($hours, 4, 2).strrev(substr($hours, 0, 4))), 1);

                $comment = "";

                $comments_length = hexdec(substr($binary, $comments_pointer, 2));

                if ($comments_length) {
                    $comments_pointer += 2;
                    if (hexdec(substr($binary, $comments_pointer, 2)) < 16)
                    {
                        $comments_length = $comments_length + (hexdec(substr($binary, $comments_pointer, 2)) -1)*128;
                        $comments_pointer += 2;
                    }
                    $comment = $this->hextostring(substr($binary, $comments_pointer, $comments_length*2));
                    $comments_pointer += $comments_length*2;
                }
                else {
                    $comments_pointer += 2;
                }

                $data[] = array(
                    "day" => intval(strtotime($date)/86400),
                    "worktimeSeconds" => $hours*3600,
                    "taskid"  => $task_id,
                    "userid"  => $userid,
                    "date"    => $date,
                    "comment" => preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $comment)
                );
            }
        }

        return $data;
    }

    private function hextostring($hex = '')
    {
        $string='';
        for ($i=0; $i < strlen($hex)-1; $i+=2){
            $string .= chr(hexdec($hex[$i].$hex[$i+1]));
        }
        return $string;
    }

    private function strToHex($string){
        $hex = '';
        for ($i=0; $i<strlen($string); $i++){
            $ord = ord($string[$i]);
            $hexCode = dechex($ord);
            $hex .= substr('0'.$hexCode, -2);
        }
        return strToUpper($hex);
    }

    private function getWorkingDaysFromBinary($binary)
    {
        $parts = hexdec(implode('', array_reverse(str_split(substr($binary, 0, 8), 2))));

        $start = 8+$parts*18;

        $wd_parts = hexdec(implode('', array_reverse(str_split(substr($binary, $start, 8), 2))));

        return substr($binary, $start, 8+$wd_parts*8);
    }

    private function FloatToHex($data)
    {
        return bin2hex(strrev(pack("f",$data)));
    }

    private function hexTo32Float($strHex) {
        $v = hexdec($strHex);
        $x = ($v & ((1 << 23) - 1)) + (1 << 23) * ($v >> 31 | 1);
        $exp = ($v >> 23 & 0xFF) - 127;
        return $x * pow(2, $exp - 23);
    }

    public function GetTimesheet()
    {
        $userid = intval($this->getRequestParamValue('userid', false));
        $taskid = $this->getRequestParamValue('taskid', false);
        $projid = $this->getRequestParamValue('projid', false);
        $from   = intval($this->getRequestParamValue('from', false));
        $count  = intval($this->getRequestParamValue('count', false));

        $this->response = array(
            "status" => 0
        );

        /* search condition */
        $where = array();

        if ($userid) {
            $this->response['userid'] = $userid;

            $where[] = "`uid`={$userid}";
        }

        if ($taskid) {
            $this->response['taskid'] = $taskid;

            if (is_array($taskid)) {
                foreach ($taskid as &$t) {
                    $t = intval($t);
                }
                $taskid = implode(',', $taskid);
            }
            else
                $taskid = intval($taskid);

            $where[] = "`id` IN ({$taskid})";
        }

        if ($projid) {
            if (is_array($projid)) {
                foreach ($projid as &$p) {
                    $p = intval($p);
                }
                $projid = implode(',', $projid);
            }
            else
                $projid = intval($projid);

            $where[] = "`projid` IN ({$projid})";
        }

        if (empty($where))
            throw new Exception('Search params are empty', 400);

        $where = implode(" AND ", $where);
        /* end of search condition*/


        $items = array();
        if ($from && $count) {

            $data = array();

            $query = mysql_query("SELECT id, uid, HEX(calendar) AS calendar FROM `task` WHERE calendarVersion IS NOT NULL AND {$where}");

            while( $row = mysql_fetch_assoc( $query ) )
            {
                $data = array_merge($this->parseTimesheet($row['calendar'], $row['id'], $row['uid']), $data);
            }

            $this->response['from'] = $from;
            $this->response['count'] = $count;

            $to = $from + $count;

            if (!empty($data))
            foreach ($data as $d) {

                if ($from && $count) {
                    if ($d['day'] >= $from && $d['day'] < $to) {
                        $items[] = $d;
                    }
                }
            }
        }

        $this->response["items"] = $items;
        $this->responseStatus = 200;
    }

    public function GetSummaryTimesheet()
    {
        $userid = intval($this->getResourceNamePart( 1 ));

        $from   = intval($this->getRequestParamValue('from', true));
        $count  = intval($this->getRequestParamValue('count', true));

        $to = $from + $count;

        $query = mysql_query("SELECT id, uid, HEX(calendar) AS calendar FROM `task` WHERE calendarVersion IS NOT NULL AND uid={$userid}");

        $data = array();
        while( $row = mysql_fetch_assoc( $query ) )
        {
            $data = array_merge($this->parseTimesheet($row['calendar'], $row['id'], $row['uid']), $data);
        }

        $temp_items = array();
        $items = array();

        if (!empty($data))
        foreach ($data as $d) {
            if ($d['day'] >= $from && $d['day'] <= $to) {
                if (!isset($temp_items[$d['day']]))
                    $temp_items[$d['day']] = $d;
                else
                    $temp_items[$d['day']]['worktimeSeconds'] += $d['worktimeSeconds'];
            }
        }

        if (!empty($temp_items))
        foreach ($temp_items as $i) {
            $items[] = $i;
        }

        $this->response = array(
            "status" => 0,
            "from"  => $from,
            "count" => $count,
            "items" => $items
        );
        $this->responseStatus = 200;
    }

    private function updateTimesheet($data = array())
    {
        $query = mysql_query("SELECT HEX(calendar) AS calendar FROM `task` WHERE id={$data['taskid']}");
        $binary = mysql_fetch_array( $query );
        $binary = $binary[0];

        $days = $this->parseTimesheet($binary);
        $working_on_part = $this->getWorkingDaysFromBinary($binary);

        $comments_part = "";

        $updated = false;
        if (!empty($days))
        foreach ($days as &$d) {
            if ($d['day'] == $data['day']) {
                $d['worktimeSeconds'] = $data['worktimeSeconds'];
                $d['comment'] = $data['comment'];
                $updated = true;
            }
        }

        if (!$updated) {
            $days[] = array(
                "day" => $data['day'],
                "worktimeSeconds" => $data['worktimeSeconds']
            );
        }

        $parts = implode('', array_reverse(str_split(sprintf("%'.08s", dechex(count($days))), 2)));
        $new_binary = $parts;

        foreach ($days as $day) {
            $p = str_split(date("Ymd", ($day['day']+1)*86400), 2);

            $new_binary .= implode('', array_reverse(str_split(sprintf("%'.04s", dechex($p[0].$p[1])).sprintf("%'.02s", dechex($p[2])).sprintf("%'.02s", dechex($p[3])), 2)));

            $new_binary .= implode('', array_reverse(str_split($this->FloatToHex($day['worktimeSeconds']/3600), 2)));

            $new_binary .= "01";

            if ($day['comment']) {
                if (strlen($day['comment']) > 256) {
                    $comments_part .= sprintf( "%'.02s", dechex(strlen($day['comment'])%128) );
                    $comments_part .= sprintf( "%'.02s", dechex(floor(strlen($day['comment'])/128)-1) );
                }
                else {
                    $comments_part .= sprintf("%'.02s", dechex(strlen($day['comment'])));
                }
                $comments_part .= $this->strToHex($day['comment']);
            }
            else {
                $comments_part .= "00";
            }
        }

        $new_binary .= $working_on_part;
        $new_binary .= $parts;
        $new_binary .= $comments_part;
        $new_binary = strtoupper($new_binary);

        $kilo = ceil(strlen($new_binary)/512);
        $new_binary = str_pad($new_binary, $kilo*512, "0");

        return $new_binary;
    }

    public function EditTimesheet()
    {
        $is_owner = $this->loggedUser->getId() == $this->getRequestBodyValue("userid", true);

        $data = $this->GetParamsFromRequestBody('edit', $is_owner);

        $controllers_task = new Controllers_task($this->request);
        $sheets = $controllers_task->checkTaskExists($data['taskid']);

        $data['userid'] = $this->loggedUser->getId();

        /*if (!isset($data['comment']))
            $data['comment'] = '';*/

        $new_binary = $this->updateTimesheet($data);

        //echo $new_binary; exit;

        mysql_query("UPDATE `task` SET calendar=UNHEX('{$new_binary}') WHERE id={$data['taskid']}") or $this->throwMySQLError();

        $this->response = array(
            "status" => 0
        );

        $this->responseStatus = 202;
    }
}

?>
