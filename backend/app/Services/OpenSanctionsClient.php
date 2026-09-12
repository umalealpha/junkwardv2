<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AML Screening Client — connects to self-hosted AML service.
 * Replaces OpenSanctions API with zero-cost, self-hosted sanctions screening.
 *
 * The self-hosted service aggregates: UN, OFAC, UK HMT, EU, World Bank, Interpol.
 * Config: services.opensanctions.base = http://your-server:5001
 * Config: services.opensanctions.key  = your-aml-api-key
 */
class OpenSanctionsClient
{
    /**
     * Screen a person against sanctions lists.
     * Returns data in the same structure the RunOpenSanctionsJob expects.
     */
    public function match(string $name, ?string $dob = null, ?string $nationality = null): array
    {
        $base = rtrim(config('services.opensanctions.base'), '/');
        $key = config('services.opensanctions.key');

        $payload = [
            'names' => [$name],
            'birth_date' => $dob ?? '',
            'nationality' => $nationality ?? '',
        ];

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders(['X-AML-Key' => $key])
                ->post("{$base}/screen", $payload)
                ->throw()
                ->json();

            // Transform self-hosted response to match the format RunOpenSanctionsJob expects
            return $this->transformResponse($response, $name);

        } catch (\Throwable $e) {
            Log::error('AML screening failed', [
                'name' => $name,
                'error' => $e->getMessage(),
                'base' => $base,
            ]);
            throw $e;
        }
    }

    /**
     * Screen with customer_id for server-side audit logging.
     */
    public function screenCustomer(int $customerId, string $name, ?string $dob = null, ?string $nationality = null): array
    {
        $base = rtrim(config('services.opensanctions.base'), '/');
        $key = config('services.opensanctions.key');

        $payload = [
            'customer_id' => $customerId,
            'names' => [$name],
            'birth_date' => $dob ?? '',
            'nationality' => $nationality ?? '',
        ];

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders(['X-AML-Key' => $key])
                ->post("{$base}/screen", $payload)
                ->throw()
                ->json();

            return $this->transformResponse($response, $name);

        } catch (\Throwable $e) {
            Log::error('AML screening failed', [
                'customer_id' => $customerId,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Batch screen multiple customers.
     */
    public function batchScreen(array $customers): array
    {
        $base = rtrim(config('services.opensanctions.base'), '/');
        $key = config('services.opensanctions.key');

        try {
            return Http::timeout(60)
                ->acceptJson()
                ->withHeaders(['X-AML-Key' => $key])
                ->post("{$base}/batch-screen", ['customers' => $customers])
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            Log::error('AML batch screening failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Check service health.
     */
    public function health(): array
    {
        $base = rtrim(config('services.opensanctions.base'), '/');

        return Http::timeout(5)
            ->acceptJson()
            ->get("{$base}/health")
            ->throw()
            ->json();
    }

    /**
     * Transform self-hosted AML response to the format RunOpenSanctionsJob expects.
     *
     * RunOpenSanctionsJob reads:
     *   - responses.q1.results[] (array of match results)
     *   - Each result has: score, datasets[], target, properties.programId[]
     */
    private function transformResponse(array $response, string $queryName): array
    {
        $results = [];

        foreach ($response['results'] ?? [] as $match) {
            $source = $match['source'] ?? '';

            // Map source to dataset name
            $datasets = [$this->mapSourceToDataset($source)];

            $results[] = [
                'id' => md5($match['matched_name'] ?? ''),
                'caption' => $match['matched_name'] ?? '',
                'schema' => 'Person',
                'score' => $match['score'] ?? 0,
                'target' => ($match['score'] ?? 0) >= 0.7,
                'datasets' => $datasets,
                'properties' => [
                    'name' => [$match['matched_name'] ?? ''],
                    'alias' => $match['aliases'] ?? [],
                    'birthDate' => array_filter([$match['dob'] ?? '']),
                    'nationality' => array_filter([$match['nationality'] ?? '']),
                    'programId' => array_filter([$source]),
                    'notes' => array_filter([$match['reason'] ?? '']),
                ],
            ];
        }

        return [
            'status' => 'ok',
            'total' => count($results),
            'responses' => [
                'q1' => [
                    'query' => ['name' => $queryName],
                    'results' => $results,
                    'total' => ['value' => count($results)],
                ],
            ],
        ];
    }

    /**
     * Map AML service source IDs to human-readable dataset names.
     */
    private function mapSourceToDataset(string $source): string
    {
        return match ($source) {
            'un_sc_sanctions' => 'un_sc_sanctions',
            'us_ofac_sdn' => 'us_ofac_sdn',
            'uk_hmt_sanctions' => 'uk_hmt_sanctions',
            'eu_sanctions' => 'eu_sanctions',
            'worldbank_debarred' => 'worldbank_debarred',
            'interpol_red_notices' => 'interpol_red_notices',
            'sa_fic' => 'za_fic',
            default => $source ?: 'unknown',
        };
    }
}
