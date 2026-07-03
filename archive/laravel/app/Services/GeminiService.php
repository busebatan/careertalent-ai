<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    public function extractSkillsFromCv(string $cvText): array
    {
        $apiKey = config('services.gemini.api_key');

        if (empty($apiKey)) {
            Log::warning('GEMINI_API_KEY tanımlı değil, boş profil dönülüyor.');

            return ['skills' => [], 'summary' => 'API anahtarı bekleniyor'];
        }

        $response = Http::timeout(60)->post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='.$apiKey,
            [
                'contents' => [[
                    'parts' => [[
                        'text' => "Bu CV metninden yetenekleri JSON olarak çıkar. Format: {\"skills\":[],\"experience_years\":0,\"education\":[],\"certificates\":[],\"summary\":\"\"}\n\n".$cvText,
                    ]],
                ]],
            ]
        );

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini API hatası: '.$response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text', '{}');
        $text = trim(str_replace(['```json', '```'], '', $text));

        return json_decode($text, true) ?? ['skills' => [], 'summary' => 'Parse hatası'];
    }
}
