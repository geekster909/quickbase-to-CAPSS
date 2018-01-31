<?php 

date_default_timezone_set('UTC');
$postData = json_decode(file_get_contents('php://input'));
$location = $postData->location;
$timestamp = (strtotime('yesterday midnight')*1000);

include_once('settings.php');// include the account info variables
include_once('quickbase.php');//include the api file

if ( !isset($location) ) {
    $return_array = array('status' => 0, 'error' => 'No location set');
} else {
	$return_array = array(
		'status' => 1,
		'location' => $location,
		'timestamp' => array(
			'milliseconds' => $timestamp,
			'date' => gmdate("m-d-Y", $timestamp / 1000)
		)
	);

	$qbTransactions = new QuickBase($qbUser, $qbPassword, $qbUserToken, true, $qbDbTables['transactions'], $qbAppToken, $qbRealm, '');//creating the object

	$transactionQueries = array(
		array(
			'fid'  => '46', // Feild ID
			'ev'   => 'EX', // Exact
			'cri'  => (strtotime('yesterday midnight')*1000) // criteria
		),
		array(
			'ao'  => 'AND',//OR is also acceptable
			'fid' => '57',
			'ev'  => 'EX',
			'cri' => 'customer'
		),
		array(
			'ao'  => 'AND',
			'fid' => '20',
			'ev'  => 'EX',
			'cri' => $location
		)
	);
	$transactionResults = $qbTransactions->do_query($transactionQueries, '', '', '3.26.46.42', '20', 'structured', 'sortorder-A');
	$transactionResults = $transactionResults->table->records->record;

	$return_array['transactionCount'] = count($transactionResults);


}


echo json_encode($return_array);