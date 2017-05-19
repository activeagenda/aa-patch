<?php
/**
 * Utility to install Active Agenda database and data.
 * 
 * PHP version 5
 *
 *
 * LICENSE NOTE:
 *
 * Copyright  2003-2009 Active Agenda Inc., All Rights Reserved.
 *
 * Unless explicitly acquired and licensed from Licensor under another license, the
 * contents of this file are subject to the Reciprocal Public License ("RPL")
 * Version 1.5, or subsequent versions as allowed by the RPL, and You may not copy
 * or use this file in either source code or executable form, except in compliance
 * with the terms and conditions of the RPL. You may obtain a copy of the RPL from
 * Active Agenda Inc. at http://www.activeagenda.net/license.
 *
 * All software distributed under the RPL is provided strictly on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESS OR IMPLIED, AND LICENSOR HEREBY
 * DISCLAIMS ALL SUCH WARRANTIES, INCLUDING WITHOUT LIMITATION, ANY WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, QUIET ENJOYMENT, OR
 * NON-INFRINGEMENT. See the RPL for specific language governing rights and
 * limitations under the RPL.
 *
 * @author         Mattias Thorslund <mthorslund@activeagenda.net>
 * @copyright      2003-2009 Active Agenda Inc.
 * @license        http://www.activeagenda.net/license  RPL 1.5
 * @version        SVN: $Revision: 1506 $
 * @last-modified  SVN: $Date: 2009-02-07 18:28:34 +0100 (So, 07 lut 2009) $
 */




/**
 * Defines execution state as 'non-generating command line'.  Several classes and
 * functions behave differently because of this flag.
 */
DEFINE('EXEC_STATE', 2);

$project = 'active_agenda';   //folder location
$add_user_only = false;
if(2 <= ($_SERVER['argc'])){
    if('-u' == substr($_SERVER['argv'][1], 0, 2)){
        $add_user_only = true;
    } else {
        if(3 <= ($_SERVER['argc'])){
            $project = $_SERVER['argv'][2];
        } else {
            $project = $_SERVER['argv'][1];
        }
    }
}

//assumes we're in the 's2a' folder 
$site_folder = realpath(dirname($_SERVER['SCRIPT_FILENAME']).'');
$site_folder .= '/'.$project;

//includes
$config_file = $site_folder . '/config.php';
if(!file_exists($config_file)){
    print "Config file not found at $config_file\n";
    exit;
}
$gen_config_file = $site_folder . '/gen-config.php';
if(!file_exists($gen_config_file)){
    print "Config file not found at $gen_config_file\n";
    exit;
}

//get settings
include_once $config_file;
include_once $gen_config_file;
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());
require_once PEAR_PATH . '/DB.php' ;  //PEAR DB class
include_once INCLUDE_PATH . '/general_util.php';
include_once INCLUDE_PATH . '/parse_util.php';
include_once INCLUDE_PATH . '/web_util.php'; //need dbQuote()
include_once INCLUDE_PATH . '/usrFunctions.php'; //need encryptPassword()

$debug_prefix = 's2a-install-db:';

if($add_user_only){
    AddUser();
    exit;
}


/**
 *  Attempt to find mysql.exe on Windows, because it's not in the PATH by default
 */
if(isWindows()){

     if(!$mysql_folder = findMySQLexe()){
          print("Could not find the MySQL executable in the expected default location.\n");
          if(!prompt("Do you know the location of the MySQL executable? Answering 'n' will exit the program.")){
              die("Exited the program.\n");
          }
          $mysql_folder = textPrompt("Please enter the folder location where the MySQL executable is located.");
     }
     //$mysql_command = '"'.$mysql_folder.'"';
    $mysql_path = dirname($mysql_folder);

    $path_val = getenv('PATH');
    if(putenv("PATH=$path_val;$mysql_path")){
        $mysql_command = 'mysql.exe';
    } else {
        die("$debug_prefix could not set PATH\n");
    }
    $path_val = getenv('PATH');
    //print "$debug_prefix This is the PATH: $path_val\n";
    $mysql_command = escapeshellcmd($mysql_command);

} else {
     $mysql_command = 'mysql';

    $mysql_loc = shellCommand('which '.$mysql_command, false, false);
    if(empty($mysql_loc)){
        $mysql_command = textPrompt("Please enter the location where the MySQL executable is located, uncluding the name of the mysql executable.");
    }
}


//sanity check
if('root' == GEN_DB_USER){
   die("$debug_prefix The GEN_DB_USER cannot be 'root'. Please change gen-config.php to a different name.\n");
}

//TIP: to install a root password on the server if there isn't one, use one of there commands
// /usr/bin/mysqladmin -u root password 'new-password'
// /usr/bin/mysqladmin -u root -h localhost password 'new-password'



if(!prompt("\nIn order to install the Active Agenda database, the MySQL root password is required. Continue?")){
    die("$debug_prefix Database install was canceled\n");
}

$root_pwd = textPrompt("Please type the MySQL root password.");

//because the root password could be set with the "new password", and PHP 4 does not support it, we will have to do this from the command line

$sql = "SHOW DATABASES;";
$result = shellQuery($sql);
$existing_dbs = explode("\n", $result);
unset($existing_dbs[0]); //first line is the column header
foreach($existing_dbs as $existing_db){
    if(DB_NAME == trim($existing_db)){
        if(prompt("Warning: There is a database by the name '".DB_NAME."' already. To continue, we must first DELETE this database. If it contains any data, this will be LOST! Really continue?")){
            $sql = "DROP DATABASE ".DB_NAME.";";
            shellQuery($sql);
            print "\n\n";
        } else {
            die("$debug_prefix Database install was canceled\n");
        }
    }
}


//setting permissions on DB
$sql = "CREATE DATABASE ".DB_NAME.";";
//$sql = "CREATE DATABASE ".DB_NAME." CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
shellQuery($sql);
print "\n\n";


$sql = "GRANT ALL ON ".DB_NAME.".* TO '".GEN_DB_USER."'@'".DB_HOST."' IDENTIFIED BY '".GEN_DB_PASS."';" ;
shellQuery($sql);
print "\n\n";


$sql = "UPDATE mysql.user SET Password = OLD_PASSWORD('".GEN_DB_PASS."') WHERE Host ='".DB_HOST."' AND User='".GEN_DB_USER."';";
shellQuery($sql);
print "\n\n";


$sql = "GRANT INSERT, UPDATE, SELECT ON ".DB_NAME.".* TO '".DB_USER."'@'".DB_HOST."' IDENTIFIED BY '".DB_PASS."';" ;
shellQuery($sql);
print "\n\n";


$sql = "UPDATE mysql.user SET Password = OLD_PASSWORD('".DB_PASS."') WHERE Host ='".DB_HOST."' AND User='".DB_USER."';";
shellQuery($sql);
print "\n\n";


$sql = "FLUSH PRIVILEGES;";
shellQuery($sql);
print "\n\n";

$emptySQLPath = S2A_FOLDER.'/install/empty.sql';
if(!file_exists($emptySQLPath)){
    die("The file $emptySQLPath, which allows installing the special database tables, is not existing.\n
The database has been created, but without tables.\n");
}

//install tables
print "$debug_prefix Installing empty database tables...\n";
shellCommand($mysql_command .' -u '.GEN_DB_USER.' -p'.GEN_DB_PASS.' '.DB_NAME.' < "'.S2A_FOLDER.'/install/empty.sql"');
print "$debug_prefix Finished installing empty database tables.\n\n";

//install master data
print "$debug_prefix Installing master data...\n";
shellCommand($mysql_command .' -u '.GEN_DB_USER.' -p'.GEN_DB_PASS.' '.DB_NAME.' < "'.S2A_FOLDER.'/install/master.sql"');
print "$debug_prefix Finished installing master data.\n\n";

if(prompt("Would you like to install sample data? This would be helpful for training purposes, but it would get \"in the way\" on a prodction server.")){

    print "$debug_prefix Installing sample data...\n";
    shellCommand($mysql_command .' -u '.GEN_DB_USER.' -p'.GEN_DB_PASS.' '.DB_NAME.' < "'.S2A_FOLDER.'/install/sample.sql"');
    print "$debug_prefix Finished installing sample data.\n\n";

}


//testing that a connection is possible
global $dbh;
$dbh = DB::connect(GEN_DB_DSN);
dbErrorCheck($dbh);

//add a user?
print "The database installation is almost done. You have created the Active Agenda database, you have set up access credentials for the web server as well as for maintenance utilities, and you have imported some data into the database.\nBut we still don't have a real Active Agenda user that can log in to the application and start setting things up - an Administrator.\n\n";

AddUser();

print "$debug_prefix That's all folks! Hope you had fun!!\n\n";


function AddUser()
{
    print "We will set up the adminstrator user. This should be a real person. It is possible to set up more administrators in the application, so it is not necessary to share passwords.\n\n";

    $debug_prefix = 's2a-install-db (AddUser):';
	$frontend_admin_file = APP_FOLDER.'/frontend-admin.conf';
	print $frontend_admin_file;
	if(file_exists($frontend_admin_file)){
		include_once $frontend_admin_file;
		$organization_name = ORGANIZATION;
		$person_first_name = FIRST_NAME;
		$person_last_name = LAST_NAME;
		$person_display_name = FULL_NAME;
		$user_name = USERNAME;
		$user_pass = PASSWORD;
	}else{
		do {				
			$organization_name = textPrompt("Please enter the name of the administrator's ORGANIZATION.");
			$person_first_name = textPrompt("Please enter the FIRST NAME (given name) of your the administrator.");
			$person_last_name = textPrompt("Please enter the LAST NAME (family name) of your the administrator.");
			$person_display_name = textPrompt("Please enter the administrator's FULL NAME, as it would be written within the organization. For instance, if there are several people with the same first and last name, include a middle initial, or even a nickname.");
			$user_name = textPrompt("Please enter the desired USERNAME, i.e. login name.");
			$user_pass = textPrompt("Please enter the PASSWORD.");
			
			print "You entered: \n";
			print "\n";
			print "Organization: $organization_name\n";
			print "First Name: $person_first_name\n";
			print "Last Name: $person_last_name\n";
			print "Full Name: $person_display_name\n";
			print "Username: $user_name\n";
			print "Password: $user_pass\n";
			print "\n";
			
		} while(!prompt("Does this look right? (Sorry, but you'll have to re-enter all of them if you want to change something.)"));			
	}
    ob_start();
    $dh = GetDataHandler('org');
    $organization_id = $dh->saveRow(array('Name' => $organization_name, 'Participant' => 1), 0);
    if(0 == $organization_id){
        die("$debug_prefix Something about saving the organization record went wrong.\n");
    }
    ob_end_clean();
    print "$debug_prefix Inserted Organization record\n";

    ob_start();
    $dh = GetDataHandler('ppl');
    $person_id = $dh->saveRow(
        array(
            'FirstName' => $person_first_name,
            'LastName' => $person_last_name,
            'DisplayName' => $person_display_name,
            'OrganizationID' => $organization_id
        ), 0);
    if(0 == $person_id){
        die("$debug_prefix Something about saving the person record went wrong.\n");
    }
    ob_end_clean();
    print "$debug_prefix Inserted Person record\n";

    ob_start();
    $dh = GetDataHandler('usr');
    if(!$dh->saveRow(
        array(
            'Username' => $user_name,
            'Password' => encryptPassword($user_pass),
            'IsAdmin' => 1,
            'LangID' => 1,
            'DefaultOrganizationID' => $organization_id
        ), $person_id
    )){
        die("$debug_prefix Something about saving the user record went wrong.\n");
    }
    ob_end_clean();
    print "$debug_prefix Inserted User record\n";
}
?>