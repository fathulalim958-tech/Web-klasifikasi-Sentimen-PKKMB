<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SentimentClassificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.key' => 'test-key']);
    }

    public function test_a_manual_comment_is_classified(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '[{"index":0,"label":"Positif","confidence":94,"explanation":"Komentar menunjukkan pengalaman yang menyenangkan."}]']]]]],
        ])]);

        $response = $this->post('/classify', [
            'comment' => 'PKKMB sangat seru, panitianya ramah dan acaranya teratur.',
        ]);

        $response->assertOk()->assertSee('Positif')->assertSee('1 komentar dianalisis')->assertSee('Distribusi sentimen')->assertSee('100.0%')->assertSee('Komentar menunjukkan pengalaman yang menyenangkan.');
    }

    public function test_comments_can_be_classified_from_a_csv_file(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '[{"index":0,"label":"Positif","confidence":90,"explanation":"Komentar menilai kegiatan secara baik."},{"index":1,"label":"Negatif","confidence":91,"explanation":"Komentar mengeluhkan keterlambatan dan kondisi yang berantakan."}]']]]]],
        ])]);

        $file = UploadedFile::fake()->createWithContent('komentar.csv', "komentar\nAcara ini bagus dan sangat membantu\nJadwalnya terlambat dan berantakan");

        $response = $this->post('/classify', ['file' => $file]);

        $response->assertOk()->assertSee('2 komentar dianalisis')->assertSee('Positif')->assertSee('Negatif');
    }

    public function test_a_comment_with_membosankan_is_classified_as_negative(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '[{"index":0,"label":"Negatif","confidence":97,"explanation":"Komentar menyatakan kegiatan sangat membosankan."}]']]]]],
        ])]);

        $response = $this->post('/classify', [
            'comment' => 'luar biasa sangat membosankan semoga tetap seperti ini terus',
        ]);

        $response->assertOk()->assertSee('pill negatif');
    }

    public function test_large_comment_sets_are_split_into_batches(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => function (HttpRequest $request) {
            $payload = json_decode($request->body(), true);
            preg_match_all('/^\d+\./m', (string) ($payload['contents'][0]['parts'][0]['text'] ?? ''), $matches);
            $results = array_map(fn (int $index): array => [
                'index' => $index,
                'label' => 'Netral',
                'confidence' => 80,
                'explanation' => 'Komentar tidak menunjukkan kecenderungan sentimen yang kuat.',
            ], range(0, count($matches[0]) - 1));

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($results)]]]]]]);
        }]);

        $comments = array_map(fn (int $index): string => "Komentar nomor {$index}", range(1, 41));
        $file = UploadedFile::fake()->createWithContent('komentar.csv', "komentar\n".implode("\n", $comments));
        $response = $this->post('/classify', ['file' => $file]);

        $response->assertOk()->assertSee('41 komentar dianalisis');
        Http::assertSentCount(2);
    }

    public function test_gemini_api_errors_are_returned_as_a_user_message(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Invalid key']], 403)]);

        $response = $this->post('/classify', ['comment' => 'Kegiatan PKKMB sangat baik.']);

        $response->assertRedirect('/')->assertSessionHasErrors(['comment' => 'API key Gemini tidak valid atau tidak memiliki akses. Periksa GEMINI_API_KEY.']);
    }

    public function test_gemini_label_variations_are_normalized(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => "```json\n[{\"index\":0,\"sentiment\":\"NEGATIVE\",\"confidence\":88}]\n```"]]]]],
        ])]);

        $response = $this->post('/classify', ['comment' => 'Jadwal kegiatan sangat buruk.']);

        $response->assertOk()->assertSee('Negatif')->assertSee('Komentar menunjukkan pengalaman yang negatif.');
    }
}
