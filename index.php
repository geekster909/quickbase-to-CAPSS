<?php

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

$results = $qb->do_query($queries, '', '', 'a', '20', 'structured', 'sortorder-A'); // receive all fields

foreach($results->table->records->record as $record) {
	echo '<pre>'; print_r($record); echo '</pre>';
}