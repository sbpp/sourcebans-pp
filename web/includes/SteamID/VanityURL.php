<?php

namespace SteamID;

/**
 * Class VanityURL
 * @package SteamID
 */
class VanityURL
{
    /**
     * @param string $url
     * @param string $steamApiKey
     * @return bool|mixed
     */
    public static function resolve($url, $steamApiKey)
    {
        $endpoint = "https://api.steampowered.com/ISteamUser/ResolveVanityURL/v1/?key=$steamApiKey&vanityurl=$url";

        $curl = curl_init($endpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            curl_close($curl);
            return false;
        }

        curl_close($curl);
        $data = json_decode($response, true);

        if ($data['response']['success'] === 1) {
            return $data['response']['steamid'];
        }
        return false;
    }
}
