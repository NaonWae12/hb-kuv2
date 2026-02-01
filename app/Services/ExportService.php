<?php

namespace App\Services;

use App\Models\Form;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class ExportService
{
    protected $htmlService;
    protected $chartService;

    public function __construct(HtmlService $htmlService, ChartService $chartService)
    {
        $this->htmlService = $htmlService;
        $this->chartService = $chartService;
    }

    /**
     * Export summary responses to Word document.
     */
    public function exportSummary(Form $form, array $responseData)
    {
        $questionSummaries = $responseData['questionSummaries'];
        $totalResponses = $responseData['totalResponses'];

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Cambria');
        $phpWord->setDefaultFontSize(11);

        $properties = $phpWord->getDocInfo();
        $properties->setCreator('Hb-ku Form Builder');
        $properties->setTitle($this->htmlService->stripHtml($form->title ?? 'Form') . ' - Export Summary');

        $section = $phpWord->addSection([
            'marginTop' => 1440,
            'marginRight' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
        ]);

        $tempChartFiles = [];
        $tableStyle = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80];
        $titleCellStyle = ['bgColor' => 'E5E5E5', 'valign' => 'center'];

        // Header Table
        $headerTable = $section->addTable($tableStyle);
        $headerTable->addRow();
        $headerCell = $headerTable->addCell(10000, $titleCellStyle);
        $headerCell->addText(
            '🩸 ' . $this->htmlService->stripHtml($form->title ?? 'Form') . ' 🩸',
            ['bold' => true, 'size' => 18, 'name' => 'Cambria'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
        );

        if ($form->description) {
            $headerTable->addRow();
            $descCell = $headerTable->addCell(10000);
            $descCell->addText(
                $this->htmlService->stripHtml($form->description),
                ['size' => 12, 'name' => 'Cambria'],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
            );
        }

        $headerTable->addRow();
        $metaCell = $headerTable->addCell(10000);
        $metaCell->addText('Ringkasan Jawaban', ['bold' => true, 'size' => 14], ['spaceAfter' => 60]);
        $metaCell->addText('Tanggal Export: ' . Carbon::now()->format('d M Y H:i'), ['size' => 10, 'color' => '666666'], ['spaceAfter' => 30]);
        $metaCell->addText('Total Jawaban: ' . $totalResponses, ['size' => 10, 'color' => '666666'], ['spaceAfter' => 0]);

        $section->addTextBreak(1);

        // Question Summaries
        if (empty($questionSummaries)) {
            $section->addText('Belum ada pertanyaan dalam form ini.', ['italic' => true, 'color' => '999999']);
        } else {
            $sections = $responseData['sections'] ?? [];
            $summariesBySection = collect($questionSummaries)->groupBy('section_id');

            foreach ($sections as $sec) {
                $secSummaries = $summariesBySection->get($sec['id']);
                if ($secSummaries && $secSummaries->isNotEmpty()) {
                    // Section Header in Word
                    $sectionTable = $section->addTable($tableStyle);
                    $sectionTable->addRow();
                    $secHeaderCell = $sectionTable->addCell(10000, ['bgColor' => 'F0F0F0']);
                    $secHeaderCell->addText($this->htmlService->stripHtml($sec['title'] ?? 'Bagian'), ['bold' => true, 'size' => 14, 'color' => 'FF0000']);
                    $section->addTextBreak(1);

                    foreach ($secSummaries as $summary) {
                        $this->addQuestionSummaryToWord($section, $summary, $tableStyle, $titleCellStyle, $tempChartFiles);
                    }
                }
            }

            // Handle root questions
            $rootSummaries = $summariesBySection->get(null);
            if ($rootSummaries && $rootSummaries->isNotEmpty()) {
                foreach ($rootSummaries as $summary) {
                    $this->addQuestionSummaryToWord($section, $summary, $tableStyle, $titleCellStyle, $tempChartFiles);
                }
            }
        }

        $filename = 'export_' . ($form->slug ?? 'form') . '_summary_' . date('Y-m-d_His') . '.docx';
        return $this->saveAndDownload($phpWord, $filename, $tempChartFiles);
    }

    /**
     * Export individual responses to Word document.
     */
    public function exportIndividual(Form $form, array $responseData)
    {
        $individualResponses = $responseData['individualResponses'];

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Cambria');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 1440, 'marginRight' => 1440, 'marginBottom' => 1440, 'marginLeft' => 1440,
        ]);

        $headerTableStyle = ['borderSize' => 0, 'cellMargin' => 50];
        $answerTableStyle = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80];
        $titleCellStyle = ['bgColor' => 'E5E5E5', 'valign' => 'center'];
        $sectionHeaderStyle = ['bgColor' => 'F0F0F0', 'valign' => 'center'];

        // Document Title
        $section->addText(
            '🩸 ' . $this->htmlService->stripHtml($form->title ?? 'Form') . ' 🩸',
            ['bold' => true, 'size' => 16],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 240]
        );

        if (!empty($individualResponses)) {
            foreach ($individualResponses as $index => $response) {
                // Page break for subsequent responses
                if ($index > 0) {
                    $section->addPageBreak();
                }

                $section->addText('Jawaban #' . ($response['position'] ?? ($index + 1)), ['bold' => true, 'size' => 14, 'color' => 'B91C1C'], ['spaceAfter' => 120]);

                // Metadata Table
                $metaTable = $section->addTable($headerTableStyle);
                $metaTable->addRow();
                $metaTable->addCell(2500)->addText('Email', ['bold' => true]);
                $metaTable->addCell(7500)->addText(': ' . ($response['email'] ?? 'Anonim'));
                $metaTable->addRow();
                $metaTable->addCell(2500)->addText('Tanggal Submit', ['bold' => true]);
                $metaTable->addCell(7500)->addText(': ' . ($response['submitted_at'] ?? '-'));
                if (isset($response['total_score'])) {
                    $metaTable->addRow();
                    $metaTable->addCell(2500)->addText('Skor Total', ['bold' => true]);
                    $metaTable->addCell(7500)->addText(': ' . $response['total_score'], ['bold' => true]);
                }

                $section->addTextBreak(1);

                // Answers Table
                $table = $section->addTable($answerTableStyle);
                
                // Table Header
                $table->addRow();
                $table->addCell(4500, $titleCellStyle)->addText('Pertanyaan', ['bold' => true]);
                $table->addCell(4000, $titleCellStyle)->addText('Jawaban', ['bold' => true]);
                $table->addCell(1500, $titleCellStyle)->addText('Skor', ['bold' => true], ['alignment' => Jc::CENTER]);

                if (!empty($response['answers'])) {
                    $sections = $responseData['sections'] ?? [];
                    $answersBySection = collect($response['answers'])->groupBy('section_id');
                    $sectionScores = $response['section_scores'] ?? [];

                    // 1. Process Questions in Sections
                    foreach ($sections as $sec) {
                        $secAnswers = $answersBySection->get($sec['id']);
                        if ($secAnswers && $secAnswers->isNotEmpty()) {
                            // Section Title Row
                            $table->addRow();
                            $cell = $table->addCell(10000, array_merge($sectionHeaderStyle, ['gridSpan' => 3]));
                            $cell->addText($this->htmlService->stripHtml($sec['title'] ?? 'Bagian'), ['bold' => true, 'italic' => true]);

                            foreach ($secAnswers as $answer) {
                                $table->addRow();
                                $table->addCell(4500)->addText($this->htmlService->stripHtml($answer['question'] ?? ''));
                                $table->addCell(4000)->addText($this->htmlService->stripHtml($answer['value'] ?? '-'));
                                $table->addCell(1500)->addText($answer['score'] ?? '0', [], ['alignment' => Jc::CENTER]);
                            }

                            // Subtotal Row
                            if ($sec['calculate_subtotal'] && isset($sectionScores[$sec['id']])) {
                                $table->addRow();
                                $cell = $table->addCell(10000, ['bgColor' => 'FFF5F5', 'gridSpan' => 3]);
                                $sectionTitle = $this->htmlService->stripHtml($sec['title'] ?? '');
                                $cell->addText("Subtotal Bagian ($sectionTitle): " . $sectionScores[$sec['id']]['score'], ['bold' => true, 'color' => 'B91C1C'], ['alignment' => Jc::RIGHT]);
                            }
                        }
                    }

                    // 2. Process Root Questions (No section)
                    $rootAnswers = $answersBySection->get(null);
                    if ($rootAnswers && $rootAnswers->isNotEmpty()) {
                        $table->addRow();
                        $cell = $table->addCell(10000, array_merge($sectionHeaderStyle, ['gridSpan' => 3]));
                        $cell->addText('Umum / Lainnya', ['bold' => true, 'italic' => true]);

                        foreach ($rootAnswers as $answer) {
                            $table->addRow();
                            $table->addCell(4500)->addText($this->htmlService->stripHtml($answer['question'] ?? ''));
                            $table->addCell(4000)->addText($this->htmlService->stripHtml($answer['value'] ?? '-'));
                            $table->addCell(1500)->addText($answer['score'] ?? '0', [], ['alignment' => Jc::CENTER]);
                        }
                    }
                } else {
                    $table->addRow();
                    $table->addCell(10000, ['gridSpan' => 3])->addText('Tidak ada jawaban.', ['italic' => true]);
                }

                // Derived Metrics & Result Text (Outside Table)
                if (!empty($response['derived_metrics']) || !empty($response['result_text'])) {
                    $section->addTextBreak(1);
                    $extraTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 50]);

                    if (!empty($response['derived_metrics'])) {
                        foreach ($response['derived_metrics'] as $metric) {
                            $extraTable->addRow();
                            $extraTable->addCell(10000)->addText($metric['label'] . ': ' . $metric['value'], ['bold' => true, 'size' => 12]);
                        }
                    }

                    if (!empty($response['result_text'])) {
                        $extraTable->addRow();
                        $cell = $extraTable->addCell(10000);
                        $cell->addText('Kesimpulan:', ['bold' => true, 'underline' => 'single', 'size' => 12], ['spaceBefore' => 120]);
                        $this->formatResultTextForWord($response['result_text'], $cell);
                    }
                }
            }
        }

        $filename = $this->sanitizeFilename($form->title ?? 'form') . '_individual_' . date('Y-m-d_His') . '.docx';
        return $this->saveAndDownload($phpWord, $filename);
    }

    private function saveAndDownload(PhpWord $phpWord, string $filename, array $tempFilesToCleanup = [])
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpword_');
        try {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tempFile);

            // Cleanup temp files after send
            register_shutdown_function(function () use ($tempFilesToCleanup) {
                foreach ($tempFilesToCleanup as $file) {
                    if (file_exists($file)) @unlink($file);
                }
            });

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            if (file_exists($tempFile)) @unlink($tempFile);
            Log::error('Word export failed', ['error' => $e->getMessage()]);
            abort(500, 'Gagal membuat dokumen Word.');
        }
    }

    private function formatResultTextForWord(?string $text, $cell): void
    {
        if (empty($text)) return;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) continue;
            
            $bulletPattern = '/^[\s]*([•·▪▫\-\*\+]|[\d]+[\.\)])[\s\t]*(.+)$/u';
            if (preg_match($bulletPattern, $line, $matches)) {
                $cell->addText('• ' . trim($matches[2]), [], ['indentation' => ['left' => 240, 'hanging' => 240]]);
            } else {
                $cell->addText($trimmed, [], ['indentation' => ['left' => 240]]);
            }
        }
    }

    private function addQuestionSummaryToWord($section, $summary, $tableStyle, $titleCellStyle, &$tempChartFiles)
    {
        $questionTitle = $this->htmlService->stripHtml($summary['title'] ?? 'Pertanyaan');
        $questionTable = $section->addTable($tableStyle);
        $questionTable->addRow();
        $titleCell = $questionTable->addCell(10000, $titleCellStyle);
        $titleCell->addText('Pertanyaan: ' . $questionTitle, ['bold' => true, 'size' => 12]);

        $questionTable->addRow();
        $contentCell = $questionTable->addCell(10000);
        $contentCell->addText('Total Jawaban: ' . ($summary['total'] ?? 0), ['size' => 10, 'color' => '666666'], ['spaceAfter' => 120]);

        if (isset($summary['chart']) && !empty($summary['chart']['labels'])) {
            $chartImagePath = $this->chartService->generateChartImage($summary['chart'], $summary['id']);
            if ($chartImagePath && file_exists($chartImagePath)) {
                $tempChartFiles[] = $chartImagePath;
                $contentCell->addImage($chartImagePath, ['width' => 400, 'height' => 300, 'alignment' => Jc::CENTER]);
            }
            foreach ($summary['chart']['labels'] as $labelIndex => $label) {
                $contentCell->addText('  - ' . $this->htmlService->stripHtml($label) . ': ' . ($summary['chart']['values'][$labelIndex] ?? 0) . ' jawaban');
            }
        } elseif (!empty($summary['text_answers'])) {
            foreach ($summary['text_answers'] as $answer) {
                $contentCell->addText('- ' . $this->htmlService->stripHtml((string)$answer), ['size' => 11], ['indentation' => ['left' => 360]]);
            }
        }

        $section->addTextBreak(1);
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = strip_tags($filename);
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        return trim(preg_replace('/_+/', '_', $filename), '_') ?: 'form';
    }
}
