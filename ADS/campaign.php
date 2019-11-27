<?php 

require_once '/var/www/html/root/CONFIG/common_config.php';
require_once COMMON_FUNCTIONS;
require_once LOG_CLASS;
require_once DB_CONNECTION_CLASS;


date_default_timezone_set('Asia/Calcutta');

$sLogPath 	= 	'/home/log/' . date('Y') . '/' . date('m') . '/adnetwork/campaign_' . date('Y-m-d') . '.log';
checkLogPath($sLogPath);

$sClickId	=	date("YmdHis").rand(0,9999);

$sLog		=	"\n".date("Y-m-d H:i:s")."|".$sClickId."|CAMPAIGN|".$_SERVER["QUERY_STRING"];
error_log($sLog, 3, $sLogPath);

$camp_id	=	$_REQUEST["cid"];
$hash_id	=	$_REQUEST["hash"];
$pub_id		=	$_REQUEST["pub_id"];

$DB_CLASS 	= 	new Db_Connection_Class();

$sGetCampaignDetails 	= 	"SELECT PARTNER_URL FROM ".TBL_ADNETWORK_CAMPAIGN." WHERE ID = ".$camp_id;
$aCampaignDetails 		= 	$DB_CLASS->executeQuery($sGetCampaignDetails);

$sLog		=	"\n".date("Y-m-d H:i:s")."|".$sClickId."|CAMPAIGN_DETAILS|".json_encode($aCampaignDetails);
error_log($sLog, 3, $sLogPath);

foreach($aCampaignDetails['data'] as $iIndex=>$details)
{
	$sUrl	=	$details["PARTNER_URL"];
}

if($sUrl != "")
{	
	$sUrl 													= 	preg_replace('/##CLICK_ID##/i', $sClickId, $sUrl);
	
	$campaign_table_details["CLICK_ID"]                    	=   $sClickId;
	$campaign_table_details["PUB_ID"]                      	=   $pub_id;
	$campaign_table_details["HASH_ID"]                    	=   $hash_id;
	$campaign_table_details["STATUS"]                       =   '1';
	//$campaign_table_details["SEND_AD_NETWORK_STATUS"]      	=   '0';
	$campaign_table_details["ADDED_ON"]                     =   date("Y-m-d H:i:s");
	$campaign_table_details["USER_AGENT"]                   =   addslashes($_SERVER["HTTP_USER_AGENT"]);
	$campaign_table_details["REFERRER_URL"]                 =   $_SERVER["HTTP_REFERER"];
	$campaign_table_details["CURRENT_URL"]                  =   $sUrl;
	$campaign_table_details["CAMP_ID"]                      =   $camp_id;	

	$aInsert                                				=   $DB_CLASS->insertTable(TBL_ADNETWORK_CLICKS, $campaign_table_details);
	
	$sLog		=	"\n".date("Y-m-d H:i:s")."|".$sClickId."|INSERT CLICKS|".json_encode($aInsert);
	error_log($sLog, 3, $sLogPath);
	
	header("Location:".$sUrl);
	exit;
}
else
{
	$sLog		=	"\n".date("Y-m-d H:i:s")."|".$sClickId."|BLANK URL";
	error_log($sLog, 3, $sLogPath);
	
	echo "Bad Request";
	exit;
}

?>