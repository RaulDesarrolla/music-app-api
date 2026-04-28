<?php

namespace App\Http\Controllers;

use App\Services\SpotifyService;
use Illuminate\Http\Request;

class SpotifyController extends Controller
{
    protected $spotifyService;

    public function __construct(SpotifyService $spotifyService)
    {
        $this->spotifyService = $spotifyService;
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');

        if (empty($query)) {
            return response()->json(['error' => 'Search query is required'], 400);
        }

        try {
            $results = $this->spotifyService->searchSongs($query);

            // Extract the desired information from the results
            $formattedResults = [];
            foreach ($results->tracks->items as $track) {
                $formattedResults[] = [
                    'name' => $track->name,
                    'artist' => $track->artists[0]->name,
                    'album_cover' => $track->album->images[0]->url ?? null,
                ];
            }

            return response()->json($formattedResults);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
