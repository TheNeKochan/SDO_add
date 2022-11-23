<?php

include_once 'SchoolNSOAPI/api.php';
use SchoolNSOAPI\api;

$api = new api();
$stat = $api->login("********", "*********");
if($stat == api::STATUS_OK) {
    $api->selectClassById(0);
    $api->selectClassSubject(1);

    for($k = 0; $k < $api->getClassSubgroupsCount(); $k++) {
        $api->selectClassSubgroup($k);
        for ($j = 0; $j < $api->getSubgroupStudentsCount(); $j++) {
            //for ($i = 8; $i < 8; $i++) {
                $i = 8;
                try {
                    $api->setStudentMark($j, null, date_create('2022-11-' . $i), null);
                    echo $api->getSubgroupStudentName($j) . '(2022-11-' . $i . ') = 4' . "\n";
                } catch (Exception $e) {

                }
            //}
        }
    }
} else {
    echo $stat;
}

