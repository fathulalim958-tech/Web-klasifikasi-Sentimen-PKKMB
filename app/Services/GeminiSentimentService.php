<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiSentimentService
{
    private const BATCH_SIZE = 40;

    private const MAX_PARALLEL_BATCHES = 5;

    public function classify(array $comments): array
    {
        $apiKey = config('services.gemini.key');

        if (blank($apiKey)) {
            throw new RuntimeException('API key Gemini belum dikonfigurasi. Tambahkan GEMINI_API_KEY di file .env.');
        }

        $batches = array_chunk($comments, self::BATCH_SIZE);
        $results = [];

        foreach (array_chunk($batches, self::MAX_PARALLEL_BATCHES) as $batchGroup) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($batchGroup, $apiKey): array {
                    $requests = [];

                    foreach ($batchGroup as $batchIndex => $batch) {
                        $requests['batch_'.$batchIndex] = $pool
                            ->as('batch_'.$batchIndex)
                            ->connectTimeout(5)
                            ->timeout(20)
                            ->retry([200, 500], 1)
                            ->post($this->endpoint($apiKey), [
                                'contents' => [['parts' => [['text' => $this->prompt($batch)]]]],
                                'generationConfig' => [
                                    'temperature' => 0.1,
                                    'responseMimeType' => 'application/json',
                                    'maxOutputTokens' => 4096,
                                ],
                            ]);
                    }

                    return $requests;
                });
            } catch (ConnectionException $exception) {
                throw new RuntimeException('Gemini tidak dapat dihubungi saat ini.', 0, $exception);
            }

            foreach ($batchGroup as $batchIndex => $batch) {
                $results = array_merge($results, $this->parseResponse($responses['batch_'.$batchIndex], $batch));
            }
        }

        return $results;
    }

    private function endpoint(string $apiKey): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/'.config('services.gemini.model').':generateContent?key='.urlencode($apiKey);
    }

    private function prompt(array $comments): string
    {
        $prompt = <<<'PROMPT'
Anda adalah analis sentimen komentar PKKMB berbahasa Indonesia.
Klasifikasikan setiap komentar hanya menjadi satu dari: Positif, Netral, Negatif.
Pertimbangkan konteks, sarkasme, kata negasi, dan makna keseluruhan kalimat, bukan sekadar kata yang terpisah.

Kembalikan JSON array saja tanpa markdown dengan format tepat:
[{"index":0,"label":"Positif","confidence":92,"explanation":"Komentar menunjukkan pengalaman yang menyenangkan."}]
Confidence harus berupa angka integer 0 sampai 100. Jumlah objek harus sama dengan jumlah komentar.
Explanation harus singkat dalam Bahasa Indonesia, maksimal 80 karakter, dan menjelaskan alasan label berdasarkan komentar.

Komentar:
PROMPT;

        foreach ($comments as $index => $comment) {
            $prompt .= "\n{$index}. {$comment}";
        }

        return $prompt;
    }

    private function parseResponse(mixed $response, array $comments): array
    {
        if ($response instanceof ConnectionException) {
            throw new RuntimeException('Gemini tidak dapat dihubungi saat ini.', 0, $response);
        }

        if ($response instanceof RequestException) {
            throw new RuntimeException($this->apiErrorMessage($response->response), 0, $response);
        }

        if (! $response instanceof Response) {
            throw new RuntimeException('Respons Gemini tidak dapat diproses.');
        }

        if ($response->failed()) {
            throw new RuntimeException($this->apiErrorMessage($response));
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $decoded = is_string($text) ? $this->decodeJson($text) : null;

        if (! is_array($decoded) || count($decoded) !== count($comments)) {
            throw new RuntimeException('Respons Gemini tidak sesuai format yang diharapkan.');
        }

        return array_map(function (array $item, int $index) use ($comments): array {
            $label = $this->normalizeLabel($item['label'] ?? $item['sentiment'] ?? null);
            $confidence = $item['confidence'] ?? null;
            $explanation = trim((string) ($item['explanation'] ?? $item['reason'] ?? ''));

            if ($label === null || ! is_numeric($confidence)) {
                throw new RuntimeException('Respons Gemini berisi label sentimen yang tidak valid.');
            }

            if ($explanation === '') {
                $explanation = match ($label) {
                    'Positif' => 'Komentar menunjukkan pengalaman yang positif.',
                    'Negatif' => 'Komentar menunjukkan pengalaman yang negatif.',
                    default => 'Komentar tidak menunjukkan kecenderungan sentimen yang kuat.',
                };
            }

            return [
                'comment' => $comments[$index],
                'label' => $label,
                'explanation' => $explanation,
                'score' => $label === 'Positif' ? 1 : ($label === 'Negatif' ? -1 : 0),
                'confidence' => max(0, min(100, (int) $confidence)),
            ];
        }, $decoded, array_keys($comments));
    }

    private function apiErrorMessage(Response $response): string
    {
        return match ($response->status()) {
            400 => 'Permintaan ke Gemini tidak valid. Periksa nama model dan format request.',
            401, 403 => 'API key Gemini tidak valid atau tidak memiliki akses. Periksa GEMINI_API_KEY.',
            404 => 'Model Gemini tidak ditemukan. Periksa nilai GEMINI_MODEL di file .env.',
            429 => 'Kuota atau batas rate Gemini sedang habis. Tunggu sebentar lalu coba lagi.',
            default => $response->serverError()
                ? 'Server Gemini sedang bermasalah. Coba lagi beberapa saat.'
                : 'Gemini menolak permintaan klasifikasi (HTTP '.$response->status().').',
        };
    }

    private function decodeJson(string $text): mixed
    {
        $cleanText = trim($text);
        $cleanText = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $cleanText) ?? $cleanText;
        $decoded = json_decode(trim($cleanText), true);

        if (is_array($decoded) && isset($decoded['results']) && is_array($decoded['results'])) {
            return $decoded['results'];
        }

        return $decoded;
    }

    private function normalizeLabel(mixed $label): ?string
    {
        return match (strtolower(trim((string) $label))) {
            'positif', 'positive' => 'Positif',
            'netral', 'neutral' => 'Netral',
            'negatif', 'negative' => 'Negatif',
            default => null,
        };
    }
}
