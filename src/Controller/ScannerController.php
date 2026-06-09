<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ScanSession;
use App\Entity\ScoreResult;
use App\Repository\ProductRepository;
use App\Repository\ScanSessionRepository;
use App\Service\Ean13Validator;
use App\Service\Exception\OpenFoodFactsUnavailableException;
use App\Service\Exception\ProductNotFoundException;
use App\Service\OpenFoodFactsClient;
use App\Service\Scoring\BabyProductDetector;
use App\Service\Scoring\Evaluator\InfantFormulaDetector;
use App\Service\Scoring\Evaluator\InfantFormulaScoreCalculator;
use App\Service\Scoring\ScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Contrôleur du scanner de code-barres.
 */
final class ScannerController extends AbstractController
{
    private const SESSION_COOKIE_NAME = 'np_session';
    private const SESSION_COOKIE_TTL_DAYS = 365;

    public function __construct(
        private readonly Ean13Validator $eanValidator,
        private readonly ProductRepository $productRepository,
        private readonly ScanSessionRepository $scanSessionRepository,
        private readonly OpenFoodFactsClient $offClient,
        private readonly ScoreCalculator $scoreCalculator,
        private readonly InfantFormulaDetector $infantFormulaDetector,
        private readonly InfantFormulaScoreCalculator $infantFormulaScoreCalculator,
        private readonly EntityManagerInterface $em,
        private readonly BabyProductDetector $babyProductDetector,
    ) {}

    #[Route('/app/scanner', name: 'app_pwa_scanner', methods: ['GET'])]
    public function scanner(): Response
    {
        return $this->render('pages/app/scanner.html.twig');
    }

    #[Route('/app/saisie-manuelle', name: 'app_pwa_manual_entry', methods: ['GET'])]
    public function manualEntry(): Response
    {
        return $this->render('pages/app/manual_entry.html.twig');
    }

    #[Route('/app/scan/{ean}', name: 'app_pwa_scan', methods: ['GET'], requirements: ['ean' => '\d{13}'])]
    public function scan(string $ean, Request $request): Response
    {
        // Validation EAN
        if (!$this->eanValidator->isValid($ean)) {
            return $this->render('pages/app/scan_error.html.twig', [
                'errorTitle' => 'Code-barres invalide',
                'errorMessage' => 'Ce code-barres ne respecte pas le format EAN-13.',
            ], new Response('', Response::HTTP_BAD_REQUEST));
        }

        // ScanSession
        $scanSession = $this->resolveScanSession($request);

        // Produit
        $product = $this->productRepository->findByEan($ean);

        if (null === $product) {
            try {
                $dto = $this->offClient->fetchByEan($ean);
            } catch (ProductNotFoundException) {
                return $this->render('pages/app/scan_error.html.twig', [
                    'errorTitle' => 'Produit inconnu',
                    'errorMessage' => 'Ce produit n\'existe pas dans la base Open Food Facts.',
                ], new Response('', Response::HTTP_NOT_FOUND));
            } catch (OpenFoodFactsUnavailableException) {
                return $this->render('pages/app/scan_error.html.twig', [
                    'errorTitle' => 'Service indisponible',
                    'errorMessage' => 'Open Food Facts est temporairement inaccessible. Réessayez dans quelques instants.',
                ], new Response('', Response::HTTP_SERVICE_UNAVAILABLE));
            }

            $product = $this->createProductFromDto($dto);
        }

        // Vérifier que c'est un produit bébé/nourrisson (NutriPetit 0-3 ans)
        if (!$this->babyProductDetector->isBabyProduct($product)) {
            return $this->render('pages/app/scan_error.html.twig', [
                'errorTitle' => 'Produit non destiné aux 0-3 ans',
                'errorMessage' => 'NutriPetit analyse uniquement les produits alimentaires destinés aux nourrissons et jeunes enfants (0-3 ans). Pour les autres produits, nous vous invitons à utiliser une application généraliste comme Yuka ou Open Food Facts.',
            ], new Response('', Response::HTTP_OK));
        }

        // Âge du bébé
        $babyAgeMonths = $request->cookies->getInt('np_baby_age');

        // Calcul du score
        $isInfantFormula = $this->infantFormulaDetector->isInfantFormula($product);

        if ($isInfantFormula) {
            $scoreDto = $this->infantFormulaScoreCalculator->calculate($product, $babyAgeMonths);
        } else {
            $scoreDto = $this->scoreCalculator->calculate($product, $babyAgeMonths);
        }

        $scoreResult = new ScoreResult(
            product: $product,
            finalScore: $scoreDto->finalScore,
            level: $scoreDto->level,
            algoVersion: $scoreDto->algoVersion,
            babyAgeMonths: $scoreDto->babyAgeMonths,
            scanSession: $scanSession,
        );

        $scoreResult->setAppliedRules(array_map(
            static function ($r): array {
                $base = $r->toArray();
                $base['category'] = $base['points'] >= 0 ? 'bonus' : 'malus';
                $base['icon'] = 'lucide:circle';

                return $base;
            },
            $scoreDto->appliedRules,
        ));

        $this->em->persist($scoreResult);
        $scanSession->touch();
        $this->em->flush();
        $nutrients = $this->buildNutrients($product, $isInfantFormula);
        $uniqueSources = $this->buildUniqueSources($scoreResult->getAppliedRules());
        $criticalAlert = $this->detectCriticalAlert($scoreResult->getAppliedRules());
        $minAgeMonths = $this->extractMinAgeMonths($product);
        $environment = $this->buildEnvironment($product);
        $scoresByAge = $this->buildScoresByAge($product, $isInfantFormula, $babyAgeMonths, $minAgeMonths);

        $response = $this->render('pages/app/product_preview.html.twig', [
            'product' => [
                'ean' => $product->getEan(),
                'name' => $product->getName(),
                'brand' => $product->getBrand(),
                'image_url' => $product->getImageUrl(),
            ],
            'babyAgeMonths' => $babyAgeMonths,
            'finalScore' => $scoreDto->finalScore,
            'level' => $scoreDto->level,
            'algoVersion' => $scoreDto->algoVersion,
            'scoresByAge' => $scoresByAge,
            'criticalAlert' => $criticalAlert,
            'appliedRules' => $scoreResult->getAppliedRules(),
            'nutrients' => $nutrients,
            'environment' => $environment,
            'uniqueSources' => $uniqueSources,
            'isInfantFormula' => $isInfantFormula,
            'minAgeMonths' => $minAgeMonths,
            'additives' => $this->extractAdditives($product),
            'carbonFootprint' => $this->extractCarbonFootprint($product),
        ]);

        // Poser le cookie si nouvelle session
        if (null === $request->cookies->get(self::SESSION_COOKIE_NAME)) {
            $response->headers->setCookie(
                Cookie::create(
                    name: self::SESSION_COOKIE_NAME,
                    value: (string) $scanSession->getId(),
                    expire: time() + (self::SESSION_COOKIE_TTL_DAYS * 86400),
                    path: '/',
                    secure: true,
                    httpOnly: true,
                    sameSite: 'lax',
                ),
            );
        }

        return $response;
    }

    private function resolveScanSession(Request $request): ScanSession
    {
        $cookieValue = $request->cookies->get(self::SESSION_COOKIE_NAME);

        if (\is_string($cookieValue) && Uuid::isValid($cookieValue)) {
            $session = $this->scanSessionRepository->findById(Uuid::fromString($cookieValue));
            if (null !== $session) {
                return $session;
            }
        }

        $session = new ScanSession($request->headers->get('User-Agent', 'unknown'));
        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }

    private function createProductFromDto(\App\Dto\ProductDto $dto): Product
    {
        $product = new Product($dto->ean, $dto->name);
        $product->setBrand($dto->brand);
        $product->setImageUrl($dto->imageUrl);
        $product->setIngredientsRaw($dto->ingredientsRaw);
        $product->setNutriments($dto->nutriments);
        $product->setAllergens($dto->allergens);
        $product->setAdditives($dto->additives);
        $product->setOffRawData($dto->rawData);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildNutrients(Product $product, bool $isInfantFormula = false): array
    {
        $n = $product->getNutriments();

        if ($isInfantFormula) {
            return $this->buildInfantFormulaNutrients($n);
        }

        return $this->buildBabyFoodNutrients($n);
    }

    /**
     * @param array<string, mixed> $n
     *
     * @return list<array<string, mixed>>
     */
    private function buildInfantFormulaNutrients(array $n): array
    {
        $criteria = [
            ['key' => 'energy-kcal_100g', 'name' => 'Énergie', 'unit' => 'kcal', 'min' => 60.0, 'max' => 70.0, 'maxScale' => 80.0, 'source' => 'UE 2016/127', 'category' => 'Macronutriments'],
            ['key' => 'proteins_100g', 'name' => 'Protéines', 'unit' => 'g', 'min' => 1.2, 'max' => 1.8, 'idealMax' => 1.34, 'maxScale' => 3.0, 'source' => 'UE 2016/127 / SFP', 'category' => 'Macronutriments'],
            ['key' => 'fat_100g', 'name' => 'Lipides', 'unit' => 'g', 'min' => 4.4, 'max' => 6.0, 'maxScale' => 7.0, 'source' => 'UE 2016/127', 'category' => 'Macronutriments'],
            ['key' => 'carbohydrates_100g', 'name' => 'Glucides', 'unit' => 'g', 'min' => 9.0, 'max' => 14.0, 'maxScale' => 16.0, 'source' => 'UE 2016/127', 'category' => 'Macronutriments'],
            ['key' => 'dha_100g', 'name' => 'DHA (Oméga 3)', 'unit' => 'mg', 'min' => 0.020, 'max' => 0.050, 'maxScale' => 0.080, 'source' => 'UE 2016/127 (obligatoire)', 'category' => 'Acides gras', 'multiplier' => 1000],
            ['key' => 'arachidonic-acid_100g', 'name' => 'ARA (Oméga 6)', 'unit' => 'mg', 'min' => 0.020, 'max' => 0.060, 'maxScale' => 0.080, 'source' => 'EAP/CHF 2020', 'category' => 'Acides gras', 'multiplier' => 1000],
            ['key' => 'linoleic-acid_100g', 'name' => 'Acide linoléique', 'unit' => 'mg', 'min' => 0.35, 'max' => 0.84, 'maxScale' => 1.5, 'source' => 'UE 2016/127', 'category' => 'Acides gras', 'multiplier' => 1000],
            ['key' => 'alpha-linolenic-acid_100g', 'name' => 'Acide α-linolénique', 'unit' => 'mg', 'min' => 0.035, 'max' => 0.070, 'maxScale' => 0.150, 'source' => 'UE 2016/127', 'category' => 'Acides gras', 'multiplier' => 1000],
            ['key' => 'sodium_100g', 'name' => 'Sodium', 'unit' => 'mg', 'min' => 0.013, 'max' => 0.024, 'maxScale' => 0.060, 'source' => 'mpedia.fr / UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000],
            ['key' => 'iron_100g', 'name' => 'Fer', 'unit' => 'mg', 'min' => 0.0003, 'max' => 0.0013, 'maxScale' => 0.002, 'source' => 'UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000],
            ['key' => 'calcium_100g', 'name' => 'Calcium', 'unit' => 'mg', 'min' => 0.050, 'max' => 0.140, 'maxScale' => 0.200, 'source' => 'UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000],
            ['key' => 'phosphorus_100g', 'name' => 'Phosphore', 'unit' => 'mg', 'min' => 0.025, 'max' => 0.100, 'maxScale' => 0.140, 'source' => 'UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000],
            ['key' => 'iodine_100g', 'name' => 'Iode', 'unit' => 'µg', 'min' => 0.000015, 'max' => 0.000029, 'maxScale' => 0.000060, 'source' => 'UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000000],
            ['key' => 'zinc_100g', 'name' => 'Zinc', 'unit' => 'mg', 'min' => 0.0005, 'max' => 0.001, 'maxScale' => 0.002, 'source' => 'UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000],
            ['key' => 'vitamin-d_100g', 'name' => 'Vitamine D', 'unit' => 'µg', 'min' => 0.000002, 'max' => 0.000003, 'maxScale' => 0.000005, 'source' => 'UE 2016/127', 'category' => 'Vitamines', 'multiplier' => 1000000],
            ['key' => 'vitamin-a_100g', 'name' => 'Vitamine A', 'unit' => 'µg', 'min' => 0.00006, 'max' => 0.00018, 'maxScale' => 0.00030, 'source' => 'UE 2016/127', 'category' => 'Vitamines', 'multiplier' => 1000000],
            ['key' => 'vitamin-c_100g', 'name' => 'Vitamine C', 'unit' => 'mg', 'min' => 0.004, 'max' => 0.030, 'maxScale' => 0.050, 'source' => 'UE 2016/127', 'category' => 'Vitamines', 'multiplier' => 1000],
        ];

        $result = [];
        foreach ($criteria as $c) {
            $preparedKey = str_replace('_100g', '_prepared_100g', $c['key']);
            $rawValue = $n[$preparedKey] ?? $n[$c['key']] ?? null;

            if (!is_numeric($rawValue)) {
                $result[] = [
                    'name' => $c['name'],
                    'category' => $c['category'],
                    'available' => false,
                    'value' => null,
                    'unit' => $c['unit'],
                    'threshold_baby' => $c['max'],
                    'max_scale' => $c['maxScale'],
                    'level' => 'unknown',
                    'message' => 'Donnée non disponible sur Open Food Facts.',
                    'reference' => \sprintf('Plage légale : %s à %s %s/100ml', $c['min'], $c['max'], $c['unit']),
                ];
                continue;
            }

            $value = (float) $rawValue;
            $displayValue = isset($c['multiplier']) ? $value * $c['multiplier'] : $value;
            $displayMin = isset($c['multiplier']) ? $c['min'] * $c['multiplier'] : $c['min'];
            $displayMax = isset($c['multiplier']) ? $c['max'] * $c['multiplier'] : $c['max'];
            $displayMaxScale = isset($c['multiplier']) ? $c['maxScale'] * $c['multiplier'] : $c['maxScale'];

            $level = match (true) {
                $value < $c['min'] => 'limit',
                $value <= ($c['idealMax'] ?? $c['max']) => 'ideal',
                $value <= $c['max'] => 'good',
                default => 'occasional',
            };

            if ('ideal' === $level) {
                $message = 'Valeur optimale selon les recommandations.';
            } elseif ('good' === $level) {
                $message = 'Valeur conforme au cadre légal.';
            } elseif ('limit' === $level) {
                $message = $value < $c['min']
                    ? 'En dessous du seuil légal minimum.'
                    : 'Au-dessus de la plage recommandée.';
            } else {
                $message = 'Au-dessus du seuil légal maximum.';
            }

            $result[] = [
                'name' => $c['name'],
                'category' => $c['category'],
                'available' => true,
                'value' => round($displayValue, 4),
                'unit' => $c['unit'],
                'threshold_baby' => $displayMax,
                'max_scale' => $displayMaxScale,
                'level' => $level,
                'message' => $message,
                'reference' => \sprintf('Plage légale : %s à %s %s/100ml (source : %s)', $displayMin, $displayMax, $c['unit'], $c['source']),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $n
     *
     * @return list<array<string, mixed>>
     */
    private function buildBabyFoodNutrients(array $n): array
    {
        $thresholds = [
            ['key' => 'sugars_100g', 'name' => 'Sucres', 'unit' => 'g', 'threshold' => 4.0, 'max' => 40.0],
            ['key' => 'salt_100g', 'name' => 'Sel', 'unit' => 'g', 'threshold' => 0.3, 'max' => 1.0],
            ['key' => 'proteins_100g', 'name' => 'Protéines', 'unit' => 'g', 'threshold' => 15.0, 'max' => 25.0],
            ['key' => 'energy-kcal_100g', 'name' => 'Calories', 'unit' => 'kcal', 'threshold' => 400.0, 'max' => 800.0],
        ];

        $result = [];
        foreach ($thresholds as $t) {
            $rawValue = $n[$t['key']] ?? null;
            if (!is_numeric($rawValue)) {
                continue;
            }
            $value = (float) $rawValue;
            $ratio = $value / $t['threshold'];
            $level = match (true) {
                $ratio <= 0.5 => 'ideal',
                $ratio <= 1.0 => 'good',
                $ratio <= 1.5 => 'occasional',
                $ratio <= 2.5 => 'limit',
                default => 'discouraged',
            };

            $result[] = [
                'name' => $t['name'],
                'category' => 'Nutrition',
                'available' => true,
                'value' => $value,
                'unit' => $t['unit'],
                'threshold_baby' => $t['threshold'],
                'max_scale' => $t['max'],
                'level' => $level,
                'message' => $value <= $t['threshold']
                    ? 'Conforme aux recommandations ANSES nourrisson.'
                    : 'Au-dessus des recommandations ANSES nourrisson.',
                'reference' => \sprintf('ANSES nourrisson : %s%s/100g max', $t['threshold'], $t['unit']),
            ];
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $appliedRules
     *
     * @return list<array<string, mixed>>
     */
    private function buildUniqueSources(array $appliedRules): array
    {
        $unique = [];
        foreach ($appliedRules as $rule) {
            $name = $rule['source_name'] ?? null;
            if (!\is_string($name) || '' === $name) {
                continue;
            }
            if (!isset($unique[$name])) {
                $unique[$name] = [
                    'name' => $name,
                    'url' => $rule['source_url'] ?? '#',
                    'rules' => [],
                ];
            }
            $unique[$name]['rules'][] = $rule['rule_label'] ?? '';
        }

        return array_values($unique);
    }

    /**
     * @param list<array<string, mixed>> $appliedRules
     *
     * @return array<string, string>|null
     */
    private function detectCriticalAlert(array $appliedRules): ?array
    {
        foreach ($appliedRules as $rule) {
            $code = $rule['rule_code'] ?? '';
            if (\in_array($code, ['choking_hazard', 'contaminated_fish'], true)) {
                return [
                    'title' => $rule['rule_label'] ?? 'Alerte critique',
                    'message' => $rule['reason'] ?? '',
                    'source_name' => $rule['source_name'] ?? '',
                    'source_url' => $rule['source_url'] ?? '#',
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEnvironment(Product $product): array
    {
        $raw = $product->getOffRawData();
        $labels = $raw['labels_tags'] ?? [];
        $ingredients = mb_strtolower((string) ($raw['ingredients_text_fr'] ?? $raw['ingredients_text'] ?? ''));
        $countries = $raw['countries_tags'] ?? [];
        $packagingTags = $raw['packaging_tags'] ?? [];
        $packagingMaterialsTags = $raw['packaging_materials_tags'] ?? [];

        if (!\is_array($labels)) {
            $labels = [];
        }
        if (!\is_array($packagingTags)) {
            $packagingTags = [];
        }
        if (!\is_array($packagingMaterialsTags)) {
            $packagingMaterialsTags = [];
        }
        if (!\is_array($countries)) {
            $countries = [];
        }

        // Origine
        $origin = null;
        if ([] !== $countries) {
            $firstCountry = (string) $countries[0];
            $countryName = ucfirst(str_replace(['en:', 'fr:'], '', $firstCountry));
            $flag = match (true) {
                str_contains(strtolower($countryName), 'france') => '🇫🇷',
                str_contains(strtolower($countryName), 'germany') => '🇩🇪',
                str_contains(strtolower($countryName), 'italy') => '🇮🇹',
                str_contains(strtolower($countryName), 'spain') => '🇪🇸',
                str_contains(strtolower($countryName), 'belgium') => '🇧🇪',
                default => '🌍',
            };
            $origin = ['country' => $countryName, 'flag' => $flag];
        }

        // Emballage
        $packaging = null;
        $allTags = array_merge($packagingTags, $packagingMaterialsTags);

        if ([] !== $allTags) {
            $details = [];
            $allRecyclable = true;
            $hasNonRecyclable = false;
            $seen = [];

            foreach ($allTags as $tag) {
                $clean = str_replace(['en:', 'fr:'], '', (string) $tag);
                $name = $this->translatePackaging($clean);

                if (\in_array($name, $seen, true)) {
                    continue;
                }
                $seen[] = $name;

                $recyclable = $this->isPackagingRecyclable($clean);

                $details[] = ['name' => $name, 'recyclable' => $recyclable];
                if (!$recyclable) {
                    $hasNonRecyclable = true;
                    $allRecyclable = false;
                }
            }

            $count = \count($details);

            $label = match (true) {
                $allRecyclable => 'Recyclable',
                $hasNonRecyclable && $count > 1 => 'Partiellement recyclable',
                $hasNonRecyclable => 'Non recyclable',
                default => 'Non précisé',
            };

            $level = match (true) {
                $allRecyclable => 'ideal',
                $hasNonRecyclable && $count > 1 => 'limit',
                $hasNonRecyclable => 'discouraged',
                default => 'neutral',
            };

            $packaging = [
                'label' => $label,
                'level' => $level,
                'details' => $details,
            ];
        }

        return [
            'origin' => $origin,
            'bio_certified' => \in_array('en:organic', $labels, true) || \in_array('fr:ab-agriculture-biologique', $labels, true),
            'palm_oil_free' => !str_contains($ingredients, 'palme') && !str_contains($ingredients, 'palm oil'),
            'packaging' => $packaging,
        ];
    }

    private function isPackagingRecyclable(string $cleanTag): bool
    {
        $nonRecyclablePatterns = [
            'cellophane',
            'cellulose',
            'composite',
            'sticker',
            'label-non-recyclable',
            'wrapper',
            'individual-wrapper',
            'cork',
            'liège',
            'liege',
        ];

        foreach ($nonRecyclablePatterns as $pattern) {
            if (str_contains($cleanTag, $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildScoresByAge(
        Product $product,
        bool $isInfantFormula,
        ?int $currentAge,
        ?int $minAgeMonths = null,
    ): array {
        if ($isInfantFormula) {
            return [];
        }

        $ages = [6, 12, 18, 24, 36];

        if (null !== $minAgeMonths) {
            $ages = array_values(array_filter($ages, static fn($a) => $a >= $minAgeMonths));
        }

        $result = [];
        foreach ($ages as $age) {
            $dto = $this->scoreCalculator->calculate($product, $age);
            $result[] = [
                'months' => $age,
                'score' => $dto->finalScore,
                'level' => $dto->level,
                'label' => match ($dto->level) {
                    'ideal' => 'Idéal',
                    'good' => 'Bon choix',
                    'occasional' => 'Occasionnel',
                    'limit' => 'À limiter',
                    'discouraged' => 'Déconseillé',
                    default => '',
                },
            ];
        }

        return $result;
    }

    private function extractMinAgeMonths(Product $product): ?int
    {
        $tags = array_merge(
            $product->getOffRawData()['categories_tags'] ?? [],
            $product->getOffRawData()['labels_tags'] ?? [],
        );

        foreach ($tags as $tag) {
            $tag = strtolower((string) $tag);
            if (preg_match('/des?-(\d+)-(?:month|mois)/', $tag, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/from-(\d+)-(?:month|mois)/', $tag, $m)) {
                return (int) $m[1];
            }
        }

        $name = mb_strtolower($product->getName());
        if (preg_match('/d[èe]s (\d+) mois/', $name, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    private function extractAdditives(Product $product): array
    {
        $additivesTags = $product->getAdditives();
        $result = [];
        $seen = [];

        $known = [
            'E100' => 'Curcumine',
            'E101' => 'Riboflavine',
            'E150A' => 'Caramel ordinaire',
            'E150D' => 'Caramel au sulfite d\'ammonium',
            'E160A' => 'Carotènes',
            'E202' => 'Sorbate de potassium',
            'E270' => 'Acide lactique',
            'E290' => 'Dioxyde de carbone',
            'E296' => 'Acide malique',
            'E300' => 'Acide ascorbique (Vitamine C)',
            'E306' => 'Tocophérols (Vitamine E)',
            'E322' => 'Lécithines',
            'E330' => 'Acide citrique',
            'E331' => 'Citrates de sodium',
            'E332' => 'Citrates de potassium',
            'E333' => 'Citrates de calcium',
            'E336' => 'Tartrates de potassium',
            'E412' => 'Gomme de guar',
            'E415' => 'Gomme xanthane',
            'E440' => 'Pectines',
            'E471' => 'Mono- et diglycérides d\'acides gras',
            'E500' => 'Carbonates de sodium',
            'E501' => 'Carbonates de potassium',
            'E503' => 'Carbonates d\'ammonium',
            'E504' => 'Carbonates de magnésium',
            'E575' => 'Glucono-delta-lactone',
            'E950' => 'Acésulfame K (édulcorant)',
            'E951' => 'Aspartame (édulcorant)',
            'E952' => 'Cyclamates (édulcorant)',
            'E954' => 'Saccharine (édulcorant)',
            'E955' => 'Sucralose (édulcorant)',
            'E960' => 'Glycosides de stéviol (édulcorant)',
        ];

        foreach ($additivesTags as $tag) {
            $code = strtoupper(str_replace(['EN:', 'FR:'], '', strtoupper((string) $tag)));

            if (\in_array($code, $seen, true)) {
                continue;
            }
            $seen[] = $code;

            $result[] = [
                'code' => $code,
                'name' => $known[$code] ?? 'Additif non documenté',
            ];
        }

        return $result;
    }

    /**
     * @return array{value: float, unit: string, car_km: float}|null
     */
    private function extractCarbonFootprint(Product $product): ?array
    {
        $raw = $product->getOffRawData();
        $nutriments = $raw['nutriments'] ?? [];

        $candidates = [
            'carbon-footprint-from-known-ingredients_100g',
            'carbon-footprint_100g',
        ];

        foreach ($candidates as $key) {
            $value = $nutriments[$key] ?? null;
            if (is_numeric($value)) {
                $v = (float) $value;

                return [
                    'value' => round($v, 1),
                    'unit' => 'g CO₂e/100g',
                    'car_km' => round($v / 200, 2),
                ];
            }
        }

        $ecoCo2 = $raw['ecoscore_data']['agribalyse']['co2_total'] ?? null;
        if (is_numeric($ecoCo2)) {
            $v = (float) $ecoCo2 * 1000;

            return [
                'value' => round($v, 1),
                'unit' => 'g CO₂e/kg',
                'car_km' => round($v / 200, 2),
            ];
        }

        return null;
    }

    private function translatePackaging(string $tag): string
    {
        $translations = [
            'plastic' => 'Plastique',
            'box' => 'Boîte',
            'cardboard' => 'Carton',
            'paper' => 'Papier',
            'film' => 'Film',
            'plastic-film' => 'Film plastique',
            'film-en-plastique' => 'Film plastique',
            'bag' => 'Sachet',
            'plastic-bag' => 'Sachet plastique',
            'bottle' => 'Bouteille',
            'plastic-bottle' => 'Bouteille plastique',
            'glass' => 'Verre',
            'glass-bottle' => 'Bouteille en verre',
            'jar' => 'Pot',
            'glass-jar' => 'Pot en verre',
            'can' => 'Boîte de conserve',
            'metal' => 'Métal',
            'aluminium' => 'Aluminium',
            'steel' => 'Acier',
            'tin' => 'Fer-blanc',
            'tetra-pak' => 'Brique Tetra Pak',
            'brique' => 'Brique',
            'wood' => 'Bois',
            'tray' => 'Barquette',
            'plastic-tray' => 'Barquette plastique',
            'lid' => 'Couvercle',
            'cap' => 'Bouchon',
            'cork' => 'Liège',
            'pouch' => 'Sachet souple',
            'sachet' => 'Sachet',
            'pot' => 'Pot',
            'plastic-pot' => 'Pot plastique',
            'sticker' => 'Étiquette',
            'label' => 'Étiquette',
            'wrapping' => 'Emballage',
            'wrapper' => 'Emballage',
            'individual-wrappers' => 'Emballages individuels',
        ];

        if (isset($translations[$tag])) {
            return $translations[$tag];
        }

        return ucfirst(str_replace('-', ' ', $tag));
    }
}
