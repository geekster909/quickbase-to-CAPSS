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

$qb = new QuickBase($qbUser, $qbPassword, $qbUserToken, true, $qbDbId, $qbAppToken, $qbRealm, '');//creating the object

$queries = array(
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

// $results = $qb->do_query($queries, '', '', 'a', '20', 'structured', 'sortorder-A'); // receive all fields
$results = $qb->do_query($queries, '', '', '3.46.42.16.33.26.17.29.9.57.37.13.23.7.32.20.64.69.1.120.121.28.171.175', '20', 'structured', 'sortorder-A');

foreach($results->table->records->record as $record) {
	$record = json_decode(json_encode($record->f), true);
	// echo '<pre>'; print_r($record); echo '</pre>';die('here');

	//convert time of day from seconds to hours:mins:seconds
	$record[2] = $record[2]/1000;
	$hours = floor($record[2] / 3600);
	$mins = floor($record[2] / 60 % 60);
	$secs = floor($record[2] % 60);
	$timeOfDay = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

	$recordInfo = array(
		"Record ID" => $record[0],
		"Date" => $record[1]/1000,
		"Time of Day" => $timeOfDay,
		"Customer Address" => $record[3],
		"Employee" => $record[4],
		"Full Name" => $record[5],
		"Cusomter City" => $record[6],
		"Cusomter Marketing Source" => $record[7],
		"Amount Paid" => $record[8],
		"Transaction Type" => $record[9],
		"Check Paid" => $record[10],
		"Check Number(s)" => $record[11],
		"Repeat Customer" => $record[12],
		"Jewelry" => $record[13],
		"Bullion" => $record[14],
		"Total Price paid for Diamonds" => $record[15],
		"Location" => $record[16],
		"Marketing Source or Repeat" => $record[17],
		"Time2" => $record[18],
		"Date Created" => $record[19],
		"Current Location" => $record[20],
		"Current Location - Related Location" => $record[21],
		"Notes" => $record[22],
		"Customer Phone" => $record[23],
		"Cusomter Email" => $record[24],
	);
	// echo '<pre>'; print_r($recordInfo); echo '</pre>';die();

	$xmlDoc = create_xml($recordInfo);
	//make the output pretty
	$xmlDoc->formatOutput = true;
	// print_r($xmlDoc->saveXML());die();

	$filePath = 'xml/'.gmdate("Y", $recordInfo['Date']).'/'.gmdate("m", $recordInfo['Date']).'/'.gmdate("d", $recordInfo['Date']);

	if (!file_exists($filePath)) {
    	mkdir($filePath, 0777, true);
	}

	if ($xmlDoc->save($filePath."/".$recordInfo['Record ID'].".xml")) {
		echo 'Saved Record ID '.$recordInfo['Record ID'].' to '.$filePath.'<br/><br/>';
	} else {
		echo 'Did not save Record ID '.$recordInfo['Record ID'].'<br/><br/>';
	}
}

function create_xml($recordInfo) {
	// header("Content-Type: text/plain");

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
    	$xmlDoc->createElement("transactionTime", gmdate('Y-m-d', $recordInfo['Date']).'T'.$recordInfo['Time of Day']));

	$customer = $propertyTransaction->appendChild(
    	$xmlDoc->createElement("customer"));
	$custLastName = $customer->appendChild(
    	$xmlDoc->createElement("custLastName", $recordInfo['Full Name']));
	$custFirstName = $customer->appendChild(
    	$xmlDoc->createElement("custFirstName", $recordInfo['Full Name']));
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