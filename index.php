<style type="text/css">
	#results {
		min-height: 500px;
		max-height: 500px;
		width: 500px;
		padding: 15px;
		overflow-y: scroll;
		border: solid 1px #000;
		background-color: #f0ead6;
	}

</style>
<?php
	// date_default_timezone_set('UTC');
	date_default_timezone_set('America/New_York');
	// echo '<pre>'; print_r(date('D-d-M-Y H:i', strtotime('midnight'))); echo '</pre>';die('here');
	// $timestamp = strtotime('yesterday midnight');
	// $timestamp = strtotime('midnight');
	// $timestamp = strtotime('-2 days', strtotime('yesterday midnight'));
	// echo 'The day being processed is <strong>' . gmdate("m-d-Y", $timestamp) . '</strong>';
	$timestampToday = strtotime('midnight');
	$timestampYesterday = strtotime('yesterday midnight');
	$timestampTwoDaysAgo = strtotime('-1 days', strtotime('yesterday midnight'));
?>
<br/>
<br/>
<form id="location-form" method="post">
	<div>
		<label for="js-date"> Select a Date</label>
		<select id="js-date">
			<option value="">--Select Date--</option>
			<option value="<?php echo $timestampToday; ?>"><?php echo gmdate("m-d-Y", $timestampToday); ?></option>
			<option value="<?php echo $timestampYesterday; ?>"><?php echo gmdate("m-d-Y", $timestampYesterday); ?></option>
			<option value="<?php echo $timestampTwoDaysAgo; ?>"><?php echo gmdate("m-d-Y", $timestampTwoDaysAgo); ?></option>
		</select>
	</div>
	<br />
	<div>
		<label for="js-location"> Select a location</label>
		<select id="js-location">
			<option value="">--Select Location--</option>
			<option value="Carlsbad, 2525 El Camino Real #231">Carlsbad, 2525 El Camino Real #231</option>
			<option value="Chino, 12924 Central Ave.">Chino, 12924 Central Ave.</option>
			<option value="Corona, 1297 E. Ontario Ave Suite 104">Corona, 1297 E. Ontario Ave Suite 104</option>
			<option value="Hemet, 326 S. Sanderson Ave">Hemet, 326 S. Sanderson Ave</option>
			<option value="Ontario, 980 N. Ontario Mills Drive Suite B">Ontario, 980 N. Ontario Mills Drive Suite B</option>
			<option value="Palm Desert, 72333 Highway 111 Suite C">Palm Desert, 72333 Highway 111 Suite C</option>
			<option value="Rancho Santa Margarita, 30352 Esperanza">Rancho Santa Margarita, 30352 Esperanza</option>
			<option value="Redlands, 1615 W. Redlands Blvd Suite F">Redlands, 1615 W. Redlands Blvd Suite F</option>
			<option value="Riverside, 10319 Magnolia Ave.">Riverside, 10319 Magnolia Ave.</option>
			<option value="San Bernardino, 222 Inland Center">San Bernardino, 222 Inland Center</option>
			<option value="Victorville, 14400 Bear Valley Road">Victorville, 14400 Bear Valley Road</option>
			<option value="West Covina, 633 Plaza Drive">West Covina, 633 Plaza Drive</option>
		</select>
	</div>
	<br />
	<button id="form-submit" type="submit">Submit</button>
</form>
<div>
	Status: <span id="script-status">Ready</span>
</div>
<br/>
<br/>
<div>
	Console:
	<div id="results"></div>
</div>

<script>
	var locationForm = document.getElementById('location-form');
	var results = document.getElementById('results');
	var scriptStatus = document.getElementById('script-status');
	console.log(scriptStatus);
	locationForm.addEventListener('submit', function(e){
		e.preventDefault();
		var selectedDate = document.getElementById('js-date').value;
		var selectedLocation = document.getElementById('js-location').value;

		results.innerHTML += '----------------------------------- START REPORT -----------------------------------<br/>';
		
		

		if (selectedLocation != '' && selectedDate != '') {
			results.innerHTML += 'Running scripts for location: '+selectedLocation+'<br/><br/>';
	
			ajax(selectedLocation, selectedDate);

			scriptStatus.innerHTML = 'Loading...';
			document.getElementById("form-submit").disabled = true;
		} else {
			results.innerHTML += 'Please select a date and location';
			results.innerHTML += '<br/>------------------------------------- END REPORT -------------------------------------<br/><br/>';
		}
	});

	async function ajax(selectedLocation, selectedDate) {
		try {
			let response = await fetch('ajax-get-transactions.php', {
				method: 'POST',
				body: JSON.stringify({location: selectedLocation, timestamp: selectedDate}),
				headers: {
					"Content-type": "application/json"
				}
			});
			if (response.ok) {
				let jsonResponse = await response.json();
				console.log(jsonResponse);
				if (jsonResponse.status === 1){
					results.innerHTML += `
						Finished running scripts for location: ${jsonResponse.location}<br/><br/>
						Number of transactions processed: ${jsonResponse.transactionCount}<br/><br/>
						`;
					
					Object.keys(jsonResponse.transactions).map((index) => {
						const transaction = jsonResponse.transactions[index];
						results.innerHTML += `
							Processed transaction: ${transaction.recordId}<br/>
						`;
					});

					results.innerHTML += `
						<br/>
						Download .xml for location ${jsonResponse.locationCity} <a href="${jsonResponse.xmlPath}/${jsonResponse.locationCity}.xml" download>HERE</a>
					`;
				} else {
					results.innerHTML += `Error: ${jsonResponse.error}`;
				}
				results.innerHTML += '<br/>------------------------------------- END REPORT -------------------------------------<br/><br/>';
				scriptStatus.innerHTML = 'Please wait to submit again';
				
				setTimeout(function(){ 
					scriptStatus.innerHTML = 'Ready';
					document.getElementById("form-submit").disabled = false;
				}, 3000);
				
				return jsonResponse;
			}
			throw new Error('Request failed!');
		} catch (error) {
			console.log(error);
		}
	}
</script>