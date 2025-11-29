<?php

namespace App\Http\Controllers;

use App\Services\AI\OpenRouterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Example Controller showing how to use OpenRouterService
 */
class FarmAnalysisController extends Controller
{
    protected OpenRouterService $aiService;

    public function __construct(OpenRouterService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Example 1: Analyze poultry laying performance
     */
    public function analyzePoultryLaying(Request $request): JsonResponse
    {
        $data = [
            'date' => '2024-11-01',
            'total_birds' => 10000,
            'eggs_collected' => 8500,
            'feed_consumed_kg' => 1200,
            'mortality_count' => 5,
            'average_egg_weight_g' => 62,
            'temperature_celsius' => 24,
            'humidity_percent' => 65,
        ];

        $requirements = [
            'depth' => 'comprehensive',
            'focus_areas' => ['production_rate', 'feed_efficiency', 'health_indicators'],
            'priority_level' => 'high',
            'industry_standards' => true,
            'risk_assessment' => true,
            'format' => 'structured',
        ];

        try {
            // Check cache first
            $cached = $this->aiService->getCachedAnalysis($data, 'poultry_laying', $requirements);
            if ($cached) {
                return response()->json([
                    'success' => true,
                    'from_cache' => true,
                    'data' => $cached
                ]);
            }

            // Perform analysis
            $analysis = $this->aiService->analyzeData(
                $data,
                'poultry_laying',
                $requirements
            );

            return response()->json([
                'success' => true,
                'from_cache' => false,
                'data' => $analysis
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Example 2: Analyze swine breeding performance
     */
    public function analyzeSwineBreeding(Request $request): JsonResponse
    {
        $data = [
            'breeding_date' => '2024-09-15',
            'total_sows' => 50,
            'successful_breedings' => 45,
            'conception_rate' => 0.90,
            'average_litter_size' => 11,
            'boar_efficiency' => 0.95,
            'repeat_breeding_needed' => 5,
        ];

        $requirements = [
            'depth' => 'standard',
            'focus_areas' => ['conception_rate', 'litter_size', 'breeding_efficiency'],
            'compare_to_previous' => true,
            'industry_standards' => true,
        ];

        $analysis = $this->aiService->analyzeData(
            $data,
            'swine_breeding',
            $requirements
        );

        return response()->json([
            'success' => true,
            'data' => $analysis
        ]);
    }

    /**
     * Example 3: Analyze sales performance
     */
    public function analyzeSales(Request $request): JsonResponse
    {
        $data = [
            'period' => 'Q3-2024',
            'total_revenue' => 150000,
            'units_sold' => 5000,
            'average_price_per_unit' => 30,
            'top_products' => [
                ['name' => 'Grade A Eggs', 'revenue' => 80000, 'units' => 4000],
                ['name' => 'Broiler Chickens', 'revenue' => 50000, 'units' => 800],
                ['name' => 'Pork', 'revenue' => 20000, 'units' => 200],
            ],
            'customer_segments' => [
                'wholesale' => 0.60,
                'retail' => 0.30,
                'direct' => 0.10,
            ],
        ];

        $requirements = [
            'depth' => 'comprehensive',
            'focus_areas' => ['revenue_trends', 'product_performance', 'customer_behavior'],
            'priority_level' => 'medium',
            'include_recommendations' => true,
            'format' => 'executive_summary',
        ];

        $analysis = $this->aiService->analyzeData(
            $data,
            'sales_analysis',
            $requirements,
            ['temperature' => 0.5] // Lower temperature for more factual analysis
        );

        return response()->json([
            'success' => true,
            'data' => $analysis
        ]);
    }

    /**
     * Example 4: Batch analysis of multiple operations
     */
    public function batchAnalysis(Request $request): JsonResponse
    {
        $datasets = [
            'flock_a' => [
                'location' => 'Barn A',
                'total_birds' => 5000,
                'eggs_collected' => 4200,
                'feed_consumed_kg' => 600,
            ],
            'flock_b' => [
                'location' => 'Barn B',
                'total_birds' => 5000,
                'eggs_collected' => 4500,
                'feed_consumed_kg' => 580,
            ],
        ];

        $requirements = [
            'depth' => 'standard',
            'focus_areas' => ['comparative_performance'],
            'format' => 'structured',
        ];

        $results = $this->aiService->batchAnalyze(
            $datasets,
            'poultry_laying',
            $requirements
        );

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Example 5: Advanced analysis with custom model
     */
    public function advancedAnalysis(Request $request): JsonResponse
    {
        $data = $request->input('data');
        $analysisType = $request->input('type', 'general');
        
        $requirements = [
            'depth' => 'comprehensive',
            'priority_level' => 'critical',
            'include_recommendations' => true,
            'risk_assessment' => true,
            'forecast' => true,
            'context' => 'This is a critical analysis for quarterly review',
        ];

        $options = [
            'model' => 'anthropic/claude-3-opus', // Use most powerful model
            'temperature' => 0.3, // More deterministic
            'max_tokens' => 6000, // Longer response
            'cache' => true,
        ];

        $analysis = $this->aiService->analyzeData(
            $data,
            $analysisType,
            $requirements,
            $options
        );

        return response()->json([
            'success' => true,
            'data' => $analysis
        ]);
    }
}