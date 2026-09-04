<?php

namespace App\Http\Controllers;

use App\Services\GeminiSentimentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class SentimentController extends Controller
{
    public function index(): View
    {
        return view('welcome');
    }

    public function classify(Request $request, GeminiSentimentService $gemini): View|RedirectResponse
    {
        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);
        $comments = [];

        if (! empty($validated['comment'])) {
            $comments[] = trim($validated['comment']);
        }
        if ($request->hasFile('file')) {
            $comments = array_merge($comments, $this->commentsFromFile($request->file('file')));
        }
        $comments = array_values(array_filter(array_unique($comments)));

        if ($comments === []) {
            return redirect()->route('home')->withErrors(['comment' => 'Masukkan komentar atau pilih file terlebih dahulu.']);
        }

        try {
            $results = $gemini->classify($comments);
        } catch (\RuntimeException $exception) {
            return redirect()->route('home')->withErrors(['comment' => $exception->getMessage()]);
        }
        $summary = [
            'total' => count($results),
            'positive' => count(array_filter($results, fn (array $result): bool => $result['label'] === 'Positif')),
            'neutral' => count(array_filter($results, fn (array $result): bool => $result['label'] === 'Netral')),
            'negative' => count(array_filter($results, fn (array $result): bool => $result['label'] === 'Negatif')),
        ];

        return view('welcome', compact('results', 'summary'));
    }

    private function commentsFromFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());

        if ($path === false) {
            return [];
        }
        if ($extension === 'txt') {
            return preg_split('/\r\n|\r|\n/', file_get_contents($path) ?: '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $handle = fopen($path, 'rb');
        $rows = [];
        if ($handle === false) {
            return [];
        }
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        if ($rows === []) {
            return [];
        }
        $headerIndex = collect($rows[0])->search(fn (mixed $value): bool => in_array(strtolower(trim((string) $value)), ['komentar', 'comment', 'text'], true));
        $commentColumn = $headerIndex !== false ? $headerIndex : 0;
        $dataRows = $headerIndex !== false ? array_slice($rows, 1) : $rows;

        return array_map(fn (array $row): string => trim((string) ($row[$commentColumn] ?? '')), $dataRows);
    }
}
