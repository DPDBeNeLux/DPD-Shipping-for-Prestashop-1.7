<?php

class Gmaps
{

	public function getGeoData($postal_code, $isoCode)
	{
		$gmapsKey = Configuration::get('PS_API_KEY');

		$data = urlencode('country:' . $isoCode . '|postal_code:' . $postal_code);
		$url = "https://maps.googleapis.com/maps/api/geocode/json?key=". $gmapsKey . "&components=". $data;

		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, $url);
	    
	    /* For local development only */
	    //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		//curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		
	  	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($curl);
		$gmapsData = json_decode($result);
		curl_close($curl);

		$LATITUDE = $gmapsData->results[0]->geometry->location->lat;
		$LONGITUDE = $gmapsData->results[0]->geometry->location->lng;

		return [
			'longitude' => $LONGITUDE,
			'latitude' => $LATITUDE
		];

	}

}