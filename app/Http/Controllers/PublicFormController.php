<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormResponse;
use App\Models\ResponseAnswer;
use App\Models\SettingResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicFormController extends Controller
{
    public function show(Form $form): View
    {
        abort_unless($form->is_active, 404);

        $form->load([
            'header',
            'textFormattings',
            'sections' => function ($query) {
                $query->orderBy('order')
                    ->with([
                        'textFormattings',
                        'questions' => function ($questionQuery) {
                            $questionQuery->orderBy('order')
                                ->with([
                                    'textFormatting',
                                    'options' => function ($optionQuery) {
                                        $optionQuery->orderBy('order')
                                            ->with('answerTemplate');
                                    }
                                ]);
                        }
                    ]);
            },
            'questions' => function ($query) {
                $query->orderBy('order')
                    ->with([
                        'textFormatting',
                        'options' => function ($optionQuery) {
                            $optionQuery->orderBy('order')
                                ->with('answerTemplate');
                        }
                    ]);
            },
        ]);

        // Check for existing response if limit_one_response is active
        if ($form->limit_one_response) {
            $existingResponse = FormResponse::where('form_id', $form->id)
                ->where('ip_address', request()->ip())
                ->first();

            if ($existingResponse) {
                return view('forms.already_responded', ['form' => $form]);
            }
        }

        // Group questions into pages based on sections as dividers
        $pages = $this->groupQuestionsIntoPages($form);

        // Prepare formatting data
        $formattingMap = $this->prepareFormattingMap($form);

        // Collect all unique font families used in formatting
        $usedFonts = $this->collectUsedFonts($formattingMap);

        return view('forms.public', [
            'form' => $form,
            'pages' => $pages,
            'totalPages' => count($pages),
            'shareUrl' => route('forms.public.show', $form),
            'formattingMap' => $formattingMap,
            'usedFonts' => $usedFonts,
        ]);
    }

    /**
     * Check if a section has been edited (has meaningful content).
     */
    private function isSectionEdited($section): bool
    {
        if (!$section) {
            return false;
        }

        $title = trim($section->title ?? '');
        $description = trim($section->description ?? '');
        $hasImage = !empty($section->image);

        // Strip HTML tags for validation check only
        $titlePlainText = strip_tags($title);
        // If title has HTML tags, it means it's been formatted, so it's valid
        $hasHtmlTags = $title !== $titlePlainText;
        // Check if it's not a default "Bagian X" pattern
        $isNotDefaultPattern = !preg_match('/^Bagian\s+\d+$/i', trim($titlePlainText));
        // Title is valid if it has content AND (has HTML tags OR is not default pattern)
        $hasValidTitle = $title !== '' && ($hasHtmlTags || $isNotDefaultPattern);

        return $hasValidTitle || $description !== '' || $hasImage;
    }

    /**
     * Group questions into pages where sections act as page dividers.
     * All questions before a section appear on the same page.
     * The section and its questions appear on the next page.
     * Sections always act as dividers (partition), but only edited sections are displayed.
     */
    private function groupQuestionsIntoPages(Form $form): array
    {
        $allQuestions = $form->questions->sortBy('order')->values();
        $sections = $form->sections->sortBy('order')->values();

        $pages = [];
        $currentPageQuestions = [];
        $currentPageSection = null;

        // Create a map of section_id to section for quick lookup (all sections)
        $sectionMap = $sections->keyBy('id');

        // Process each question in order
        foreach ($allQuestions as $question) {
            $questionSectionId = $question->section_id;

            if ($questionSectionId === null) {
                // Question has no section - add to current page
                $currentPageQuestions[] = $question;
            } else {
                // Question belongs to a section
                $questionSection = $sectionMap->get($questionSectionId);

                if ($questionSection) {
                    // Section always acts as divider, regardless of whether it's edited
                    if ($currentPageSection && $currentPageSection->id === $questionSectionId) {
                        // Same section as current page - add to current page
                        $currentPageQuestions[] = $question;
                    } else {
                        // Different section - save current page and start new page
                        // Section always acts as divider, but only show if edited
                        if (count($currentPageQuestions) > 0 || $currentPageSection) {
                            $pages[] = [
                                'section' => ($currentPageSection && $this->isSectionEdited($currentPageSection)) ? $currentPageSection : null,
                                'questions' => $currentPageQuestions,
                            ];
                        }

                        // Start new page with this section (always use as divider)
                        $currentPageSection = $questionSection;
                        $currentPageQuestions = [$question];
                    }
                } else {
                    // Section not found, treat question as if it has no section
                    $currentPageQuestions[] = $question;
                }
            }
        }

        // Add the last page
        if (count($currentPageQuestions) > 0 || $currentPageSection) {
            $pages[] = [
                'section' => ($currentPageSection && $this->isSectionEdited($currentPageSection)) ? $currentPageSection : null,
                'questions' => $currentPageQuestions,
            ];
        }

        // Handle section dividers (sections with no questions)
        // These should split questions based on order
        // All sections act as dividers, but only edited ones are displayed
        foreach ($sections as $section) {
            $sectionQuestions = $allQuestions->where('section_id', $section->id);

            // If this is a section divider (no questions assigned to it)
            // Section always acts as divider, regardless of whether it's edited
            if ($sectionQuestions->isEmpty()) {
                // Find questions that should be split by this section divider
                // Questions with order < section.order and no section_id go before
                // Questions with order > section.order and no section_id go after
                $questionsBefore = $allQuestions
                    ->where('order', '<', $section->order)
                    ->whereNull('section_id')
                    ->values();

                $questionsAfter = $allQuestions
                    ->where('order', '>', $section->order)
                    ->whereNull('section_id')
                    ->values();

                // If we have both before and after, we need to split
                if ($questionsBefore->isNotEmpty() && $questionsAfter->isNotEmpty()) {
                    // Rebuild pages to account for this divider
                    $newPages = [];
                    $splitDone = false;

                    foreach ($pages as $page) {
                        if (!$splitDone && $page['section'] === null) {
                            // Check if this page contains questions that should be split
                            $pageQuestionIds = collect($page['questions'])->pluck('id')->toArray();
                            $beforeIds = $questionsBefore->pluck('id')->toArray();
                            $afterIds = $questionsAfter->pluck('id')->toArray();

                            $hasBefore = count(array_intersect($pageQuestionIds, $beforeIds)) > 0;
                            $hasAfter = count(array_intersect($pageQuestionIds, $afterIds)) > 0;

                            if ($hasBefore && $hasAfter) {
                                // Split this page
                                $beforeQuestions = collect($page['questions'])
                                    ->where('order', '<', $section->order)
                                    ->values()
                                    ->toArray();

                                $afterQuestions = collect($page['questions'])
                                    ->where('order', '>', $section->order)
                                    ->values()
                                    ->toArray();

                                if (count($beforeQuestions) > 0) {
                                    $newPages[] = [
                                        'section' => null,
                                        'questions' => $beforeQuestions,
                                    ];
                                }

                                if (count($afterQuestions) > 0) {
                                    $newPages[] = [
                                        'section' => $this->isSectionEdited($section) ? $section : null,
                                        'questions' => $afterQuestions,
                                    ];
                                }

                                $splitDone = true;
                            } else {
                                $newPages[] = $page;
                            }
                        } else {
                            $newPages[] = $page;
                        }
                    }

                    if ($splitDone) {
                        $pages = $newPages;
                    }
                }
            }
        }

        // If no pages were created, create one with all questions
        if (empty($pages)) {
            $pages[] = [
                'section' => null,
                'questions' => $allQuestions->toArray(),
            ];
        }

        // Final shuffle pass if enabled
        if ($form->shuffle_questions) {
            foreach ($pages as &$page) {
                if (count($page['questions']) > 1) {
                    shuffle($page['questions']);
                }
            }
        }

        return $pages;
    }

    public function submit(Request $request, Form $form): RedirectResponse
    {
        abort_unless($form->is_active, 404);

        $form->load([
            'sections.questions.options.answerTemplate',
            'questions.options.answerTemplate',
            'resultRules.texts.textSetting',
            'answerTemplates' => function ($query) {
                $query->orderBy('order');
            },
        ]);

        $allQuestions = collect($form->questions)
            ->concat($form->sections->flatMap->questions)
            ->unique('id')
            ->values();

        $answerTemplateScoreMap = $form->answerTemplates
            ->mapWithKeys(function ($template) {
                $key = Str::lower(trim($template->answer_text ?? ''));
                return $key !== '' ? [$key => $template->score] : [];
            })
            ->toArray();

        $rules = [];
        $messages = [];

        $rules['email'] = $form->collect_email ? ['required', 'email'] : ['nullable', 'email'];

        foreach ($allQuestions as $question) {
            $this->buildQuestionValidation($question->id, $question->is_required, $question->type, $rules, $messages);
        }

        $validated = $request->validate($rules, $messages);

        if ($form->limit_one_response) {
            // Check by IP
            $existsIp = FormResponse::where('form_id', $form->id)
                ->where('ip_address', $request->ip())
                ->exists();

            if ($existsIp) {
                return back()->with('error', 'Anda sudah mengisi formulir ini (IP terdeteksi).');
            }

            // Check by Email if collected
            if ($form->collect_email && isset($validated['email'])) {
                $existsEmail = FormResponse::where('form_id', $form->id)
                    ->where('email', $validated['email'])
                    ->exists();

                if ($existsEmail) {
                    return back()
                        ->withInput()
                        ->withErrors(['email' => 'Anda sudah mengisi formulir ini.']);
                }
            }
        }

        $answers = $validated['answers'] ?? [];
        $totalScore = 0;
        $sectionScores = [];
        $derivedMetrics = [];
        $finalResultData = null;

        DB::transaction(function () use ($form, $validated, $answers, &$totalScore, &$sectionScores, &$derivedMetrics, &$finalResultData, $request, $allQuestions, $answerTemplateScoreMap) {
            $formResponse = FormResponse::create([
                'form_id' => $form->id,
                'email' => $validated['email'] ?? null,
                'total_score' => 0,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Track question-to-section mapping
            $questionSectionMap = [];
            foreach ($form->sections as $section) {
                $sectionScores[$section->id] = [
                    'title' => $section->title,
                    'score' => 0,
                    'calculate_subtotal' => $section->calculate_subtotal,
                    'include_in_total' => $section->include_in_total,
                ];
                foreach ($section->questions as $q) {
                    $questionSectionMap[$q->id] = $section->id;
                }
            }

            foreach ($allQuestions as $question) {
                $answerValue = $answers[$question->id] ?? null;

                if ($answerValue === null || $answerValue === '') {
                    continue;
                }

                $sectionId = $questionSectionMap[$question->id] ?? null;
                $options = $question->options->keyBy('id');
                $questionScore = 0;

                if ($question->type === 'checkbox') {
                    $selectedOptions = is_array($answerValue) ? $answerValue : [$answerValue];
                    foreach ($selectedOptions as $optionId) {
                        $option = $options->get((int) $optionId);
                        if (! $option) continue;

                        $score = optional($option->answerTemplate)->score;
                        if ($score === null) {
                            $score = $this->resolveOptionScoreFallback($option->text, $answerTemplateScoreMap);
                        }
                        $questionScore += ($score ?? 0);

                        ResponseAnswer::create([
                            'form_response_id' => $formResponse->id,
                            'question_id' => $question->id,
                            'question_option_id' => $option->id,
                            'answer_text' => $option->text,
                            'score' => $score ?? 0,
                        ]);
                    }
                } elseif (in_array($question->type, ['multiple-choice', 'dropdown'], true)) {
                    $option = $options->get((int) $answerValue);
                    if ($option) {
                        $score = optional($option->answerTemplate)->score;
                        if ($score === null) {
                            $score = $this->resolveOptionScoreFallback($option->text, $answerTemplateScoreMap);
                        }
                        $questionScore = ($score ?? 0);

                        ResponseAnswer::create([
                            'form_response_id' => $formResponse->id,
                            'question_id' => $question->id,
                            'question_option_id' => $option->id,
                            'answer_text' => $option->text,
                            'score' => $questionScore,
                        ]);
                    }
                } else {
                    $textAnswer = is_array($answerValue) ? implode(', ', $answerValue) : $answerValue;
                    ResponseAnswer::create([
                        'form_response_id' => $formResponse->id,
                        'question_id' => $question->id,
                        'answer_text' => $textAnswer,
                        'score' => 0,
                    ]);
                }

                // Update section score and total score
                if ($sectionId && isset($sectionScores[$sectionId])) {
                    $sectionScores[$sectionId]['score'] += $questionScore;
                }
            }

            // Finalize total score based on section settings
            foreach ($sectionScores as $sId => $sData) {
                if ($sData['include_in_total']) {
                    $totalScore += $sData['score'];
                }
            }

            // Handle BMI Formula
            if ($form->use_bmi_formula && !empty($form->bmi_mapping)) {
                $weightQuestionId = $form->bmi_mapping['weight_question_id'] ?? null;
                $heightQuestionId = $form->bmi_mapping['height_question_id'] ?? null;

                $weight = $weightQuestionId ? ($answers[$weightQuestionId] ?? null) : null;
                $height = $heightQuestionId ? ($answers[$heightQuestionId] ?? null) : null;

                if (is_numeric($weight) && is_numeric($height) && $height > 0) {
                    $heightInMeters = $height / 100;
                    $bmi = $weight / ($heightInMeters * $heightInMeters);
                    $derivedMetrics['bmi'] = [
                        'label' => 'Indeks Massa Tubuh (IMT)',
                        'value' => round($bmi, 2),
                        'weight' => $weight,
                        'height' => $height,
                    ];
                }
            }

            $resultData = $this->resolveResultText($form, $totalScore);
            $finalResultData = $resultData;

            $resultTextPlain = null;
            if ($resultData && isset($resultData['texts'])) {
                $resultTextPlain = implode("\n\n", array_column($resultData['texts'], 'result_text'));
            }

            $formResponse->update([
                'total_score' => $totalScore,
                'result_text' => $resultTextPlain,
                'section_scores' => $sectionScores,
                'derived_metrics' => $derivedMetrics,
            ]);
        });

        return redirect()
            ->route('forms.public.show', $form)
            ->with('status', 'Terima kasih! Jawaban Anda telah disimpan.')
            ->with('result_data', $finalResultData);
    }

    private function buildQuestionValidation(int $questionId, bool $isRequired, string $type, array &$rules, array &$messages): void
    {
        $key = "answers.{$questionId}";

        if ($type === 'checkbox') {
            $rules[$key] = $isRequired ? ['required', 'array', 'min:1'] : ['nullable', 'array'];
            $rules["{$key}.*"] = ['nullable'];
        } else {
            $rules[$key] = $isRequired ? ['required'] : ['nullable'];
        }

        if ($isRequired) {
            $messages["{$key}.required"] = 'Pertanyaan wajib diisi.';
            if ($type === 'checkbox') {
                $messages["{$key}.min"] = 'Pilih minimal satu jawaban.';
            }
        }
    }

    private function resolveResultText(Form $form, int $totalScore): ?array
    {
        $matchingRule = $form->resultRules
            ->sortBy('order')
            ->first(function ($rule) use ($totalScore) {
                return match ($rule->condition_type) {
                    'range' => ($rule->min_score === null || $totalScore >= $rule->min_score)
                        && ($rule->max_score === null || $totalScore <= $rule->max_score),
                    'equal' => $rule->single_score !== null && $totalScore === $rule->single_score,
                    'greater' => $rule->single_score !== null && $totalScore > $rule->single_score,
                    'less' => $rule->single_score !== null && $totalScore < $rule->single_score,
                    default => false,
                };
            });

        if (! $matchingRule) {
            Log::warning('No matching result rule found', [
                'form_id' => $form->id,
                'total_score' => $totalScore,
            ]);
            return null;
        }

        // Get all texts from matching rule (ordered by order)
        $allRuleTexts = $matchingRule->texts->sortBy('order')->values();

        if ($allRuleTexts->isEmpty()) {
            return null;
        }

        $ruleGroupId = $matchingRule->rule_group_id;

        // Load setting_results for this rule_group_id (if exists)
        $settingResults = null;
        $textAlignment = 'center';
        $imageAlignment = 'center';

        if ($ruleGroupId) {
            $settingResults = SettingResult::where('form_id', $form->id)
                ->where('rule_group_id', $ruleGroupId)
                ->with('resultRuleText')
                ->orderBy('order')
                ->get()
                ->keyBy('result_rule_text_id');

            // Get alignment from first setting if exists
            if ($settingResults->isNotEmpty()) {
                $firstSetting = $settingResults->first();
                $textAlignment = $firstSetting->text_alignment ?? 'center';
                $imageAlignment = $firstSetting->image_alignment ?? 'center';
            }
        }

        // Build texts array: get all texts from rule, merge with settings if available
        $texts = $allRuleTexts->map(function ($ruleText) use ($settingResults) {
            $setting = $settingResults ? $settingResults->get($ruleText->id) : null;

            return [
                'title' => $setting ? $setting->title : null,
                'image' => $setting ? $setting->image : null,
                'image_url' => $setting && $setting->image ? asset($setting->image) : null,
                'result_text' => $ruleText->result_text ?? '',
            ];
        })->filter(function ($text) {
            return !empty($text['result_text']);
        })->values()->toArray();

        if (empty($texts)) {
            return null;
        }

        return [
            'text_alignment' => $textAlignment,
            'image_alignment' => $imageAlignment,
            'texts' => $texts,
        ];
    }

    private function resolveOptionScoreFallback(?string $optionText, array $scoreMap): ?int
    {
        if ($optionText === null) {
            return null;
        }

        $key = Str::lower(trim($optionText));
        if ($key === '') {
            return null;
        }

        return $scoreMap[$key] ?? null;
    }

    /**
     * Prepare formatting map for easy access in views
     */
    private function prepareFormattingMap(Form $form): array
    {
        $formattingMap = [];

        // Form title and description formatting
        foreach ($form->textFormattings as $formatting) {
            if ($formatting->element_type === 'form_title') {
                $formattingMap['form_title'] = [
                    'text_align' => $formatting->text_align ?? 'left',
                    'font_family' => $formatting->font_family ?? null,
                    'font_size' => $formatting->font_size ?? null,
                    'font_weight' => $formatting->font_weight ?? 'normal',
                    'font_style' => $formatting->font_style ?? 'normal',
                    'text_decoration' => $formatting->text_decoration ?? 'none',
                ];
            } elseif ($formatting->element_type === 'form_description') {
                $formattingMap['form_description'] = [
                    'text_align' => $formatting->text_align ?? 'left',
                    'font_family' => $formatting->font_family ?? null,
                    'font_size' => $formatting->font_size ?? null,
                    'font_weight' => $formatting->font_weight ?? 'normal',
                    'font_style' => $formatting->font_style ?? 'normal',
                    'text_decoration' => $formatting->text_decoration ?? 'none',
                ];
            }
        }

        // Question title formatting
        foreach ($form->questions as $question) {
            if ($question->textFormatting) {
                $formatting = $question->textFormatting;
                $formattingMap["question_title_{$question->id}"] = [
                    'text_align' => $formatting->text_align ?? 'left',
                    'font_family' => $formatting->font_family ?? null,
                    'font_size' => $formatting->font_size ?? null,
                    'font_weight' => $formatting->font_weight ?? 'normal',
                    'font_style' => $formatting->font_style ?? 'normal',
                    'text_decoration' => $formatting->text_decoration ?? 'none',
                ];
            }
        }

        // Section title and description formatting
        foreach ($form->sections as $section) {
            foreach ($section->textFormattings as $formatting) {
                if ($formatting->element_type === 'section_title') {
                    $formattingMap["section_title_{$section->id}"] = [
                        'text_align' => $formatting->text_align ?? 'left',
                        'font_family' => $formatting->font_family ?? null,
                        'font_size' => $formatting->font_size ?? null,
                        'font_weight' => $formatting->font_weight ?? 'normal',
                        'font_style' => $formatting->font_style ?? 'normal',
                        'text_decoration' => $formatting->text_decoration ?? 'none',
                    ];
                } elseif ($formatting->element_type === 'section_description') {
                    $formattingMap["section_description_{$section->id}"] = [
                        'text_align' => $formatting->text_align ?? 'left',
                        'font_family' => $formatting->font_family ?? null,
                        'font_size' => $formatting->font_size ?? null,
                        'font_weight' => $formatting->font_weight ?? 'normal',
                        'font_style' => $formatting->font_style ?? 'normal',
                        'text_decoration' => $formatting->text_decoration ?? 'none',
                    ];
                }
            }
        }

        return $formattingMap;
    }

    /**
     * Collect all unique font families used in formatting
     */
    private function collectUsedFonts(array $formattingMap): array
    {
        $fonts = [];
        $systemFonts = ['Arial', 'Helvetica', 'Times New Roman', 'Courier New', 'Verdana', 'Georgia', 'Comic Sans MS'];

        foreach ($formattingMap as $formatting) {
            if (isset($formatting['font_family']) && $formatting['font_family']) {
                $fontFamily = trim($formatting['font_family']);
                // Skip system fonts and empty values
                if (!empty($fontFamily) && !in_array($fontFamily, $systemFonts, true)) {
                    $fonts[$fontFamily] = true;
                }
            }
        }

        return array_keys($fonts);
    }
}
