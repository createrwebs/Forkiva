<?php

namespace Modules\User\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\User\Models\User;
use Modules\User\Services\Pdpa\PdpaAnonymizerService;

class PdpaController extends Controller
{
    public function __construct(private PdpaAnonymizerService $pdpaService) {}

    public function recordConsent(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        return ApiResponse::success($this->pdpaService->recordConsent($user));
    }

    public function anonymize(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        return ApiResponse::success($this->pdpaService->anonymize($user));
    }
}
