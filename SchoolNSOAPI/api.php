<?php

namespace SchoolNSOAPI {

    use DateTime;

    include_once __DIR__ . '/../WebDriver/Chrome/ChromeDriver.php';

    use Exception;
    use WebDriver\ChromeDriver;

    class api
    {

        private $driver;
        private $session;
        private $logged_in;

        private $classes;
        private $selected_class = -1;

        private $selected_class_subjects;

        private $selected_class_subgroups;
        private $selected_class_subgroup_id = -1;

        private $selected_class_subgroup_students;

        public const STATUS_OK = 0;
        public const STATUS_ERROR_UNKNOWN = -1;
        public const STATUS_ERROR_ALREADY_LOGGED_IN = -2;
        public const STATUS_ERROR_NOT_LOGGED_IN = -3;
        public const STATUS_ERROR_INVALID_USERNAME_OR_PASSWORD = -4;
        public const STATUS_ERROR_INVALID_DATA = -5;
        public const STATUS_ERROR_CLASS_NOT_SELECTED = -6;
        public const STATUS_ERROR_SUBGROUP_NOT_SELECTED = -7;

        /**
         * Api constructor.
         */
        public function __construct(){
            $this->logged_in = false;

            $this->driver = new ChromeDriver('/Users/nekochan/Downloads/chromedriver');
            sleep(1);
            $this->session = $this->driver->CreateSession(false);
            sleep(1);
        }

        /**
         * Api destructor.
         */
        public function __destruct(){
            $this->logout();
            $this->session->close();
            $this->driver->close();
        }

        /**
         * Log in to SCH
         * @param $username string Username
         * @param $password string Password
         * @return int Status code
         * @throws Exception
         */
        public function login($username, $password){
            if($this->logged_in)
                return api::STATUS_ERROR_ALREADY_LOGGED_IN;

            $this->session->GoTo('https://school.nso.ru/authorize');
            sleep(1);

            $result = $this->session->Fetch('https://school.nso.ru/ajaxauthorize', array(
                "method" => "POST",
                "body"=> json_encode(array(
                        "username" => urlencode($username),
                        "password" => urlencode($password),
                        "return_uri" => "/"
                )),
                "headers" => array(
                    "Content-type" => "application/json; charset=UTF-8"
                )
            ));
            if($result['ok']){
                $this->session->GoTo('https://school.nso.ru' . json_decode($result['body'], true)['actions'][0]['url']);
                $this->logged_in = true;
                sleep(1);
                if($this->loadClasses() == api::STATUS_OK)
                    return api::STATUS_OK;
            } else if($result['status'] == 400){
                return api::STATUS_ERROR_INVALID_USERNAME_OR_PASSWORD;
            }
            return api::STATUS_ERROR_UNKNOWN;
        }

        /**
         * Log out from SCH
         * @return int Status
         */
        public function logout(){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            $this->session->GoTo('https://school.nso.ru/journal-user-logout-action');
            $this->logged_in = false;
            sleep(1);
            return api::STATUS_OK;
        }

        /**
         * Load class array for logged in user
         * @return int
         * @throws Exception
         */
        private function loadClasses(){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            $this->session->GoTo('https://school.nso.ru/');
            $doc = $this->session->Document();
            $classes = $doc->querySelectorAll("html > body > div.layout > div.layout-base > header > div.navigation-header > div.layout-content > nav > div.menu2 > table > tbody >  tr > td.choose_classes > div.selection > a");
            $i = 0;
            $cls = array();
            foreach ($classes as $class){
                $cls[$i++] = array('name' => $class->innerText(), 'path' => $class->getAttribute('href'));
            }
            if($i > 0) {
                $this->classes = $cls;
                $this->selected_class = -1;
                return api::STATUS_OK;
            }
            return api::STATUS_ERROR_UNKNOWN;
        }

        /**
         * Get class count
         * @return int
         */
        public function getClassCount(){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            return count($this->classes);
        }

        /**
         * Get class name
         * @param $index int
         * @return int|string String - name, Int - error code
         */
        public function getClassName($index){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if(!array_key_exists($index, $this->classes))
                return api::STATUS_ERROR_INVALID_DATA;
            return $this->classes[$this->selected_class]['name'];
        }

        /**
         * Select class by it's index
         * @param $index int Index
         * @return int
         */
        public function selectClassById($index){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if(!array_key_exists($index, $this->classes))
                return api::STATUS_ERROR_INVALID_DATA;
            $this->selected_class = $index;
            $this->session->GoTo('https://school.nso.ru' . $this->classes[$this->selected_class]['path']);
            sleep(1);
            $this->loadClassSubjects();
            return api::STATUS_OK;
        }

        /**
         * Get subjects from page
         * @return int
         */
        private function loadClassSubjects(){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class == -1)
                return api::STATUS_ERROR_CLASS_NOT_SELECTED;
            $doc = $this->session->Document();
            $subjects = $doc->querySelectorAll("html > body > div.layout > div.layout-base > header > div.navigation-header > div.layout-content > nav > div.menu2 > table > tbody >  tr > td.right > div.selection.fr > a, html > body > div.layout > div.layout-base > header > div.navigation-header > div.layout-content > nav > div.menu2 > table > tbody >  tr > td.right > div.selection.fr > span");
            $i = 0;
            $subjs = array();
            foreach ($subjects as $subject){
                if($subject->tagName() == 'span')
                    $subjs[$i++] = array('name' => $subject->innerText(), 'path' => null, 'current' => true);
                else if($subject->tagName() == 'a')
                    $subjs[$i++] = array('name' => $subject->innerText(), 'path' => $subject->getAttribute('href'), 'current' => false);
            }
            if($i == 0) {
                $subjs[0] = array('name' => '', 'path' => null, 'current' => true);
            }

            $this->selected_class_subjects = $subjs;

            $this->loadClassSubgroups();

            return api::STATUS_OK;
        }

        /**
         * Get subject count for current class
         * @return int
         */
        public function getSubjectCount(){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class == -1)
                return api::STATUS_ERROR_CLASS_NOT_SELECTED;
            return count($this->selected_class_subjects);
        }

        /**
         * Get subject name for current class
         * @param $index int
         * @return int|string String - name, Int - error code
         */
        public function getSubjectName($index){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class == -1)
                return api::STATUS_ERROR_CLASS_NOT_SELECTED;
            if(!array_key_exists($index, $this->selected_class_subjects))
                return api::STATUS_ERROR_INVALID_DATA;
            return $this->selected_class_subjects[$this->selected_class]['name'];
        }

        /**
         * Set subject for current class
         * @param $index int
         * @return int
         */
        public function selectClassSubject($index){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class == -1)
                return api::STATUS_ERROR_CLASS_NOT_SELECTED;
            if(!array_key_exists($index, $this->selected_class_subjects))
                return api::STATUS_ERROR_INVALID_DATA;

            if($this->selected_class_subjects[$index]['current'])
                return api::STATUS_OK;
            $this->session->GoTo('https://school.nso.ru' . $this->selected_class_subjects[$index]['path']);
            sleep(1);
            $this->loadClassSubjects();
            $this->loadClassSubgroups();
            return api::STATUS_OK;
        }

        /**
         * Get subgroups from page
         * @return int
         */
        private function loadClassSubgroups(){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class == -1)
                return api::STATUS_ERROR_CLASS_NOT_SELECTED;

            $doc = $this->session->Document();
            $subgroups = $doc->querySelectorAll("html > body > div.layout > div.layout-base > main > div#main-inner > div.layout-main > div.layout-content > div#grid > div.grid-group > div.grid-body > div[id$=_journal]");

            $sgrps = array();
            $i = 0;

            foreach ($subgroups as $subgroup){
                $subMatches = array();
                preg_match('/g([0-9]+)_journal/', $subgroup->getAttribute("id"), $subMatches);
                $groupid = intval($subMatches[1]);
                if($groupid == 0){
                    $sgrps[0] = array('name' => 'global', 'id' => 0);
                    $this->selected_class_subgroup_id = 0;
                    break;
                }
                $sgrps[$i++] = array('name' => $doc->querySelector('a[name=gsel' . $groupid . ']')->innerText(), 'id' => $groupid);
            }

            $this->selected_class_subgroups = $sgrps;
            $this->selectClassSubgroup(0);
            return api::STATUS_OK;
        }

        /**
         * Get subgroup count for current class/subject
         * @return int
         */
        public function getClassSubgroupsCount(){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class == -1)
                return api::STATUS_ERROR_CLASS_NOT_SELECTED;
            return count($this->selected_class_subgroups);
        }

        /**
         * Get subgroup name for current class/subject
         * @param $index int
         * @return int
         */
        public function getClassSubgroupName($index){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class == -1)
                return api::STATUS_ERROR_CLASS_NOT_SELECTED;
            if(!array_key_exists($index, $this->selected_class_subjects))
                return api::STATUS_ERROR_INVALID_DATA;
            return $this->selected_class_subgroups[$this->selected_class]['name'];
        }

        /**
         * Select subgroup for current class/subject
         * @param $index int
         * @return int
         */
        public function selectClassSubgroup($index){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class == -1)
                return api::STATUS_ERROR_CLASS_NOT_SELECTED;
            if(!array_key_exists($index, $this->selected_class_subgroups))
                return api::STATUS_ERROR_INVALID_DATA;

//            if($index == $this->selected_class_subgroup_id) {
//                $this->loadSubgroupStudents();
//                return api::STATUS_OK;
//            }
            $this->selected_class_subgroup_id = $index;
            $groupid = $this->selected_class_subgroups[$this->selected_class_subgroup_id]['id'];
            if($groupid == 0){
                $this->loadSubgroupStudents();
                return api::STATUS_OK;
            }
            $this->session->ExecuteScript('Grid.changeGroup(' . $groupid . ');');
            sleep(1);
            $this->loadSubgroupStudents();
            sleep(1);
            return api::STATUS_OK;
        }

        /**
         * Get subgroups from page
         * @return int
         */
        private function loadSubgroupStudents(){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class_subgroup_id == -1)
                return api::STATUS_ERROR_SUBGROUP_NOT_SELECTED;

            $groupid = $this->selected_class_subgroups[$this->selected_class_subgroup_id]['id'];

            $doc = $this->session->Document();
            $students_info = $doc->querySelectorAll('html > body > div.layout > div.layout-base > main > div#main-inner > div.layout-main > div.layout-content > div#grid > div.grid-group > div.grid-body > div#g' . $groupid . '_journal > div#g' . $groupid . '_fio > div');
            $students = array();
            $i = 0;
            foreach ($students_info as $student){
                try {
                    $students[$i++] = array('name' => $student->getAttribute('title'), 'id' => intval($student->getAttribute('uid')));
                } catch (Exception $ignored) {

                }
            }

            $this->selected_class_subgroup_students = $students;

            return api::STATUS_OK;
        }

        /**
         * Get student count
         * @return int
         */
        public function getSubgroupStudentsCount(){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class_subgroup_id == -1)
                return api::STATUS_ERROR_SUBGROUP_NOT_SELECTED;
            return count($this->selected_class_subgroup_students);
        }

        /**
         * Get student name
         * @param $index int
         * @return int|string String - name, Int - error code
         */
        public function getSubgroupStudentName($index){
            if(!$this->logged_in)
                return api::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class_subgroup_id == -1)
                return api::STATUS_ERROR_SUBGROUP_NOT_SELECTED;
            if(!array_key_exists($index, $this->selected_class_subgroup_students))
                return api::STATUS_ERROR_INVALID_DATA;
            return $this->selected_class_subgroup_students[$index]['name'];
        }


        /**
         * Set student mark for specified date
         * @param $student int
         * @param $mark null | 0-5
         * @param $date DateTime
         * @param string $comment
         * @return int
         * @throws Exception
         */
        public function setStudentMark($student, $mark /* null | 0-5 */, $date /* Y-m-d */, $comment=""){
            if(!$this->logged_in)
                return self::STATUS_ERROR_NOT_LOGGED_IN;
            if($this->selected_class == -1)
                return api::STATUS_ERROR_CLASS_NOT_SELECTED;
            if($this->selected_class_subgroup_id == -1)
                return api::STATUS_ERROR_SUBGROUP_NOT_SELECTED;
            if(!array_key_exists($student, $this->selected_class_subgroup_students))
                return api::STATUS_ERROR_INVALID_DATA;
            if(!(($mark == null) || ((0 < $mark) && ($mark < 6))))
                return api::STATUS_ERROR_INVALID_DATA;

            $response = $this->session->Fetch('https://school.nso.ru/journal-index-rpc-teacher-action?method=teacher.set_mark', array(
                'method' => 'POST',
                'body' => http_build_query(array(
                    "lesson_id"=>$this->__getClassId(),
                    "student"=>$this->selected_class_subgroup_students[$student]['id'],
                    "date"=>$date->format("Y-m-d"),
                    "nm"=>0,
                    "grp" => $this->selected_class_subgroups[$this->selected_class_subgroup_id]['id'],
                    "mark"=>($mark == null ? "" : $mark),
                    "type"=>"0",
                    "comment"=>($comment == null ? "false" : $comment),
                    "miss_type"=>"none"
                )),
                'headers' => array(
                    'Content-type' => 'application/x-www-form-urlencoded; charset=UTF-8'
                )
            ));
            sleep(1);
            if(json_decode($response['body'])->{'result'}){
                return self::STATUS_OK;
            }
            return self::STATUS_ERROR_UNKNOWN;
        }

        /**
         * Get current Class+Subject id
         * @return int
         * @throws Exception
         */
        private function __getClassId(){
            if(!$this->logged_in)
                return null;
            $doc = $this->session->Document();
            $class_id = $doc->querySelector('html > body > div.layout > div.layout-base  > main > div#main-inner > div.layout-main > div.layout-content > form > input#plesson_id');
            sleep(1);
            return intval($class_id->getAttribute('value'));
        }
    }
}
