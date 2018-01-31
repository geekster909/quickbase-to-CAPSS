<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

date_default_timezone_set('UTC');
echo 'Timestamp being ran: '.(strtotime('yesterday midnight')*1000)." milliseconds<br/>";
$timestamp=(strtotime('yesterday midnight')*1000);
echo 'Timestamp converts to: '.gmdate("m-d-Y", $timestamp / 1000). "<br/><br/>";

include_once('settings.php');// include the account info variables
include_once('quickbase.php');//include the api file

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
	)
);

// $transactionResults = $qbTransactions->do_query($transactionQueries, '', '', 'a', '20', 'structured', 'sortorder-A'); // receive all fields
// $transactionResults = $qbTransactions->do_query($transactionQueries, '', '', '3.46.42.16.33.26.17.29.9.57.37.13.23.7.32.20.64.69.1.120.121.28.171.175', '20', 'structured', 'sortorder-A');
$transactionResults = $qbTransactions->do_query($transactionQueries, '', '', '3.26.46.42', '20', 'structured', 'sortorder-A');
$transactionResults = $transactionResults->table->records->record;

// loop through all the transactions for the day
echo '<pre>'; print_r(count($transactionResults)); echo '</pre>';
foreach($transactionResults as $record) {
	$record = json_decode(json_encode($record->f), true);
	// echo '<pre>'; print_r($record); echo '</pre>';die('here');

	//convert time of day from seconds to hours:mins:seconds
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

	// get customer data based on transaction
	$qbCustomers = new QuickBase($qbUser, $qbPassword, $qbUserToken, true, $qbDbTables['customers'], $qbAppToken, $qbRealm, '');//creating the object
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
	// echo '<pre>'; print_r($customerResults); echo '</pre>';die('here');

	// default to brown hair color if not specified
	$hairColorOptions = array ('Bald', 'Black', 'Blond', 'Brown', 'Gray', 'Red', 'Sandy', 'White');
	$hairColor = in_array($customerResults[8],$hairColorOptions) ? $customerResults[8] : 'Brown';

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
		'eyeColor' => $customerResults[9],
		'heightFeet' => $customerResults[10],
		'heightInches' => $customerResults[11],
		'weight' => $customerResults[12],
		'identificationType' => $customerResults[13],
		'idNumber' => $customerResults[14],
	);

	echo '<pre>'; print_r($transactionInfo); echo '</pre>';//die('here');

	// $xmlDoc = create_xml($recordInfo);
	// //make the output pretty
	// $xmlDoc->formatOutput = true;
	// // print_r($xmlDoc->saveXML());die();

	// $filePath = 'xml/'.gmdate("Y", $recordInfo['date']).'/'.gmdate("m", $recordInfo['date']).'/'.gmdate("d", $recordInfo['date']);

	// if (!file_exists($filePath)) {
 //    	mkdir($filePath, 0777, true);
	// }

	// if ($xmlDoc->save($filePath."/".$recordInfo['recordId'].".xml")) {
	// 	echo 'Saved Record ID '.$recordInfo['recordId'].' to '.$filePath.'<br/><br/>';
	// } else {
	// 	echo 'Did not save Record ID '.$recordInfo['recordId'].'<br/><br/>';
	// }
}

function create_xml($recordInfo) {
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
      		$xmlDoc->createTextNode("01081001"));

	$propertyTransaction = $bulkUploadData->appendChild(
    	$xmlDoc->createElement("propertyTransaction"));

	$transactionTime = $propertyTransaction->appendChild(
    	$xmlDoc->createElement("transactionTime", gmdate('Y-m-d', $recordInfo['date']).'T'.$recordInfo['timeOfDay']));

	$customer = $propertyTransaction->appendChild(
    	$xmlDoc->createElement("customer"));
	$custLastName = $customer->appendChild(
    	$xmlDoc->createElement("custLastName", $recordInfo['fullName']));
	$custFirstName = $customer->appendChild(
    	$xmlDoc->createElement("custFirstName", $recordInfo['fullName']));
	$custMiddleName = $customer->appendChild(
    	$xmlDoc->createElement("custMiddleName", "one"));
	$gender = $customer->appendChild(
    	$xmlDoc->createElement("gender", "MALE"));
	$race = $customer->appendChild(
    	$xmlDoc->createElement("race", ""));
	$hairColor = $customer->appendChild(
    	$xmlDoc->createElement("hairColor", "BROWN"));
	$eyeColor = $customer->appendChild(
    	$xmlDoc->createElement("eyeColor", "BROWN"));
	$height = $customer->appendChild(
    	$xmlDoc->createElement("height", "507"));
	$weight = $customer->appendChild(
    	$xmlDoc->createElement("weight", "185"));
		$weight->appendChild(
	    	$xmlDoc->createAttribute("unit"))->appendChild(
	      		$xmlDoc->createTextNode("pounds"));
	$dateOfBirth = $customer->appendChild(
    	$xmlDoc->createElement("dateOfBirth", "1951-11-15"));
	$streetAddress = $customer->appendChild(
    	$xmlDoc->createElement("streetAddress", "150 Baxter Ave"));
	$city = $customer->appendChild(
    	$xmlDoc->createElement("city", "Sacramento"));
	$state = $customer->appendChild(
    	$xmlDoc->createElement("state", "CA"));
	$postalCode = $customer->appendChild(
    	$xmlDoc->createElement("postalCode", "95815"));
	$phoneNumber = $customer->appendChild(
    	$xmlDoc->createElement("phoneNumber", ""));



    return $xmlDoc;
}