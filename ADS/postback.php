<?php 

require_once '/var/www/html/root/CONFIG/common_config.php';
require_once COMMON_FUNCTIONS;
require_once LOG_CLASS;
require_once DB_CONNECTION_CLASS_18;


date_default_timezone_set('Asia/Calcutta');

$sLogPath 	= 	'/home/log/' . date('Y') . '/' . date('m') . '/adnetwork/postback_' . date('Y-m-d') . '.log';
checkLogPath($sLogPath);

$sClickId		=	$_REQUEST["track_id"];
$camp_id		=	$_REQUEST["pid"];

$sLog		=	"\n".date("Y-m-d H:i:s")."|".$sClickId."|POSTBACK|".$_SERVER["QUERY_STRING"];
error_log($sLog, 3, $sLogPath);

$DB_CLASS_18 	= 	new Db_Connection_Class_18();

if(!empty($_SERVER['HTTP_CLIENT_IP'])) 
{
    $ip 	= 	$_SERVER['HTTP_CLIENT_IP'];
} 
else
if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) 
{
    $ip 	= 	$_SERVER['HTTP_X_FORWARDED_FOR'];
} 
else 
{
    $ip 	= 	$_SERVER['REMOTE_ADDR'];
}

$sGetCampaignDetails 	= 	"UPDATE ".TBL_ADNETWORK_CLICKS." SET STATUS = 2, POSTBACK_RECEIVED_ON = '".date("Y-m-d H:i:s")."', POSTBACK_IP = '".$ip."' WHERE CLICK_ID = '".$sClickId."'";
$aCampaignDetails 		= 	$DB_CLASS_18->executeQuery($sGetCampaignDetails);

$sLog		=	"\n".date("Y-m-d H:i:s")."|".$sClickId."|UPDATE_POSTBACK|".json_encode($aCampaignDetails);
error_log($sLog, 3, $sLogPath);

echo '1';
exit;

?>