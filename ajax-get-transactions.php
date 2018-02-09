<?php 

date_default_timezone_set('UTC');
$postData = json_decode(file_get_contents('php://input'));
$location = $postData->location;
$locationCity = str_replace(' ', '_', substr($location, 0, strpos($location, ',')));
$yesterdayMidnight = strtotime('yesterday midnight');
$timestamp = ($yesterdayMidnight*1000);

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
		'xmlPath' => 'xml/'.gmdate("Y", $yesterdayMidnight).'/'.gmdate("m", $yesterdayMidnight).'/'.gmdate("d", $yesterdayMidnight),
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
			'cri'  => ($yesterdayMidnight*1000) // criteria
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
	$transactionResults = $qbTransactions->do_query($transactionQueries, '', '', '3.26.46.42.33', '3', 'structured', 'sortorder-A');
	$transactionResults = $transactionResults->table->records->record;

	// return the count of how many transactions
	$return_array['transactionCount'] = count($transactionResults);

	//check if there was 0 transactions and do not continue
	if ($return_array['transactionCount'] === 0) {
		$return_array['status'] = 0;
		$return_array['error'] = 'No transactions to be proccessed';
		echo json_encode($return_array);
		return;
	}

	// create the object for the CUSTOMERS table
	$qbLocations = new QuickBase($qbUser, $qbPassword, $qbUserToken, true, $qbDbTables['locations'], $qbAppToken, $qbRealm, '');
	
	// set the queries for the CUSTOMERS table
	$locationsQueries = array(
		array(
			'fid'  => '20',
			'ev'   => 'EX',
			'cri'  => $location
		)
	);

	// do the query in the CUSTOMERS table
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
			'transactionDate' => $transactionDate,
			'transactionTime' => $transactionTimeOfDay,
			'xmlPath' => 'xml/'.gmdate("Y", $transactionDateSeconds).'/'.gmdate("m", $transactionDateSeconds).'/'.gmdate("d", $transactionDateSeconds)
		);

		// create the object for the CUSTOMERS table
		$qbCustomers = new QuickBase($qbUser, $qbPassword, $qbUserToken, true, $qbDbTables['customers'], $qbAppToken, $qbRealm, '');
		
		// set the queries for the CUSTOMERS table
		$customersQueries = array(
			array(
				'fid'  => '31',
				'ev'   => 'EX',
				'cri'  => $transactionInfo['customerFullName']
			)
		);

		// do the query in the CUSTOMERS table
		$customerResults = $qbCustomers->do_query($customersQueries, '', '', '6.7.35.8.9.10.11.14.70.71.72.73.74.75.62.79.76.77.64.81.80', '', 'structured', 'sortorder-A');
		$customerResults = $customerResults->table->records->record->f;
		$customerResults = json_decode(json_encode($customerResults), true);

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

		// set the customer information for the transaction
		$transactionInfo['customerInfo'] = array(
			'firstName' => $customerResults[0],
			'lastName' => $customerResults[1],
			'dob' => $customerResults[2],
			'address' => $customerResults[3],
			'city' => $customerResults[4],
			'state' => $customerResults[5],
			'postalCode' => $customerResults[6],
			'gender' => $customerResults[7],
			'hairColor' => $hairColor,
			'eyeColor' => $eyeColor,
			'height' => $heightFeet.'0'.$heightInches,
			'weight' => $weight,
			'idType' => $customerResults[13],
			'idNumber' => $customerResults[14],
			'idDateOfIssue' => $customerResults[15],
			'idIssueState' => $customerResults[16],
			'idIssueCountry' => $customerResults[17],
			'idYearOfExpiration' => $customerResults[18],
			// 'customerSignature' => $customerResults[19],
			// 'customerThumbprint' => $customerResults[20],
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

		// convert customer information
		$transactionInfo['customerInfo']['dob'] = gmdate("Y-m-d", $transactionInfo['customerInfo']['dob'] / 1000);
		$transactionInfo['customerInfo']['idDateOfIssue'] = gmdate("Y-m-d", $transactionInfo['customerInfo']['idDateOfIssue'] / 1000);
		$transactionInfo['customerInfo']['customerSignature'] = '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';
		// $transactionInfo['customerInfo']['customerSignature'] = base64_encode(file_get_contents($transactionInfo['customerInfo']['customerSignature']));
		$transactionInfo['customerInfo']['customerThumbprint'] = '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';
		// $transactionInfo['customerInfo']['customerThumbprint'] = base64_encode(file_get_contents($transactionInfo['customerInfo']['customerThumbprint']));
		// echo '<pre>'; print_r($transactionInfo['customerInfo']); echo '</pre>';die('here');

		//set the store info for the transaction
		$transactionInfo['storeInfo'] = array(
			'employeeName' => $employeeFullName
		);









		$return_array['transactions'][$transactionInfo['recordId']] = $transactionInfo;

	}
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
			Customer Thumbprint (image)
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
			$xmlDoc->createTextNode("33131099"));
		//$return_array['location_license']

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
				$customerThumbprint = $customer->appendChild(
					$xmlDoc->createElement("thumbprint", $transactionInfo['customerInfo']['customerThumbprint']));
			$store = $propertyTransaction->appendChild(
				$xmlDoc->createElement("store"));
				$employeeName = $store->appendChild(
					$xmlDoc->createElement("employeeName", $transactionInfo['storeInfo']['employeeName']));
				$employeeSignature = $store->appendChild(
					$xmlDoc->createElement("signature", $transactionInfo['storeInfo']['employeeSignature']));
			$items = $propertyTransaction->appendChild(
				$xmlDoc->createElement("items"));

			// foreach ($items as $item) {
				$item = $items->appendChild(
					$xmlDoc->createElement("item"));
					$itemType = $item->appendChild(
						$xmlDoc->createElement("type", 'BUY'));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("loanBuyNumber", '1234'));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("amount", '713.00'));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("article", 'WATCH'));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("brand", 'ROLEX'));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("serialNumber", '123U601945'));
					$itemReferenceId = $item->appendChild(
						$xmlDoc->createElement("description", 'S G ENGRAVED ON BACK,COLOR-GOLDEN,VALUE$10,000'));

			// }
	}
	return $xmlDoc;
}