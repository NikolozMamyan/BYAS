<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\OnboardingFandomService;
use App\Service\PublicPassportProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OnboardingController extends AbstractController
{
    #[Route('/app/onboarding', name: 'app_onboarding', methods: ['GET'])]
    public function index(
        Request $request,
        PublicPassportProfileService $publicPassportProfileService,
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        if (!$publicPassportProfileService->requiresOnboarding($user)) {
            return $this->redirectToRoute('app_front_passport');
        }

        $next = $request->query->get('next');
        $continueUrl = is_string($next) && str_starts_with($next, '/') && !str_starts_with($next, '//')
            ? $next
            : $this->generateUrl('app_front_passport');

        return $this->render('front/auth/onboarding.html.twig', [
            'continueUrl' => $continueUrl,
            'fandoms' => array_map(
                static fn (array $fandom, int $index): array => $fandom + [
                    'selected' => $index === 0,
                ],
                $this->fandomCatalog(),
                array_keys($this->fandomCatalog()),
            ),
        ]);
    }

    #[Route('/app/onboarding/ready', name: 'app_onboarding_ready', methods: ['GET'])]
    public function ready(
        Request $request,
        PublicPassportProfileService $publicPassportProfileService,
        OnboardingFandomService $onboardingFandomService,
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        if (!$publicPassportProfileService->requiresOnboarding($user)) {
            return $this->redirectToRoute('app_front_passport');
        }

        $next = $request->query->get('next');
        $continueUrl = is_string($next) && str_starts_with($next, '/') && !str_starts_with($next, '//')
            ? $next
            : $this->generateUrl('app_front_passport');

        $selectedSlug = (string) $request->query->get('fandom', 'blackpink');

        $selected = $this->fandomCatalog()[0];

        foreach ($this->fandomCatalog() as $fandom) {
            if ($fandom['slug'] === $selectedSlug) {
                $selected = $fandom;
                break;
            }
        }

        $onboardingFandomService->select($user, $selected);
        $publicPassportProfileService->completeOnboarding($user);

        return $this->render('front/auth/ready.html.twig', [
            'continueUrl' => $continueUrl,
            'selectedArtist' => $selected['name'],
            'selectedFandom' => $selected['fandom'],
        ]);
    }

    /**
     * @return list<array{name: string, fandom: string, slug: string, theme: string, image: ?string}>
     */
    private function fandomCatalog(): array
    {
        $themes = ['pink', 'violet', 'blue', 'red', 'indigo', 'purple'];
        $images = [
            'blackpink' => 'Blackpink.png',
            'bts' => 'BTS.png',
            'stray-kids' => 'stray kids.png',
            'seventeen' => 'seventeen.png',
            'twice' => 'twice.png',
            'newjeans' => 'newjeans.png',
            'ive' => 'ive.png',
            'le-sserafim' => 'le sserafim.png',
            'aespa' => 'aespa.png',
            'txt' => 'txt.png',
            'enhypen' => 'enhypen.png',
            'ateez' => 'ateez.png',
            'g-idle' => 'I-DLE.png',
            'itzy' => 'itzy.png',
            'babymonster' => 'babymonster.png',
            'nmixx' => 'nmixx.png',
            'illit' => 'illit.png',
            'kiss-of-life' => 'kiss of life.png',
            'red-velvet' => 'red velvet.png',
            'girls-generation' => 'girl_s generation.png',
            'mamamoo' => 'mamamoo.png',
            'exo' => 'exo.png',
            'nct-127' => 'nct.png',
            'nct-dream' => 'nct dream.png',
            'riize' => 'riize.png',
            'the-boyz' => 'the boyz.png',
            'zerobaseone' => 'zerobaseone.png',
            'shinee' => 'shinee.png',
            'bigbang' => 'bigbang.png',
            'monsta-x' => 'monsta x.png',
        ];
        $entries = [
            ['name' => 'BLACKPINK', 'fandom' => 'BLINK', 'slug' => 'blackpink'],
            ['name' => 'BTS', 'fandom' => 'ARMY', 'slug' => 'bts'],
            ['name' => 'Stray Kids', 'fandom' => 'STAY', 'slug' => 'stray-kids'],
            ['name' => 'SEVENTEEN', 'fandom' => 'CARAT', 'slug' => 'seventeen'],
            ['name' => 'TWICE', 'fandom' => 'ONCE', 'slug' => 'twice'],
            ['name' => 'NewJeans', 'fandom' => 'Bunnies', 'slug' => 'newjeans'],
            ['name' => 'IVE', 'fandom' => 'DIVE', 'slug' => 'ive'],
            ['name' => 'LE SSERAFIM', 'fandom' => 'FEARNOT', 'slug' => 'le-sserafim'],
            ['name' => 'aespa', 'fandom' => 'MY', 'slug' => 'aespa'],
            ['name' => 'TXT', 'fandom' => 'MOA', 'slug' => 'txt'],
            ['name' => 'ENHYPEN', 'fandom' => 'ENGENE', 'slug' => 'enhypen'],
            ['name' => 'ATEEZ', 'fandom' => 'ATINY', 'slug' => 'ateez'],
            ['name' => '(G)I-DLE', 'fandom' => 'NEVERLAND', 'slug' => 'g-idle'],
            ['name' => 'ITZY', 'fandom' => 'MIDZY', 'slug' => 'itzy'],
            ['name' => 'BABYMONSTER', 'fandom' => 'MONSTIEZ', 'slug' => 'babymonster'],
            ['name' => 'NMIXX', 'fandom' => 'NSWER', 'slug' => 'nmixx'],
            ['name' => 'ILLIT', 'fandom' => 'GLLIT', 'slug' => 'illit'],
            ['name' => 'KISS OF LIFE', 'fandom' => 'KISSY', 'slug' => 'kiss-of-life'],
            ['name' => 'Red Velvet', 'fandom' => 'ReVeluv', 'slug' => 'red-velvet'],
            ['name' => "Girls' Generation", 'fandom' => 'SONE', 'slug' => 'girls-generation'],
            ['name' => 'MAMAMOO', 'fandom' => 'MooMoo', 'slug' => 'mamamoo'],
            ['name' => 'EXO', 'fandom' => 'EXO-L', 'slug' => 'exo'],
            ['name' => 'NCT 127', 'fandom' => 'NCTzen', 'slug' => 'nct-127'],
            ['name' => 'NCT DREAM', 'fandom' => 'NCTzen', 'slug' => 'nct-dream'],
            ['name' => 'RIIZE', 'fandom' => 'BRIIZE', 'slug' => 'riize'],
            ['name' => 'THE BOYZ', 'fandom' => 'THE B', 'slug' => 'the-boyz'],
            ['name' => 'ZEROBASEONE', 'fandom' => 'ZEROSE', 'slug' => 'zerobaseone'],
            ['name' => 'SHINee', 'fandom' => 'Shawol', 'slug' => 'shinee'],
            ['name' => 'BIGBANG', 'fandom' => 'VIP', 'slug' => 'bigbang'],
            ['name' => 'MONSTA X', 'fandom' => 'MONBEBE', 'slug' => 'monsta-x'],
        ];

        return array_map(
            static fn (array $entry, int $index): array => $entry + [
                'name' => strtoupper($entry['name']),
                'theme' => $themes[$index % count($themes)],
                'image' => $images[$entry['slug']] ?? null,
            ],
            $entries,
            array_keys($entries),
        );
    }
}
