<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurrencyExchangeRate;
use App\Models\Tenant;
use App\Services\MoneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __construct(
        protected MoneyService $moneyService
    ) {}

    /**
     * List all available ISO currencies.
     */
    public function index(): JsonResponse
    {
        $currencies = $this->moneyService->getAvailableCurrencies();
        $currencyDetails = [];

        foreach ($currencies as $code) {
            $info = $this->moneyService->getCurrencyInfo($code);
            if ($info) {
                $currencyDetails[] = $info;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'currencies' => $currencyDetails,
                'default_currency' => $this->moneyService->getDefaultCurrency(),
            ],
            'message' => 'Available currencies retrieved successfully',
        ], 200);
    }

    /**
     * Get exchange rates for the system (global) or a specific tenant.
     */
    public function rates(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');

        $rates = CurrencyExchangeRate::getRatesForTenant(
            $tenantId !== null ? (int) $tenantId : null
        );

        return response()->json([
            'success' => true,
            'data' => [
                'rates' => $rates,
                'base_currency' => $this->moneyService->getDefaultCurrency(),
            ],
            'message' => 'Exchange rates retrieved successfully',
        ], 200);
    }

    /**
     * Create or update an exchange rate.
     */
    public function updateRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'base_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'target_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', 'different:base_currency'],
            'rate' => ['required', 'numeric', 'min:0.0000000001'],
        ]);

        $rate = CurrencyExchangeRate::updateRate(
            $validated['base_currency'],
            $validated['target_currency'],
            (float) $validated['rate'],
            'manual',
            $validated['tenant_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => [
                'rate' => [
                    'base_currency' => $rate->base_currency,
                    'target_currency' => $rate->target_currency,
                    'rate' => (float) $rate->rate,
                    'source' => $rate->source,
                    'effective_at' => $rate->effective_at?->toIso8601String(),
                    'tenant_id' => $rate->tenant_id,
                ],
            ],
            'message' => 'Exchange rate updated successfully',
        ], 200);
    }

    /**
     * Delete an exchange rate.
     */
    public function deleteRate(int $rateId): JsonResponse
    {
        $rate = CurrencyExchangeRate::findOrFail($rateId);
        $rate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate deleted successfully',
        ], 200);
    }

    /**
     * Sync exchange rates from European Central Bank.
     */
    public function syncRates(): JsonResponse
    {
        try {
            $ratesSynced = $this->moneyService->syncRatesFromECB();

            return response()->json([
                'success' => true,
                'data' => [
                    'rates_synced' => $ratesSynced,
                ],
                'message' => "Successfully synced {$ratesSynced} exchange rates from ECB",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ECB_SYNC_FAILED',
                    'message' => 'Failed to sync rates from ECB: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Convert an amount between currencies.
     */
    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'from_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'to_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ]);

        try {
            $result = $this->moneyService->convertAmount(
                (float) $validated['amount'],
                $validated['from_currency'],
                $validated['to_currency']
            );

            $rate = $this->moneyService->getExchangeRate(
                $validated['from_currency'],
                $validated['to_currency']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'from' => [
                        'amount' => (float) $validated['amount'],
                        'currency' => $validated['from_currency'],
                    ],
                    'to' => [
                        'amount' => $result,
                        'currency' => $validated['to_currency'],
                    ],
                    'rate' => $rate,
                ],
                'message' => 'Currency conversion completed',
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CONVERSION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Get a tenant's currency configuration.
     */
    public function tenantCurrency(Tenant $tenant): JsonResponse
    {
        $currency = $tenant->resolveCurrency();
        $currencyInfo = $this->moneyService->getCurrencyInfo($currency);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'currency' => $currency,
                'currency_info' => $currencyInfo,
            ],
            'message' => 'Tenant currency retrieved successfully',
        ], 200);
    }

    /**
     * Update a tenant's base currency.
     */
    public function updateTenantCurrency(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ]);

        $tenant->update(['currency' => $validated['currency']]);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'currency' => $tenant->currency,
            ],
            'message' => 'Tenant currency updated successfully',
        ], 200);
    }
}
