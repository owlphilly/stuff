<?php
defined('BASEPATH') OR exit('No direct script access allowed');
//session_start(); //we need to start session in order to access it through CI
class Users extends CI_Controller {

    function __construct() {

        parent::__construct();
        error_reporting(E_PARSE);
        $this->load->model('DbHandler');
        $this->load->library('session');
        $this->load->library('encrypt');


    }
    public function index(){
        //$this->unsetflashdatainfo();
        $this->session->sess_expire_on_close = TRUE;
        $session_data = $this->session->userdata('logged_in');
        $userrole=$session_data['UserRole'];
        $userstation=$session_data['StationId'];


        $query = $this->DbHandler->selectAllSystemUsers($userstation,'station','systemusers');  //value,field,table
        //  var_dump($query);
        if ($query) {
            $data['allusers'] = $query;
        } else {
            $data['allusers'] = array();
        }

        //All Stations
        $query = $this->DbHandler->selectAllFromSystemData($userstation,'StationName','stations');
        //  var_dump($query);
        if ($query) {
            $data['stationsdata'] = $query;
        } else {
            $data['stationsdata'] = array();
        }

        $this->load->view('users', $data);
    }

    public function DisplayStationUsersForm(){
        $this->unsetflashdatainfo();
        $name='displaynewstationuserform';
        $data['displaynewstationuserform'] = array('name' => $name);

        //Get all Stations.
        $session_data = $this->session->userdata('logged_in');
        //$userrole=$session_data['UserRole'];
        $userstation=$session_data['UserStation'];

        $query = $this->DbHandler->selectAllFromSystemData($userstation,'StationName','stations');  //value,field,table
        //  var_dump($query);
        if ($query) {
            $data['stationsdata'] = $query;
        } else {
            $data['stationsdata'] = array();
        }

        /////////////////////////////////////////////////////////


        $this->load->view('users', $data);

    }
    public function DisplayStationUsersFormForUpdate(){
        $this->unsetflashdatainfo();
        $session_data = $this->session->userdata('logged_in');
        $userrole=$session_data['UserRole'];
        $userstation=$session_data['UserStation'];

        $query = $this->DbHandler->selectAllFromSystemData($userstation,'StationName','stations');  //value,field,table
        if ($query) {
            $data['stationsdata'] = $query;
        } else {
            $data['stationsdata'] = array();
        }

        ///////////////////////////////////////////////////////////////////////

        $userid = $this->uri->segment(3);

        $query = $this->DbHandler->selectUserById($userid,'Userid','systemusers');  //$value, $field,$table
        if ($query) {
            $data['stationuserdataid'] = $query;
        } else {
            $data['stationuserdataid'] = array();
        }


        $this->load->view('users', $data);
    }



    public function insertUser(){
        $this->unsetflashdatainfo();
                $session_data = $this->session->userdata('logged_in');
        $UserRole=$session_data['UserRole'];
        $firstname =firstcharuppercase(chgtolowercase($this->input->post('user_firstname')));
        //$fname=firstcharuppercase($firstname);
        $surname = firstcharuppercase(chgtolowercase($this->input->post('user_surname')));
        //$sname=firstcharuppercase($surname);
        $useremail = chgtolowercase($this->input->post('user_email'));
        $userphone = $this->input->post('user_phone');
        //$urole=firstcharuppercase($URole);

        if($UserRole=="Manager" || $UserRole=="ManagerData"){
            $userRoleAssigned = $this->input->post('user_Role_AssignedBy_Manager');
            $station = firstcharuppercase(chgtolowercase($this->input->post('user_station_Manager')));
            $stationNo = $this->input->post('user_stationNo_Manager');
          }
      else if($UserRole=="OC"){
          $userRoleAssigned = $this->input->post('user_Role_AssignedBy_OC');
          $station = firstcharuppercase(chgtolowercase($this->input->post('user_station_OC')));
          $stationNo = $this->input->post('user_stationNo_OC');
        }

      if($userRoleAssigned=="OC" || $userRoleAssigned=="Observer" || $userRoleAssigned=="ObserverArchive" || $userRoleAssigned=="ObserverDataEntrant" || $userRoleAssigned=="WeatherForecaster"){

                $stationRegion = "NULL";

            }elseif($UserRole=="ZonalOfficer" || $UserRole=="SeniorDataOfficer"){

              $station ="NULL";
              $stationNo ="NULL";
              $stationRegion = $this->input->post('user_stationRegion_AssignedBy_Manager');
            }else{
          $station ="NULL";
          $stationNo ="NULL";
          $stationRegion = "NULL";
        }

        $createdBy=$UserRole;
        $active = 1;
        $reset=1;


        //Before you insert check for Duplicate User.
        //Send User Credentials to the Email Address
        $result=$this->DbHandler->checkforDuplicateUserDetails($firstname,$surname,$useremail,$station,$stationNo,$stationRegion);
        if(!$result){
           $stationId=$this->DbHandler->identifyStationById($station,$stationNo);
            $username = firstcharlowercase(firstletter($firstname)).'.'.firstcharlowercase($surname);
            //Check if username to be generated Exists in the DB Already
            $usernameExists=$this->DbHandler->checkIfUserNameExistsAlreadyInDB($username);
            if(!$usernameExists){

            $randompassword = $this->generatePasswdString();
            if($randompassword){
                $encryptpassword=md5($randompassword);

                //UserName:FirstLeta of FirstName and the SurName
                //Load a custom helper
                $this->load->helper("myphpstringfunctions_helper");

               // $username = firstcharlowercase(firstletter($firstname)).'.'.firstcharlowercase($surname);



                    $insertUserData=array(
                        'FirstName' => $firstname, 'SurName' => $surname,
                        'UserPassword' => $encryptpassword,'UserName' => $username,
                        'UserEmail' => $useremail, 'UserPhone' => $userphone,
                        'UserRole' => $userRoleAssigned, 'station' => $stationId,
                        "region_zone" => $stationRegion,
                        'LastPasswdChange'=> date('Y-m-d H:i:s'),
                        'LastLoggedIn'=>date('Y-m-d H:i:s'),
                        'Active'=>$active,
                        'Reset'=>$reset,'CreatedBy'=>$createdBy);

                    $insertsuccess= $this->DbHandler->insertData($insertUserData,'systemusers');
                    if($insertsuccess){

                      $session_data = $this->session->userdata('logged_in');
                      $userrole=$session_data['UserRole'];
                      $userstationId=$session_data['StationId'];
                      $name=$session_data['FirstName'].' '.$session_data['SurName'];

                      $userlogoutlogs = array('User' => $name,
                          'UserRole' => $userrole,'Action' => 'Inserted New User',
                          'Details' => $name . ' Inserted new user in the system ',
                          'station' => $userstationId,
                          'IP' => $this->input->ip_address());
                      //  save user logs
                       $this->DbHandler->saveUserLogs($userlogoutlogs);


                      //Send the User Credentials.
                      $htmlmessage = 'Hello'.''.$firstname.' '.$surname.'<br></br><br></br>'.
                          'Your  New WIMEA-ICT Web Interface  Credentials are'.'<br></br><br></br>'.
                          'UserName:'.''.''.$username.'<br></br><br></br>'.
                          'Password:'.''.''.$randompassword.'<br></br><br></br>'.
                          '<a href="http://www.wimea.mak.ac.ug/weather/">Click here to login!</a>'.
                          'Thank You'.'<br></br><b></br><b></br>'.'WIMEA-ICT';

                      //If true an Email has been sent Else
                      $results=$this->sendMail($htmlmessage,$useremail);
                      if($results){  //Email has been sent
                          //Redirect the user back with  message
                          //Insert the user if email has been sent.
                        //Store User logs.
                        //Create user Logs
                       }
                          else{ //User has been inserted but Email has not been sent
                              $this->session->set_flashdata('error', 'Email not sent and user has not been inserted');
                              $this->load->view('login');

                          }




                        $this->session->set_flashdata('success', 'New User info was added successfully and User Password has been sent to their email!');
                        $this->load->view('login');



                    }//end of if failed to insert
                    //Failed to insert the user
                    else{
                        $this->session->set_flashdata('error', '"Sorry, we encountered an issue User has not been inserted! ');
                        $this->index();
                    }




        }//end of if random password
            else{
            $this->session->set_flashdata('error','Failed to generate random password');
                $this->index();

        } //end of else
               }//end of if username exists already
               else{
                   $retd = 'Account Names'.' '. $firstname .' '. $surname.''.' already has a username in the System';
                   $this->session->set_flashdata('error', $retd);
                   $this->index();
               }
        }  //Duplicate Results in the DB already
        //When user to be inserted has the same info in the db
        else{


            $ret = 'Account Names'.' '. $firstname .' '. $surname.''.'is already in the System';
            $this->session->set_flashdata('error', $ret);
            $this->index();

        }



    }
    public function generatePasswdString(){
        $this->unsetflashdatainfo();
        $this->load->helper(array('string'));

        return random_string('alnum', 8);
    }
    public function  sendMail($htmlmsgbody,$msgreceiver)
    {
        $this->unsetflashdatainfo();
        $this->load->library('email');

        $config['protocol'] = 'smtp';
        $config['smtp_host'] = 'ssl://smtp.gmail.com';
        $config['smtp_port'] = '465';
        $config['smtp_user'] = 'wimeaictwdr@gmail.com';  //change it
        $config['smtp_pass'] = '1wimeawdr2'; //change it
        $config['charset'] = 'utf-8';
        $config['newline'] = "\r\n";
        $config['mailtype'] = 'html';


        $config['wordwrap'] = TRUE;
        $this->email->initialize($config);

        $this->email->from('wimeaictwdr@gmail.com','WIMEA-ICT');   //change it
        $this->email->to($msgreceiver);       //change it
        $this->email->subject("WIMEA-ICT Web Interface Credentials");
        $this->email->message($htmlmsgbody);

        if($this->email->send(FALSE)) {
            return true;
        } else {
            return false;//die("not sent".$this->email->print_debugger(array('headers')));
        }
        // if ($this->email->send()) {
        //return  'success';
        //    $data['success'] = 1;
        // return true;
        // } else {
        //   $data['success'] = 0;
        //   $data['error']= $this->email->print_debugger(array('headers'));
        //return false;
        //}
        //   echo "<pre>";
        //   print_r($data);
        //  echo "</pre>";

    }
    public function updateUser(){
        $this->unsetflashdatainfo();

        $this->load->helper(array('form', 'url'));
        $session_data = $this->session->userdata('logged_in');
        $UserRole=$session_data['UserRole'];

        $firstname =firstcharuppercase(chgtolowercase( $this->input->post('firstname')));
        //$fname=firstcharuppercase($firstname);
        $surname = firstcharuppercase(chgtolowercase($this->input->post('surname')));
        //$sname=firstcharuppercase($surname);
        $useremail = chgtolowercase($this->input->post('email'));
        $userphone = $this->input->post('contact');
        $stationId = $this->input->post('stationId_Manager');
        //$urole=firstcharuppercase($URole);

        if($UserRole=="Manager" || $UserRole=="ManagerData"){
            $userRoleAssigned = $this->input->post('Role_AssignedBy_Manager');
        }
        else if($UserRole=="OC"){
            $userRoleAssigned = $this->input->post('Role_AssignedBy_OC');
            $station = firstcharuppercase(chgtolowercase($this->input->post('station_OC')));
            $stationNo = $this->input->post('stationNo_OC');
            $stationRegion = $this->input->post('stationRegion_OC');
        }

        if( $userRoleAssigned=="Observer" || $userRoleAssigned=="ObserverArchive" || $userRoleAssigned=="ObserverDataEntrant" || $userRoleAssigned=="WeatherForecaster"){

                  $stationRegion = "NULL";
       }elseif($userRoleAssigned=="OC"){
            $station = firstcharuppercase(chgtolowercase($this->input->post('station_Manager')));
            $stationNo = $this->input->post('stationNo_Manager');
           //$stationRegion = $this->input->post('stationRegion_Manager');
        }elseif($userRoleAssigned=="ZonalOfficer" ||$userRoleAssigned=="SeniorZonalOfficer" ){

            $station = "NULL";
            $stationNo = "NULL";
            $stationRegion = $this->input->post('stationRegion_AssignedBy_Manager');
        }else{
          $station = "NULL";
          $stationNo = "NULL";
          $stationRegion = "NULL";
        }

  $id = $this->input->post('id');
  $stationChosen=$this->DbHandler->identifyStationById($station,$stationNo);
  $updateUserData=array(
            'station' => $stationChosen,'FirstName' => $firstname, 'SurName' => $surname,
            'UserEmail' => $useremail, 'UserPhone' => $userphone,'UserRole' => $userRoleAssigned);


            $session_data = $this->session->userdata('logged_in');
            $userrole=$session_data['UserRole'];
            $userstation=$session_data['UserStation'];
            $userstationNo=$session_data['StationNumber'];
            $StationRegion=$session_data['StationRegion'];
            $userstationId=$session_data['StationId'];
            $name=$session_data['FirstName'].' '.$session_data['SurName'];

            $userlogs = array('User' => $name,
            'UserRole' => $userrole,'Action' => 'Updated user details',
            'Details' => $name . ' updated user details in the system ',
            'station' => $userstationId ,
            'IP' => $this->input->ip_address());
        
        $updatesuccess=$this->DbHandler->updateUser($updateUserData, $updateUserData2,'systemusers',$id,$stationId,$userlogs);

        //Redirect the user back with  message
        if($updatesuccess){
            //Store User logs.
            //Create user Logs
           /* $session_data = $this->session->userdata('logged_in');
            $userrole=$session_data['UserRole'];
            $userstation=$session_data['UserStation'];
            $userstationNo=$session_data['StationNumber'];
            $StationRegion=$session_data['StationRegion'];
            $userstationId=$session_data['StationId'];
            $name=$session_data['FirstName'].' '.$session_data['SurName'];

           /* $userlogs = array('User' => $name,
                'UserRole' => $userrole,'Action' => 'Updated user details',
                'Details' => $name . ' updated user details in the system ',
                'station' => $userstationId ,
                'IP' => $this->input->ip_address());
            //  save user logs
            $this->DbHandler->saveUserLogs($userlogs);*/



            $this->session->set_flashdata('success', 'User Information has been updated successfully!');
            $this->index();

        }
        else{
            $this->session->set_flashdata('error', 'Sorry, we encountered an issue!');
            $this->index();

        }



    }
    public function deleteUser() {
        $this->unsetflashdatainfo();

        $id = $this->uri->segment(3); // URL Segment Three.
        //$id = $this->uri->segment(3); // URL Segment Three.

        $rowsaffected = $this->DbHandler->deleteUser('systemusers',$id);  //$rowsaffected > 0  //$tablename,id of the row

        if ($rowsaffected) {

            //Store User logs.
            //Create user Logs
            $session_data = $this->session->userdata('logged_in');
            $userrole=$session_data['UserRole'];
            $userstation=$session_data['UserStation'];
            $userstationNo=$session_data['StationNumber'];
            $userstationId=$session_data['StationId'];
            $name=$session_data['FirstName'].' '.$session_data['SurName'];

            $userlogs = array('User' => $name,
                'UserRole' => $userrole,'Action' => 'Deleted user details',
                'Details' => $name . ' deleted user details in the system ',
                'station' => $userstationId ,
                'IP' => $this->input->ip_address());
            //  save user logs
            // $this->DbHandler->saveUserLogs($userlogs);

            $this->session->set_flashdata('success', 'User info was deleted successfully!');
            $this->index();

            //redirect('/element', 'refresh');
        }
        else {

            $this->session->set_flashdata('error', '"Sorry, we encountered an issue! ');
            $this->index();

        }

    }

    public function SelectManagerStations(){
       
        $this->load->helper(array('form', 'url'));

        $region = ($stationName == "") ? $this->input->post('region') : $region;
        $result = $this->DbHandler->SelectZonalStations($region);   // $value, $field, $table
        echo json_encode($result);
    }

    public function GetUserLogs(){
        $region=$this->input->post('region');
        $station=$this->input->post('station');
        $action=$this->input->post('action');
        $typeofform=$this->input->post('typeofform');
        $startdate=$this->input->post('startdate');
        $enddate=$this->input->post('enddate');
        exit($startdate.' - '.$enddate);
    }

    public function userlogs() {
        $this->unsetflashdatainfo();
        $session_data = $this->session->userdata('logged_in');
        $userrole=$session_data['UserRole'];
        $userstation=$session_data['UserStation'];
        $userregion=$session_data['ZonalRegion'];


        $query = $this->DbHandler->selectAllFromSystemData($userstation,'StationName','userlogs');  //value,field,table
        $query1 = $this->DbHandler->SelectZonalStations($userregion);
        //  var_dump($query);
        if ($query && $query1) {
            $data['userlogs'] = $query;
            $data['zonalstations'] = $query1;
        } else {
            $data['userlogs'] = array();
            $data['zonalstations'] = array();
        }

        $this->load->view('userlogs', $data);

    }

    public function deleteUserLogs() {
        $this->unsetflashdatainfo();

        $id = $this->uri->segment(3); // URL Segment Three.

        $query = $this->DbHandler->deleteUserLogs($id);

        if ($this->db->affected_rows() > 0) {
            $StationElementlogs = array('user' => $this->session->userdata('username'),
                'userid' => $this->session->userdata('id'), 'action' => 'Deleted User Logs info',
                'details' => $this->session->userdata('username') . ' Deleted User Logs info into the system ',
                 'ip' => $this->input->ip_address());
            //  save user logs
            $this->DbHandler->saveUserLogs($StationElementlogs);

            redirect('/element', 'refresh');
        } else {

            redirect('/element', 'refresh');
        }

    }
    ///Check DB against the DATE,STATIONName,StationNumber,TIME,METAR/SPECI OPTION
    function checkInDBIfUserDetailsRecordExistsAlready($firstname,$surname,$email,$stationName,$stationNumber,$stationRegion,$userRole) {  //Pass the StationName to get the Station Number.
        $this->load->helper(array('form', 'url'));

        $stationName = ($stationName == "") ? $this->input->post('stationName') : $stationName;
        $firstname = ($firstname == "") ? $this->input->post('firstname') : $firstname;
        $stationNumber = ($stationNumber == "") ? $this->input->post('stationNumber') : $stationNumber;
        $stationRegion = ($stationRegion == "") ? $this->input->post('stationRegion') : $stationRegion;
        $userRole = ($userRole == "") ? $this->input->post('userRole') : $userRole;
        $surname = ($surname == "") ? $this->input->post('surname') : $surname;
        //$phone = ($phone == "") ? $this->input->post('phone') : $phone;
        $email = ($email == "") ? $this->input->post('email') : $email;
        //check($value,$field,$table)
        if ($this->input->post('stationName') == "") {
            echo '<span style="color:#f00">Please Input Name. </span>';
        }
        else {
            $stationId= $this->DbHandler->identifyStationById($stationName, $stationNumber);
            $get_result = $this->DbHandler->checkInDBIfUserDetailsRecordExistsAlready($firstname,$surname,$email,$stationId,$userRole,'systemusers');   // $value, $field, $table

            if( $get_result){
                echo json_encode($get_result);

            }
            else{

                echo json_encode($get_result);
            }
        }


    }

    function checkInDBIfUserDetailsRecordExistsAlreadyWithSameStationRegion($firstname,$surname,$email,$stationRegion) {  //Pass the StationName to get the Station Number.
        $this->load->helper(array('form', 'url'));

        $stationRegion = ($stationRegion == "") ? $this->input->post('stationRegion') : $stationRegion;
        $firstname = ($firstname == "") ? $this->input->post('firstname') : $firstname;
       // $stationNumber = ($stationNumber == "") ? $this->input->post('stationNumber') : $stationNumber;
        $surname = ($surname == "") ? $this->input->post('surname') : $surname;
        //$phone = ($phone == "") ? $this->input->post('phone') : $phone;
        $email = ($email == "") ? $this->input->post('email') : $email;
        //check($value,$field,$table)
        if ($this->input->post('stationRegion') == "") {
            echo '<span style="color:#f00">Please Input Name. </span>';
        }
        else {


            $get_result = $this->DbHandler->checkInDBIfUserDetailsRecordExistsAlreadyWithSameStationRegion($firstname,$surname,$email,$stationRegion,'systemusers');   // $value, $field, $table

            if( $get_result){
                echo json_encode($get_result);

            }
            else{

                echo json_encode($get_result);
            }
        }


    }
    public function checkifemailexistsInDB() {
        $this->load->helper(array('form', 'url'));

        $email = $this->input->post('useremail');
        if ($email == "") {
            echo '<span style="color:#f00">Please Input E-mail Address. </span>';
        } else {
                    //check($value,$field,$table)
            $get_result = $this->DbHandler->check($email, 'UserEmail', 'systemusers');    // $value, $field, $table

            if ($get_result)
                echo '<span style="color:#f00">Email already in use. </span>';
            else
                echo '<span style="color:#0c0">Email not in use</span>';
        }
    }

    public function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    public function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }


    public function unsetflashdatainfo(){

        if(isset($_SESSION['error'])){
            unset($_SESSION['error']);
        }

        elseif(isset($_SESSION['success'])){
            unset($_SESSION['success']);
        }
        elseif(isset($_SESSION['warning'])){
            unset($_SESSION['warning']);
        }
        elseif(isset($_SESSION['info'])){
            unset($_SESSION['info']);
        }

    }
}
?>
