<?php

namespace App\Http\Controllers\App;

use App\Services\HarvardCvPdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CvPdfController extends PanelController
{
    public function __invoke(Request $request, HarvardCvPdfRenderer $renderer): Response|JsonResponse
    {
        $baseValidator = Validator::make($request->all(), [
            'language' => ['required', 'string', Rule::in(['tr', 'en'])],
            'locales' => ['required', 'array'],
        ]);
        if ($baseValidator->fails()) {
            return $this->validationError($baseValidator->errors()->toArray());
        }

        $validated = $baseValidator->validated();
        $language = $validated['language'];
        $localeValidator = Validator::make($request->all(), [
            'locales.'.$language => ['required', 'array'],
        ]);
        if ($localeValidator->fails()) {
            return $this->validationError($localeValidator->errors()->toArray());
        }

        return $renderer->download($validated['locales'], $language);
    }

    /** @param array<string, array<int, string>> $errors */
    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $errors,
        ], 422);
    }
}
