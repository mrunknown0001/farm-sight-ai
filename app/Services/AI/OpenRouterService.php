<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\AIAnalysisException;

/**
 * OpenRouter AI Service
 * 
 * A comprehensive service for analyzing farm operation data using OpenRouter's AI API.
 * Supports multiple operation types: poultry (laying, hatching, feeding), 
 * swine (breeding, farrowing, feeding), and sales analytics.
 */
class OpenRouterService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $defaultModel;
    protected int $maxTokens;
    protected float $temperature;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
        $this->baseUrl = config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');
        $this->defaultModel = config('services.openrouter.default_model', 'anthropic/claude-3.5-sonnet');
        $this->maxTokens = config('services.openrouter.max_tokens', 4000);
        $this->temperature = config('services.openrouter.temperature', 0.7);
        $this->timeout = config('services.openrouter.timeout', 120);
    }

    /**
     * Main method to analyze farm operation data
     *
     * @param array $data The data to analyze
     * @param string $analysisType Type of analysis (e.g., 'poultry_laying', 'swine_breeding')
     * @param array $requirements Specific requirements for the analysis
     * @param array $options Additional options (model, temperature, etc.)
     * @return array Analysis results with insights and recommendations
     * @throws AIAnalysisException
     */
    public function analyzeData(
        array $data,
        string $analysisType,
        array $requirements = [],
        array $options = []
    ): array {
        try {
            // Validate inputs
            $this->validateAnalysisRequest($data, $analysisType, $requirements);

            // Build the prompt based on analysis type and requirements
            $prompt = $this->buildPrompt($data, $analysisType, $requirements);

            // Prepare system instructions
            $systemPrompt = $this->buildSystemPrompt($analysisType);

            // Make API request
            $response = $this->makeRequest($systemPrompt, $prompt, $options);

            // Parse and structure the response
            $analysis = $this->parseResponse($response, $analysisType);

            // Cache results if enabled
            if ($options['cache'] ?? true) {
                $this->cacheResults($data, $analysisType, $requirements, $analysis);
            }

            // Log successful analysis
            Log::info('AI Analysis completed', [
                'type' => $analysisType,
                'data_points' => count($data),
                'model' => $options['model'] ?? $this->defaultModel
            ]);

            return $analysis;

        } catch (\Exception $e) {
            Log::error('AI Analysis failed', [
                'type' => $analysisType,
                'error' => $e->getMessage()
            ]);
            throw new AIAnalysisException("Analysis failed: " . $e->getMessage());
        }
    }

    /**
     * Build system prompt based on analysis type
     */
    protected function buildSystemPrompt(string $analysisType): string
    {
        $baseInstructions = <<<EOT
You are an expert agricultural data analyst specializing in farm operations management.
Your role is to analyze operational data and provide actionable insights.

Core Responsibilities:
1. Analyze data patterns and trends accurately
2. Identify anomalies, risks, and opportunities
3. Provide specific, actionable recommendations
4. Consider industry best practices and standards
5. Ensure recommendations are practical and implementable

Output Format:
- Use clear, professional language
- Structure insights logically
- Prioritize recommendations by impact
- Include relevant metrics and KPIs
- Highlight critical issues requiring immediate attention
EOT;

        $typeSpecificInstructions = $this->getTypeSpecificInstructions($analysisType);

        return $baseInstructions . "\n\n" . $typeSpecificInstructions;
    }

    /**
     * Get type-specific instructions for the AI
     */
    protected function getTypeSpecificInstructions(string $analysisType): string
    {
        $instructions = [
            'poultry_laying' => <<<EOT
Poultry Laying Operations Focus:
- Egg production rates and trends
- Feed conversion ratios
- Mortality rates and flock health
- Peak production timing
- Age-based performance metrics
- Environmental factors (temperature, lighting)
- Compare against industry standards (75-85% lay rate)
EOT,
            'poultry_hatching' => <<<EOT
Poultry Hatching Operations Focus:
- Hatchability rates and trends
- Fertility rates
- Incubation conditions
- Chick quality metrics
- Mortality during hatching
- Seasonal variations
- Compare against industry standards (80-90% hatchability)
EOT,
            'poultry_feeding' => <<<EOT
Poultry Feeding Operations Focus:
- Feed consumption patterns
- Feed conversion efficiency (FCR)
- Growth rates
- Feed costs vs output value
- Nutritional adequacy
- Waste reduction opportunities
- Target FCR: 1.8-2.2 for layers, 1.5-1.9 for broilers
EOT,
            'swine_breeding' => <<<EOT
Swine Breeding Operations Focus:
- Breeding success rates
- Conception rates
- Litter sizes
- Genetic performance
- Breeding cycle timing
- Sow productivity metrics
- Compare against standards (10-12 piglets per litter)
EOT,
            'swine_farrowing' => <<<EOT
Swine Farrowing Operations Focus:
- Farrowing rates and timing
- Piglet survival rates
- Birth weights
- Litter uniformity
- Sow condition post-farrowing
- Weaning metrics
- Target: 90%+ piglet survival to weaning
EOT,
            'swine_feeding' => <<<EOT
Swine Feeding Operations Focus:
- Feed consumption by growth stage
- Average daily gain (ADG)
- Feed conversion ratios
- Growth curve analysis
- Feed costs optimization
- Weight gain efficiency
- Target FCR: 2.5-3.0 for growing pigs
EOT,
            'sales_analysis' => <<<EOT
Sales Operations Focus:
- Revenue trends and patterns
- Product performance
- Customer behavior
- Pricing effectiveness
- Seasonal variations
- Profit margins by product/category
- Market opportunities
EOT,
            'general' => <<<EOT
General Farm Operations Focus:
- Overall operational efficiency
- Resource utilization
- Cost-benefit analysis
- Trend identification
- Performance benchmarking
- Risk assessment
EOT
        ];

        return $instructions[$analysisType] ?? $instructions['general'];
    }

    /**
     * Build analysis prompt
     */
    protected function buildPrompt(array $data, string $analysisType, array $requirements): string
    {
        $promptBuilder = new PromptBuilder($analysisType);

        // Add data to analyze
        $promptBuilder->addData($data);

        // Add analysis requirements
        $promptBuilder->addRequirements($this->normalizeRequirements($requirements));

        // Add context if provided
        if (!empty($requirements['context'])) {
            $promptBuilder->addContext($requirements['context']);
        }

        // Add time period if specified
        if (!empty($requirements['period'])) {
            $promptBuilder->addTimePeriod($requirements['period']);
        }

        // Add comparison benchmarks if provided
        if (!empty($requirements['benchmarks'])) {
            $promptBuilder->addBenchmarks($requirements['benchmarks']);
        }

        return $promptBuilder->build();
    }

    /**
     * Normalize and validate requirements
     */
    protected function normalizeRequirements(array $requirements): array
    {
        $defaults = [
            'depth' => 'standard', // basic, standard, comprehensive
            'focus_areas' => [], // specific areas to focus on
            'exclude_areas' => [], // areas to exclude
            'compare_to_previous' => false,
            'include_recommendations' => true,
            'include_visualizations' => false,
            'priority_level' => 'medium', // low, medium, high, critical
            'industry_standards' => true,
            'risk_assessment' => true,
            'forecast' => false,
            'format' => 'structured', // structured, narrative, executive_summary
        ];

        return array_merge($defaults, $requirements);
    }

    /**
     * Validate analysis request
     */
    protected function validateAnalysisRequest(array $data, string $analysisType, array $requirements): void
    {
        if (empty($data)) {
            throw new AIAnalysisException('Data cannot be empty');
        }

        $validTypes = [
            'poultry_laying', 'poultry_hatching', 'poultry_feeding',
            'swine_breeding', 'swine_farrowing', 'swine_feeding',
            'sales_analysis', 'general'
        ];

        if (!in_array($analysisType, $validTypes)) {
            throw new AIAnalysisException("Invalid analysis type: {$analysisType}");
        }

        // Validate data size
        $dataSize = strlen(json_encode($data));
        if ($dataSize > 500000) { // 500KB limit
            throw new AIAnalysisException('Data size exceeds maximum allowed size');
        }
    }

    /**
     * Make API request to OpenRouter
     */
    protected function makeRequest(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? $this->temperature;
        $maxTokens = $options['max_tokens'] ?? $this->maxTokens;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ])
        ->timeout($this->timeout)
        ->post($this->baseUrl . '/chat/completions', [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt
                ]
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'top_p' => $options['top_p'] ?? 1,
            'frequency_penalty' => $options['frequency_penalty'] ?? 0,
            'presence_penalty' => $options['presence_penalty'] ?? 0,
        ]);

        if (!$response->successful()) {
            throw new AIAnalysisException(
                'API request failed: ' . $response->body(),
                $response->status()
            );
        }

        return $response->json();
    }

    /**
     * Parse API response
     */
    protected function parseResponse(array $response, string $analysisType): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';

        return [
            'analysis_type' => $analysisType,
            'content' => $content,
            'model_used' => $response['model'] ?? 'unknown',
            'tokens_used' => [
                'prompt' => $response['usage']['prompt_tokens'] ?? 0,
                'completion' => $response['usage']['completion_tokens'] ?? 0,
                'total' => $response['usage']['total_tokens'] ?? 0,
            ],
            'timestamp' => now()->toIso8601String(),
            'parsed_insights' => $this->extractInsights($content),
        ];
    }

    /**
     * Extract structured insights from AI response
     */
    protected function extractInsights(string $content): array
    {
        // This is a simple implementation - enhance based on your needs
        return [
            'summary' => $this->extractSection($content, 'summary', 'Summary:', 'Key Findings:'),
            'key_findings' => $this->extractSection($content, 'findings', 'Key Findings:', 'Recommendations:'),
            'recommendations' => $this->extractSection($content, 'recommendations', 'Recommendations:', 'Risks:'),
            'risks' => $this->extractSection($content, 'risks', 'Risks:', 'Opportunities:'),
            'opportunities' => $this->extractSection($content, 'opportunities', 'Opportunities:', null),
        ];
    }

    /**
     * Extract specific section from content
     */
    protected function extractSection(string $content, string $key, string $startMarker, ?string $endMarker): string
    {
        $startPos = stripos($content, $startMarker);
        if ($startPos === false) {
            return '';
        }

        $startPos += strlen($startMarker);

        if ($endMarker) {
            $endPos = stripos($content, $endMarker, $startPos);
            if ($endPos === false) {
                return trim(substr($content, $startPos));
            }
            return trim(substr($content, $startPos, $endPos - $startPos));
        }

        return trim(substr($content, $startPos));
    }

    /**
     * Cache analysis results
     */
    protected function cacheResults(array $data, string $analysisType, array $requirements, array $analysis): void
    {
        $cacheKey = $this->generateCacheKey($data, $analysisType, $requirements);
        $ttl = config('services.openrouter.cache_ttl', 3600); // 1 hour default

        Cache::put($cacheKey, $analysis, $ttl);
    }

    /**
     * Generate cache key
     */
    protected function generateCacheKey(array $data, string $analysisType, array $requirements): string
    {
        $payload = [
            'type' => $analysisType,
            'data_hash' => md5(json_encode($data)),
            'requirements_hash' => md5(json_encode($requirements))
        ];

        return 'ai_analysis:' . md5(json_encode($payload));
    }

    /**
     * Get cached analysis if available
     */
    public function getCachedAnalysis(array $data, string $analysisType, array $requirements = []): ?array
    {
        $cacheKey = $this->generateCacheKey($data, $analysisType, $requirements);
        return Cache::get($cacheKey);
    }

    /**
     * Batch analyze multiple datasets
     */
    public function batchAnalyze(array $datasets, string $analysisType, array $requirements = [], array $options = []): array
    {
        $results = [];

        foreach ($datasets as $key => $data) {
            try {
                $results[$key] = $this->analyzeData($data, $analysisType, $requirements, $options);
            } catch (\Exception $e) {
                $results[$key] = [
                    'error' => true,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}



/**
 * Prompt Builder Helper Class
 */
class PromptBuilder
{
    protected string $analysisType;
    protected array $sections = [];

    public function __construct(string $analysisType)
    {
        $this->analysisType = $analysisType;
    }

    public function addData(array $data): self
    {
        $this->sections['data'] = "# Data to Analyze\n\n" . 
            "```json\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n```";
        return $this;
    }

    public function addRequirements(array $requirements): self
    {
        $reqText = "# Analysis Requirements\n\n";
        
        $reqText .= "**Depth Level:** " . ucfirst($requirements['depth']) . "\n";
        
        if (!empty($requirements['focus_areas'])) {
            $reqText .= "**Focus Areas:** " . implode(', ', $requirements['focus_areas']) . "\n";
        }
        
        if (!empty($requirements['exclude_areas'])) {
            $reqText .= "**Exclude Areas:** " . implode(', ', $requirements['exclude_areas']) . "\n";
        }
        
        $reqText .= "**Priority Level:** " . ucfirst($requirements['priority_level']) . "\n";
        $reqText .= "**Include Recommendations:** " . ($requirements['include_recommendations'] ? 'Yes' : 'No') . "\n";
        $reqText .= "**Risk Assessment:** " . ($requirements['risk_assessment'] ? 'Yes' : 'No') . "\n";
        $reqText .= "**Compare to Industry Standards:** " . ($requirements['industry_standards'] ? 'Yes' : 'No') . "\n";
        $reqText .= "**Output Format:** " . ucfirst($requirements['format']) . "\n";

        $this->sections['requirements'] = $reqText;
        return $this;
    }

    public function addContext(string $context): self
    {
        $this->sections['context'] = "# Additional Context\n\n" . $context;
        return $this;
    }

    public function addTimePeriod(array $period): self
    {
        $this->sections['period'] = "# Time Period\n\n" .
            "**From:** " . ($period['from'] ?? 'N/A') . "\n" .
            "**To:** " . ($period['to'] ?? 'N/A') . "\n";
        return $this;
    }

    public function addBenchmarks(array $benchmarks): self
    {
        $this->sections['benchmarks'] = "# Comparison Benchmarks\n\n" .
            "```json\n" . json_encode($benchmarks, JSON_PRETTY_PRINT) . "\n```";
        return $this;
    }

    public function build(): string
    {
        $prompt = "# Farm Operations Analysis Request\n\n";
        $prompt .= "**Analysis Type:** " . str_replace('_', ' ', ucwords($this->analysisType, '_')) . "\n\n";
        
        foreach ($this->sections as $section) {
            $prompt .= $section . "\n\n";
        }

        $prompt .= <<<EOT

# Required Output Structure

Please provide your analysis in the following structure:

## Summary
A brief executive summary of the overall situation (2-3 sentences)

## Key Findings
- List the most important discoveries from the data
- Include specific metrics and percentages
- Highlight any concerning trends

## Recommendations
Provide actionable recommendations prioritized by:
1. Critical (immediate action needed)
2. Important (action within 1 week)
3. Beneficial (action within 1 month)

## Risks
Identify potential risks and their severity level

## Opportunities
Highlight areas for improvement and optimization

## Metrics Dashboard
Key performance indicators that should be monitored

---

Please analyze thoroughly and provide specific, data-driven insights.
EOT;

        return $prompt;
    }
}