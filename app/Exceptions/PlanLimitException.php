<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when a free-plan user hits a paywalled limit. Rendered as 402 so
 * the mobile app can route straight to the Go Premium screen.
 */
class PlanLimitException extends Exception
{
    public function __construct(
        string $message = 'This feature is part of Brilliant Buddy Pro.',
        public readonly ?int $limit = null,
        public readonly ?int $used = null,
        public readonly string $feature = 'pro',
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $this->getMessage(),
            'error_code' => 'plan_limit_reached',
            'upgrade_required' => true,
            'feature' => $this->feature,
            'limit' => $this->limit,
            'used' => $this->used,
        ], 402);
    }
}
