<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormTextFormatting;
use App\Models\SettingResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FormService
{
    protected $htmlService;

    public function __construct(HtmlService $htmlService)
    {
        $this->htmlService = $htmlService;
    }

    /**
     * Sync form relations using a "Smart Sync" strategy to avoid data loss.
     */
    public function syncRelations(Form $form, array $payload): void
    {
        Log::info('FormService@syncRelations - Start', ['form_id' => $form->id]);

        DB::transaction(function () use ($form, $payload) {
            // 1. Sync Sections
            $sectionIdMap = $this->syncSections($form, $payload['sections'] ?? []);

            // 2. Sync Answer Templates & Result Rules (Rules Context)
            $rulesContext = $this->syncRules($form, $payload);

            // 3. Sync Questions
            $questionIdMap = $this->syncQuestions($form, $payload['questions'] ?? [], $sectionIdMap, $rulesContext);

            // 4. Sync Result Text Settings
            $this->syncSettingResults($form, $payload);

            // 5. Sync Header
            if (isset($payload['header'])) {
                $this->syncHeader($form, $payload['header']);
            }

            // 6. Sync Formatting
            if (isset($payload['text_formatting'])) {
                // Formatting sync is tricky with IDs, we'll handle it carefully
                $this->syncFormatting($form, $payload['text_formatting'], $sectionIdMap, $questionIdMap);
            }
        });

        Log::info('FormService@syncRelations - End', ['form_id' => $form->id]);
    }

    private function syncSections(Form $form, array $sectionsPayload): array
    {
        $existingIds = $form->sections()->pluck('id')->toArray();
        $payloadIds = array_filter(array_column($sectionsPayload, 'id'));

        // Delete sections not in payload
        $form->sections()->whereNotIn('id', $payloadIds)->delete();

        $idMap = []; // Map frontend index to database ID
        foreach ($sectionsPayload as $index => $data) {
            $section = $form->sections()->updateOrCreate(
                ['id' => $data['id'] ?? null],
                [
                    'title' => $this->htmlService->sanitize($data['title'] ?? ''),
                    'description' => $this->htmlService->sanitize($data['description'] ?? ''),
                    'image' => $this->processImage($data['image'] ?? null, $form),
                    'image_alignment' => $data['image_alignment'] ?? 'center',
                    'image_wrap_mode' => $data['image_wrap_mode'] ?? 'fixed',
                    'calculate_subtotal' => (bool)($data['calculate_subtotal'] ?? false),
                    'include_in_total' => (bool)($data['include_in_total'] ?? true),
                    'order' => $index,
                ]
            );
            $idMap[$index] = $section->id;
        }

        return $idMap;
    }

    private function syncQuestions(Form $form, array $questionsPayload, array $sectionIdMap, array $rulesContext): array
    {
        $existingIds = $form->questions()->pluck('id')->toArray();
        $payloadIds = array_filter(array_column($questionsPayload, 'id'));

        // Delete questions not in payload
        $form->questions()->whereNotIn('id', $payloadIds)->delete();

        $idMap = [];
        foreach ($questionsPayload as $index => $data) {
            $sectionId = null;
            if (isset($data['section_id']) && isset($sectionIdMap[$data['section_id']])) {
                $sectionId = $sectionIdMap[$data['section_id']];
            }

            $question = $form->questions()->updateOrCreate(
                ['id' => $data['id'] ?? null],
                [
                    'section_id' => $sectionId,
                    'type' => $data['type'] ?? 'short-answer',
                    'title' => $this->htmlService->sanitize($data['title'] ?? 'Pertanyaan tanpa judul'),
                    'description' => $data['description'] ?? null,
                    'image' => $this->processImage($data['image'] ?? null, $form),
                    'image_alignment' => $data['image_alignment'] ?? 'center',
                    'image_width' => $data['image_width'] ?? null,
                    'is_required' => (bool) ($data['is_required'] ?? false),
                    'order' => $index,
                ]
            );

            // Map frontend index to database ID
            $idMap[$index] = $question->id;

            // Sync Options for this question
            $this->syncOptions($question, $data['options'] ?? [], $rulesContext);
        }

        return $idMap;
    }

    private function syncOptions($question, array $optionsPayload, array $rulesContext): void
    {
        $existingIds = $question->options()->pluck('id')->toArray();
        $payloadIds = array_filter(array_column($optionsPayload, 'id'));

        $question->options()->whereNotIn('id', $payloadIds)->delete();

        foreach ($optionsPayload as $index => $data) {
            $templateId = null;
            $templateIndex = $data['answer_template_index'] ?? null;
            if ($templateIndex !== null && isset($rulesContext['template_id_map'][$templateIndex])) {
                $templateId = $rulesContext['template_id_map'][$templateIndex];
            }

            $question->options()->updateOrCreate(
                ['id' => $data['id'] ?? null],
                [
                    'answer_template_id' => $templateId,
                    'text' => trim($data['text'] ?? ''),
                    'order' => $index,
                ]
            );
        }
    }

    private function syncRules(Form $form, array $payload): array
    {
        // Get all known Rule Group IDs that are "Saved" (exist in rule_groups table)
        // We must NOT delete items belonging to these groups during a main form sync
        $savedRuleGroupIds = $form->ruleGroups()->pluck('rule_group_id')->toArray();

        // Answer Templates
        $templatesPayload = $payload['answer_templates'] ?? [];
        $payloadTemplateIds = array_filter(array_column($templatesPayload, 'id'));
        
        // Find templates to delete: present in DB but not in payload
        // AND not belonging to a saved rule group
        $form->answerTemplates()
            ->whereNotIn('id', $payloadTemplateIds)
            ->where(function ($query) use ($savedRuleGroupIds) {
                $query->whereNull('rule_group_id')
                      ->orWhereNotIn('rule_group_id', $savedRuleGroupIds);
            })
            ->delete();

        $templateIdMap = [];
        foreach ($templatesPayload as $index => $data) {
            $template = $form->answerTemplates()->updateOrCreate(
                ['id' => $data['id'] ?? null],
                [
                    'answer_text' => trim($data['answer_text'] ?? ''),
                    'score' => $data['score'] ?? 0,
                    'order' => $index,
                    'rule_group_id' => $data['rule_group_id'] ?? (string) Str::uuid(),
                ]
            );
            $templateIdMap[$index] = $template->id;
        }

        // Result Rules
        $rulesPayload = $payload['result_rules'] ?? [];
        $payloadRuleIds = array_filter(array_column($rulesPayload, 'id'));
        
        // Find rules to delete: present in DB but not in payload
        // AND not belonging to a saved rule group
        $form->resultRules()
            ->whereNotIn('id', $payloadRuleIds)
            ->where(function ($query) use ($savedRuleGroupIds) {
                $query->whereNull('rule_group_id')
                      ->orWhereNotIn('rule_group_id', $savedRuleGroupIds);
            })
            ->delete();

        foreach ($rulesPayload as $index => $data) {
            $rule = $form->resultRules()->updateOrCreate(
                ['id' => $data['id'] ?? null],
                [
                    'condition_type' => $data['condition_type'] ?? 'range',
                    'min_score' => $data['min_score'] ?? null,
                    'max_score' => $data['max_score'] ?? null,
                    'single_score' => $data['single_score'] ?? null,
                    'order' => $index,
                    'rule_group_id' => $data['rule_group_id'] ?? (string) Str::uuid(),
                ]
            );

            // Sync Rule Texts
            $this->syncRuleTexts($rule, $data['texts'] ?? [], $rule->rule_group_id);
        }

        return ['template_id_map' => $templateIdMap];
    }

    private function syncRuleTexts($rule, array $textsPayload, string $ruleGroupId): void
    {
        $existingIds = $rule->texts()->pluck('id')->toArray();
        $payloadIds = array_filter(array_column($textsPayload, 'id'));
        $rule->texts()->whereNotIn('id', $payloadIds)->delete();

        foreach ($textsPayload as $index => $textData) {
            $textValue = is_array($textData) ? ($textData['result_text'] ?? '') : $textData;
            $rule->texts()->updateOrCreate(
                ['id' => is_array($textData) ? ($textData['id'] ?? null) : null],
                [
                    'result_text' => trim($textValue),
                    'order' => $index,
                    'rule_group_id' => $ruleGroupId,
                ]
            );
        }
    }

    private function syncSettingResults(Form $form, array $payload): void
    {
        $settings = $payload['result_text_settings'] ?? [];
        if (empty($settings)) return;

        // Note: setting_results sync is complex because of legacy structure.
        // For now, let's keep the existing logic but wrap it in service.
        SettingResult::where('form_id', $form->id)->delete();

        foreach ($settings as $index => $data) {
            $ruleGroupId = $data['rule_group_id'] ?? null;
            if (!$ruleGroupId) continue;

            $cardImagePath = $this->processImage($data['card_image'] ?? null, $form);
            $rules = $form->resultRules()->where('rule_group_id', $ruleGroupId)->with('texts')->get();
            
            $allTexts = [];
            foreach ($rules->sortBy('order') as $rule) {
                foreach ($rule->texts->sortBy('order') as $text) {
                    $allTexts[] = $text;
                }
            }

            foreach ($data['text_settings'] ?? [] as $tIndex => $ts) {
                $textIdx = $ts['order'] ?? $tIndex;
                if (isset($allTexts[$textIdx])) {
                    $text = $allTexts[$textIdx];
                    SettingResult::create([
                        'form_id' => $form->id,
                        'rule_group_id' => $ruleGroupId,
                        'result_rule_text_id' => $text->id,
                        'card_title' => $data['card_title'] ?? null,
                        'title' => $ts['title'] ?? null,
                        'image' => $this->processImage($ts['image'] ?? null, $form),
                        'card_image' => $cardImagePath,
                        'image_alignment' => $data['image_alignment'] ?? 'center',
                        'text_alignment' => $data['text_alignment'] ?? 'center',
                        'order' => $textIdx,
                        'card_order' => $data['card_order'] ?? $index,
                    ]);
                }
            }
        }
    }

    private function syncHeader(Form $form, array $data): void
    {
        if (empty($data['image_path'])) {
            $form->header?->delete();
            return;
        }

        $form->header()->updateOrCreate(
            ['form_id' => $form->id],
            [
                'image_path' => $data['image_path'],
                'image_mode' => $data['image_mode'] ?? 'cover',
                'source' => $data['source'] ?? null,
            ]
        );
    }

    private function syncFormatting(Form $form, array $payload, array $sectionIdMap, array $questionIdMap): void
    {
        // Simple strategy: we match by unique combination of form_id/element_type or question_id/section_id
        foreach ($payload as $formatting) {
            $type = $formatting['element_type'] ?? null;
            if (!$type) continue;

            $match = ['element_type' => $type];
            if (in_array($type, ['form_title', 'form_description'])) {
                $match['form_id'] = $form->id;
            } elseif ($type === 'question_title') {
                $questionIndex = $formatting['question_id'] ?? null;
                // Try to map index to ID, otherwise use value if it looks like an ID (though usually safely use map)
                $questionId = isset($questionIdMap[$questionIndex]) ? $questionIdMap[$questionIndex] : null;
                
                // If not found in map and looks like a UUID or ID, might be existing? But update logic relies on index from frontend.
                // Fallback: if provided ID exists in DB (for existing questions not re-mapped? No, frontend sends index).
                
                $match['question_id'] = $questionId;
                if (!$match['question_id']) continue;
            } elseif (in_array($type, ['section_title', 'section_description'])) {
                $sectionIndex = $formatting['section_id'] ?? null;
                $sectionId = isset($sectionIdMap[$sectionIndex]) ? $sectionIdMap[$sectionIndex] : null;

                $match['section_id'] = $sectionId;
                if (!$match['section_id']) continue;
            } elseif (in_array($type, ['result_setting_title', 'result_setting_text'])) {
                $match['result_rule_text_id'] = $formatting['result_rule_text_id'] ?? null;
                if (!$match['result_rule_text_id']) continue;
            }

            FormTextFormatting::updateOrCreate($match, [
                'text_align' => $formatting['text_align'] ?? 'left',
                'font_family' => $formatting['font_family'] ?? 'Arial',
                'font_size' => $formatting['font_size'] ?? 12,
                'font_weight' => $formatting['font_weight'] ?? 'normal',
                'font_style' => $formatting['font_style'] ?? 'normal',
                'text_decoration' => $formatting['text_decoration'] ?? 'none',
            ]);
        }
    }

    private function processImage($imageSource, Form $form): ?string
    {
        if (!$imageSource) return null;
        if (strpos($imageSource, 'storage/') === 0) return $imageSource;
        if (strpos($imageSource, 'data:image/') !== 0) return null;

        // Handle Base64
        try {
            $parts = explode(',', $imageSource);
            $data = base64_decode($parts[1]);
            $extension = strpos($parts[0], 'png') !== false ? 'png' : 'jpg';
            
            $image = imagecreatefromstring($data);
            if (!$image) return null;

            // Resize
            $width = imagesx($image);
            $height = imagesy($image);
            $maxWidth = 1600;
            if ($width > $maxWidth) {
                $ratio = $maxWidth / $width;
                $newWidth = $maxWidth;
                $newHeight = (int)($height * $ratio);
                $resampled = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($resampled, false);
                imagesavealpha($resampled, true);
                imagecopyresampled($resampled, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                $image = $resampled;
            }

            ob_start();
            $extension === 'png' ? imagepng($image) : imagejpeg($image, null, 85);
            $contents = ob_get_clean();

            $path = "question-images/{$form->id}/" . Str::random(40) . "." . $extension;
            Storage::disk('public')->put($path, $contents);
            return "storage/" . $path;
        } catch (\Exception $e) {
            Log::error('Image process failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function normalizeRuleGroupId(array $data, ?string $preferredGroupId = null): string
    {
        return $preferredGroupId 
            ?? $data['rule_group_id'] 
            ?? $data['id'] 
            ?? (string) Str::uuid();
    }

    public function deleteRuleGroup(Form $form, string $ruleGroupId): array
    {
        $templateOrderBase = $form->answerTemplates()->where('rule_group_id', $ruleGroupId)->min('order');
        $ruleOrderBase = $form->resultRules()->where('rule_group_id', $ruleGroupId)->min('order');

        $form->answerTemplates()->where('rule_group_id', $ruleGroupId)->delete();
        $form->resultRules()->where('rule_group_id', $ruleGroupId)->delete();
        SettingResult::where('form_id', $form->id)->where('rule_group_id', $ruleGroupId)->delete();
        
        $form->ruleGroups()->where('rule_group_id', $ruleGroupId)->delete();

        return [
            'template_order_base' => $templateOrderBase,
            'rule_order_base' => $ruleOrderBase,
        ];
    }

    public function ensureRuleGroupsExist(Form $form): void
    {
        $ruleGroupIds = DB::table('result_rules')
            ->where('form_id', $form->id)
            ->whereNotNull('rule_group_id')
            ->distinct()
            ->pluck('rule_group_id')
            ->merge(
                DB::table('answer_templates')
                    ->where('form_id', $form->id)
                    ->whereNotNull('rule_group_id')
                    ->distinct()
                    ->pluck('rule_group_id')
            )
            ->unique();

        foreach ($ruleGroupIds as $id) {
            $form->ruleGroups()->firstOrCreate(['rule_group_id' => $id], ['title' => 'Aturan Baru']);
        }
    }

    public function arrayHasContent(array $array): bool
    {
        foreach ($array as $item) {
            if (is_array($item)) {
                if ($this->arrayHasContent($item)) return true;
            } elseif (!empty($item)) {
                return true;
            }
        }
        return false;
    }

    public function persistFormRules(Form $form, array $payload, bool $isRuleSetup = false, $templateOrderBase = null, $ruleOrderBase = null, $specificRuleGroupId = null): array
    {
        // ... (existing persistFormRules content)
        $templatesPayload = $payload['answer_templates'] ?? $payload['templates'] ?? [];
        $rulesPayload = $payload['result_rules'] ?? $payload['rules'] ?? [];
        
        $ruleGroupId = $specificRuleGroupId ?? (string) Str::uuid();
        
        $templateIdMap = [];
        foreach ($templatesPayload as $index => $data) {
            $template = $form->answerTemplates()->create([
                'answer_text' => trim($data['answer_text'] ?? $data['text'] ?? ''),
                'score' => $data['score'] ?? 0,
                'order' => ($templateOrderBase !== null) ? ($templateOrderBase + $index) : $index,
                'rule_group_id' => $ruleGroupId,
            ]);
            $templateIdMap[$index] = $template->id;
        }

        foreach ($rulesPayload as $index => $data) {
            $rule = $form->resultRules()->create([
                'condition_type' => $data['condition_type'] ?? 'range',
                'min_score' => $data['min_score'] ?? null,
                'max_score' => $data['max_score'] ?? null,
                'single_score' => $data['single_score'] ?? null,
                'order' => ($ruleOrderBase !== null) ? ($ruleOrderBase + $index) : $index,
                'rule_group_id' => $ruleGroupId,
            ]);

            $this->syncRuleTexts($rule, $data['texts'] ?? [], $ruleGroupId);
        }

        return [
            'answer_template_id_map' => $templateIdMap,
            'rule_group_id' => $ruleGroupId
        ];
    }

    /**
     * Menyusun data awal untuk form builder ketika mode edit.
     */
    public function prepareFormBuilderData(Form $form): array
    {
        // Load header data
        $form->load('header');
        $headerData = null;
        if ($form->header) {
            $headerData = [
                'image_path' => $form->header->image_path,
                'image_mode' => $form->header->image_mode,
                'source' => $form->header->source,
            ];
        }

        // Load text formatting data
        $form->load(['textFormattings', 'questions.textFormatting', 'sections.textFormattings']);
        $textFormattingData = [];

        // Form title and description formatting
        foreach ($form->textFormattings as $formatting) {
            if (in_array($formatting->element_type, ['form_title', 'form_description'])) {
                $textFormattingData[] = [
                    'element_type' => $formatting->element_type,
                    'text_align' => $formatting->text_align,
                    'font_family' => $formatting->font_family,
                    'font_size' => $formatting->font_size,
                    'font_weight' => $formatting->font_weight,
                    'font_style' => $formatting->font_style,
                    'text_decoration' => $formatting->text_decoration,
                ];
            } elseif (in_array($formatting->element_type, ['result_setting_title', 'result_setting_text'])) {
                // Result setup card formatting
                $textFormattingData[] = [
                    'element_type' => $formatting->element_type,
                    'result_rule_text_id' => $formatting->result_rule_text_id,
                    'text_align' => $formatting->text_align,
                    'font_family' => $formatting->font_family,
                    'font_size' => $formatting->font_size,
                    'font_weight' => $formatting->font_weight,
                    'font_style' => $formatting->font_style,
                    'text_decoration' => $formatting->text_decoration,
                ];
            }
        }

        $sections = $form->sections->sortBy('order')->values();
        $sectionIndexMap = [];

        $sectionsData = $sections->map(function ($section, $index) use (&$sectionIndexMap, &$textFormattingData) {
            $sectionIndexMap[$section->id] = $index;

            // Add section formatting (title and description)
            foreach ($section->textFormattings as $formatting) {
                if ($formatting->element_type === 'section_title') {
                    $textFormattingData[] = [
                        'element_type' => 'section_title',
                        'section_index' => $index,
                        'text_align' => $formatting->text_align,
                        'font_family' => $formatting->font_family,
                        'font_size' => $formatting->font_size,
                        'font_weight' => $formatting->font_weight,
                        'font_style' => $formatting->font_style,
                        'text_decoration' => $formatting->text_decoration,
                    ];
                } elseif ($formatting->element_type === 'section_description') {
                    $textFormattingData[] = [
                        'element_type' => 'section_description',
                        'section_index' => $index,
                        'text_align' => $formatting->text_align,
                        'font_family' => $formatting->font_family,
                        'font_size' => $formatting->font_size,
                        'font_weight' => $formatting->font_weight,
                        'font_style' => $formatting->font_style,
                        'text_decoration' => $formatting->text_decoration,
                    ];
                }
            }

            return [
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->description,
                'image' => $section->image,
                'image_alignment' => $section->image_alignment ?? 'center',
                'image_wrap_mode' => $section->image_wrap_mode ?? 'fixed',
                'image_url' => $section->image ? asset($section->image) : null,
            ];
        })->toArray();

        $answerTemplatesCollection = $form->answerTemplates
            ->sortBy('order')
            ->values();

        $answerTemplateIndexMap = [];
        $answerTemplatesData = $answerTemplatesCollection
            ->map(function ($template, $index) use (&$answerTemplateIndexMap) {
                $answerTemplateIndexMap[$template->id] = $index;

                return [
                    'id' => $template->id,
                    'answer_text' => $template->answer_text,
                    'score' => $template->score,
                    'rule_group_id' => $template->rule_group_id,
                ];
            })->toArray();

        $questionsData = $form->questions
            ->sortBy('order')
            ->values()
            ->map(function ($question) use ($sectionIndexMap, $answerTemplateIndexMap, &$textFormattingData) {
                // Add question formatting
                if ($question->textFormatting) {
                    $textFormattingData[] = [
                        'element_type' => 'question_title',
                        'question_id' => $question->id,
                        'text_align' => $question->textFormatting->text_align,
                        'font_family' => $question->textFormatting->font_family,
                        'font_size' => $question->textFormatting->font_size,
                        'font_weight' => $question->textFormatting->font_weight,
                        'font_style' => $question->textFormatting->font_style,
                        'text_decoration' => $question->textFormatting->text_decoration,
                    ];
                }

                $questionData = [
                    'id' => $question->id,
                    'type' => $question->type,
                    'title' => $question->title,
                    'description' => $question->description,
                    'image' => $question->image,
                    'image_alignment' => $question->image_alignment ?? 'center',
                    'image_width' => $question->image_width ?? 100,
                    'image_url' => $question->image ? asset($question->image) : null,
                    'is_required' => (bool) $question->is_required,
                    'options' => [],
                ];

                if ($question->section_id && isset($sectionIndexMap[$question->section_id])) {
                    $questionData['section_id'] = $sectionIndexMap[$question->section_id];
                }

                $questionData['options'] = $question->options
                    ->sortBy('order')
                    ->values()
                    ->map(function ($option) use ($answerTemplateIndexMap) {
                        return [
                            'id' => $option->id,
                            'text' => $option->text,
                            'answer_template_index' => $option->answer_template_id !== null && isset($answerTemplateIndexMap[$option->answer_template_id])
                                ? $answerTemplateIndexMap[$option->answer_template_id]
                                : null,
                        ];
                    })->toArray();

                return $questionData;
            })->toArray();

        $resultRulesCollection = $form->resultRules()
            ->with(['texts' => function ($textQuery) {
                $textQuery->orderBy('order')->with('textSetting');
            }])
            ->orderBy('order')
            ->get();

        $resultRulesData = $resultRulesCollection
            ->map(function ($rule) {
                // Get texts with their settings (title, image)
                $textsWithSettings = $rule->texts
                    ->sortBy('order')
                    ->map(function ($text) {
                        $textSetting = $text->textSetting;
                        return [
                            'id' => $text->id,
                            'result_text' => $text->result_text,
                            'title' => $textSetting ? $textSetting->title : null,
                            'image' => $textSetting ? $textSetting->image : null,
                            'image_url' => $textSetting && $textSetting->image ? asset($textSetting->image) : null,
                            'text_alignment' => $textSetting ? $textSetting->text_alignment : 'center',
                            'image_alignment' => $textSetting ? $textSetting->image_alignment : 'center',
                        ];
                    })
                    ->toArray();

                return [
                    'id' => $rule->id,
                    'condition_type' => $rule->condition_type,
                    'min_score' => $rule->min_score,
                    'max_score' => $rule->max_score,
                    'single_score' => $rule->single_score,
                    'rule_group_id' => $rule->rule_group_id,
                    'texts' => $textsWithSettings,
                ];
            })
            ->values()
            ->toArray();

        $ruleGroupTextSettings = $resultRulesCollection
            ->groupBy('rule_group_id')
            ->map(function ($rules) {
                return $rules->flatMap(function ($rule) {
                    return $rule->texts
                        ->sortBy('order')
                        ->map(function ($text) use ($rule) {
                            $textSetting = $text->textSetting;
                            return [
                                'result_rule_text_id' => $text->id,
                                'result_rule_id' => $rule->id, // Tambahkan result_rule_id untuk grouping
                                'result_text' => $text->result_text,
                                'title' => $textSetting ? $textSetting->title : null,
                                'image' => $textSetting ? $textSetting->image : null,
                                'image_url' => $textSetting && $textSetting->image ? asset($textSetting->image) : null,
                                'text_alignment' => $textSetting ? $textSetting->text_alignment : 'center',
                                'image_alignment' => $textSetting ? $textSetting->image_alignment : 'center',
                            ];
                        });
                })->values()->toArray();
            });

        $ruleGroupsCollection = $form->ruleGroups()->get()->keyBy('rule_group_id');

        // Build result setting cards data purely from setting_results table
        $settingResultsCollection = SettingResult::where('form_id', $form->id)
            ->whereNotNull('rule_group_id')
            ->orderBy('card_order')
            ->orderBy('order')
            ->get();

        $resultSettingsData = $settingResultsCollection
            ->groupBy('rule_group_id')
            ->map(function ($settings, $ruleGroupId) use ($ruleGroupTextSettings, $ruleGroupsCollection) {
                $textSettings = $ruleGroupTextSettings->get($ruleGroupId) ?? [];
                $firstSetting = $settings->first();
                $ruleGroup = $ruleGroupsCollection->get($ruleGroupId);

                return [
                    'result_rule_id' => null,
                    'rule_group_id' => $ruleGroupId,
                    'title' => $ruleGroup ? $ruleGroup->title : ($firstSetting->card_title ?? null),
                    'image' => $firstSetting->card_image ?? null,
                    'image_alignment' => $firstSetting->image_alignment ?? 'center',
                    'result_text' => null,
                    'text_alignment' => $firstSetting->text_alignment ?? 'center',
                    'image_url' => $firstSetting && $firstSetting->card_image
                        ? asset($firstSetting->card_image)
                        : null,
                    'text_settings' => $textSettings,
                    'order' => $firstSetting->card_order ?? $firstSetting->order ?? 0,
                ];
            })
            ->values()
            ->toArray();

        // Get rule_groups data for frontend
        // Structure: { rule_group_id: title, ... }
        $ruleGroupsData = $ruleGroupsCollection
            ->mapWithKeys(function ($ruleGroup) {
                return [$ruleGroup->rule_group_id => $ruleGroup->title];
            })
            ->toArray();

        // Fill missing titles from setting_results card_title fallback
        $fallbackRuleGroupTitles = $settingResultsCollection
            ->filter(function ($setting) {
                return $setting->rule_group_id && $setting->card_title;
            })
            ->mapWithKeys(function ($setting) {
                return [$setting->rule_group_id => $setting->card_title];
            })
            ->toArray();

        foreach ($fallbackRuleGroupTitles as $ruleGroupId => $cardTitle) {
            if (!isset($ruleGroupsData[$ruleGroupId]) || !$ruleGroupsData[$ruleGroupId]) {
                $ruleGroupsData[$ruleGroupId] = $cardTitle;
            }
        }

        return [
            'id' => $form->id,
            'slug' => $form->slug,
            'title' => $form->title,
            'description' => $form->description,
            'theme_color' => $form->theme_color,
            'collect_email' => (bool) $form->collect_email,
            'limit_one_response' => (bool) $form->limit_one_response,
            'show_progress_bar' => (bool) $form->show_progress_bar,
            'shuffle_questions' => (bool) $form->shuffle_questions,
            'use_bmi_formula' => (bool) $form->use_bmi_formula,
            'bmi_mapping' => $form->bmi_mapping,
            'share_url' => route('forms.public.show', $form),
            'header' => $headerData,
            'text_formatting' => $textFormattingData,
            'sections' => $sectionsData,
            'questions' => $questionsData,
            'answer_templates' => $answerTemplatesData,
            'result_rules' => $resultRulesData,
            'result_settings' => $resultSettingsData,
            'rule_groups' => $ruleGroupsData, // Add rule_groups for frontend
            'result_text_settings' => [], // Deprecated, but kept for backward compatibility
        ];
    }

    public function buildSavedRules(Form $form): array
    {
        $templatesByGroup = $form->answerTemplates
            ->groupBy(function ($template) {
                return $template->rule_group_id ?? 'default';
            });

        $rulesByGroup = $form->resultRules
            ->groupBy(function ($rule) {
                return $rule->rule_group_id ?? 'default';
            });

        $groupIds = $templatesByGroup->keys()
            ->merge($rulesByGroup->keys())
            ->unique();

        // Get all rule groups with titles
        $ruleGroups = $form->ruleGroups()
            ->whereIn('rule_group_id', $groupIds->filter(fn($id) => $id !== 'default'))
            ->pluck('title', 'rule_group_id');

        return $groupIds->map(function ($groupId) use ($templatesByGroup, $rulesByGroup, $ruleGroups) {
            $templates = $templatesByGroup->get($groupId, collect())->map(function ($template) {
                return [
                    'id' => $template->id,
                    'answer_text' => $template->answer_text,
                    'score' => $template->score,
                    'rule_group_id' => $template->rule_group_id,
                ];
            })->values();

            $rules = $rulesByGroup->get($groupId, collect())->map(function ($rule) {
                return [
                    'id' => $rule->id,
                    'condition_type' => $rule->condition_type,
                    'min_score' => $rule->min_score,
                    'max_score' => $rule->max_score,
                    'single_score' => $rule->single_score,
                    'rule_group_id' => $rule->rule_group_id,
                    'texts' => $rule->texts->sortBy('order')->map(function ($text) {
                        return [
                            'id' => $text->id,
                            'result_text' => $text->result_text,
                        ];
                    })->toArray(),
                ];
            })->values();

            if ($templates->isEmpty()) {
                return null;
            }

            $title = $groupId !== 'default' ? ($ruleGroups[$groupId] ?? null) : null;

            return [
                'rule_group_id' => $groupId === 'default' ? null : $groupId,
                'title' => $title,
                'templates' => $templates,
                'result_rules' => $rules,
            ];
        })->filter()->values()->toArray();
    }

    public function buildResponseData(Form $form): array
    {
        $form->loadMissing([
            'sections' => function ($query) {
                $query->orderBy('order');
            },
            'questions' => function ($query) {
                $query->with(['options' => function ($optionQuery) {
                    $optionQuery->orderBy('order');
                }])->orderBy('order');
            },
        ]);

        $responses = $form->responses()
            ->with(['answers.question', 'answers.questionOption'])
            ->oldest()
            ->get();

        $questionSummaries = $form->questions->map(function ($question) use ($responses) {
            $answers = $responses->flatMap(function ($response) use ($question) {
                return $response->answers->where('question_id', $question->id)->values();
            });

            $total = $answers->count();
            $chart = null;
            $textAnswers = null;

            if (in_array($question->type, ['multiple-choice', 'dropdown', 'checkbox'])) {
                $labels = [];
                $counts = [];
                foreach ($question->options as $option) {
                    $labels[] = $option->text ?? 'Opsi';
                    $counts[] = $answers->where('question_option_id', $option->id)->count();
                }

                $chartType = $question->type === 'checkbox' ? 'bar' : 'pie';
                $chart = [
                    'type' => $chartType,
                    'labels' => $labels,
                    'values' => $counts,
                ];
            } else {
                $textAnswers = $answers->pluck('answer_text')->filter()->values()->take(50)->toArray();
            }

            return [
                'id' => $question->id,
                'title' => $question->title ?? 'Pertanyaan',
                'type' => $question->type,
                'total' => $total,
                'chart' => $chart,
                'text_answers' => $textAnswers,
                'section_id' => $question->section_id,
            ];
        })->values()->toArray();

        $individualResponses = $responses->map(function ($response, $index) {
            return [
                'id' => $response->id,
                'position' => $index + 1,
                'submitted_at' => optional($response->created_at)->format('d M Y H:i'),
                'email' => $response->email,
                'total_score' => $response->total_score,
                'result_text' => $response->result_text,
                'section_scores' => $response->section_scores,
                'derived_metrics' => $response->derived_metrics,
                'answers' => $response->answers->map(function ($answer) {
                    return [
                        'question' => $answer->question->title ?? 'Pertanyaan',
                        'value' => $answer->questionOption->text ?? $answer->answer_text,
                        'score' => $answer->score,
                        'section_id' => $answer->question->section_id ?? null,
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();

        $latestResponse = $responses->first();

        return [
            'totalResponses' => $responses->count(),
            'latestResponseAt' => $latestResponse && $latestResponse->created_at
                ? $latestResponse->created_at->format('d M Y H:i')
                : null,
            'sections' => $form->sections->map(fn($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'calculate_subtotal' => $s->calculate_subtotal,
            ])->toArray(),
            'questionSummaries' => $questionSummaries,
            'individualResponses' => $individualResponses,
        ];
    }

    public function responseStats(?Form $form = null, int $questionCount = 0): array
    {
        if (!$form || !$form->exists) {
            return [
                'total_responses' => 0,
                'question_count' => $questionCount,
                'latest_response_at' => null,
            ];
        }

        $totalResponses = $form->responses()->count();
        $latest = $form->responses()->latest('created_at')->value('created_at');

        return [
            'total_responses' => $totalResponses,
            'question_count' => $questionCount,
            'latest_response_at' => $latest ? $latest->format('d M Y H:i') : null,
        ];
    }
}
