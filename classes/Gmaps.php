<?php

class Gmaps
{
    public function getGeoData($postal_code, $isoCode)
    {
        $gmapsKey = Configuration::get('PS_API_KEY');
        $data = urlencode('country:' . $isoCode . '|postal_code:' . $postal_code);
        $url = "https://maps.googleapis.com/maps/api/geocode/json?key=" . $gmapsKey . "&address=" . $data . '&sensor=false';
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $source = curl_exec($ch);
        curl_close($ch);
        $gmapsData = json_decode($source);

        $latitude = null;
        $longitude = null;
        if (count($gmapsData->results) > 0) {
            $latitude = $gmapsData->results[0]->geometry->location->lat;
            $longitude = $gmapsData->results[0]->geometry->location->lng;
        }

        return [
            'longitude' => $longitude,
            'latitude' => $latitude,
        ];
    }
}