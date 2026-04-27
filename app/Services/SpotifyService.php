<?php

namespace App\Services;

use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;
use SpotifyWebAPI\SpotifyWebApiAuthException;

class SpotifyService
{
    protected $api;

    public function __construct()
    {
        $clientId = env('SPOTIFY_CLIENT_ID');
        $clientSecret = env('SPOTIFY_CLIENT_SECRET');

        $session = new Session(
            $clientId,
            $clientSecret,
            'http://localhost/callback'
        );

        $this->api = new SpotifyWebAPI();
        $this->api->setClientId($clientId);
        $this->api->setClientSecret($clientSecret);

        // Get an access token
        try {
            $session->requestCredentialsToken();
            $accessToken = $session->getAccessToken();
            $this->api->setAccessToken($accessToken);
        } catch (SpotifyWebApiAuthException $e) {
            throw new \Exception('Failed to authenticate with Spotify API');
        }
    }

    public function searchSongs(string $query): array
    {
        return $this->api->search($query, 'track');
    }
}
