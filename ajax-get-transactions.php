<?php 
	$postData = json_decode(file_get_contents('php://input'));
	$location = $postData->location;

if ( !isset($location) ) {
    $return_array = array('status' => 0, 'error' => 'No location set');
} else {
	$return_array = array('status' => 1, 'location' => $location);
}


echo json_encode($return_array);