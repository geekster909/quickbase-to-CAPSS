<?php 

date_default_timezone_set('UTC');
$postData = json_decode(file_get_contents('php://input'));
$location = $postData->location;
$locationCity = str_replace(' ', '_', substr($location, 0, strpos($location, ',')));
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
			'xmlPath' => 'xml/'.gmdate("Y", $transactionDateSeconds).'/'.gmdate("m", $transactionDateSeconds).'/'.gmdate("d", $transactionDateSeconds).'/'.$locationCity
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
		// $transactionInfo['customerInfo']['customerSignature'] = base64_encode(file_get_contents($transactionInfo['customerInfo']['customerSignature']));
		// $transactionInfo['customerInfo']['customerThumbprint'] = base64_encode(file_get_contents($transactionInfo['customerInfo']['customerThumbprint']));
		// echo '<pre>'; print_r($transactionInfo['customerInfo']); echo '</pre>';die('here');

		//set the store info for the transaction

		$transactionInfo['storeInfo'] = array(
			'employeeName' => $employeeFullName
		);









		$return_array['transactions'][$transactionInfo['recordId']] = $transactionInfo;
		$xmlDoc = create_xml($transactionInfo);
		//make the output pretty
		$xmlDoc->formatOutput = true;
		// print_r($xmlDoc->saveXML());die();

		

		if (!file_exists($transactionInfo['xmlPath'])) {
			mkdir($transactionInfo['xmlPath'], 0777, true);
		}

		$xmlDoc->save($transactionInfo['xmlPath']."/".$transactionInfo['recordId'].".xml");
		// if ($xmlDoc->save($filePath."/".$recordInfo['recordId'].".xml")) {
		// 	echo 'Saved Record ID '.$recordInfo['recordId'].' to '.$filePath.'<br/><br/>';
		// } else {
		// 	echo 'Did not save Record ID '.$recordInfo['recordId'].'<br/><br/>';
		// }

	}
}


echo json_encode($return_array);
return;


function create_xml($transactionInfo) {
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
			Article
			Brand Name
			Loan/Buy Number
			$ Amount
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
				$itemReferenceId = $item->appendChild(
					$xmlDoc->createElement("referenceId", ''));
				$itemType = $item->appendChild(
					$xmlDoc->createElement("type", 'BUY'));
				$itemReferenceId = $item->appendChild(
					$xmlDoc->createElement("loanBuyNumber", ''));
				$itemReferenceId = $item->appendChild(
					$xmlDoc->createElement("amount", ''));
				$itemReferenceId = $item->appendChild(
					$xmlDoc->createElement("article", ''));
				$itemReferenceId = $item->appendChild(
					$xmlDoc->createElement("brand", ''));
				$itemReferenceId = $item->appendChild(
					$xmlDoc->createElement("serialNumber", ''));
				$itemReferenceId = $item->appendChild(
					$xmlDoc->createElement("description", ''));
		// }
	return $xmlDoc;
}