<?php

namespace AlphaDirect\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class WebfleetClient
{
    private Client $http;
    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('webfleet');
        $this->http = new Client([
            'base_uri' => $this->cfg['base'],
            'auth'     => [$this->cfg['user'], $this->cfg['password']],
            'timeout'  => 30,
        ]);
    }

    private function commonParams(): array
    {
        return [
            'apikey'       => $this->cfg['apikey'],
            'account'      => $this->cfg['account'],
            'lang'         => $this->cfg['lang'],
            'outputformat' => 'json',
            'useISO8601'   => 'true',
        ];
    }

    public function showTripReport(string $objectNo, string $fromIso, string $toIso): array
    {
        $params = array_merge($this->commonParams(), [
            'action'           => 'showTripReportExtern',
            'objectno'         => $objectNo,
            'range_pattern'    => 'ud',
            'rangefrom_string' => $fromIso,
            'rangeto_string'   => $toIso,
        ]);

        try {
            $res = $this->http->get('', ['query' => $params]);
            return json_decode($res->getBody()->getContents(), true) ?? [];
        } catch (RequestException $e) {
            throw $e;
        }
    }

    public function showTripSummary(string $objectNo, string $fromIso, string $toIso): array
    {
        $params = array_merge($this->commonParams(), [
            'action'           => 'showTripSummaryReportExtern',
            'objectno'         => $objectNo,
            'range_pattern'    => 'ud',
            'rangefrom_string' => $fromIso,
            'rangeto_string'   => $toIso,
        ]);
        $res = $this->http->get('', ['query' => $params]);
        return json_decode($res->getBody()->getContents(), true) ?? [];
    }

    public function listObjects(): array
    {
        $params = array_merge($this->commonParams(), ['action' => 'showObjectReportExtern']);
        $res = $this->http->get('', ['query' => $params]);
        return json_decode($res->getBody()->getContents(), true) ?? [];
    }
}

