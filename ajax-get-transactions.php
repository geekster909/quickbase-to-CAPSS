<?php 

// date_default_timezone_set('UTC');
date_default_timezone_set('America/New_York');
$postData = json_decode(file_get_contents('php://input'));
$location = $postData->location;
$locationCity = str_replace(' ', '_', substr($location, 0, strpos($location, ',')));
// $yesterdayMidnight = strtotime('yesterday midnight');
// $todayMidnight = strtotime('midnight');
// $yesterdayMidnight = strtotime('-2 days', strtotime('yesterday midnight'));
$submittedTimestamp = $postData->timestamp;
$timestamp = ($submittedTimestamp*1000);

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
		'locationCity' => $locationCity,
		'xmlPath' => 'xml/'.gmdate("Y", $submittedTimestamp).'/'.gmdate("m", $submittedTimestamp).'/'.gmdate("d", $submittedTimestamp),
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
			'cri'  => ($submittedTimestamp*1000) // criteria
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

	// do the query in the TRANSACTIONS table
	// 3 - Record ID
	// 26 - Full Name
	// 46 - Date (in milliseconds)
	// 42 - Time of Day (in milliseconds)
	// 33 - Employee
	// 196 - Customer Address
	$transactionResults = $qbTransactions->do_query($transactionQueries, '', '', '3.26.46.42.33.196', '3', 'structured', 'sortorder-A');
	$transactionResults = $transactionResults->table->records->record;
	// echo '<pre>'; print_r($transactionResults); echo '</pre>';die('here');

	// return the count of how many transactions
	$return_array['transactionCount'] = count($transactionResults);

	//check if there was 0 transactions and do not continue
	if ($return_array['transactionCount'] === 0) {
		$return_array['status'] = 0;
		$return_array['error'] = 'No transactions to be proccessed';
		echo json_encode($return_array);
		return;
	}

	// create the object for the LOCATIONS table
	$qbLocations = new QuickBase($qbUser, $qbPassword, $qbUserToken, true, $qbDbTables['locations'], $qbAppToken, $qbRealm, '');
	
	// set the queries for the LOCATIONS table
	$locationsQueries = array(
		array(
			'fid'  => '20',
			'ev'   => 'EX',
			'cri'  => $location
		)
	);

	// do the query in the LOCATIONS table
	$locationResults = $qbLocations->do_query($locationsQueries, '', '', '62', '', 'structured', 'sortorder-A');
	$locationResults = $locationResults->table->records->record->f;
	$locationResults = json_decode(json_encode($locationResults), true);
	$return_array['location_license'] = $locationResults[0];

	//check if there is a license number and do not continue
	if (is_null($return_array['location_license'])) {
		$return_array['status'] = 0;
		$return_array['error'] = 'No location license number';
		echo json_encode($return_array);
		return;
	}

	// loop through every transaction that was placed for the day
	foreach($transactionResults as $record) {
		$record = json_decode(json_encode($record->f), true);

		// set the variables for the results
		$recordId = $record[0];
		$customerFullName = $record[1];
		$customerAddress = $record[5];
		$transactionDateSeconds = $record[2] / 1000;
		$transactionDate = gmdate("Y-m-d", $transactionDateSeconds);
		$employeeFullName = $record[4];

		// convert time of day from seconds to hours:mins:seconds
		$record[3] = $record[3]/1000;
		$hours = floor($record[3] / 3600);
		$mins = floor($record[3] / 60 % 60);
		$transactionTimeOfDay = sprintf('%02d:%02d', $hours, $mins);
		$transactionTimeOfDay = date('h:i:s', strtotime($transactionTimeOfDay));

		// set the transaction info array
		$transactionInfo = array(
			'recordId' => $recordId,
			'customerFullName' => $customerFullName,
			'customerAddress' => $customerAddress,
			'transactionDate' => $transactionDate,
			'transactionTime' => $transactionTimeOfDay,
			'xmlPath' => 'xml/'.gmdate("Y", $transactionDateSeconds).'/'.gmdate("m", $transactionDateSeconds).'/'.gmdate("d", $transactionDateSeconds)
		);
		// echo '<pre>'; print_r($transactionInfo); echo '</pre>';die('here');

		// create the object for the CUSTOMERS table
		$qbCustomers = new QuickBase($qbUser, $qbPassword, $qbUserToken, true, $qbDbTables['customers'], $qbAppToken, $qbRealm, '');
		
		// set the queries for the CUSTOMERS table
		$customersQueries = array(
			array(
				'fid'  => '31',
				'ev'   => 'EX',
				'cri'  => $transactionInfo['customerFullName']
			),
			array(
				'ao'  => 'AND',//OR is also acceptable
				'fid' => '84',
				'ev'  => 'EX',
				'cri' => $transactionInfo['customerAddress']
			)
		);

		// do the query in the CUSTOMERS table
		// 6 - First Name
		// 7 - Last Name
		// 35 - DOB
		// 85 - Address #1
		// 87 - Address City
		// 88 - Address State
		// 89 - Address Postal Code
		// 14 - Gender
		// 70 - Hair Color
		// 71 - Eye Color
		// 72 - Height (feet)
		// 73 - Height (inches)
		// 74 - Weight (inches)
		// 75 - idType
		// 62 - idNumber
		// 79 - idDateOfIssue
		// 76 - idIssueState
		// 77 - idIssueCountry
		// 64 - idYearOfExpiration
		$customerResults = $qbCustomers->do_query($customersQueries, '', '', '6.7.35.85.87.88.89.14.70.71.72.73.74.75.62.79.76.77.64', '', 'structured', 'sortorder-A');
		$customerResults = $customerResults->table->records->record->f;
		$customerResults = json_decode(json_encode($customerResults), true);
		// echo '<pre>'; print_r($customerResults); echo '</pre>';die('here');

		// set a default hair color if there is not one
		$hairColorOptions = array ('Bald', 'Black', 'Blond', 'Brown', 'Gray', 'Red', 'Sandy', 'White');
		$hairColor = in_array($customerResults[8],$hairColorOptions) ? $customerResults[8] : 'Brown';

		// set a default eye color if there is not one
		$eyeColorOptions = array ('Black', 'Blue', 'Brown', 'Gray', 'Hazel', 'Pink', 'Green', 'Multi Color', 'Unknown');
		$eyeColor = in_array($customerResults[9],$eyeColorOptions) ? $customerResults[9] : 'Brown';

		// set a default height if there is not one
		$heightFeet = is_array($customerResults[10]) ? '5' : $customerResults[10];
		$heightInches = is_array($customerResults[11]) ? '7' : $customerResults[11];
		$weight = is_array($customerResults[12]) ? '175' : $customerResults[12];

		// set gender if M or F
		$gender = $customerResults[7];
		if ($gender == 'M' || $gender == 'm') {
			$gender = 'Male';
		} elseif ($gender == 'F' || $gender == 'f') {
			$gender = 'Female';
		}

		// create the object for the CUSTOMER ATTACHMENTS table
		$qbCustomerAttachments = new QuickBase($qbUser, $qbPassword, $qbUserToken, true, $qbDbTables['customerAttachments'], $qbAppToken, $qbRealm, '');
		
		// set the queries for the CUSTOMERS table
		$customerAttachmentsQueries = array(
			array(
				'fid'  => '9',
				'ev'   => 'EX',
				'cri'  => $transactionInfo['customerFullName']
			),
			array(
				'ao'  => 'AND',//OR is also acceptable
				'fid' => '11',
				'ev'  => 'EX',
				'cri' => $customerResults[2]
			)
		);

		// do the query in the CUSTOMERS table
		// 6 - Customer Signature
		// 7 - Customer Fingerprint

		// do the query in the CUSTOMERS table for fingerprint
		$customerSignature = $qbCustomerAttachments->do_query($customerAttachmentsQueries, '', '', '6', '', 'structured', 'sortorder-A');
		$customerSignature = $customerSignature->table->records->record->f;
		$customerSignature = json_decode(json_encode($customerSignature), true);
		if ($customerSignature['url']) {
			$customerSignature = base64_encode(file_get_contents($customerSignature['url']));
		}

		// do the query in the CUSTOMERS table for fingerprint
		$customerFingerprint = $qbCustomerAttachments->do_query($customerAttachmentsQueries, '', '', '7', '', 'structured', 'sortorder-A');
		$customerFingerprint = $customerFingerprint->table->records->record->f;
		$customerFingerprint = json_decode(json_encode($customerFingerprint), true);
		if ($customerFingerprint['url']) {
			$customerFingerprint = base64_encode(file_get_contents($customerFingerprint['url']));
		}

		// set the customer information for the transaction
		$transactionInfo['customerInfo'] = array(
			'firstName' => $customerResults[0],
			'lastName' => $customerResults[1],
			'dob' => gmdate("Y-m-d", $customerResults[2] / 1000),
			'address' => $customerResults[3],
			'city' => $customerResults[4],
			'state' => $customerResults[5],
			'postalCode' => $customerResults[6],
			'phoneNumber' => '',
			'gender' => $gender,
			'hairColor' => $hairColor,
			'eyeColor' => $eyeColor,
			'height' => $heightFeet.'0'.$heightInches,
			'weight' => $weight,
			'idType' => $customerResults[13],
			'idNumber' => $customerResults[14],
			'idDateOfIssue' => gmdate("Y-m-d", $customerResults[15] / 1000),
			'idIssueState' => $customerResults[16],
			'idIssueCountry' => $customerResults[17],
			'idYearOfExpiration' => $customerResults[18],
			'customerSignature' => $customerSignature,
			'customerFingerprint' => $customerFingerprint,
		);
		// echo '<pre>'; print_r($transactionInfo['customerInfo']); echo '</pre>';die('here');

		// Loop through Customer Info to make sure there are values
		foreach($transactionInfo['customerInfo'] as $key => $value) {
			if (is_array($value)) {
				$return_array['status'] = 0;
				$return_array['error'] = 'Customer Field "'.$key.'" has no value for Record ' . $transactionInfo['recordId'];
				echo json_encode($return_array);
				return;
			}
		}
		// echo '<pre>'; print_r($transactionInfo['customerInfo']); echo '</pre>';die('here');

		//set the store info for the transaction
		$transactionInfo['storeInfo'] = array(
			'employeeName' => $employeeFullName,
			'employeeSignature' => ''
		);

		// create the object for the ITEMS table
		$qbItems = new QuickBase($qbUser, $qbPassword, $qbUserToken, true, $qbDbTables['items'], $qbAppToken, $qbRealm, '');
		
		// set the queries for the ITEMS table
		$itemsQueries = array(
			array(
				'fid'  => '18',
				'ev'   => 'EX',
				'cri'  => $transactionInfo['recordId']
			)
		);

		// do the query in the ITEMS table
		$itemResults = $qbItems->do_query($itemsQueries, '', '', '6.7.8.9.10.11', '', 'structured', 'sortorder-A');
		$itemResults = $itemResults->table->records->record;
		// echo '<pre>'; print_r(count($itemResults)); echo '</pre>';die('here');

		//check if there are items and do not continue
		if (count($itemResults)=== 0) {
			$return_array['status'] = 0;
			$return_array['error'] = 'There are no items for Record ' . $transactionInfo['recordId'];
			echo json_encode($return_array);
			return;
		}

		// set the items for the transaction
		$i = 0;
		foreach ($itemResults as $key => $value) {
			$item = json_decode(json_encode($value->f), true);
			
			$itemInfo = array(
				'loanBuyNumber' => $item[0],
				'amount' => $item[1],
				'article' => $item[2],
				'brand' => $item[3],
				'serialNumber' => $item[4],
				'description' => $item[5],
			);

			$transactionInfo['items'][$i] = $itemInfo;
			$i++;
		}

		$return_array['transactions'][$transactionInfo['recordId']] = $transactionInfo;

	}
	
	// echo '<pre>'; print_r($return_array); echo '</pre>';die('here');
	$xmlDoc = create_xml($return_array);
		//make the output pretty
	$xmlDoc->formatOutput = true;
	// print_r($xmlDoc->saveXML());die();

	

	if (!file_exists($transactionInfo['xmlPath'])) {
		mkdir($transactionInfo['xmlPath'], 0777, true);
	}

	$xmlDoc->save($transactionInfo['xmlPath']."/".$locationCity.".xml");
	// if ($xmlDoc->save($filePath."/".$recordInfo['recordId'].".xml")) {
	// 	echo 'Saved Record ID '.$recordInfo['recordId'].' to '.$filePath.'<br/><br/>';
	// } else {
	// 	echo 'Did not save Record ID '.$recordInfo['recordId'].'<br/><br/>';
	// }
}


echo json_encode($return_array);
return;


function create_xml($return_array) {
	// header("Content-Type: text/plain");

	/*
		REQUIRED FIELDS

		CUSTOMER INFO
			Last Name 
			First Name
			DOB mm/dd/yyyy
			Address
			City
			State "California"
			Postal Code
			Gender (Male, Female)
			Hair Color (Bald, Black, Blond, Brown, Gray, Red, Sandy, White)
			Eye Color (Black, Blue, Browm, Gray, Hazel, Pink, Green, Multi Color)
			Height (ft.)
			Height (in.)
			Weight
			Indentification Type (Drivers License, Passport, State Id, Military Id, Matricula Consular, United States Id)
			ID Number
		
		STORE INFO
			Store Name
			License Number
			Law Enforcement Agency
			Address
			City
			State
			Postal Code
			Store Phone Number
			Employee Name
			Employee Signiture (image)

		TRANSACTION ITEMS
			Transaction Date (mm/dd/yyy)
			Transaction Time (hh:mm AM/PM)

		ITEM(S)
			Type (Buy, Consign, Trade, Auction)
			Loan/Buy Number
			$ Amount
			Article
			Brand Name
			Serial Number
			Property Description (One Item Only, Size, Color, Material, etc...)

		SIGNITURE
			Customer Signiture (image)
			Customer Fingerprint (image)
	*/

	//create the xml document
	$xmlDoc = new DOMDocument('1.0', 'UTF-8');

	$capssUpload = $xmlDoc->appendChild(
		$xmlDoc->createElement('capssUpload'));

	$capssUpload->appendChild(
		$xmlDoc->createAttribute("xmlns:xsd"))->appendChild(
			$xmlDoc->createTextNode("http://www.w3.org/2001/XMLSchema"));

	$capssUpload->appendChild(
		$xmlDoc->createAttribute("xmlns:xsi"))->appendChild(
			$xmlDoc->createTextNode("http://www.w3.org/2001/XMLSchema-instance"));

	$bulkUploadData = $capssUpload->appendChild(
		$xmlDoc->createElement("bulkUploadData"));
	$bulkUploadData->appendChild(
		$xmlDoc->createAttribute("licenseNumber"))->appendChild(
			$xmlDoc->createTextNode($return_array['location_license']));

	foreach ($return_array['transactions'] as $transactionInfo) {
		$propertyTransaction = $bulkUploadData->appendChild(
			$xmlDoc->createElement("propertyTransaction"));
			$transactionTime = $propertyTransaction->appendChild(
				$xmlDoc->createElement("transactionTime", $transactionInfo['transactionDate'].'T'.$transactionInfo['transactionTime']));
			$customer = $propertyTransaction->appendChild(
				$xmlDoc->createElement("customer"));
				$custLastName = $customer->appendChild(
					$xmlDoc->createElement("custLastName", $transactionInfo['customerInfo']['lastName']));
				$custFirstName = $customer->appendChild(
					$xmlDoc->createElement("custFirstName", $transactionInfo['customerInfo']['firstName']));
				$gender = $customer->appendChild(
					$xmlDoc->createElement("gender", $transactionInfo['customerInfo']['gender']));
				$hairColor = $customer->appendChild(
					$xmlDoc->createElement("hairColor", $transactionInfo['customerInfo']['hairColor']));
				$eyeColor = $customer->appendChild(
					$xmlDoc->createElement("eyeColor", $transactionInfo['customerInfo']['eyeColor']));
				$height = $customer->appendChild(
					$xmlDoc->createElement("height", $transactionInfo['customerInfo']['height']));
				$weight = $customer->appendChild(
					$xmlDoc->createElement("weight", $transactionInfo['customerInfo']['weight']));
				$dateOfBirth = $customer->appendChild(
					$xmlDoc->createElement("dateOfBirth", $transactionInfo['customerInfo']['dob']));
				$streetAddress = $customer->appendChild(
					$xmlDoc->createElement("streetAddress", $transactionInfo['customerInfo']['address']));
				$city = $customer->appendChild(
					$xmlDoc->createElement("city", $transactionInfo['customerInfo']['city']));
				$state = $customer->appendChild(
					$xmlDoc->createElement("state", $transactionInfo['customerInfo']['state']));
				$postalCode = $customer->appendChild(
					$xmlDoc->createElement("postalCode", $transactionInfo['customerInfo']['postalCode']));
				$phoneNumber = $customer->appendChild(
					$xmlDoc->createElement("phoneNumber", $transactionInfo['customerInfo']['phoneNumber']));
				$id = $customer->appendChild(
					$xmlDoc->createElement("id"));
					$idType = $id->appendChild(
						$xmlDoc->createElement("type", $transactionInfo['customerInfo']['idType']));
					$idNumber = $id->appendChild(
						$xmlDoc->createElement("number", $transactionInfo['customerInfo']['idNumber']));
					$idDateOfIssue = $id->appendChild(
						$xmlDoc->createElement("dateOfIssue", $transactionInfo['customerInfo']['idDateOfIssue']));
					$idIssueState= $id->appendChild(
						$xmlDoc->createElement("issueState", $transactionInfo['customerInfo']['idIssueState']));
					$idIssueCountry= $id->appendChild(
						$xmlDoc->createElement("issueCountry", $transactionInfo['customerInfo']['idIssueCountry']));
					$idYearOfExpiration = $id->appendChild(
						$xmlDoc->createElement("yearOfExpiration", $transactionInfo['customerInfo']['idYearOfExpiration']));
				$customerSignature = $customer->appendChild(
					$xmlDoc->createElement("signature", $transactionInfo['customerInfo']['customerSignature']));
				$customerFingerprint = $customer->appendChild(
					$xmlDoc->createElement("fingerprint", $transactionInfo['customerInfo']['customerFingerprint']));
			$store = $propertyTransaction->appendChild(
				$xmlDoc->createElement("store"));
				$employeeName = $store->appendChild(
					$xmlDoc->createElement("employeeName", $transactionInfo['storeInfo']['employeeName']));
				$employeeSignature = $store->appendChild(
					$xmlDoc->createElement("signature", $transactionInfo['storeInfo']['employeeSignature']));
			$items = $propertyTransaction->appendChild(
				$xmlDoc->createElement("items"));

			for ($i=0; $i < count($transactionInfo['items']); $i++) { 
				$item = $items->appendChild(
					$xmlDoc->createElement("item"));
					$itemType = $item->appendChild(
						$xmlDoc->createElement("type", 'BUY'));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("loanBuyNumber", $transactionInfo['items'][$i]['loanBuyNumber']));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("amount", $transactionInfo['items'][$i]['amount']));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("article", $transactionInfo['items'][$i]['article']));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("brand", $transactionInfo['items'][$i]['brand']));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("serialNumber", $transactionInfo['items'][$i]['serialNumber']));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("description", $transactionInfo['items'][$i]['description']));
			}
	}
	return $xmlDoc;
}