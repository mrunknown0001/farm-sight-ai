<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class AIAnalysisException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): void
    {
        // You can add custom logging or notification logic here
        Log::error('AI Analysis Exception', [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'trace' => $this->getTraceAsString()
        ]);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => true,
                'message' => 'AI Analysis failed',
                'details' => $this->getMessage(),
                'code' => $this->getCode() ?: 500
            ], $this->getCode() ?: 500);
        }

        return parent::render($request);
    }
}