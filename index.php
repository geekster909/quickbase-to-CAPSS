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

<form id="location-form" method="post">
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
	<button id="form-submit" type="submit">Submit</button>
</form>
<div>
	Console:
	<div id="results">
	</div>
</div>

<script>
	var locationForm = document.getElementById('location-form');
	var results = document.getElementById('results');

	locationForm.addEventListener('submit', function(e){
		e.preventDefault();
		var selectedOption = document.getElementById('js-location').value;

		results.innerHTML += '----------------------------------- START REPORT -----------------------------------<br/>';

		if (selectedOption != '') {
			results.innerHTML += 'Running scripts for location: '+selectedOption+'<br/><br/>';
			
			document.getElementById("form-submit").disabled = true;
			
			ajax(selectedOption);

		} else {
			results.innerHTML += 'Please select a location';
			results.innerHTML += '<br/>------------------------------------- END REPORT -------------------------------------<br/><br/>';
		}
	});

	async function ajax(selectedOption) {
		try {
			let response = await fetch('ajax-get-transactions.php', {
				method: 'POST',
				body: JSON.stringify({location: selectedOption}),
				headers: {
					"Content-type": "application/json"
				}
			});
			if (response.ok) {
				let jsonResponse = await response.json();
				console.log(jsonResponse);
				if (jsonResponse.status === 1){
					results.innerHTML += `
						Finished running scripts for location:  ${jsonResponse.location}<br/><br/>
						Transactions Processed: ${jsonResponse.transactionCount}
						`;
				} else {
					results.innerHTML += `Error: ${jsonResponse.error}`;
				}
				results.innerHTML += '<br/>------------------------------------- END REPORT -------------------------------------<br/><br/>';
				document.getElementById("form-submit").disabled = false;
				return jsonResponse;
			}
			throw new Error('Request failed!');
		} catch (error) {
			console.log(error);
		}
	}
</script>