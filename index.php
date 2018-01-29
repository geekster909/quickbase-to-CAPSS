<?php
date_default_timezone_set('UTC');
echo 'Timestamp being ran: '.(strtotime('yesterday midnight')*1000)." milliseconds<br/>";
$timestamp=(strtotime('yesterday midnight')*1000);
echo 'Timestamp converts to: '.gmdate("m-d-Y", $timestamp / 1000). "<br/>";

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
	$record = $record->f;
	echo '<pre>'; print_r($record); echo '</pre>';
}