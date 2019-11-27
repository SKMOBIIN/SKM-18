<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '/var/www/html/root/CONFIG/common_config.php';
require_once LOG_CLASS;
require_once COMMON_FUNCTIONS;
require_once DB_CONNECTION_CLASS;
require_once DB_CONNECTION_CLASS_18;

$aOption = getopt('o:');
$rOperator = isset($aOption['o']) ? $aOption['o'] : '1';

$DB_CLASS = new Db_Connection_Class();
$DB_CLASS_18 = new Db_Connection_Class_18();

$logfilename = '/home/log/'.date('Y').'/'.date('m').'/adnetwork/'.$rOperator.'_SEND_TO_PARTNER_'.date('Y-m-d').'.log';
checkLogPath($sLogPath);

$sDate = date('Y-m-d H:i:s');

$checklogfilename = '/home/log/'.date('Y').'/'.date('m').'/adnetwork/'.$rOperator.'_CHECK_SEND_TO_PARTNER_'.date('Y-m-d').'.log';
checkLogPath($checklogfilename);

$extra_check_param = '';

exec("ps agfx | grep '/var/www/html/root/ADS/CRON/send_to_adnetwork.php -o $rOperator' | grep -v grep | grep -v 'bin/bash' $extra_check_param", $aProcessCount);

$sLog 		= 	"\n".date("Y-m-d H:i:s")."|===================== Start Of Send to Partner cron - $operatorName ============================";
error_log($sLog, 3, $sLogPath);

$sLog 		= 	"\n".date("Y-m-d H:i:s")."|".json_encode($aProcessCount);
error_log($sLog, 3, $sLogPath);

if(count($aProcessCount) > 2) {
	$sLog 		= 	"\n".date("Y-m-d H:i:s")."|End Time";
	error_log($sLog, 3, $sLogPath);
}

$current_date = date('Y-m-d');

$sGetAdnetworkDetails = "SELECT t1.ID,t1.SUBS_ID,t1.TRANS_TYPE_ID,t2.CAMPAIGN_TABLE_ID,t2.CAMPAIGN_ID,t2.ADNETWORK_ID,t1.SEND_AD_NETWORK_STATUS, t1.RATE FROM $sTableName t1, $tbl_subscription t2 WHERE t1.ADDED_ON >= '$current_date 00:00:00' AND  t1.ADDED_ON <= '$current_date 23:59:59' AND t1.TRANS_TYPE_ID=2 AND t1.SUBS_ID=t2.ID $rcondtion";


$sGetAdnetworkDetails	=	"SELECT t1.CLICK_ID, t1.PUB_ID, t1.HASH_ID, t1.STATUS, t1.SEND_AD_NETWORK_STATUS, t1.CAMP_ID, t2.ADNETWORK_ID, t2.ADNETWORK_NAME, t2.CPA_PERCENTAGE FROM ".TBL_ADNETWORK_CLICKS." t1, ".TBL_ADNETWORK_CAMPAIGN." t2 WHERE t1.ADDED_ON >= '$current_date 00:00:00' AND  t1.ADDED_ON <= '$current_date 23:59:59' AND t1.STATUS=2";

$aAdnetworkDetails = $DB_CLASS->executeQuery($sGetAdnetworkDetails);

echo "<pre>";
print_r($aAdnetworkDetails);
//exit;
$aAdNetworkFullArray =array();

$sLog 		= 	"\n".date("Y-m-d H:i:s")."Query | $sGetAdnetworkDetails | ".$aAdnetworkDetails['status'].' | ', __LINE__;
error_log($sLog, 3, $sLogPath);

foreach($aAdnetworkDetails['data'] as $iIndex=>$details)
{
    $send_ad_network_status 	= 	$details['SEND_AD_NETWORK_STATUS'];
    $ad_network_Id 				= 	$details['ADNETWORK_ID'];
    $trans_typeId  				= 	$details['STATUS'];
    $campaign_table_id 			= 	$details['CAMP_ID'];
    $cpa_percentage 			= 	$details['CAMP_ID'];
    
    $sGetTransType = getTansType($trans_typeId);
    
    if($send_ad_network_status == 0)
    {
        if(isset($aAdNetworkFullArray[$ad_network_Id][$sGetTransType]['REQUEST_TO_PROCESS']['COUNT']))
        {
            $aAdNetworkFullArray[$ad_network_Id][$sGetTransType]['REQUEST_TO_PROCESS']['COUNT']++;
        }
        else
        {
            $aAdNetworkFullArray[$ad_network_Id][$sGetTransType]['REQUEST_TO_PROCESS']['COUNT'] = 1;
        }
        $aRequestToProcess[$ad_network_Id][$sGetTransType][] = $details;
        $aDistintAdIds[$ad_network_Id] = $ad_network_Id;
        $DistictTransType[$sGetTransType] = $sGetTransType;
    }
    elseif($send_ad_network_status == 1)
    {
        if(isset($aAdNetworkFullArray[$ad_network_Id][$sGetTransType]['REQUEST_SENT']['COUNT']))
        {
            $aAdNetworkFullArray[$ad_network_Id][$sGetTransType]['REQUEST_SENT']['COUNT']++;
        }
        else
        {
            $aAdNetworkFullArray[$ad_network_Id][$sGetTransType]['REQUEST_SENT']['COUNT'] = 1;
        }
    }
    elseif($send_ad_network_status == 2)
    {
        if(isset($aAdNetworkFullArray[$ad_network_Id][$sGetTransType]['REQUEST_SKIPPED']['COUNT']))
        {
            $aAdNetworkFullArray[$ad_network_Id][$sGetTransType]['REQUEST_SKIPPED']['COUNT']++;
        }
        else
        {
            $aAdNetworkFullArray[$ad_network_Id][$sGetTransType]['REQUEST_SKIPPED']['COUNT'] = 1;
        }
    }

}
// LOGIC TO LOOP REQUEST_TO_PROCESS ARRAY AND SEND RESPONSE TO AD NETWORK -
$aAdNetworkDetails = $DB_CLASS->getAllAdNetworkDetail();

$sLog 		= 	"\n".date("Y-m-d H:i:s")."Details found | ". json_encode($aAdNetworkFullArray).' | ', __LINE__;
error_log($sLog, 3, $sLogPath);

print_r($aAdNetworkFullArray);
print_r($aAdNetworkDetails);
print_r($aDistintAdIds);
print_r($DistictTransType);

//exit;
foreach($aDistintAdIds as $Adindex => $sAdId)
{
    
    if($sAdId == 7 || $sAdId == 151)
    {
        continue;
    }
    foreach($DistictTransType as $transType)
    {
        $sPostBackUrl = $aAdNetworkDetails['data'][$sAdId]['ad_postback_url'];
        $sPayoutType = $aAdNetworkDetails['data'][$sAdId]['am_payout_type'];
        $sAdName = $aAdNetworkDetails['data'][$sAdId]['ad_name'];
        $sSubPer = $aAdNetworkDetails['data'][$sAdId]['am_sub_per'];

        $total_ActRecieved =  $aAdNetworkFullArray[$sAdId][$transType]['REQUEST_TO_PROCESS']['COUNT'];

        $reqToSend  = (int)(($sSubPer/100) * $total_ActRecieved);
        
        if($rOperator == 21)
        {
            if($total_ActRecieved <=4)
            {
                if($total_ActRecieved > 1)
                    $reqToSend = $total_ActRecieved - 1;
                else
                    $reqToSend = $total_ActRecieved;
            }
        }
        $LOG_CLASS->commonLogging("Ad Wise Details found | ADID - $sAdId | ADNAME - $sAdName | REQUEST TO PROCESS - $total_ActRecieved | REQUEST TO SEND - $reqToSend  | SEND PERCENTAGE SET - $sSubPer | POSTBACK URL - $sPostBackUrl   | ", __LINE__, 'SEND_TO_PARTNER');
        
        $i=0;
        
        foreach($aRequestToProcess[$sAdId][$transType] as $index1 => $transdetails)
        {
            $subsId = $transdetails['SUBS_ID'];
            $TransTableId = $transdetails['ID'];
            $campaign_table_id = $transdetails['CAMPAIGN_TABLE_ID'];
            $rate           =   $transdetails['RATE'];
            
            if($rOperator == '1' &&  $rate <= 6)
            {
                $sUpdateTransactionTableStatus = "UPDATE ".$tbl_transaction." SET SEND_AD_NETWORK_STATUS='2' WHERE ID='$TransTableId'";
                echo "<br>".$sUpdateTransactionTableStatus."<br>";
                $updateDetails = $DB_CLASS_18->executeQuery($sUpdateTransactionTableStatus);
                $aAdNetworkFullArray[$sAdId][$transType]['REQUEST_SKIPPED']['COUNT']++ ;

                $LOG_CLASS->commonLogging('|'.$sAdName . "| => FINAL SKIPPED TO AD PARTNER LOG = | SUBS ID - $subsId | TRANSACTION ID - $TransTableId | REASON OF SKIPPPING - AIRTEL PRICE LESS THAN 5", __LINE__, 'SEND_AD_NETWORK');

                continue;
            }
            if($i <= $reqToSend)
            {
                // CONDITION TO SEND THE POSTABCK AND UPDATE THE STATUS
                // BELOW QUERY TO GET HASH DETAILS FROM CAMPAIGN DETAILS TABLE-
                $sGetCampHashKeyDetails = "SELECT ID, HASH_KEY, PUB_ID  FROM  ".$tbl_campaign." WHERE ID='$campaign_table_id'";
                $aHashDetails = $DB_CLASS->executeQuery($sGetCampHashKeyDetails);

				if(strtoupper($aHashDetails['status']) == "FAIL")				
				{
					$tbl_campaign_backup	=	str_replace("db_skm", "db_backup", $tbl_campaign)."_".date("Y_m", strtotime("-1 DAY"));
					
					$sGetCampHashKeyDetails = "SELECT ID, HASH_KEY, PUB_ID  FROM  ".$tbl_campaign_backup." WHERE ID='$campaign_table_id'";
					$aHashDetails = $DB_CLASS->executeQuery($sGetCampHashKeyDetails);
				}
				
				if($rOperator == '1' || $rOperator == '9' )
				{
					echo "<pre>";
					//print_r($aHashDetails);
					//exit;
				}
				
                $hash = $aHashDetails['data'][0]['HASH_KEY'];
                $pub_id = $aHashDetails['data'][0]['PUB_ID'];
                $LOG_CLASS->commonLogging('|'.$sAdName . ' => GET HASH QUERY = ' . json_encode($aHashDetails), __LINE__, 'SEND_AD_NETWORK');
                if($hash == '')
                {
                    $sUpdateTransactionTableStatus = "UPDATE ".$tbl_transaction." SET SEND_AD_NETWORK_STATUS='2' WHERE ID='$TransTableId'";
                    echo "<br>".$sUpdateTransactionTableStatus."<br>";
                    $updateDetails = $DB_CLASS_18->executeQuery($sUpdateTransactionTableStatus);
                    $aAdNetworkFullArray[$sAdId][$transType]['REQUEST_SKIPPED']['COUNT']++ ;

                    $LOG_CLASS->commonLogging('|'.$sAdName . "| => FINAL SKIPPED TO AD PARTNER LOG = | SUBS ID - $subsId | TRANSACTION ID - $TransTableId", __LINE__, 'SEND_AD_NETWORK');
                    
                    continue;
                }
                
                // CONDITION TO CHECK IF HASH IS ALREDY SENT - 
                
                $query = "grep '$hash' $checklogfilename | wc -l";
                $check = 0;
                
                $check = exec($query);
                $LOG_CLASS->commonLogging('|'.$sAdName . ' => HASH SENT CHECK QUERY = ' . $query . '|RESPONSE - ' . $check .'|', __LINE__, 'SEND_AD_NETWORK');
                
                if($check >= 1)
                {
                    $sUpdateTransactionTableStatus = "UPDATE ".$tbl_transaction." SET SEND_AD_NETWORK_STATUS='2' WHERE ID='$TransTableId'";
                    echo "<br>".$sUpdateTransactionTableStatus."<br>";
                    $updateDetails = $DB_CLASS_18->executeQuery($sUpdateTransactionTableStatus);
                    $aAdNetworkFullArray[$sAdId][$transType]['REQUEST_SKIPPED']['COUNT']++ ;

                    $LOG_CLASS->commonLogging('|'.$sAdName . "| => FINAL SKIPPED TO AD PARTNER LOG = | SUBS ID - $subsId | TRANSACTION ID - $TransTableId | REASON OF SKIPPPING - FOUND IN MULTIPLE HASH CHECK", __LINE__, 'SEND_AD_NETWORK');
                    
                    continue;
                }
                
                // CONDITION TO CHECK IF HASH IS ALREDY SENT ENDS- 
                
                if (preg_match('/##HASH##/i', $sPostBackUrl)) {
                    $sPostBack1 = preg_replace('/##HASH##/i', $hash, $sPostBackUrl);
                    $sPostBack = preg_replace('/##PUB_ID##/i', $pub_id, $sPostBack1);
                }
                
                // UPDATE TRANSACTION TABLE SENT TO PARTNER STATUS TO 1
                $sUpdateTransactionTableStatus = "UPDATE ".$tbl_transaction." SET SEND_AD_NETWORK_STATUS='1', CAMPAIGN_TABLE_ID='$campaign_table_id', ADNETWORK_ID='$sAdId',ADNETWORK_NAME='$sAdName', HASH_ID = '$hash'   WHERE ID='$TransTableId'";
                $updateDetails = $DB_CLASS_18->executeQuery($sUpdateTransactionTableStatus);
                
                echo "<br>".$sUpdateTransactionTableStatus."<br>";
                $LOG_CLASS->commonLogging('|'.$sAdName . ' => Update Trans SENT STATUS= ' . json_encode($updateDetails), __LINE__, 'SEND_AD_NETWORK');
                $LOG_CLASS->commonLogging('|'.$sAdName . ' => FINAL SENT TO AD PARTNER LOG = | '.$sPostBack."| SUBS ID - $subsId | TRANSACTION ID - $TransTableId | HASH = $hash", __LINE__, 'SEND_AD_NETWORK');
                
                error_log("\n ". $sDate .'|'.$sAdName . ' => FINAL SENT TO AD PARTNER LOG = | '.$sPostBack."| SUBS ID - $subsId | TRANSACTION ID - $TransTableId | HASH = $hash" , 3, $checklogfilename);
                
                if($sPostBack) {
                    $sResponse = curlSend($sPostBack);
                    echo "<br>$sPostBack";
                    $sResponse = trim($sResponse);
                    
                    #sSendCount++;
                    $sReason = 'SUCCESS';
                    $aAdNetworkFullArray[$sAdId][$transType]['REQUEST_SENT']['COUNT']++;
                    
                    error_log(date('Y-m-d H:i:s')."| $sAdName | $sPostBack | $sResponse \n  ",3,'/home/log/'.date('Y').'/'.date('m')."/".$operatorName."_SEND_TO_PARTNER_RESPONSE_LOG".date('Ymd').'.txt');
                    #$sActualAdSendRemain--;
                }
            }
            else
            {
                // SKIPPING SEND TO PARTNER AND UPDATING TRANS TABLE STATUS - 

                $sUpdateTransactionTableStatus = "UPDATE ".$tbl_transaction." SET SEND_AD_NETWORK_STATUS='2', CAMPAIGN_TABLE_ID='$campaign_table_id', ADNETWORK_ID='$sAdId',ADNETWORK_NAME='$sAdName', HASH_ID = '$hash'  WHERE ID='$TransTableId'";
                echo "<br>".$sUpdateTransactionTableStatus."<br>";
                $updateDetails = $DB_CLASS_18->executeQuery($sUpdateTransactionTableStatus);
                $aAdNetworkFullArray[$sAdId][$transType]['REQUEST_SKIPPED']['COUNT']++ ;

                $LOG_CLASS->commonLogging('|'.$sAdName . "| => FINAL SKIPPED TO AD PARTNER LOG = | SUBS ID - $subsId | TRANSACTION ID - $TransTableId", __LINE__, 'SEND_AD_NETWORK');
                
            }
            $i++;
        }
    }
    
}


?>