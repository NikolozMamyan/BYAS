<?php

namespace App\Service;

use App\Entity\AppNotification;
use App\Entity\Badge;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationCenter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $contextData
     */
    public function notify(
        User $user,
        string $type,
        string $title,
        string $body,
        ?string $targetUrl = null,
        ?User $actorUser = null,
        ?string $actorName = null,
        ?string $actorAvatarUrl = null,
        array $contextData = [],
    ): AppNotification {
        $notification = (new AppNotification())
            ->setUser($user)
            ->setType($type)
            ->setTitle($title)
            ->setBody($body)
            ->setTargetUrl($targetUrl)
            ->setActorUser($actorUser)
            ->setActorName($actorName ?? $actorUser?->getDisplayName())
            ->setActorAvatarUrl($actorAvatarUrl ?? $actorUser?->getAvatarUrl())
            ->setContextData($contextData);

        $this->entityManager->persist($notification);

        return $notification;
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function notifyPublicVisit(User $recipient, ?User $viewer): void
    {
        $this->notify(
            $recipient,
            AppNotification::TYPE_PUBLIC_VISIT,
            'Public profile viewed',
            $viewer instanceof User ? sprintf('%s opened your public Passport', $viewer->getDisplayName()) : 'Someone opened your public Passport from a link or QR code',
            $this->urlGenerator->generate('app_front_passport'),
            $viewer,
        );
    }

    public function notifyContactIntent(User $recipient, ?User $sender): void
    {
        $this->notify(
            $recipient,
            AppNotification::TYPE_CONTACT_INTENT,
            'New message intent',
            $sender instanceof User ? sprintf('%s wants to connect with you', $sender->getDisplayName()) : 'Someone wants to connect with you',
            $this->urlGenerator->generate('app_front_notifications'),
            $sender,
        );
    }

    public function notifyBadgeUnlocked(User $recipient, Badge $badge): void
    {
        $this->notify(
            $recipient,
            AppNotification::TYPE_BADGE_UNLOCKED,
            sprintf('Badge unlocked: %s', $badge->getName()),
            'A new milestone has been added to your Passport.',
            $this->urlGenerator->generate('app_front_passport'),
            null,
            null,
            null,
            ['badgeId' => $badge->getId(), 'badgeCode' => $badge->getCode()]
        );
    }

    public function notifyProviderConnected(User $recipient, string $provider, string $label): void
    {
        $providerName = $this->humanizeProvider($provider);

        $this->notify(
            $recipient,
            AppNotification::TYPE_PROVIDER_CONNECTED,
            sprintf('%s connected', $providerName),
            sprintf('%s is now linked to your Passport as %s.', $providerName, $label),
            $this->urlGenerator->generate('app_front_passport_settings'),
            null,
            null,
            null,
            ['provider' => $provider]
        );
    }

    /**
     * @param array<int, array{provider:string,inserted:int,xpAwarded:int,status:string}> $providers
     */
    public function notifySyncCompleted(User $recipient, array $providers, int $totalInserted, int $totalXpAwarded): void
    {
        $connectedProviders = array_values(array_filter(array_map(
            static fn (array $provider): ?string => ($provider['status'] ?? '') === 'success' ? ($provider['provider'] ?? null) : null,
            $providers
        )));

        $summary = $connectedProviders !== []
            ? implode(', ', array_map(fn (string $provider): string => $this->humanizeProvider($provider), $connectedProviders))
            : 'your connected accounts';

        $body = $totalInserted > 0
            ? sprintf('Sync finished across %s with %d new plays and %d XP gained.', $summary, $totalInserted, $totalXpAwarded)
            : sprintf('Sync checked %s but found no new plays to import.', $summary);

        $this->notify(
            $recipient,
            AppNotification::TYPE_SYNC_COMPLETED,
            'Sync completed',
            $body,
            $this->urlGenerator->generate('app_front_play_history'),
            null,
            null,
            null,
            [
                'providers' => $providers,
                'totalInserted' => $totalInserted,
                'totalXpAwarded' => $totalXpAwarded,
            ]
        );
    }

    public function notifyRankImproved(User $recipient, int $previousRank, int $currentRank): void
    {
        if ($currentRank >= $previousRank) {
            return;
        }

        $gain = $previousRank - $currentRank;

        $this->notify(
            $recipient,
            AppNotification::TYPE_RANK_IMPROVED,
            sprintf('Rank improved to #%d', $currentRank),
            sprintf('You climbed %d place%s in the global leaderboard.', $gain, $gain > 1 ? 's' : ''),
            $this->urlGenerator->generate('app_front_leaderboard'),
            null,
            null,
            null,
            [
                'previousRank' => $previousRank,
                'currentRank' => $currentRank,
                'gain' => $gain,
            ]
        );
    }

    private function humanizeProvider(string $provider): string
    {
        return match ($provider) {
            'spotify' => 'Spotify',
            'youtube' => 'YouTube',
            'apple_music' => 'Apple Music',
            default => ucfirst(str_replace('_', ' ', $provider)),
        };
    }
}
