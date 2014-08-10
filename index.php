<?php
set_time_limit(0);
include('connexion.php');
include('plugin_date.php');

include('plugin_datetime.php');

//Webservice
$sUrl=$sUrlMkmail.'webservice.php?WSDL';

$oSoap = new SoapClient($sUrl, array("trace" => 1, "exception" => 0,'cache_wsdl' => WSDL_CACHE_NONE)); 
$sDate=$oSoap->getLastDateIntegration();

require_once('ImapMailbox.php');

// IMAP must be enabled in Google Mail Settings
define('GMAIL_EMAIL', $username);
define('GMAIL_PASSWORD', $password);
define('ATTACHMENTS_DIR', dirname(__FILE__) . '/attachments');

$mailbox = new ImapMailbox('{imap.gmail.com:993/imap/ssl}INBOX', GMAIL_EMAIL, GMAIL_PASSWORD, ATTACHMENTS_DIR, 'utf-8');
$mails = array();

if($sDate){
	$oDate=new plugin_date($sDate);
}else{
	$oDate=new plugin_date('2014-07-01');
}

// Get some mail
$mailsIds = $mailbox->searchMailBox('SINCE "'.$oDate->toString('d M Y').'"');
if(!$mailsIds) {
	die('Mailbox is empty');
}

$tMail=array();
foreach($mailsIds as $mailId){
	$mail = $mailbox->getMail($mailId);
	
	$body=$mail->textHtml;
	if($body==''){
		$body=$mail->textPlain;
	}
	
	$sDateTime=date("Y-m-d H:i:s", strtotime($mail->date));
	list($sDate,$sTime)=explode(' ',$sDateTime);
	
	$oMail=new stdclass;
	$oMail->messageUId=$mail->id.$mail->date;
	$oMail->subject=$mail->subject;
	$oMail->body=$body;
	$oMail->from=$mail->fromAddress;
	$oMail->sNameFrom=$mail->fromAddress;
	$oMail->date=$sDate;
	$oMail->time=$sTime;
	$oMail->tFiles=null;
	
	$tAttachments=$mail->getAttachments();
	if($tAttachments){
		$oMail->tFiles=array();
		foreach($tAttachments as $oAttachment){
			$data=file_get_contents($oAttachment->filePath);
			
			$tDetailFile=array(
				'name'=>$oAttachment->name,
				'content'=>$data,
			);
			
			$oMail->tFiles[]=$tDetailFile;
		}
	}
	
	$tMail[]=$oMail;

	
	$return=$oSoap->setContent(
		$oMail->messageUId,
		$oMail->subject,
		base64_encode($oMail->body),
		$oMail->from,
		$oMail->sNameFrom,
		$oMail->date,
		$oMail->time
	);
	//files
	if($oMail->tFiles){
		foreach($oMail->tFiles as $DetailtFile){
			$oSoap->addFile($oMail->messageUId,base64_encode($DetailtFile['content']),$DetailtFile['name']);
		}
	}
	
}

print count($tMail).' mails processed';

exit;




$mailId = reset($mailsIds);
$mail = $mailbox->getMail($mailId);

var_dump($mail);
var_dump($mail->getAttachments());




?>
<style>
table td, table th{
	border:1px solid gray;
}
</style>
<h1>Liste mails import&eacute;es</h1>
<table>
<tr>
	<th>messageId</th>
	<th>Date</th>
	<th>From</th>
	<th>Sujet</th>

</tr>
<?php foreach($tMail as $oMail):?>
<tr>
	<td><?php echo $oMail->messageUId?></td>
	<td><?php echo $oMail->date.' '.$oMail->time?></td>
	<td><?php echo $oMail->from?></td>
	<td><?php echo $oMail->subject?></td>
	
</tr>
<?php endforeach;?>
</table>

