<?php 

date_default_timezone_set('UTC');
$postData = json_decode(file_get_contents('php://input'));
$location = $postData->location;
$timestamp = (strtotime('yesterday midnight')*1000);

include_once('settings.php');// include the account info variables
include_once('quickbase.php');//include the api file

if ( !isset($location) ) {
    $return_array = array(
    	'status' => 0,
    	'error' => 'No location set'
    );
} else {
	$return_array = array(
		'status' => 1,
		'location' => $location,
		'timestamp' => array(
			'milliseconds' => $timestamp,
			'date' => gmdate("m-d-Y", $timestamp / 1000)
		),
		'transactionCount' => '',
		'transactions' => array()
	);

	// create the object for the TRANSACTIONS table
	$qbTransactions = new QuickBase($qbUser, $qbPassword, $qbUserToken, true, $qbDbTables['transactions'], $qbAppToken, $qbRealm, '');

	// set the queries for the TRANSACTIONS table
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

	// do the query in the TRANSATIONS table
	// 3 - Record ID
	// 26 - Full Name
	// 46 - Date (in milliseconds)
	// 42 - Time of Day (in milliseconds)
	$transactionResults = $qbTransactions->do_query($transactionQueries, '', '', '3.26.46.42', '3', 'structured', 'sortorder-A');
	$transactionResults = $transactionResults->table->records->record;

	// return the count of how many transactions
	$return_array['transactionCount'] = count($transactionResults);

	//check if 
	if ($return_array['transactionCount'] === 0) {
		$return_array['status'] = 0;
		$return_array['error'] = 'No transactions to be proccessed';
		echo json_encode($return_array);
		return;
	}

	foreach($transactionResults as $record) {
		$record = json_decode(json_encode($record->f), true);



		// convert time of day from seconds to hours:mins:seconds
		$record[3] = $record[3]/1000;
		$hours = floor($record[3] / 3600);
		$mins = floor($record[3] / 60 % 60);
		$transactionTimeOfDay = sprintf('%02d:%02d', $hours, $mins);
		$transactionTimeOfDay = date('h:i A', strtotime($transactionTimeOfDay));

		$transactionInfo = array(
			'recordId' => $record[0],
			'customerFullName' => $record[1],
			'transactionDate' => gmdate("m-d-Y", $record[2] / 1000),
			'transactionTime' => $transactionTimeOfDay,
		);

		// create the object for the CUSTOMERS table
		$qbCustomers = new QuickBase($qbUser, $qbPassword, $qbUserToken, true, $qbDbTables['customers'], $qbAppToken, $qbRealm, '');
		
		// do the query in the CUSTOMERS table
		$customersQueries = array(
			array(
				'fid'  => '31',
				'ev'   => 'EX',
				'cri'  => $transactionInfo['customerFullName']
			)
		);

		$customerResults = $qbCustomers->do_query($customersQueries, '', '', '6.7.35.8.9.10.11.14.70.71.72.73.74.75.62', '', 'structured', 'sortorder-A');
		$customerResults = $customerResults->table->records->record->f;
		$customerResults = json_decode(json_encode($customerResults), true);

		$hairColorOptions = array ('Bald', 'Black', 'Blond', 'Brown', 'Gray', 'Red', 'Sandy', 'White');
		$hairColor = in_array($customerResults[8],$hairColorOptions) ? $customerResults[8] : 'Brown';

		$eyeColorOptions = array ('Black', 'Blue', 'Brown', 'Gray', 'Hazel', 'Pink', 'Green', 'Multi Color', 'Unknown');
		$eyeColor = in_array($customerResults[9],$eyeColorOptions) ? $customerResults[9] : 'Brown';

		$transactionInfo['customer'] = array(
			'firstName' => $customerResults[0],
			'lastName' => $customerResults[1],
			'dob' => gmdate("m-d-Y", $customerResults[2] / 1000),
			'address' => $customerResults[3],
			'city' => $customerResults[4],
			'state' => $customerResults[5],
			'postalCode' => $customerResults[6],
			'gender' => $customerResults[7],
			'hairColor' => $hairColor,
			'eyeColor' => $eyeColor,
			'heightFeet' => $customerResults[10],
			'heightInches' => $customerResults[11],
			'weight' => $customerResults[12],
			'identificationType' => $customerResults[13],
			'idNumber' => $customerResults[14],
		);









		$return_array['transactions'][$transactionInfo['recordId']] = $transactionInfo;


	}
}


echo json_encode($return_array);
return;