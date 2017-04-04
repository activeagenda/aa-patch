#!/usr/bin/php
<?php
/**
 * crone script to send out notifications stored in the ntf table
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
$codeSQL = "SELECT Value, Description FROM `cod` WHERE CodeTypeID=312";
$codes = $dbh->getAll($codeSQL, DB_FETCHMODE_ASSOC);
dbErrorCheck( $codes );
foreach( $codes as $code){
	$state[ $code['Value'] ] = trim( strip_tags( $code['Description'] ) );
}

// If this doesn't scale well do several scripts with the additional  SQL condition RelatedModuleID='xxx' attached below:
$SQL = "SELECT * FROM `ntf` WHERE _Deleted = 0 AND SendFlag = 1 AND HandOver IS NULL AND Error IS NULL";
$r = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
dbErrorCheck($r);
if(count($r) > 0){
	foreach($r as $notification){
		$mail = new PHPMailer(); // defaults to using php "mail()"
		$mail->IsSendmail(); // telling the class to use SendMail transport
		$mail->CharSet = 'utf-8';
		$mail->SetFrom( SENDER_EMAIL, SENDER_DISPLAY_NAME );

		//To: email header
		if( !empty( $notification['Receivers'] ) ){
			$ReceiversFilter = 'ppl.'.$notification['Receivers'].' = 1';
			$receiverCondition = $ReceiversFilter;
		}
		if( !empty( $notification['Receiver'] ) ){
			$ReceiverID = 'ppl.PersonID = '.$notification['Receiver'];
			$receiverCondition = $ReceiverID;
		}
		if( !empty( $notification['Receivers'] ) AND !empty( $notification['Receiver'] ) ){
			$receiverCondition = '('.$ReceiversFilter.' OR '.$ReceiverID.')';
		}		
		
		$ToSQL = "SELECT ppl.WorkEmail, ppl.DisplayName FROM ppl 
		WHERE  ppl._Deleted = 0 AND ppl.PersonStatusID = 1 AND ".$receiverCondition;				
		$To = $dbh->getAll( $ToSQL, DB_FETCHMODE_ASSOC );
		dbErrorCheck( $To );
		if( count( $To ) >0 ){
			foreach( $To as $ToRecipient){
				if( empty( $ToRecipient['WorkEmail'] ) ){
					$ErrorSQL = "UPDATE ntf SET Error = 'Brak emaila odbiorcy w module Osoby' WHERE NotificationID = ".$notification['NotificationID'];
					$Error = $dbh->getAll( $ErrorSQL, DB_FETCHMODE_ASSOC );
					dbErrorCheck( $Error );
					continue 2;
			}
				$mail->AddAddress( $ToRecipient['WorkEmail'], $ToRecipient['DisplayName']);
			}
		}else{
			$ErrorSQL = "UPDATE ntf SET Error = 'Brak odbiorcy w module Osoby' WHERE NotificationID = ".$notification['NotificationID'];
			$Error = $dbh->getAll( $ErrorSQL, DB_FETCHMODE_ASSOC );
			dbErrorCheck( $Error );
			continue;
		}
		
		// Message subject
		$modSQL = "SELECT Name FROM `mod` WHERE  mod._Deleted = 0  AND mod.ModuleID = '".$notification['RelatedModuleID']."'";				
		$mod = $dbh->getAll( $modSQL, DB_FETCHMODE_ASSOC );
		dbErrorCheck( $mod );
		$rdcSQL = "SELECT Value, OwnedBy FROM `rdc` WHERE  rdc._Deleted = 0  AND rdc.ModuleID = '".$notification['RelatedModuleID']
		 ."' AND rdc.RecordID = ".$notification['RelatedRecordID'];				
		$rdc = $dbh->getAll( $rdcSQL, DB_FETCHMODE_ASSOC );
		dbErrorCheck( $rdc );
		
		$recordTitle = preg_replace( '/[\x00-\x1F\x7F]/', '', strip_tags( $rdc[0]['Value'] ) );
		$recordTitle = preg_replace( "/&#?[a-z0-9]+;/i","", $recordTitle );	
		
		$CaseOwnerSQL =  "SELECT ppl.DisplayName As DisplayName, org.Name AS Organization, loc.Name AS Location 
		 FROM ppl, org, loc WHERE"
		." ppl._Deleted = 0 AND ppl.PersonStatusID = 1 AND ppl.PersonID = ".$rdc[0]['OwnedBy']
		." AND org._Deleted = 0 AND ppl.OrganizationID = org.OrganizationID"
		." AND loc._Deleted = 0 AND ppl.LocationID = loc.LocationID";		
		$CaseOwner = $dbh->getAll( $CaseOwnerSQL , DB_FETCHMODE_ASSOC );
		dbErrorCheck( $To );
		
		switch( $notification['MessageTemplate'] ){
			case 1:
				$mail->Subject  = $mod[0]['Name'].'-> '.$recordTitle;	
						
				//Message body - text  format, UTF-8				
				$mail->Body  = "Witaj!\n\n@ W module \"".$mod[0]['Name']."\" dodana została nowa sprawa \"".$recordTitle."\".\n";
				$mail->Body  .= "\n@ Jesteś uczestnikiem tej sprawy! Szczegóły sprawy są widoczne pod adresem:\n";
				$mail->Body  .= EMAIL_HTTP_HOST.'/frames.php?dest='.base64_encode( 'view.php?mdl='.$notification['RelatedModuleID'].'&rid='.$notification['RelatedRecordID'] )."\n";
				break;	
			default:
				$mail->Subject  = $mod[0]['Name'].'-> '.$recordTitle.'-> Sprawa '.$state[ $notification['ProcessStateID'] ];	
						
				//Message body - text  format, UTF-8				
				$mail->Body  = "Witaj!\n\n@ W module \"".$mod[0]['Name']."\" zmienił się stan sprawy \"".$recordTitle."\" z \"".$state[ $notification['OldProcessStateID'] ]."\" na \"".$state[ $notification['ProcessStateID'] ]."\".\n";
				$mail->Body  .= "\n@ Jesteś uczestnikiem tej sprawy! Nowy stan i szczegóły historii sprawy są widoczne pod adresem:\n";
				$mail->Body  .= EMAIL_HTTP_HOST.'/frames.php?dest='.base64_encode( 'view.php?mdl='.$notification['RelatedModuleID'].'&rid='.$notification['RelatedRecordID'] )."\n";
				if ( !empty($notification['Remark']) ){
					$mail->Body  .= "\n@ Uwagi nadającego do nowego stanu sprawy:\n\"".$notification['Remark']."\"\n";
				}
				$mail->Body  .= "\n@ Jeżeli Twoja rola w procesie tego wymaga podejmij działania w sprawie pod adresem:\n";
				$mail->Body  .= EMAIL_HTTP_HOST.'/frames.php?dest='.base64_encode( 'edit.php?scr=Actions&mdl='.$notification['RelatedModuleID'].'&rid='.$notification['RelatedRecordID'] )."\n";
		}
		$mail->Body  .= "\nPozdrowienia\n".SENDER_DISPLAY_NAME."\n"; 
		$mail->Body  .= "\nPS. Ten email został automatycznie wygenerowany i wysłany przez system informatyczny. Odpowiedź na niego nie będzie przez nikogo czytana!\n";
		$mail->Body  .= "\nZnaczniki do ustawiania sobie reguł poczty w Outlooku:\n";		
		$mail->Body  .= "[".$CaseOwner[0]['DisplayName']."]\n";
		$mail->Body  .= "[".$CaseOwner[0]['Organization']."]\n";
		$mail->Body  .= "[".$CaseOwner[0]['Location']."]\n";
		
		if(!$mail->Send()){
			$ErrorSQL = "UPDATE ntf SET Error = '".$mail->ErrorInfo."' WHERE NotificationID = ".$notification['NotificationID'];
			$Error = $dbh->getAll( $ErrorSQL, DB_FETCHMODE_ASSOC );
			dbErrorCheck( $Error );
			continue;
		}
		$HandOverSQL = "UPDATE ntf SET HandOver = now() WHERE NotificationID = ".$notification['NotificationID'];
		$HandOver = $dbh->getAll( $HandOverSQL, DB_FETCHMODE_ASSOC );
		dbErrorCheck( $HandOver );
	} //foreach($r as $notification)
} 
exit
?>