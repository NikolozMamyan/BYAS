<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\SpotifyDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/spotify', name: 'app_api_spotify_')]
class SpotifyDataController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user, SpotifyDataService $spotifyDataService): JsonResponse
    {
        $this->denyUnlessAuthenticated($user);

        return $this->json($spotifyDataService->getCurrentProfile($user));
    }

    #[Route('/recently-played', name: 'recently_played', methods: ['GET'])]
    public function recentlyPlayed(
        #[CurrentUser] ?User $user,
        Request $request,
        SpotifyDataService $spotifyDataService
    ): JsonResponse {
        $this->denyUnlessAuthenticated($user);

        $limit = (int) $request->query->get('limit', 20);
        $after = $request->query->get('after');
        $before = $request->query->get('before');

        return $this->json(
            $spotifyDataService->getRecentlyPlayed(
                $user,
                $limit,
                is_string($after) ? $after : null,
                is_string($before) ? $before : null
            )
        );
    }

    #[Route('/top/tracks', name: 'top_tracks', methods: ['GET'])]
    public function topTracks(
        #[CurrentUser] ?User $user,
        Request $request,
        SpotifyDataService $spotifyDataService
    ): JsonResponse {
        $this->denyUnlessAuthenticated($user);

        return $this->json(
            $spotifyDataService->getTopTracks(
                $user,
                (string) $request->query->get('time_range', 'medium_term'),
                (int) $request->query->get('limit', 20),
                (int) $request->query->get('offset', 0)
            )
        );
    }

    #[Route('/top/artists', name: 'top_artists', methods: ['GET'])]
    public function topArtists(
        #[CurrentUser] ?User $user,
        Request $request,
        SpotifyDataService $spotifyDataService
    ): JsonResponse {
        $this->denyUnlessAuthenticated($user);

        return $this->json(
            $spotifyDataService->getTopArtists(
                $user,
                (string) $request->query->get('time_range', 'medium_term'),
                (int) $request->query->get('limit', 20),
                (int) $request->query->get('offset', 0)
            )
        );
    }

    #[Route('/saved/tracks', name: 'saved_tracks', methods: ['GET'])]
    public function savedTracks(
        #[CurrentUser] ?User $user,
        Request $request,
        SpotifyDataService $spotifyDataService
    ): JsonResponse {
        $this->denyUnlessAuthenticated($user);

        return $this->json(
            $spotifyDataService->getSavedTracks(
                $user,
                (int) $request->query->get('limit', 20),
                (int) $request->query->get('offset', 0)
            )
        );
    }

    #[Route('/saved/episodes', name: 'saved_episodes', methods: ['GET'])]
    public function savedEpisodes(
        #[CurrentUser] ?User $user,
        Request $request,
        SpotifyDataService $spotifyDataService
    ): JsonResponse {
        $this->denyUnlessAuthenticated($user);

        return $this->json(
            $spotifyDataService->getSavedEpisodes(
                $user,
                (int) $request->query->get('limit', 20),
                (int) $request->query->get('offset', 0)
            )
        );
    }

    #[Route('/saved/shows', name: 'saved_shows', methods: ['GET'])]
    public function savedShows(
        #[CurrentUser] ?User $user,
        Request $request,
        SpotifyDataService $spotifyDataService
    ): JsonResponse {
        $this->denyUnlessAuthenticated($user);

        return $this->json(
            $spotifyDataService->getSavedShows(
                $user,
                (int) $request->query->get('limit', 20),
                (int) $request->query->get('offset', 0)
            )
        );
    }

    #[Route('/playback', name: 'playback', methods: ['GET'])]
    public function playback(
        #[CurrentUser] ?User $user,
        SpotifyDataService $spotifyDataService
    ): JsonResponse {
        $this->denyUnlessAuthenticated($user);

        return $this->json($spotifyDataService->getPlaybackState($user));
    }

    private function denyUnlessAuthenticated(?User $user): void
    {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }
    }
}