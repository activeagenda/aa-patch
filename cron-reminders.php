#!/usr/bin/php
<?php
/**
 * crone script to send out reminders stored in the rmd table
 * 
 * PHP version 5
 *
 */

//get settings
$site_folder = '/var/www/s2a';
$config_file = $site_folder .'/active_agenda/config.php';
if(!file_exists($config_file)){
    print "Config file not found at $config_file\n";
    exit;
}
include_once $config_file;

set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());
require_once PEAR_PATH . '/DB.php' ;  //PEAR DB class
require_once(PEAR_PATH .'/PHPMailer/class.phpmailer.php');
//utility functions
include_once INCLUDE_PATH . '/parse_util.php';

global $dbh;
$dbh = DB::connect(DB_DSN);
dbErrorCheck($dbh);

/**
 * Defines execution state as 'non-generating command line'.  Several classes and
 * functions behave differently because of this flag.
 */
DEFINE('EXEC_STATE', 2);

// Single script running in the background
//DEV: może to przerobić na pętle-> przechodzi po wszystkich kodach i powtraza generowanie?
// 1 - jeden raz w dzień
$SQL[1] = 'SELECT * FROM `rmd` WHERE _Deleted = 0
 AND DATE( HandOver ) <  CURDATE() AND PeriodID = 1
 AND StartDate = CURDATE()';

// 2 - co tydzień od dnia
$SQL[2] = 'SELECT * FROM `rmd` WHERE _Deleted = 0
 AND DATE( HandOver ) <  CURDATE() AND PeriodID = 2
 AND DAYOFWEEK( StartDate ) = DAYOFWEEK( CURDATE() )';

// 3 - co miesiąc od dnia
$SQL[3] = 'SELECT * FROM `rmd` WHERE _Deleted = 0
 AND DATE( HandOver ) <  CURDATE() AND PeriodID = 3
 AND ( 
      DAYOFMONTH( StartDate ) = DAYOFMONTH( CURDATE() )
      OR
     (
	  DAYOFMONTH( CURDATE() ) = LAST_DAY( CURDATE() )
	  AND
	  DAYOFMONTH( StartDate ) > DAYOFMONTH( CURDATE() )
      )
 )';

// 4 - co roku od dnia
$SQL[4] = 'SELECT *  FROM `rmd` WHERE _Deleted = 0
	AND DATE( HandOver ) <  CURDATE() AND PeriodID = 4
 AND (
   (
	DAYOFMONTH( StartDate ) = DAYOFMONTH( CURDATE() )
	AND
	MONTH( StartDate ) = MONTH( CURDATE() )
    )
    OR
   (
	MONTH( StartDate ) = MONTH( CURDATE() )
	AND	
	DAYOFMONTH( CURDATE() ) = LAST_DAY( CURDATE() )
	AND
	DAYOFMONTH( StartDate ) > DAYOFMONTH( CURDATE() )
    )
 )';

$weekDays[0] = 'ą niedzielę';
$weekDays[1] = 'y poniedziałek';
$weekDays[2] = 'y wtorek';
$weekDays[3] = 'ą środę';
$weekDays[4] = 'y czwartek';
$weekDays[5] = 'y piątek';
$weekDays[6] = 'ą sobotę';

$yearsMonths[1] = 'stycznia';
$yearsMonths[2] = 'lutego';
$yearsMonths[3] = 'marca';
$yearsMonths[4] = 'kwietnia';
$yearsMonths[5] = 'maja';
$yearsMonths[6] = 'czerwca';
$yearsMonths[7] = 'lipca';
$yearsMonths[8] = 'sierpnia';
$yearsMonths[9] = 'września';
$yearsMonths[10] = 'pażdziernika';
$yearsMonths[11] = 'listopada';
$yearsMonths[12] = 'grudnia';
 
$curdate = $dbh->getAll( 'SELECT 
 CURDATE() AS currentDate,
 DAYOFMONTH( CURDATE() ) AS currentDayOfMonth,
 DAYOFWEEK( CURDATE() ) AS currentDayOfWeek,
 YEAR( CURDATE() ) AS currentYearOfDay,
 MONTH( CURDATE() ) AS currentMonthOfDay,
 LAST_DAY( CURDATE() ) AS currentLastDay', DB_FETCHMODE_ASSOC);
$currentDate = $curdate[0]['currentDate'];
$currentDayOfMonth = $curdate[0]['currentDayOfMonth'];
$currentDayOfWeek = $weekDays[ $curdate[0]['currentDayOfWeek'] ];
$currentYearOfDay = $curdate[0]['currentYearOfDay'];
$currentMonthOfDay = $yearsMonths[ $curdate[0]['currentMonthOfDay'] ];
$currentLastDay = $curdate[0]['currentLastDay'];
( $currentLastDay == $currentDayOfMonth ? $lastday = ' (ostatni)' : $lastday = '' );

$reminderPhrase[1] = 'jednorazowo na dzień '.$currentDate;
$reminderPhrase[2] = 'co tydzień w każd'.$currentDayOfWeek;
$reminderPhrase[3] = 'w '.$currentDayOfMonth.$lastday.' dzień każdego miesiąca';
$reminderPhrase[4] = $currentDayOfMonth.' '.$currentMonthOfDay.' każdego roku';


for(  $codeValue = 1;  $codeValue <=4;  $codeValue++ ){	
	$r = $dbh->getAll($SQL[$codeValue], DB_FETCHMODE_ASSOC);
	dbErrorCheck($r);
	if(count($r) > 0){
		foreach($r as $reminder){
			$mail = new PHPMailer(); // defaults to using php "mail()"
			$mail->IsSendmail(); // telling the class to use SendMail transport
			$mail->CharSet = 'utf-8';
			$mail->SetFrom( SENDER_EMAIL, SENDER_DISPLAY_NAME );
			
			$ToSQL = "SELECT ppl.WorkEmail, ppl.DisplayName FROM ppl 
			WHERE  ppl._Deleted = 0 AND ppl.PersonStatusID = 1 AND ppl.PersonID = ".$reminder['_OwnedBy'];				
			$To = $dbh->getAll( $ToSQL, DB_FETCHMODE_ASSOC );
			dbErrorCheck( $To );
			if( count( $To ) >0 ){
				foreach( $To as $ToRecipient){
					if( empty( $ToRecipient['WorkEmail'] ) ){
						$ErrorSQL = "UPDATE rmd SET Error = 'Brak emaila odbiorcy w module Osoby' WHERE ReminderID = ".$reminder['ReminderID'];
						$Error = $dbh->getAll( $ErrorSQL, DB_FETCHMODE_ASSOC );
						dbErrorCheck( $Error );
						continue 2;
				}
					$mail->AddAddress( $ToRecipient['WorkEmail'], $ToRecipient['DisplayName']);
				}
			}else{
				$ErrorSQL = "UPDATE rmd SET Error = 'Brak odbiorcy w module Osoby' WHERE ReminderID = ".$reminder['ReminderID'];
				$Error = $dbh->getAll( $ErrorSQL, DB_FETCHMODE_ASSOC );
				dbErrorCheck( $Error );
				continue;
			}
			
			// Message subject
			$modSQL = "SELECT Name FROM `mod` WHERE  mod._Deleted = 0  AND mod.ModuleID = '".$reminder['RelatedModuleID']."'";				
			$mod = $dbh->getAll( $modSQL, DB_FETCHMODE_ASSOC );
			dbErrorCheck( $mod );
			$rdcSQL = "SELECT Value FROM `rdc` WHERE  rdc._Deleted = 0  AND rdc.ModuleID = '".$reminder['RelatedModuleID']
			."' AND rdc.RecordID = ".$reminder['RelatedRecordID'];				
			$rdc = $dbh->getAll( $rdcSQL, DB_FETCHMODE_ASSOC );
			dbErrorCheck( $rdc );

			$recordTitle = preg_replace( '/[\x00-\x1F\x7F]/', '', strip_tags( $rdc[0]['Value'] ) );
			$recordTitle = preg_replace( "/&#?[a-z0-9]+;/i","", $recordTitle );	
			
			$mail->Subject  = $mod[0]['Name'].'-> '.$recordTitle.'-> '.$currentDate;			
			
			//Message body - text  format, UTF-8			
			$mail->Body  = "Witaj!\n\n@ Ten email to powiadomienie, które otrzymujesz ".$reminderPhrase[$codeValue].". Powiadomienie dotyczy wiersza \"".$recordTitle ."\" w module \"".$mod[0]['Name']."\".\n";
			$mail->Body  .= "\n@ Szczegóły rekordu są widoczne pod adresem:\n"; 
			$mail->Body  .= EMAIL_HTTP_HOST.'/frames.php?dest='.base64_encode( 'view.php?mdl='.$reminder['RelatedModuleID'].'&rid='.$reminder['RelatedRecordID'] )."\n";
			$mail->Body  .= "\n@ Do powiadomienia dołączona jest wiadomość dla Ciebie:\n    ".$reminder['Message']."\n";		
			$mail->Body  .= "\nPozdrowienia\n".SENDER_DISPLAY_NAME."\n"; 
			$mail->Body  .= "\nPS. Ten email został automatycznie wygenerowany i wysłany przez system informatyczny. Odpowiedź na niego nie będzie przez nikogo czytana!\n";
			
			if(!$mail->Send()){
				$ErrorSQL = "UPDATE rmd SET Error = '".$mail->ErrorInfo."' WHERE ReminderID = ".$reminder['ReminderID'];
				$Error = $dbh->getAll( $ErrorSQL, DB_FETCHMODE_ASSOC );
				dbErrorCheck( $Error );
				continue;
			}
			$HandOverSQL = "UPDATE rmd SET HandOver = now() WHERE ReminderID = ".$reminder['ReminderID'];
			$HandOver = $dbh->getAll( $HandOverSQL, DB_FETCHMODE_ASSOC );
			dbErrorCheck( $HandOver );
		} //foreach($r as $reminder)
	} 
}
exit
?>