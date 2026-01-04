<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormHeader;
use App\Models\FormResponse;
use App\Models\FormTextFormatting;
use App\Models\SettingResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

class FormController extends Controller
{
    /**
     * Menampilkan halaman dashboard
     */
    public function index()
    {
        $userId = Auth::id();

        $formsQuery = Form::withCount(['questions', 'responses'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        $formsCollection = $formsQuery->get();
        $formIds = $formsCollection->pluck('id');

        $totalResponses = $formIds->isNotEmpty()
            ? FormResponse::whereIn('form_id', $formIds)->count()
            : 0;

        $activeThisMonth = $formIds->isNotEmpty()
            ? FormResponse::whereIn('form_id', $formIds)
            ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->distinct()
            ->count('form_id')
            : 0;

        $stats = [
            'total_forms' => $formsCollection->count(),
            'total_responses' => $totalResponses,
            'active_this_month' => $activeThisMonth,
        ];

        $forms = $formsCollection->map(function ($form) {
            return [
                'id' => $form->id,
                'title' => $form->title,
                'description' => $form->description ?? '',
                'questions_count' => $form->questions_count ?? 0,
                'responses_count' => $form->responses_count ?? 0,
                'created_at' => optional($form->created_at)->toDateString() ?? Carbon::now()->toDateString(),
            ];
        })->toArray();

        return view('dashboard', compact('stats', 'forms'));
    }

    /**
     * Menampilkan halaman pembuatan form baru
     */
    public function create()
    {
        $responseStats = $this->responseStats();

        return view('forms.create', [
            'formData' => null,
            'formMode' => 'create',
            'saveFormUrl' => route('forms.store'),
            'saveFormMethod' => 'POST',
            'formId' => null,
            'shareUrl' => null,
            'savedRules' => [],
            'responsesStats' => $responseStats,
        ]);
    }

    /**
     * Menyimpan form baru ke database
     */
    public function store(Request $request)
    {
        // Strip HTML from title for validation (check plain text length)
        $titlePlainText = strip_tags($request->title);
        $request->merge(['title_plain' => $titlePlainText]);

        $request->validate([
            'title' => 'required|string',
            'title_plain' => 'required|string|max:255',
            'description' => 'nullable|string',
            'theme_color' => 'nullable|string|in:red,blue,green,purple',
            'collect_email' => 'boolean',
            'limit_one_response' => 'boolean',
            'show_progress_bar' => 'boolean',
            'shuffle_questions' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // Use HTML title for slug generation (strip HTML for slug)
            $slugTitle = strip_tags($request->title);
            // Clean and optimize HTML before storing
            $cleanedTitle = $this->cleanHTML($request->title);
            $cleanedDescription = $request->description ? $this->cleanHTML($request->description) : null;
            $form = Form::create([
                'user_id' => Auth::id(),
                'title' => $cleanedTitle, // Store cleaned HTML in database
                'description' => $cleanedDescription,
                'slug' => Str::slug($slugTitle) . '-' . time(),
                'theme_color' => $request->theme_color ?? 'red',
                'collect_email' => $request->boolean('collect_email'),
                'limit_one_response' => $request->boolean('limit_one_response'),
                'show_progress_bar' => $request->boolean('show_progress_bar'),
                'shuffle_questions' => $request->boolean('shuffle_questions'),
                'is_active' => true,
            ]);

            $this->syncFormRelations($form, [
                'sections' => $request->input('sections', []),
                'answer_templates' => $request->input('answer_templates', []),
                'result_rules' => $request->input('result_rules', []),
                'questions' => $request->input('questions', []),
                'result_text_settings' => $request->input('result_text_settings', []),
            ]);

            // Sync header and text formatting
            $this->syncFormHeader($form, $request->input('header', []));

            // Build question and section ID maps for formatting sync
            // Map by order (index) to database ID
            $questionIdMap = [];
            $form->questions()->orderBy('order')->get()->each(function ($question, $index) use (&$questionIdMap) {
                $questionIdMap[$index] = $question->id;
            });

            $sectionIdMap = [];
            $form->sections()->orderBy('order')->get()->each(function ($section, $index) use (&$sectionIdMap) {
                $sectionIdMap[$index] = $section->id;
            });

            $this->syncTextFormatting($form, $request->input('text_formatting', []), $questionIdMap, $sectionIdMap);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Form berhasil disimpan!',
                'form_id' => $form->id,
                'slug' => $form->slug,
                'share_url' => route('forms.public.show', $form),
                'edit_url' => route('forms.edit', $form),
                'update_url' => route('forms.update', $form),
                'save_method' => 'PUT',
                'form_rules_save_url' => route('forms.rules.store', $form),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan form: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menampilkan halaman edit form.
     */
    public function edit(Form $form)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        $form->load([
            'sections' => function ($query) {
                $query->orderBy('order');
            },
            'questions' => function ($query) {
                $query->with(['options' => function ($optionQuery) {
                    $optionQuery->orderBy('order');
                }])->orderBy('order');
            },
            'answerTemplates' => function ($query) use ($form) {
                $query->where('form_id', $form->id)->orderBy('order');
            },
            'resultRules' => function ($query) use ($form) {
                $query->where('form_id', $form->id)
                    ->with(['texts' => function ($textQuery) {
                        $textQuery->orderBy('order')->with('textSetting');
                    }])->orderBy('order');
            },
        ]);

        $formData = $this->prepareFormBuilderData($form);
        $savedRules = $this->buildSavedRules($form);

        $responseStats = $this->responseStats($form, count($formData['questions'] ?? []));

        return view('forms.create', [
            'formData' => $formData,
            'formMode' => 'edit',
            'saveFormUrl' => route('forms.update', $form),
            'saveFormMethod' => 'PUT',
            'formId' => $form->id,
            'shareUrl' => route('forms.public.show', $form),
            'savedRules' => $savedRules,
            'responsesStats' => $responseStats,
        ]);
    }

    /**
     * Memperbarui form yang sudah ada.
     */
    public function update(Request $request, Form $form)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        // Strip HTML from title for validation (check plain text length)
        $titlePlainText = strip_tags($request->title);
        $request->merge(['title_plain' => $titlePlainText]);

        $request->validate([
            'title' => 'required|string',
            'title_plain' => 'required|string|max:255',
            'description' => 'nullable|string',
            'theme_color' => 'nullable|string|in:red,blue,green,purple',
            'collect_email' => 'boolean',
            'limit_one_response' => 'boolean',
            'show_progress_bar' => 'boolean',
            'shuffle_questions' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $sectionsInput = $request->input('sections', []);
            $answerTemplatesInput = $request->input('answer_templates', []);
            $resultRulesInput = $request->input('result_rules', []);
            $questionsInput = $request->input('questions', []);
            $resultTextSettingsInput = $request->input('result_text_settings', []);

            // Log initial state before any changes
            $initialRuleGroupsCount = $form->ruleGroups()->count();
            $initialRuleGroups = $form->ruleGroups()->pluck('rule_group_id', 'title')->toArray();

            Log::info('=== FORM UPDATE START ===', [
                'form_id' => $form->id,
                'initial_rule_groups_count' => $initialRuleGroupsCount,
                'initial_rule_groups' => $initialRuleGroups,
                'payload' => [
                    'sections_count' => count($sectionsInput),
                    'answer_templates_count' => count($answerTemplatesInput),
                    'result_rules_count' => count($resultRulesInput),
                    'questions_count' => count($questionsInput),
                    'result_text_settings_count' => count($resultTextSettingsInput),
                    'answer_templates_sample' => array_slice($answerTemplatesInput, 0, 2),
                    'result_rules_sample' => array_slice($resultRulesInput, 0, 2),
                    'result_text_settings_sample' => array_slice($resultTextSettingsInput, 0, 2),
                ],
            ]);

            // Only reset rules if we have actual rule data to sync
            // Check if arrays are not empty AND contain valid data
            $hasAnswerTemplates = !empty($answerTemplatesInput) && $this->arrayHasContent($answerTemplatesInput);
            $hasResultRules = !empty($resultRulesInput) && $this->arrayHasContent($resultRulesInput);
            $hasResultTextSettings = !empty($resultTextSettingsInput) && $this->arrayHasContent($resultTextSettingsInput);

            // IMPORTANT: Only reset rules if answer_templates or result_rules changed
            // result_text_settings does NOT require rule reset because it only affects setting_results table
            $hasRulePayload = $hasAnswerTemplates || $hasResultRules;

            Log::info('Rule payload check', [
                'form_id' => $form->id,
                'has_answer_templates' => $hasAnswerTemplates,
                'has_result_rules' => $hasResultRules,
                'has_result_text_settings' => $hasResultTextSettings,
                'has_rule_payload' => $hasRulePayload,
                'note' => 'result_text_settings does NOT trigger rule reset',
                'array_has_content_check' => [
                    'answer_templates' => $this->arrayHasContent($answerTemplatesInput),
                    'result_rules' => $this->arrayHasContent($resultRulesInput),
                    'result_text_settings' => $this->arrayHasContent($resultTextSettingsInput),
                ],
            ]);

            // Clean and optimize HTML before storing
            $cleanedTitle = $this->cleanHTML($request->title);
            $cleanedDescription = $request->description ? $this->cleanHTML($request->description) : null;
            $form->update([
                'title' => $cleanedTitle,
                'description' => $cleanedDescription,
                'theme_color' => $request->theme_color ?? 'red',
                'collect_email' => $request->boolean('collect_email'),
                'limit_one_response' => $request->boolean('limit_one_response'),
                'show_progress_bar' => $request->boolean('show_progress_bar'),
                'shuffle_questions' => $request->boolean('shuffle_questions'),
            ]);

            if ($hasRulePayload) {
                // Hapus semua aturan form sebelum sinkronisasi ulang
                Log::warning('⚠️ RESETTING FORM RULES - This will delete rule_groups!', [
                    'form_id' => $form->id,
                    'has_answer_templates' => !empty($answerTemplatesInput),
                    'has_result_rules' => !empty($resultRulesInput),
                    'has_result_text_settings' => !empty($resultTextSettingsInput),
                    'rule_groups_before_delete' => $form->ruleGroups()->pluck('rule_group_id', 'title')->toArray(),
                ]);
                $this->resetFormRules($form);

                // Verify deletion
                $ruleGroupsAfterDelete = $form->ruleGroups()->count();
                Log::warning('⚠️ Rule groups after resetFormRules()', [
                    'form_id' => $form->id,
                    'rule_groups_count' => $ruleGroupsAfterDelete,
                ]);
            } else {
                Log::info('✅ Skipping rule reset - no rule payload (answer_templates or result_rules)', [
                    'form_id' => $form->id,
                    'answer_templates_count' => count($answerTemplatesInput),
                    'result_rules_count' => count($resultRulesInput),
                    'result_text_settings_count' => count($resultTextSettingsInput),
                    'note' => 'result_text_settings does NOT trigger rule reset',
                ]);
            }

            // Hapus questions dan sections setelah menghapus dependencies
            $form->questions()->delete();
            $form->sections()->delete();

            Log::info('Calling syncFormRelations()', [
                'form_id' => $form->id,
                'payload_summary' => [
                    'sections_count' => count($sectionsInput),
                    'answer_templates_count' => count($answerTemplatesInput),
                    'result_rules_count' => count($resultRulesInput),
                    'questions_count' => count($questionsInput),
                    'result_text_settings_count' => count($resultTextSettingsInput),
                ],
            ]);

            $this->syncFormRelations($form, [
                'sections' => $sectionsInput,
                'answer_templates' => $answerTemplatesInput,
                'result_rules' => $resultRulesInput,
                'questions' => $questionsInput,
                'result_text_settings' => $resultTextSettingsInput,
            ]);

            // Sync header and text formatting
            $this->syncFormHeader($form, $request->input('header', []));

            // Build question and section ID maps for formatting sync
            // Map by order (index) to database ID
            $questionIdMap = [];
            $form->questions()->orderBy('order')->get()->each(function ($question, $index) use (&$questionIdMap) {
                $questionIdMap[$index] = $question->id;
            });

            $sectionIdMap = [];
            $form->sections()->orderBy('order')->get()->each(function ($section, $index) use (&$sectionIdMap) {
                $sectionIdMap[$index] = $section->id;
            });

            $this->syncTextFormatting($form, $request->input('text_formatting', []), $questionIdMap, $sectionIdMap);

            // Log final state after sync
            $finalRuleGroupsCount = $form->ruleGroups()->count();
            $finalRuleGroups = $form->ruleGroups()->pluck('rule_group_id', 'title')->toArray();

            Log::info('=== FORM UPDATE END ===', [
                'form_id' => $form->id,
                'final_rule_groups_count' => $finalRuleGroupsCount,
                'final_rule_groups' => $finalRuleGroups,
                'rule_groups_lost' => $initialRuleGroupsCount - $finalRuleGroupsCount,
                'initial_vs_final' => [
                    'initial_count' => $initialRuleGroupsCount,
                    'final_count' => $finalRuleGroupsCount,
                ],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Form berhasil diperbarui!',
                'form_id' => $form->id,
                'slug' => $form->slug,
                'share_url' => route('forms.public.show', $form),
                'edit_url' => route('forms.edit', $form),
                'update_url' => route('forms.update', $form),
                'save_method' => 'PUT',
                'form_rules_save_url' => route('forms.rules.store', $form),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui form: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menghapus answer template dari form.
     */
    public function updateRules(Request $request, Form $form)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        $data = $request->validate([
            'answer_templates' => ['required', 'array', 'min:1'],
            'answer_templates.*.answer_text' => ['required', 'string'],
            'answer_templates.*.score' => ['nullable', 'numeric'],
            'answer_templates.*.rule_group_id' => ['nullable', 'string'],
            'result_rules' => ['required', 'array', 'min:1'],
            'result_rules.*.condition_type' => ['required', 'string', 'in:range,equal,greater,less'],
            'result_rules.*.min_score' => ['nullable', 'integer'],
            'result_rules.*.max_score' => ['nullable', 'integer'],
            'result_rules.*.single_score' => ['nullable', 'integer'],
            'result_rules.*.rule_group_id' => ['nullable', 'string'],
            'result_rules.*.texts' => ['required', 'array', 'min:1'],
            'result_rules.*.texts.*' => ['required', 'string'],
            'rule_group_id' => ['nullable', 'string'],
            'rule_group_title' => ['nullable', 'string', 'max:255'],
        ]);

        $preferredGroupId = $data['rule_group_id'] ?? null;
        $ruleGroupId = $this->normalizeRuleGroupId($data, $preferredGroupId);
        unset($data['rule_group_id']);

        $templateOrderBase = null;
        $ruleOrderBase = null;

        if ($preferredGroupId) {
            $deleteContext = $this->deleteRuleGroup($form, $ruleGroupId);
            $templateOrderBase = $deleteContext['template_order_base'];
            $ruleOrderBase = $deleteContext['rule_order_base'];
        }

        try {
            DB::beginTransaction();

            $this->persistFormRules($form, $data, true, $templateOrderBase, $ruleOrderBase, $ruleGroupId);

            // Save or update rule group title
            $ruleGroupTitle = $data['rule_group_title'] ?? null;
            if ($ruleGroupId) {
                $form->ruleGroups()->updateOrCreate(
                    ['rule_group_id' => $ruleGroupId],
                    ['title' => $ruleGroupTitle]
                );
            }

            DB::commit();
        } catch (\Throwable $throwable) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan aturan: ' . $throwable->getMessage(),
            ], 500);
        }

        $templatesBundle = $form->answerTemplates()
            ->where('rule_group_id', $ruleGroupId)
            ->orderBy('order')
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'answer_text' => $template->answer_text,
                    'score' => $template->score,
                    'rule_group_id' => $template->rule_group_id,
                ];
            })
            ->values();

        $resultRulesBundle = $form->resultRules()
            ->where('rule_group_id', $ruleGroupId)
            ->orderBy('order')
            ->with(['texts' => function ($query) {
                $query->orderBy('order');
            }])
            ->get()
            ->map(function ($rule) {
                return [
                    'id' => $rule->id,
                    'condition_type' => $rule->condition_type,
                    'min_score' => $rule->min_score,
                    'max_score' => $rule->max_score,
                    'single_score' => $rule->single_score,
                    'rule_group_id' => $rule->rule_group_id,
                    'texts' => $rule->texts->pluck('result_text')->toArray(),
                ];
            })
            ->values();

        // Get rule group title
        $ruleGroup = $form->ruleGroups()->where('rule_group_id', $ruleGroupId)->first();
        $ruleGroupTitle = $ruleGroup ? $ruleGroup->title : null;

        return response()->json([
            'success' => true,
            'message' => 'Aturan form berhasil disimpan.',
            'rule_group_id' => $ruleGroupId,
            'bundle' => [
                'rule_group_id' => $ruleGroupId,
                'title' => $ruleGroupTitle,
                'templates' => $templatesBundle,
                'result_rules' => $resultRulesBundle,
            ],
            'form_rules_save_url' => route('forms.rules.store', $form),
        ]);
    }

    /**
     * Menghapus answer template dari form.
     */
    public function destroyAnswerTemplate(Form $form, $templateId)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        $template = $form->answerTemplates()->find($templateId);
        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template tidak ditemukan.',
            ], 404);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template berhasil dihapus.',
        ]);
    }

    /**
     * Menghapus result rule dari form.
     */
    public function destroyResultRule(Form $form, $ruleId)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        $rule = $form->resultRules()->find($ruleId);
        if (!$rule) {
            return response()->json([
                'success' => false,
                'message' => 'Aturan tidak ditemukan.',
            ], 404);
        }

        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Aturan berhasil dihapus.',
        ]);
    }

    /**
     * Menghapus form beserta relasi-relasinya.
     */
    public function destroy(Form $form)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            DB::beginTransaction();
            $form->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Form berhasil dihapus.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus form: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menampilkan halaman ringkasan dan detail jawaban.
     */
    public function responses(Form $form)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        $responseData = $this->buildResponseData($form);

        return view('forms.responses', [
            'form' => $form,
            'totalResponses' => $responseData['totalResponses'],
            'questionSummaries' => collect($responseData['questionSummaries']),
            'individualResponses' => collect($responseData['individualResponses']),
        ]);
    }

    /**
     * Memberikan data jawaban untuk tab builder secara asinkron.
     */
    public function responsesData(Form $form)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        $data = $this->buildResponseData($form);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function buildResponseData(Form $form): array
    {
        $form->loadMissing([
            'questions' => function ($query) {
                $query->with(['options' => function ($optionQuery) {
                    $optionQuery->orderBy('order');
                }])->orderBy('order');
            },
        ]);

        $responses = $form->responses()
            ->with(['answers.question', 'answers.questionOption'])
            ->latest()
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
            ];
        })->values()->toArray();

        $individualResponses = $responses->map(function ($response) {
            return [
                'id' => $response->id,
                'submitted_at' => optional($response->created_at)->format('d M Y H:i'),
                'email' => $response->email,
                'total_score' => $response->total_score,
                'result_text' => $response->result_text,
                'answers' => $response->answers->map(function ($answer) {
                    return [
                        'question' => $answer->question->title ?? 'Pertanyaan',
                        'value' => $answer->questionOption->text ?? $answer->answer_text,
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
            'questionSummaries' => $questionSummaries,
            'individualResponses' => $individualResponses,
        ];
    }

    private function responseStats(?Form $form = null, int $questionCount = 0): array
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

    private function makeTemplateLookupKey(array $templateData): ?string
    {
        $answerText = trim($templateData['answer_text'] ?? $templateData['text'] ?? '');
        if ($answerText === '') {
            return null;
        }

        $score = (int) ($templateData['score'] ?? 0);

        return Str::lower($answerText) . '|' . $score;
    }

    private function processQuestionImage(?string $imageValue, Form $form): ?string
    {
        if ($imageValue === null || $imageValue === '') {
            return null;
        }

        // If it's base64 data, process it
        if (str_starts_with($imageValue, 'data:image')) {
            return $this->storeBase64Image($imageValue, $form);
        }

        // If it's already a storage path (starts with "storage/"), return as-is
        if (str_starts_with($imageValue, 'storage/')) {
            return $imageValue;
        }

        // If it's a full URL, extract the storage path
        if (str_starts_with($imageValue, 'http://') || str_starts_with($imageValue, 'https://')) {
            $path = parse_url($imageValue, PHP_URL_PATH);
            if ($path && str_starts_with($path, '/storage/')) {
                return ltrim($path, '/');
            }
        }

        // Otherwise, assume it's a storage path
        return $imageValue;
    }

    private function storeBase64Image(string $base64, Form $form): string
    {
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
            throw new \RuntimeException('Invalid image data.');
        }

        $extension = strtolower($type[1]);
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        if (!in_array($extension, ['jpg', 'png'], true)) {
            $extension = 'jpg';
        }

        $base64 = substr($base64, strpos($base64, ',') + 1);
        $imageData = base64_decode($base64);

        if ($imageData === false) {
            throw new \RuntimeException('Failed to decode image.');
        }

        $image = \imagecreatefromstring($imageData);
        if ($image === false) {
            throw new \RuntimeException('Invalid image contents.');
        }

        $image = $this->resizeImageResource($image);

        ob_start();
        if ($extension === 'png') {
            \imagepng($image, null, 8);
        } else {
            \imagejpeg($image, null, 85);
            $extension = 'jpg';
        }
        $contents = ob_get_clean();
        // imagedestroy() is deprecated in PHP 8.0+ - resources are automatically destroyed

        if ($contents === false) {
            throw new \RuntimeException('Unable to prepare image for storage.');
        }

        $directory = "question-images/{$form->id}";
        $filename = Str::random(40) . '.' . $extension;

        // Ensure directory exists
        Storage::disk('public')->makeDirectory($directory);

        // Store the file
        Storage::disk('public')->put("{$directory}/{$filename}", $contents);

        // Return path relative to public directory (for asset() helper)
        return "storage/{$directory}/{$filename}";
    }

    /**
     * Resize GD image resource while keeping aspect ratio.
     *
     * @param  \GdImage  $image
     */
    private function resizeImageResource($image, int $maxWidth = 1600)
    {
        $width = \imagesx($image);
        $height = \imagesy($image);

        if ($width <= $maxWidth) {
            return $image;
        }

        $ratio = $maxWidth / $width;
        $newWidth = $maxWidth;
        $newHeight = (int) round($height * $ratio);

        $resampled = \imagecreatetruecolor($newWidth, $newHeight);
        \imagealphablending($resampled, false);
        \imagesavealpha($resampled, true);
        \imagecopyresampled($resampled, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        // imagedestroy() is deprecated in PHP 8.0+ - resources are automatically destroyed

        return $resampled;
    }

    /**
     * Sinkronisasi relasi form berdasarkan data dari request.
     */
    private function syncFormRelations(Form $form, array $payload): void
    {
        Log::info('syncFormRelations() - Start', [
            'form_id' => $form->id,
            'payload_keys' => array_keys($payload),
            'payload_counts' => [
                'sections' => count($payload['sections'] ?? []),
                'answer_templates' => count($payload['answer_templates'] ?? []),
                'result_rules' => count($payload['result_rules'] ?? []),
                'questions' => count($payload['questions'] ?? []),
                'result_text_settings' => count($payload['result_text_settings'] ?? []),
            ],
            'rule_groups_before_sync' => $form->ruleGroups()->pluck('rule_group_id', 'title')->toArray(),
        ]);

        $sectionIdMap = [];
        $sections = $payload['sections'] ?? [];

        foreach ($sections as $index => $sectionData) {
            if (!is_array($sectionData)) {
                continue;
            }

            // Clean HTML for section title and description
            $cleanedSectionTitle = $sectionData['title'] ? $this->cleanHTML($sectionData['title']) : null;
            $cleanedSectionDescription = $sectionData['description'] ? $this->cleanHTML($sectionData['description']) : null;

            $section = $form->sections()->create([
                'title' => $cleanedSectionTitle,
                'description' => $cleanedSectionDescription,
                'image' => $this->processQuestionImage($sectionData['image'] ?? null, $form),
                'image_alignment' => $sectionData['image_alignment'] ?? 'center',
                'image_wrap_mode' => $sectionData['image_wrap_mode'] ?? 'fixed',
                'order' => $index,
            ]);

            $sectionIdMap[$index] = $section->id;
        }

        // Only persist rules if we have actual rule data
        // This prevents accidental deletion of rule_groups when updating form without rule changes
        $hasRuleData = (!empty($payload['answer_templates']) && $this->arrayHasContent($payload['answer_templates']))
            || (!empty($payload['result_rules']) && $this->arrayHasContent($payload['result_rules']));

        Log::info('syncFormRelations() - Rule data check', [
            'form_id' => $form->id,
            'has_rule_data' => $hasRuleData,
            'answer_templates_empty' => empty($payload['answer_templates']),
            'answer_templates_has_content' => $this->arrayHasContent($payload['answer_templates'] ?? []),
            'result_rules_empty' => empty($payload['result_rules']),
            'result_rules_has_content' => $this->arrayHasContent($payload['result_rules'] ?? []),
        ]);

        $rulesContext = [];
        if ($hasRuleData) {
            Log::info('syncFormRelations() - Calling persistFormRules()', ['form_id' => $form->id]);
            $rulesContext = $this->persistFormRules($form, $payload);

            // After persisting rules, ensure rule_groups exist for all rule_group_id values
            // This is important because rule_groups might have been deleted by resetFormRules()
            $this->ensureRuleGroupsExist($form);
        } else {
            Log::info('syncFormRelations() - Skipping persistFormRules() - no rule data', ['form_id' => $form->id]);
            // Return empty maps to prevent errors in question creation
            $rulesContext = [
                'answer_template_id_map' => [],
                'answer_template_lookup' => [],
                'next_template_order' => (int) ($form->answerTemplates()->max('order') ?? -1) + 1,
                'result_rule_id_map' => [],
            ];
        }

        $answerTemplateIdMap = $rulesContext['answer_template_id_map'];
        $answerTemplateLookup = $rulesContext['answer_template_lookup'];
        $nextTemplateOrder = $rulesContext['next_template_order'];
        $resultRuleIdMap = $rulesContext['result_rule_id_map'];

        $questions = $payload['questions'] ?? [];

        foreach ($questions as $index => $questionData) {
            if (!is_array($questionData)) {
                continue;
            }

            $title = trim($questionData['title'] ?? '');
            $sectionId = null;
            if (isset($questionData['section_id']) && array_key_exists($questionData['section_id'], $sectionIdMap)) {
                $sectionId = $sectionIdMap[$questionData['section_id']];
            }

            // Clean HTML for question title - remove nested spans and empty tags
            Log::info('syncFormRelations() - Cleaning question title HTML', [
                'form_id' => $form->id,
                'question_index' => $index,
                'original_title' => $title,
                'original_title_length' => strlen($title),
            ]);
            $cleanedTitle = $this->cleanHTML($title);
            Log::info('syncFormRelations() - Cleaned question title HTML', [
                'form_id' => $form->id,
                'question_index' => $index,
                'cleaned_title' => $cleanedTitle,
                'cleaned_title_length' => strlen($cleanedTitle),
            ]);
            // If cleaned title is empty after cleaning, use plain text as fallback
            if (empty(strip_tags($cleanedTitle))) {
                $cleanedTitle = strip_tags($title);
            }
            $question = $form->questions()->create([
                'section_id' => $sectionId,
                'type' => $questionData['type'] ?? 'short-answer',
                'title' => $cleanedTitle !== '' ? $cleanedTitle : 'Pertanyaan tanpa judul',
                'description' => $questionData['description'] ?? null,
                'image' => $this->processQuestionImage($questionData['image'] ?? null, $form),
                'image_alignment' => $questionData['image_alignment'] ?? 'center',
                'image_width' => isset($questionData['image_width'])
                    ? (int) $questionData['image_width']
                    : null,
                'is_required' => (bool) ($questionData['is_required'] ?? false),
                'order' => $index,
            ]);

            $savedRuleTemplateIds = [];
            if (!empty($questionData['saved_rule']['templates']) && is_array($questionData['saved_rule']['templates'])) {
                $savedRuleGroupId = data_get($questionData, 'saved_rule.rule_group_id')
                    ?? data_get($questionData, 'saved_rule.id')
                    ?? (string) Str::uuid();

                foreach ($questionData['saved_rule']['templates'] as $templateIndex => $templateData) {
                    $templateKey = $this->makeTemplateLookupKey($templateData);
                    if (!$templateKey) {
                        continue;
                    }

                    if (!isset($answerTemplateLookup[$templateKey])) {
                        $template = $form->answerTemplates()->create([
                            'answer_text' => $templateData['answer_text'] ?? $templateData['text'] ?? 'Jawaban',
                            'score' => $templateData['score'] ?? 0,
                            'order' => $nextTemplateOrder++,
                            'rule_group_id' => $templateData['rule_group_id'] ?? $savedRuleGroupId,
                        ]);

                        $answerTemplateLookup[$templateKey] = $template->id;
                    }

                    $savedRuleTemplateIds[$templateIndex] = $answerTemplateLookup[$templateKey];
                }
            }

            foreach ($questionData['options'] ?? [] as $optIndex => $optionData) {
                if (!is_array($optionData)) {
                    continue;
                }

                $optionText = trim($optionData['text'] ?? '');
                if ($optionText === '') {
                    continue;
                }

                $answerTemplateId = null;
                $templateIndexReference = $optionData['answer_template_index'] ?? $optionData['answer_template_id'] ?? null;
                if ($templateIndexReference !== null && array_key_exists($templateIndexReference, $answerTemplateIdMap)) {
                    $answerTemplateId = $answerTemplateIdMap[$templateIndexReference];
                } elseif (array_key_exists($optIndex, $savedRuleTemplateIds)) {
                    $answerTemplateId = $savedRuleTemplateIds[$optIndex];
                }

                $question->options()->create([
                    'answer_template_id' => $answerTemplateId,
                    'text' => $optionText,
                    'order' => $optIndex,
                ]);
            }
        }

        // Only sync setting results if we have valid data
        // This prevents accidental deletion of rule_groups when updating form without changing result settings
        $hasResultTextSettings = !empty($payload['result_text_settings']) && $this->arrayHasContent($payload['result_text_settings']);
        if ($hasResultTextSettings) {
            Log::info('syncFormRelations() - Calling syncSettingResults()', ['form_id' => $form->id]);
            $this->syncSettingResults($form, $payload);
        } else {
            Log::info('syncFormRelations() - Skipping syncSettingResults() - no valid payload', [
                'form_id' => $form->id,
                'result_text_settings_count' => count($payload['result_text_settings'] ?? []),
            ]);
        }

        // Log final state after sync
        $ruleGroupsAfterSync = $form->ruleGroups()->pluck('rule_group_id', 'title')->toArray();
        Log::info('syncFormRelations() - End', [
            'form_id' => $form->id,
            'rule_groups_after_sync' => $ruleGroupsAfterSync,
            'rule_groups_count_after_sync' => count($ruleGroupsAfterSync),
        ]);
    }

    /**
     * Clean and optimize HTML by removing unnecessary nested spans and empty tags
     */
    private function cleanHTML($html): string
    {
        if (empty($html)) {
            return '';
        }

        try {
            // Use DOMDocument to parse and clean HTML
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);

            // Wrap in a container div to handle fragments
            $wrappedHtml = '<div>' . $html . '</div>';
            $dom->loadHTML('<?xml encoding="UTF-8">' . $wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            // Remove empty tags recursively
            $this->removeEmptyNodes($dom);

            // Flatten nested spans with same style attributes (run multiple times to handle deeply nested)
            for ($i = 0; $i < 5; $i++) {
                $this->flattenNestedSpans($dom);
            }

            // Get cleaned HTML from the wrapper div
            $wrapper = $dom->getElementsByTagName('div')->item(0);
            if ($wrapper) {
                $cleaned = '';
                foreach ($wrapper->childNodes as $child) {
                    $cleaned .= $dom->saveHTML($child);
                }
                $result = trim($cleaned);
                Log::info('cleanHTML() - Result', [
                    'original' => $html,
                    'cleaned' => $result,
                    'original_length' => strlen($html),
                    'cleaned_length' => strlen($result),
                ]);
                return $result;
            }
        } catch (\Exception $e) {
            // If parsing fails, return original HTML (it will be stored as-is)
            Log::warning('Failed to clean HTML', ['error' => $e->getMessage()]);
        }

        return $html;
    }

    /**
     * Flatten nested spans - remove unnecessary nesting
     */
    private function flattenNestedSpans(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        $spans = $xpath->query('//span');

        // Process in reverse order to avoid issues when removing nodes
        for ($i = $spans->length - 1; $i >= 0; $i--) {
            $span = $spans->item($i);
            if (!$span || !($span instanceof \DOMElement) || !$span->parentNode) {
                continue;
            }

            $style = $span->getAttribute('style');
            $textContent = trim($span->textContent);

            // Remove empty spans
            if (empty($style) && empty($textContent)) {
                while ($span->firstChild) {
                    $span->parentNode->insertBefore($span->firstChild, $span);
                }
                $span->parentNode->removeChild($span);
                continue;
            }

            // Handle nested spans
            if ($span->parentNode instanceof \DOMElement && $span->parentNode->nodeName === 'span') {
                $parentSpan = $span->parentNode;
                $parentStyle = $parentSpan->getAttribute('style');

                // If same style, remove inner span
                if ($parentStyle === $style) {
                    while ($span->firstChild) {
                        $parentSpan->insertBefore($span->firstChild, $span);
                    }
                    $parentSpan->removeChild($span);
                }
                // If different styles, merge styles into inner span and remove parent
                elseif (!empty($style) && !empty($parentStyle)) {
                    // Merge parent style into inner span (inner style takes precedence)
                    $mergedStyle = $this->mergeStyles($parentStyle, $style);
                    $span->setAttribute('style', $mergedStyle);

                    // Move span out of parent
                    $parentSpan->parentNode->insertBefore($span, $parentSpan);

                    // Move parent's other children after span
                    while ($parentSpan->firstChild) {
                        $span->parentNode->insertBefore($parentSpan->firstChild, $span->nextSibling);
                    }

                    // Remove empty parent
                    $parentSpan->parentNode->removeChild($parentSpan);
                }
                // If parent has no style, just remove parent
                elseif (empty($parentStyle)) {
                    while ($span->firstChild) {
                        $parentSpan->insertBefore($span->firstChild, $span);
                    }
                    $parentSpan->parentNode->insertBefore($span, $parentSpan);
                    while ($parentSpan->firstChild) {
                        $span->parentNode->insertBefore($parentSpan->firstChild, $span->nextSibling);
                    }
                    $parentSpan->parentNode->removeChild($parentSpan);
                }
                // If inner span has no style but parent has, remove inner span
                elseif (empty($style) && !empty($parentStyle)) {
                    while ($span->firstChild) {
                        $parentSpan->insertBefore($span->firstChild, $span);
                    }
                    $parentSpan->removeChild($span);
                }
            }
        }
    }

    /**
     * Merge two CSS style strings, with inner style taking precedence
     */
    private function mergeStyles(string $parentStyle, string $innerStyle): string
    {
        // Parse both styles into arrays
        $parentStyles = [];
        foreach (explode(';', $parentStyle) as $rule) {
            $rule = trim($rule);
            if (empty($rule)) continue;
            $parts = explode(':', $rule, 2);
            if (count($parts) === 2) {
                $parentStyles[trim($parts[0])] = trim($parts[1]);
            }
        }

        $innerStyles = [];
        foreach (explode(';', $innerStyle) as $rule) {
            $rule = trim($rule);
            if (empty($rule)) continue;
            $parts = explode(':', $rule, 2);
            if (count($parts) === 2) {
                $innerStyles[trim($parts[0])] = trim($parts[1]);
            }
        }

        // Merge: inner style takes precedence
        $merged = array_merge($parentStyles, $innerStyles);

        // Convert back to style string
        $styleParts = [];
        foreach ($merged as $property => $value) {
            $styleParts[] = $property . ': ' . $value;
        }

        return implode('; ', $styleParts);
    }

    /**
     * Recursively remove empty nodes from DOM
     */
    private function removeEmptyNodes(\DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $this->removeEmptyNodes($child);

                // Remove if empty (no text content and no children)
                $textContent = trim($child->textContent);
                if (empty($textContent) && $child->childNodes->length === 0) {
                    $child->parentNode->removeChild($child);
                }
            }
        }
    }

    /**
     * Menyusun data awal untuk form builder ketika mode edit.
     */
    private function prepareFormBuilderData(Form $form): array
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

    private function buildSavedRules(Form $form): array
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
                    'texts' => $rule->texts->sortBy('order')->pluck('result_text')->toArray(),
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

    private function persistFormRules(
        Form $form,
        array $payload,
        bool $append = false,
        ?int $templateOrderOverride = null,
        ?int $ruleOrderOverride = null,
        ?string $ruleGroupId = null
    ): array {
        Log::info('persistFormRules() - Start', [
            'form_id' => $form->id,
            'append' => $append,
            'rule_group_id' => $ruleGroupId,
            'answer_templates_count' => count($payload['answer_templates'] ?? []),
            'result_rules_count' => count($payload['result_rules'] ?? []),
            'rule_groups_before' => $form->ruleGroups()->pluck('rule_group_id', 'title')->toArray(),
        ]);

        $answerTemplateIdMap = [];
        $answerTemplateLookup = [];
        $answerTemplates = $payload['answer_templates'] ?? [];

        if ($templateOrderOverride !== null) {
            $templateOrderBase = $templateOrderOverride;
        } elseif ($append) {
            $templateOrderBase = ((int) ($form->answerTemplates()->max('order') ?? -1) + 1);
        } else {
            $templateOrderBase = 0;
        }

        if ($ruleOrderOverride !== null) {
            $ruleOrderBase = $ruleOrderOverride;
        } elseif ($append) {
            $ruleOrderBase = ((int) ($form->resultRules()->max('order') ?? -1) + 1);
        } else {
            $ruleOrderBase = 0;
        }

        foreach ($answerTemplates as $index => $templateData) {
            if (!is_array($templateData)) {
                continue;
            }

            $answerText = trim($templateData['answer_text'] ?? '');
            if ($answerText === '') {
                continue;
            }

            // Use provided ruleGroupId if available, otherwise use from payload or generate new UUID
            $templateRuleGroupId = $ruleGroupId
                ?? $templateData['rule_group_id']
                ?? (string) Str::uuid();

            $template = $form->answerTemplates()->create([
                'form_id' => $form->id,
                'answer_text' => $answerText,
                'score' => $templateData['score'] ?? 0,
                'order' => $templateOrderBase + $index,
                'rule_group_id' => $templateRuleGroupId,
            ]);

            $answerTemplateIdMap[$index] = $template->id;
            $templateKey = $this->makeTemplateLookupKey($templateData);
            if ($templateKey) {
                $answerTemplateLookup[$templateKey] = $template->id;
            }
        }

        $nextTemplateOrder = $templateOrderBase + count($answerTemplateLookup);

        $resultRules = $payload['result_rules'] ?? [];
        $resultRuleIdMap = [];

        foreach ($resultRules as $index => $ruleData) {
            if (!is_array($ruleData)) {
                continue;
            }

            // Use provided ruleGroupId if available, otherwise use from payload or generate new UUID
            $ruleRuleGroupId = $ruleGroupId
                ?? $ruleData['rule_group_id']
                ?? (string) Str::uuid();

            $rule = $form->resultRules()->create([
                'form_id' => $form->id,
                'condition_type' => $ruleData['condition_type'] ?? 'range',
                'min_score' => $ruleData['min_score'] ?? null,
                'max_score' => $ruleData['max_score'] ?? null,
                'single_score' => $ruleData['single_score'] ?? null,
                'order' => $ruleOrderBase + $index,
                'rule_group_id' => $ruleRuleGroupId,
            ]);

            $resultRuleIdMap[$index] = $rule->id;

            foreach ($ruleData['texts'] ?? [] as $textIndex => $text) {
                $textValue = trim($text ?? '');
                if ($textValue === '') {
                    continue;
                }

                $rule->texts()->create([
                    'result_text' => $textValue,
                    'order' => $textIndex,
                    'rule_group_id' => $ruleRuleGroupId,
                ]);
            }
        }

        // Log final state after persist
        $ruleGroupsAfterPersist = $form->ruleGroups()->pluck('rule_group_id', 'title')->toArray();
        Log::info('persistFormRules() - End', [
            'form_id' => $form->id,
            'templates_created' => count($answerTemplateIdMap),
            'rules_created' => count($resultRuleIdMap),
            'rule_groups_after_persist' => $ruleGroupsAfterPersist,
            'rule_groups_count_after_persist' => count($ruleGroupsAfterPersist),
        ]);

        return [
            'answer_template_id_map' => $answerTemplateIdMap,
            'answer_template_lookup' => $answerTemplateLookup,
            'next_template_order' => $nextTemplateOrder,
            'result_rule_id_map' => $resultRuleIdMap,
        ];
    }

    private function resetFormRules(Form $form): void
    {
        // Log what will be deleted
        $ruleGroupsToDelete = DB::table('rule_groups')->where('form_id', $form->id)->get();
        $answerTemplatesToDelete = DB::table('answer_templates')->where('form_id', $form->id)->count();
        $resultRulesToDelete = DB::table('result_rules')->where('form_id', $form->id)->count();

        Log::warning('🗑️ resetFormRules() - Deleting data', [
            'form_id' => $form->id,
            'rule_groups_to_delete' => $ruleGroupsToDelete->pluck('rule_group_id', 'title')->toArray(),
            'rule_groups_count' => $ruleGroupsToDelete->count(),
            'answer_templates_count' => $answerTemplatesToDelete,
            'result_rules_count' => $resultRulesToDelete,
        ]);

        DB::table('result_rule_texts')
            ->whereIn('result_rule_id', function ($query) use ($form) {
                $query->select('id')
                    ->from('result_rules')
                    ->where('form_id', $form->id);
            })
            ->delete();

        DB::table('answer_templates')->where('form_id', $form->id)->delete();
        DB::table('result_rules')->where('form_id', $form->id)->delete();
        DB::table('rule_groups')->where('form_id', $form->id)->delete();

        // Verify deletion
        $ruleGroupsAfter = DB::table('rule_groups')->where('form_id', $form->id)->count();
        Log::warning('🗑️ resetFormRules() - After deletion', [
            'form_id' => $form->id,
            'rule_groups_count_after' => $ruleGroupsAfter,
        ]);
    }

    private function deleteRuleGroup(Form $form, string $ruleGroupId): array
    {
        $templateQuery = $form->answerTemplates();
        $templateQuery = $ruleGroupId === null
            ? $templateQuery->whereNull('rule_group_id')
            : $templateQuery->where('rule_group_id', $ruleGroupId);
        $templateOrderBase = (clone $templateQuery)->min('order');

        $rulesQuery = $form->resultRules();
        $rulesQuery = $ruleGroupId === null
            ? $rulesQuery->whereNull('rule_group_id')
            : $rulesQuery->where('rule_group_id', $ruleGroupId);
        $ruleOrderBase = (clone $rulesQuery)->min('order');
        $ruleIds = (clone $rulesQuery)->pluck('id');

        if ($ruleIds->isNotEmpty()) {
            DB::table('result_rule_texts')
                ->whereIn('result_rule_id', $ruleIds)
                ->delete();
        }

        $rulesQuery->delete();

        $templateQuery->delete();

        return [
            'template_order_base' => $templateOrderBase,
            'rule_order_base' => $ruleOrderBase,
        ];
    }

    /**
     * Hapus hanya setting_results untuk satu rule_group_id (tanpa menghapus rule/rule_groups).
     */
    public function destroySettingResultsByGroup(Request $request, Form $form, string $ruleGroupId)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        // Hanya hapus entries setting_results milik form & rule_group_id terkait
        $deleted = SettingResult::where('form_id', $form->id)
            ->where('rule_group_id', $ruleGroupId)
            ->delete();

        Log::info('destroySettingResultsByGroup()', [
            'form_id' => $form->id,
            'rule_group_id' => $ruleGroupId,
            'deleted_count' => $deleted,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Setup hasil dihapus (setting_results) tanpa menghapus aturan.',
            'deleted' => $deleted,
        ]);
    }

    /**
     * Sync result text settings (new structure with title and image per text)
     */
    private function syncSettingResults(Form $form, array $payload): void
    {
        $resultTextSettings = $payload['result_text_settings'] ?? [];

        Log::info('SyncSettingResults called', [
            'form_id' => $form->id,
            'result_text_settings_count' => count($resultTextSettings),
            'result_text_settings' => $resultTextSettings,
        ]);

        // Only remove existing settings if we have new data to sync
        // This prevents accidental deletion when updating form without changing result settings
        if (!empty($resultTextSettings) && $this->arrayHasContent($resultTextSettings)) {
            SettingResult::where('form_id', $form->id)->delete();
        } else {
            Log::info('Skipping SettingResult deletion - no valid payload', [
                'form_id' => $form->id,
                'result_text_settings_count' => count($resultTextSettings),
            ]);
            return; // Exit early if no data to sync
        }

        // Refresh form to ensure rules are loaded from database
        $form->refresh();
        $form->load(['resultRules.texts', 'ruleGroups']);

        foreach ($resultTextSettings as $index => $settingData) {
            if (!is_array($settingData)) {
                Log::warning('Invalid setting data (not array)', ['setting_data' => $settingData]);
                continue;
            }

            $ruleGroupId = $settingData['rule_group_id'] ?? null;
            if (!$ruleGroupId) {
                Log::warning('Missing rule_group_id in setting data', ['setting_data' => $settingData]);
                continue;
            }

            $textAlignment = $settingData['text_alignment'] ?? 'center';
            $imageAlignment = $settingData['image_alignment'] ?? 'center';
            $cardOrder = isset($settingData['card_order'])
                ? (int) $settingData['card_order']
                : $index;
            $cardImagePath = $this->processQuestionImage($settingData['card_image'] ?? null, $form);

            // Get all result_rule_texts for this rule_group_id (fresh from database)
            $resultRules = $form->resultRules()
                ->where('rule_group_id', $ruleGroupId)
                ->with('texts')
                ->get();
            $ruleGroupTitle = optional($form->ruleGroups->firstWhere('rule_group_id', $ruleGroupId))->title
                ?? ($settingData['card_title'] ?? null);

            Log::info('Found rules for rule_group_id', [
                'rule_group_id' => $ruleGroupId,
                'rules_count' => $resultRules->count(),
                'rule_ids' => $resultRules->pluck('id')->toArray(),
            ]);

            if ($resultRules->isEmpty()) {
                Log::warning('No rules found for rule_group_id', [
                    'rule_group_id' => $ruleGroupId,
                    'form_id' => $form->id,
                    'all_rule_groups' => $form->resultRules()->distinct()->pluck('rule_group_id')->toArray(),
                ]);
                continue;
            }

            // Create a map of temp_id or order to result_rule_text_id
            $textSettings = $settingData['text_settings'] ?? [];
            if (empty($textSettings)) {
                continue;
            }

            // Collect all texts from all rules in this group, ordered by rule order then text order
            $allTexts = [];
            foreach ($resultRules->sortBy('order') as $rule) {
                foreach ($rule->texts->sortBy('order') as $text) {
                    $allTexts[] = $text;
                }
            }

            Log::info('Matching texts with settings', [
                'rule_group_id' => $ruleGroupId,
                'all_texts_count' => count($allTexts),
                'text_settings_count' => count($textSettings),
                'all_texts' => array_map(function ($t) {
                    return ['id' => $t->id, 'order' => $t->order, 'text' => substr($t->result_text, 0, 50)];
                }, $allTexts),
                'text_settings' => $textSettings,
            ]);

            // Match by order first (most reliable when result_rule_text_id is null)
            foreach ($textSettings as $settingIndex => $ts) {
                if (!is_array($ts)) {
                    continue;
                }

                $textIndex = isset($ts['order']) ? (int) $ts['order'] : $settingIndex;

                // Find text by order position
                if (isset($allTexts[$textIndex])) {
                    $text = $allTexts[$textIndex];

                    SettingResult::create([
                        'form_id' => $form->id,
                        'rule_group_id' => $ruleGroupId,
                        'result_rule_text_id' => $text->id,
                        'card_title' => $ruleGroupTitle,
                        'title' => $ts['title'] ?? null,
                        'image' => $this->processQuestionImage($ts['image'] ?? null, $form),
                        'card_image' => $cardImagePath,
                        'image_alignment' => $imageAlignment,
                        'text_alignment' => $textAlignment,
                        'order' => $ts['order'] ?? $text->order ?? $textIndex,
                        'card_order' => $cardOrder,
                    ]);

                    Log::info('Created SettingResult', [
                        'form_id' => $form->id,
                        'rule_group_id' => $ruleGroupId,
                        'result_rule_text_id' => $text->id,
                        'order' => $ts['order'] ?? $text->order ?? $textIndex,
                    ]);
                } else {
                    Log::warning('Text not found for setting', [
                        'text_index' => $textIndex,
                        'all_texts_count' => count($allTexts),
                        'setting' => $ts,
                    ]);
                }
            }

            // Also create settings for any texts that weren't matched (use default values)
            foreach ($allTexts as $textIndex => $text) {
                $alreadyMatched = SettingResult::where('form_id', $form->id)
                    ->where('result_rule_text_id', $text->id)
                    ->exists();

                if (!$alreadyMatched) {
                    SettingResult::create([
                        'form_id' => $form->id,
                        'rule_group_id' => $ruleGroupId,
                        'result_rule_text_id' => $text->id,
                        'card_title' => $ruleGroupTitle,
                        'title' => null,
                        'image' => null,
                        'card_image' => $cardImagePath,
                        'image_alignment' => $imageAlignment,
                        'text_alignment' => $textAlignment,
                        'order' => $text->order ?? $textIndex,
                        'card_order' => $cardOrder,
                    ]);

                    Log::info('Created SettingResult (default)', [
                        'form_id' => $form->id,
                        'rule_group_id' => $ruleGroupId,
                        'result_rule_text_id' => $text->id,
                    ]);
                }
            }
        }
    }

    private function normalizeRuleGroupId(array &$data, ?string $preferredGroupId = null): string
    {
        $ruleGroupId = $preferredGroupId
            ?? $data['answer_templates'][0]['rule_group_id']
            ?? $data['result_rules'][0]['rule_group_id']
            ?? (string) Str::uuid();

        // Force all templates and rules to use the same rule_group_id for consistency
        $data['answer_templates'] = array_map(function ($template) use ($ruleGroupId) {
            $template['rule_group_id'] = $ruleGroupId;
            return $template;
        }, $data['answer_templates'] ?? []);

        $data['result_rules'] = array_map(function ($rule) use ($ruleGroupId) {
            $rule['rule_group_id'] = $ruleGroupId;
            return $rule;
        }, $data['result_rules'] ?? []);

        return $ruleGroupId;
    }

    /**
     * Ensure rule_groups exist for all rule_group_id values in answer_templates and result_rules.
     * This is important after resetFormRules() to recreate rule_groups that were deleted.
     */
    private function ensureRuleGroupsExist(Form $form): void
    {
        // Get all unique rule_group_id from answer_templates and result_rules
        $ruleGroupIds = $form->answerTemplates()
            ->whereNotNull('rule_group_id')
            ->distinct()
            ->pluck('rule_group_id')
            ->merge(
                $form->resultRules()
                    ->whereNotNull('rule_group_id')
                    ->distinct()
                    ->pluck('rule_group_id')
            )
            ->unique()
            ->filter();

        if ($ruleGroupIds->isEmpty()) {
            Log::info('ensureRuleGroupsExist() - No rule_group_id found', ['form_id' => $form->id]);
            return;
        }

        // Get existing rule_groups
        $existingRuleGroups = $form->ruleGroups()
            ->whereIn('rule_group_id', $ruleGroupIds)
            ->pluck('rule_group_id')
            ->toArray();

        // Map fallback titles from setting_results (card_title)
        $fallbackTitles = SettingResult::where('form_id', $form->id)
            ->whereNotNull('rule_group_id')
            ->pluck('card_title', 'rule_group_id')
            ->filter(function ($title) {
                return !empty($title);
            })
            ->toArray();

        // Create missing rule_groups
        $created = 0;
        foreach ($ruleGroupIds as $ruleGroupId) {
            if (!in_array($ruleGroupId, $existingRuleGroups)) {
                // Prefer fallback title from setting_results, otherwise auto-generate
                $existingCount = $form->ruleGroups()->count();
                $title = $fallbackTitles[$ruleGroupId] ?? ('Aturan ' . ($existingCount + 1));

                $form->ruleGroups()->create([
                    'rule_group_id' => $ruleGroupId,
                    'title' => $title,
                ]);

                $created++;
                Log::info('ensureRuleGroupsExist() - Created rule_group', [
                    'form_id' => $form->id,
                    'rule_group_id' => $ruleGroupId,
                    'title' => $title,
                ]);
            }
        }

        // Update existing rule_groups that miss titles but have fallbacks
        if (!empty($fallbackTitles)) {
            foreach ($fallbackTitles as $ruleGroupId => $cardTitle) {
                if (!$cardTitle) {
                    continue;
                }

                $form->ruleGroups()
                    ->where('rule_group_id', $ruleGroupId)
                    ->where(function ($query) {
                        $query->whereNull('title')->orWhere('title', '');
                    })
                    ->update(['title' => $cardTitle]);
            }
        }

        if ($created > 0) {
            Log::info('ensureRuleGroupsExist() - Summary', [
                'form_id' => $form->id,
                'created' => $created,
                'total_rule_group_ids' => $ruleGroupIds->count(),
            ]);
        }
    }

    private function arrayHasContent($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->arrayHasContent($item)) {
                    return true;
                }
            }
            return false;
        }

        return !is_null($value) && $value !== '';
    }

    /**
     * Sync form header data
     */
    private function syncFormHeader(Form $form, array $headerData): void
    {
        if (empty($headerData) || !isset($headerData['image_path'])) {
            // If no header data, delete existing header
            $form->header()?->delete();
            return;
        }

        $form->header()->updateOrCreate(
            ['form_id' => $form->id],
            [
                'image_path' => $headerData['image_path'] ?? null,
                'image_mode' => $headerData['image_mode'] ?? 'cover',
                'source' => $headerData['source'] ?? null,
            ]
        );
    }

    /**
     * Sync text formatting data
     */
    private function syncTextFormatting(Form $form, array $formattingData, array $questionIdMap = [], array $sectionIdMap = []): void
    {
        if (empty($formattingData)) {
            Log::info('syncTextFormatting() - No formatting data to sync', ['form_id' => $form->id]);
            return;
        }

        Log::info('syncTextFormatting() - Start', [
            'form_id' => $form->id,
            'formatting_count' => count($formattingData),
            'question_id_map' => $questionIdMap,
            'question_id_map_keys' => array_keys($questionIdMap),
            'question_id_map_values' => array_values($questionIdMap),
            'section_id_map' => $sectionIdMap,
            'formatting_data' => $formattingData,
        ]);

        // Delete old formatting entries for questions that no longer exist
        // Get all current question IDs
        $currentQuestionIds = array_values($questionIdMap);
        if (!empty($currentQuestionIds)) {
            // Delete formatting for questions that don't exist in current form
            $deletedCount = FormTextFormatting::where('form_id', $form->id)
                ->where('element_type', 'question_title')
                ->whereNotIn('question_id', $currentQuestionIds)
                ->delete();
            if ($deletedCount > 0) {
                Log::info('syncTextFormatting() - Deleted old question formatting entries', [
                    'form_id' => $form->id,
                    'deleted_count' => $deletedCount,
                ]);
            }
        } else {
            // If no questions, delete all question formatting for this form
            $deletedCount = FormTextFormatting::where('form_id', $form->id)
                ->where('element_type', 'question_title')
                ->delete();
            if ($deletedCount > 0) {
                Log::info('syncTextFormatting() - Deleted all question formatting entries (no questions)', [
                    'form_id' => $form->id,
                    'deleted_count' => $deletedCount,
                ]);
            }
        }

        // Delete old formatting entries for sections that no longer exist
        $currentSectionIds = array_values($sectionIdMap);
        if (!empty($currentSectionIds)) {
            $deletedSectionCount = FormTextFormatting::where('form_id', $form->id)
                ->whereIn('element_type', ['section_title', 'section_description'])
                ->whereNotIn('section_id', $currentSectionIds)
                ->delete();
            if ($deletedSectionCount > 0) {
                Log::info('syncTextFormatting() - Deleted old section formatting entries', [
                    'form_id' => $form->id,
                    'deleted_count' => $deletedSectionCount,
                ]);
            }
        } else {
            $deletedSectionCount = FormTextFormatting::where('form_id', $form->id)
                ->whereIn('element_type', ['section_title', 'section_description'])
                ->delete();
            if ($deletedSectionCount > 0) {
                Log::info('syncTextFormatting() - Deleted all section formatting entries (no sections)', [
                    'form_id' => $form->id,
                    'deleted_count' => $deletedSectionCount,
                ]);
            }
        }

        foreach ($formattingData as $formatting) {
            $elementType = $formatting['element_type'] ?? null;
            if (!$elementType) {
                Log::warning('syncTextFormatting() - Skipping formatting without element_type', ['formatting' => $formatting]);
                continue;
            }

            $data = [
                'element_type' => $elementType,
                'text_align' => $formatting['text_align'] ?? 'left',
                'font_family' => $formatting['font_family'] ?? 'Arial',
                'font_size' => (int) ($formatting['font_size'] ?? 12),
                'font_weight' => $formatting['font_weight'] ?? 'normal',
                'font_style' => $formatting['font_style'] ?? 'normal',
                'text_decoration' => $formatting['text_decoration'] ?? 'none',
            ];

            // Determine which foreign key to use based on element_type
            if (in_array($elementType, ['form_title', 'form_description'])) {
                FormTextFormatting::updateOrCreate(
                    [
                        'form_id' => $form->id,
                        'element_type' => $elementType,
                    ],
                    array_merge($data, ['form_id' => $form->id])
                );
            } elseif ($elementType === 'question_title') {
                // Map question_id from frontend index to database ID
                $questionIndex = $formatting['question_id'] ?? null;
                $questionId = null;

                Log::info('syncTextFormatting() - Processing question_title', [
                    'form_id' => $form->id,
                    'question_index' => $questionIndex,
                    'question_id_map' => $questionIdMap,
                ]);

                if ($questionIndex !== null) {
                    // First, try to map from questionIdMap (index-based mapping)
                    if (isset($questionIdMap[$questionIndex])) {
                        $questionId = $questionIdMap[$questionIndex];
                        Log::info('syncTextFormatting() - Mapped question_id from questionIdMap', [
                            'form_id' => $form->id,
                            'question_index' => $questionIndex,
                            'question_id' => $questionId,
                        ]);
                    } elseif (is_numeric($questionIndex)) {
                        // If it's numeric, check if it exists in database
                        $questionId = (int) $questionIndex;
                        $questionExists = $form->questions()->where('id', $questionId)->exists();
                        if (!$questionExists) {
                            // If question doesn't exist, try to find by order
                            $question = $form->questions()->where('order', $questionIndex)->first();
                            $questionId = $question ? $question->id : null;
                            Log::info('syncTextFormatting() - Found question by order', [
                                'form_id' => $form->id,
                                'question_index' => $questionIndex,
                                'question_id' => $questionId,
                            ]);
                        }
                    }
                }

                // Only save if we have a valid question_id that exists in database
                if ($questionId && $form->questions()->where('id', $questionId)->exists()) {
                    FormTextFormatting::updateOrCreate(
                        [
                            'question_id' => $questionId,
                            'element_type' => $elementType,
                        ],
                        array_merge($data, ['question_id' => $questionId])
                    );
                    Log::info('syncTextFormatting() - Saved question_title formatting', [
                        'form_id' => $form->id,
                        'question_id' => $questionId,
                        'formatting' => $data,
                    ]);
                } else {
                    Log::warning('syncTextFormatting() - Cannot save question_title formatting - invalid question_id', [
                        'form_id' => $form->id,
                        'question_index' => $questionIndex,
                        'question_id' => $questionId,
                        'question_exists' => $questionId ? $form->questions()->where('id', $questionId)->exists() : false,
                    ]);
                }
            } elseif (in_array($elementType, ['section_title', 'section_description'])) {
                // Map section_id from frontend index to database ID
                $sectionIndex = $formatting['section_index'] ?? $formatting['section_id'] ?? null;
                $sectionId = null;

                if ($sectionIndex !== null) {
                    // First, try to map from sectionIdMap (index-based mapping)
                    if (isset($sectionIdMap[$sectionIndex])) {
                        $sectionId = $sectionIdMap[$sectionIndex];
                    } elseif (is_numeric($sectionIndex) && !isset($formatting['section_index'])) {
                        // If it's numeric and not from section_index, check if it exists in database
                        $sectionId = (int) $sectionIndex;
                        $sectionExists = $form->sections()->where('id', $sectionId)->exists();
                        if (!$sectionExists) {
                            // If section doesn't exist, try to find by order
                            $section = $form->sections()->where('order', $sectionIndex)->first();
                            $sectionId = $section ? $section->id : null;
                        }
                    }
                }

                // Only save if we have a valid section_id that exists in database
                if ($sectionId && $form->sections()->where('id', $sectionId)->exists()) {
                    FormTextFormatting::updateOrCreate(
                        [
                            'section_id' => $sectionId,
                            'element_type' => $elementType,
                        ],
                        array_merge($data, ['section_id' => $sectionId])
                    );
                }
            } elseif (in_array($elementType, ['result_setting_title', 'result_setting_text'])) {
                // Handle result setup card formatting
                $resultRuleTextId = $formatting['result_rule_text_id'] ?? null;

                if ($resultRuleTextId) {
                    // Validate that result_rule_text_id exists
                    $resultRuleTextExists = \App\Models\ResultRuleText::where('id', $resultRuleTextId)
                        ->whereHas('resultRule', function ($query) use ($form) {
                            $query->where('form_id', $form->id);
                        })
                        ->exists();

                    if ($resultRuleTextExists) {
                        // Delete any existing formatting for this result_rule_text_id and element_type first
                        // to avoid unique constraint issues
                        FormTextFormatting::where('result_rule_text_id', $resultRuleTextId)
                            ->where('element_type', $elementType)
                            ->delete();

                        FormTextFormatting::create(
                            array_merge($data, [
                                'result_rule_text_id' => $resultRuleTextId,
                                'form_id' => $form->id, // Add form_id for consistency
                            ])
                        );
                    }
                }
            }
        }
    }

    /**
     * Export summary responses ke Word document
     */
    public function exportSummary(Form $form)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        $responseData = $this->buildResponseData($form);
        $questionSummaries = $responseData['questionSummaries'];
        $totalResponses = $responseData['totalResponses'];

        // Load result rules - temporarily disabled
        // TODO: Re-enable after fixing the issue
        /*
        try {
            $form->load([
                'resultRules' => function ($query) {
                    $query->with(['texts' => function ($textQuery) {
                        $textQuery->orderBy('order')->with('textSetting');
                    }])->orderBy('order');
                },
            ]);
        } catch (\Exception $e) {
            // If loading result rules fails, continue without them
            Log::warning('Error loading result rules for Word export', [
                'error' => $e->getMessage(),
                'form_id' => $form->id,
            ]);
        }
        */

        // Create new Word document
        $phpWord = new PhpWord();

        // Set default font to Cambria
        $phpWord->setDefaultFontName('Cambria');
        $phpWord->setDefaultFontSize(11);

        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('Hb-ku Form Builder');
        $properties->setTitle(strip_tags($form->title ?? 'Form') . ' - Export Summary');
        $properties->setDescription('Export summary responses dari form: ' . strip_tags($form->title ?? 'Form'));

        $section = $phpWord->addSection([
            'marginTop' => 1440,
            'marginRight' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
        ]);

        // Collect temporary chart image files for cleanup
        $tempChartFiles = [];

        // Define table style with borders
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 80,
        ];

        // Define cell style for title row
        $titleCellStyle = [
            'bgColor' => 'E5E5E5',
            'valign' => 'center',
        ];

        // Header Table with border
        $headerTable = $section->addTable($tableStyle);
        $headerTable->addRow();
        $headerCell = $headerTable->addCell(10000, $titleCellStyle);
        $headerCell->addText(
            '🩸 ' . $this->stripHtml($form->title ?? 'Form') . ' 🩸',
            ['bold' => true, 'size' => 18, 'name' => 'Cambria'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
        );

        if ($form->description) {
            $headerTable->addRow();
            $descCell = $headerTable->addCell(10000);
            $descCell->addText(
                $this->stripHtml($form->description),
                ['size' => 12, 'name' => 'Cambria'],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
            );
        }

        $headerTable->addRow();
        $metaCell = $headerTable->addCell(10000);
        $metaCell->addText(
            'Ringkasan Jawaban',
            ['bold' => true, 'size' => 14, 'name' => 'Cambria'],
            ['spaceAfter' => 60]
        );
        $metaCell->addText(
            'Tanggal Export: ' . Carbon::now()->format('d M Y H:i'),
            ['size' => 10, 'color' => '666666', 'name' => 'Cambria'],
            ['spaceAfter' => 30]
        );
        $metaCell->addText(
            'Total Jawaban: ' . $totalResponses,
            ['size' => 10, 'color' => '666666', 'name' => 'Cambria'],
            ['spaceAfter' => 0]
        );

        $section->addTextBreak(1);

        // Question Summaries
        if (empty($questionSummaries)) {
            $emptyTable = $section->addTable($tableStyle);
            $emptyTable->addRow();
            $emptyCell = $emptyTable->addCell(10000);
            $emptyCell->addText(
                'Belum ada pertanyaan dalam form ini.',
                ['size' => 11, 'italic' => true, 'color' => '999999', 'name' => 'Cambria'],
                ['spaceAfter' => 0]
            );
        } else {
            $section->addText(
                'Ringkasan Pertanyaan',
                ['bold' => true, 'size' => 16, 'name' => 'Cambria'],
                ['spaceBefore' => 240, 'spaceAfter' => 120]
            );

            foreach ($questionSummaries as $index => $summary) {
                $questionTitle = $this->stripHtml($summary['title'] ?? 'Pertanyaan');
                if (empty(trim($questionTitle))) {
                    $questionTitle = 'Pertanyaan ' . ($index + 1);
                }

                // Create table for each question summary
                $questionTable = $section->addTable($tableStyle);

                // Title row with background
                $questionTable->addRow();
                $titleCell = $questionTable->addCell(10000, $titleCellStyle);
                $titleCell->addText(
                    'Pertanyaan ' . ($index + 1) . ': ' . $questionTitle,
                    ['bold' => true, 'size' => 12, 'name' => 'Cambria'],
                    ['spaceAfter' => 0]
                );

                // Content row
                $questionTable->addRow();
                $contentCell = $questionTable->addCell(10000);

                $totalAnswers = isset($summary['total']) ? (int)$summary['total'] : 0;
                $contentCell->addText(
                    'Total Jawaban: ' . $totalAnswers,
                    ['size' => 10, 'color' => '666666', 'name' => 'Cambria'],
                    ['spaceAfter' => 120]
                );

                if (isset($summary['chart']) && is_array($summary['chart']) && !empty($summary['chart']['labels']) && is_array($summary['chart']['labels']) && !empty($summary['chart']['values']) && is_array($summary['chart']['values'])) {
                    $labels = $summary['chart']['labels'] ?? [];
                    $values = $summary['chart']['values'] ?? [];

                    if (empty($labels) || empty($values)) {
                        $contentCell->addText(
                            'Data chart tidak lengkap.',
                            ['size' => 10, 'italic' => true, 'color' => '999999', 'name' => 'Cambria'],
                            ['spaceAfter' => 0]
                        );
                    } else {
                        $contentCell->addText(
                            'Data Jawaban:',
                            ['bold' => true, 'size' => 11, 'name' => 'Cambria'],
                            ['spaceAfter' => 60]
                        );

                        // Generate chart image
                        $chartImagePath = $this->generateChartImage($summary['chart'], $summary['id']);
                        if ($chartImagePath && file_exists($chartImagePath)) {
                            $tempChartFiles[] = $chartImagePath;
                            $contentCell->addImage(
                                $chartImagePath,
                                [
                                    'width' => 400,
                                    'height' => 300,
                                    'alignment' => Jc::CENTER,
                                ]
                            );
                            $contentCell->addTextBreak(1);
                        }

                        // Also add text summary
                        foreach ($summary['chart']['labels'] as $labelIndex => $label) {
                            $contentCell->addText(
                                '  - ' . $this->stripHtml($label) . ': ' . $summary['chart']['values'][$labelIndex] . ' jawaban',
                                ['size' => 11, 'name' => 'Cambria'],
                                ['spaceAfter' => 30]
                            );
                        }
                    }
                } elseif (isset($summary['text_answers']) && is_array($summary['text_answers']) && !empty($summary['text_answers'])) {
                    // List text answers
                    foreach ($summary['text_answers'] as $answer) {
                        if (is_string($answer) || is_numeric($answer)) {
                            $cleanAnswer = $this->stripHtml((string)$answer);
                            if (!empty(trim($cleanAnswer))) {
                                $contentCell->addText(
                                    '- ' . $cleanAnswer,
                                    ['size' => 11, 'name' => 'Cambria'],
                                    ['spaceAfter' => 60, 'indentation' => ['left' => 360]]
                                );
                            }
                        }
                    }
                } else {
                    $contentCell->addText(
                        'Belum ada jawaban untuk pertanyaan ini.',
                        ['size' => 10, 'italic' => true, 'color' => '999999', 'name' => 'Cambria'],
                        ['spaceAfter' => 0]
                    );
                }

                $section->addTextBreak(1);
            }
        }

        // Result Settings Summary - Temporarily disabled to isolate the issue
        /*
        try {
            if (isset($form->resultRules) && $form->resultRules && method_exists($form->resultRules, 'isNotEmpty') && $form->resultRules->isNotEmpty()) {
                $section->addText(
                    'Ringkasan Hasil',
                    ['bold' => true, 'size' => 16],
                    ['spaceBefore' => 360, 'spaceAfter' => 240]
                );

                foreach ($form->resultRules as $rule) {
                    try {
                        // Format condition text - ensure it's safe
                        $conditionText = $this->formatResultRuleCondition($rule);
                        $conditionText = $this->stripHtml($conditionText);
                        if (!empty(trim($conditionText))) {
                            $section->addText(
                                $conditionText,
                                ['bold' => true, 'size' => 12],
                                ['spaceBefore' => 120, 'spaceAfter' => 60]
                            );
                        }

                        if ($rule->texts && $rule->texts->isNotEmpty()) {
                            foreach ($rule->texts as $text) {
                                try {
                                    // Process title if exists
                                    if (isset($text->textSetting) && $text->textSetting) {
                                        $textSetting = $text->textSetting;
                                        if (isset($textSetting->title) && !empty($textSetting->title)) {
                                            $cleanTitle = $this->stripHtml((string)$textSetting->title);
                                            if (!empty(trim($cleanTitle))) {
                                                $section->addText(
                                                    'Judul: ' . $cleanTitle,
                                                    ['bold' => true, 'size' => 11],
                                                    ['spaceAfter' => 30]
                                                );
                                            }
                                        }
                                    }

                                    // Process result text - use same format as Individual (no split)
                                    if (isset($text->result_text) && !empty($text->result_text)) {
                                        $section->addText(
                                            $this->stripHtml($text->result_text),
                                            ['size' => 11],
                                            ['spaceAfter' => 60, 'indentation' => ['left' => 240]]
                                        );
                                    }
                                } catch (\Exception $e) {
                                    // Skip this text if there's an error
                                    Log::warning('Error processing result text in Word export', [
                                        'error' => $e->getMessage(),
                                        'text_id' => $text->id ?? null,
                                    ]);
                                    continue;
                                }
                            }
                        }

                        $section->addTextBreak(1);
                    } catch (\Exception $e) {
                        // Skip this rule if there's an error
                        Log::warning('Error processing result rule in Word export', [
                            'error' => $e->getMessage(),
                            'rule_id' => $rule->id ?? null,
                        ]);
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
            // If result rules section fails, just skip it
            Log::warning('Error processing result rules section in Word export', [
                'error' => $e->getMessage(),
                'form_id' => $form->id,
            ]);
        }
        */

        // Generate filename
        $filename = 'export_' . ($form->slug ?? 'form') . '_summary_' . date('Y-m-d_His') . '.docx';

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'phpword_');

        try {
            // Use Word2007 format (compatible with Word 2007+)
            $writer = IOFactory::createWriter($phpWord, 'Word2007');

            // Save to temporary file with explicit permissions
            $writer->save($tempFile);

            // Set file permissions to ensure it's readable
            @chmod($tempFile, 0644);

            if (!file_exists($tempFile)) {
                throw new \Exception('File tidak berhasil dibuat');
            }

            $fileSize = filesize($tempFile);
            if ($fileSize === false || $fileSize === 0) {
                @unlink($tempFile);
                throw new \Exception('File kosong atau tidak valid');
            }

            // Minimum file size for valid DOCX (should be at least a few KB)
            if ($fileSize < 1000) {
                @unlink($tempFile);
                throw new \Exception('File terlalu kecil, mungkin tidak valid');
            }

            // Verify file is readable
            if (!is_readable($tempFile)) {
                @unlink($tempFile);
                throw new \Exception('File tidak dapat dibaca');
            }

            // Verify it's a valid ZIP file (DOCX is a ZIP archive)
            $zip = new \ZipArchive();
            $zipResult = $zip->open($tempFile, \ZipArchive::CHECKCONS);
            if ($zipResult !== true) {
                @unlink($tempFile);
                throw new \Exception('File bukan format DOCX yang valid (ZIP error: ' . $zipResult . ')');
            }

            // Verify essential DOCX files exist
            $requiredFiles = ['[Content_Types].xml', 'word/document.xml'];
            foreach ($requiredFiles as $requiredFile) {
                if ($zip->locateName($requiredFile) === false) {
                    $zip->close();
                    @unlink($tempFile);
                    throw new \Exception('File DOCX tidak lengkap: ' . $requiredFile . ' tidak ditemukan');
                }
            }

            $zip->close();
        } catch (\Exception $e) {
            if (isset($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            Log::error('Failed to generate Word document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'form_id' => $form->id,
            ]);
            abort(500, 'Gagal membuat dokumen Word: ' . $e->getMessage());
        }

        // Cleanup chart images after download
        register_shutdown_function(function () use ($tempChartFiles) {
            foreach ($tempChartFiles as $chartFile) {
                if (file_exists($chartFile)) {
                    @unlink($chartFile);
                }
            }
        });

        // Return download response with proper headers
        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Export individual responses ke Word document
     */
    public function exportIndividual(Form $form)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        $responseData = $this->buildResponseData($form);
        $individualResponses = $responseData['individualResponses'];
        $totalResponses = $responseData['totalResponses'];

        // Create new Word document
        $phpWord = new PhpWord();

        // Set default font to Cambria
        $phpWord->setDefaultFontName('Cambria');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 1440,
            'marginRight' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
        ]);

        // Define table style with borders
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 80,
        ];

        // Define cell style for title row
        $titleCellStyle = [
            'bgColor' => 'E5E5E5',
            'valign' => 'center',
        ];

        // Header Table with border
        $headerTable = $section->addTable($tableStyle);
        $headerTable->addRow();
        $headerCell = $headerTable->addCell(10000, $titleCellStyle);
        $headerCell->addText(
            '🩸 ' . $this->stripHtml($form->title ?? 'Form') . ' 🩸',
            ['bold' => true, 'size' => 18, 'name' => 'Cambria'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
        );

        if ($form->description) {
            $headerTable->addRow();
            $descCell = $headerTable->addCell(10000);
            $descCell->addText(
                $this->stripHtml($form->description),
                ['size' => 12, 'name' => 'Cambria'],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
            );
        }

        $headerTable->addRow();
        $metaCell = $headerTable->addCell(10000);
        $metaCell->addText(
            'Detail Jawaban Individual',
            ['bold' => true, 'size' => 14, 'name' => 'Cambria'],
            ['spaceAfter' => 60]
        );
        $metaCell->addText(
            'Tanggal Export: ' . Carbon::now()->format('d M Y H:i'),
            ['size' => 10, 'color' => '666666', 'name' => 'Cambria'],
            ['spaceAfter' => 30]
        );
        $metaCell->addText(
            'Total Jawaban: ' . $totalResponses,
            ['size' => 10, 'color' => '666666', 'name' => 'Cambria'],
            ['spaceAfter' => 0]
        );

        $section->addTextBreak(1);

        // Individual Responses
        if (!empty($individualResponses)) {
            foreach ($individualResponses as $index => $response) {
                // Create table for each response
                $responseTable = $section->addTable($tableStyle);

                // Title row with background
                $responseTable->addRow();
                $titleCell = $responseTable->addCell(10000, $titleCellStyle);
                $titleCell->addText(
                    'Jawaban #' . ($index + 1),
                    ['bold' => true, 'size' => 14, 'name' => 'Cambria'],
                    ['spaceAfter' => 0]
                );

                // Content row
                $responseTable->addRow();
                $contentCell = $responseTable->addCell(10000);

                $contentCell->addText(
                    'Email: ' . ($response['email'] ?? 'Anonim'),
                    ['size' => 11, 'name' => 'Cambria'],
                    ['spaceAfter' => 30]
                );

                $contentCell->addText(
                    'Tanggal Submit: ' . ($response['submitted_at'] ?? '-'),
                    ['size' => 11, 'name' => 'Cambria'],
                    ['spaceAfter' => 30]
                );

                if (isset($response['total_score'])) {
                    $contentCell->addText(
                        'Skor Total: ' . $response['total_score'],
                        ['size' => 11, 'bold' => true, 'name' => 'Cambria'],
                        ['spaceAfter' => 60]
                    );
                }

                // Answers (displayed first)
                if (!empty($response['answers'])) {
                    $contentCell->addText(
                        'Jawaban:',
                        ['size' => 11, 'bold' => true, 'name' => 'Cambria'],
                        ['spaceBefore' => 0, 'spaceAfter' => 60]
                    );

                    foreach ($response['answers'] as $answer) {
                        $contentCell->addText(
                            $this->stripHtml($answer['question'] ?? 'Pertanyaan'),
                            ['size' => 11, 'bold' => true, 'name' => 'Cambria'],
                            ['spaceAfter' => 30]
                        );
                        $contentCell->addText(
                            $this->stripHtml($answer['value'] ?? '-'),
                            ['size' => 11, 'name' => 'Cambria'],
                            ['spaceAfter' => 60, 'indentation' => ['left' => 240]]
                        );
                    }
                }

                // Result text displayed at the bottom (after all answers)
                if (!empty($response['result_text'])) {
                    $contentCell->addText(
                        'Hasil:',
                        ['size' => 11, 'bold' => true, 'name' => 'Cambria'],
                        ['spaceBefore' => 120, 'spaceAfter' => 30]
                    );
                    // Format result text with bullet points and proper line breaks
                    $this->formatResultTextForWord($response['result_text'], $contentCell);
                }

                $section->addTextBreak(1);
            }
        }

        // Generate filename
        $filename = $this->sanitizeFilename($form->title ?? 'form') . '_individual_' . Carbon::now()->format('Y-m-d_H-i-s') . '.docx';

        // Save to temporary file
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'export_' . uniqid() . '.docx';

        try {
            // Use Word2007 format (compatible with Word 2007+)
            $writer = IOFactory::createWriter($phpWord, 'Word2007');

            // Save to temporary file with explicit permissions
            $writer->save($tempFile);

            // Set file permissions to ensure it's readable
            @chmod($tempFile, 0644);

            if (!file_exists($tempFile)) {
                throw new \Exception('File tidak berhasil dibuat');
            }

            $fileSize = filesize($tempFile);
            if ($fileSize === false || $fileSize === 0) {
                @unlink($tempFile);
                throw new \Exception('File kosong atau tidak valid');
            }

            // Minimum file size for valid DOCX (should be at least a few KB)
            if ($fileSize < 1000) {
                @unlink($tempFile);
                throw new \Exception('File terlalu kecil, mungkin tidak valid');
            }

            // Verify file is readable
            if (!is_readable($tempFile)) {
                @unlink($tempFile);
                throw new \Exception('File tidak dapat dibaca');
            }

            // Verify it's a valid ZIP file (DOCX is a ZIP archive)
            $zip = new \ZipArchive();
            $zipResult = $zip->open($tempFile, \ZipArchive::CHECKCONS);
            if ($zipResult !== true) {
                @unlink($tempFile);
                throw new \Exception('File bukan format DOCX yang valid (ZIP error: ' . $zipResult . ')');
            }

            // Verify essential DOCX files exist
            $requiredFiles = ['[Content_Types].xml', 'word/document.xml'];
            foreach ($requiredFiles as $requiredFile) {
                if ($zip->locateName($requiredFile) === false) {
                    $zip->close();
                    @unlink($tempFile);
                    throw new \Exception('File DOCX tidak lengkap: ' . $requiredFile . ' tidak ditemukan');
                }
            }

            $zip->close();
        } catch (\Exception $e) {
            if (isset($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            Log::error('Failed to generate Word document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'form_id' => $form->id,
            ]);
            abort(500, 'Gagal membuat dokumen Word: ' . $e->getMessage());
        }

        // Return download response with proper headers
        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Helper method untuk strip HTML tags
     */
    private function stripHtml(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Decode HTML entities
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Strip HTML tags
        $text = strip_tags($text);

        // Replace bullet points and special characters with simpler equivalents
        $text = str_replace(['•', '·', '▪', '▫'], '-', $text);

        // Replace tabs with spaces
        $text = str_replace("\t", ' ', $text);

        // Remove control characters except newlines and carriage returns (for paragraph breaks)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Remove non-printable characters and zero-width characters
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{200E}\x{200F}]/u', '', $text);

        // Remove XML invalid characters (keep only valid XML 1.0 characters)
        // XML 1.0 valid: #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\xD7FF\xE000-\xFFFD]/u', '', $text);

        // Clean up whitespace but preserve newlines for paragraph breaks
        $text = trim($text);

        // Replace multiple spaces with single space, but preserve newlines
        $text = preg_replace('/[ \t]+/', ' ', $text);

        // Normalize newlines
        $text = preg_replace('/\r\n|\r/', "\n", $text);

        // Ensure valid UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return $text;
    }

    /**
     * Helper method untuk format result text dengan bullet points dan line breaks yang rapi
     */
    private function formatResultTextForWord(?string $text, $cell): void
    {
        if (empty($text)) {
            return;
        }

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Strip HTML tags but preserve structure
        $text = strip_tags($text);

        // Normalize line breaks
        $text = preg_replace('/\r\n|\r/', "\n", $text);

        // Split by newlines
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            // Remove leading/trailing whitespace but check original for bullet detection
            $originalLine = $line;
            $trimmedLine = trim($line);

            if (empty($trimmedLine)) {
                continue;
            }

            // Check if line starts with bullet point (•, -, *, etc.)
            // Pattern: optional whitespace, bullet character, optional whitespace/tab, then content
            // Also handle cases where bullet is followed by tab
            $bulletPattern = '/^[\s]*([•·▪▫\-\*\+]|[\d]+[\.\)])[\s\t]*(.+)$/u';

            if (preg_match($bulletPattern, $originalLine, $matches)) {
                // Line has bullet point
                $content = trim($matches[2]);

                if (!empty($content)) {
                    // Use bullet point symbol
                    $bulletChar = '•';

                    $cell->addText(
                        $bulletChar . ' ' . $content,
                        ['size' => 11, 'name' => 'Cambria'],
                        ['spaceAfter' => 60, 'indentation' => ['left' => 240, 'hanging' => 240]]
                    );
                }
            } else {
                // Regular line without bullet
                // Check if it's a continuation (starts with space/tab) or new paragraph
                if (preg_match('/^[\s\t]+/', $originalLine) && !empty($trimmedLine)) {
                    // Continuation line - add with same indentation
                    $cell->addText(
                        $trimmedLine,
                        ['size' => 11, 'name' => 'Cambria'],
                        ['spaceAfter' => 60, 'indentation' => ['left' => 240]]
                    );
                } elseif (!empty($trimmedLine)) {
                    // New paragraph or title (first line or line without leading whitespace)
                    $cell->addText(
                        $trimmedLine,
                        ['size' => 11, 'name' => 'Cambria'],
                        ['spaceAfter' => 60, 'indentation' => ['left' => 240]]
                    );
                }
            }
        }
    }

    /**
     * Helper method untuk format result rule condition
     */
    private function formatResultRuleCondition($rule): string
    {
        $conditionType = $rule->condition_type ?? 'range';

        return match ($conditionType) {
            'range' => sprintf(
                'Skor %s - %s',
                $rule->min_score !== null ? $rule->min_score : 'min',
                $rule->max_score !== null ? $rule->max_score : 'max'
            ),
            'equal' => 'Skor = ' . ($rule->single_score ?? '0'),
            'greater' => 'Skor > ' . ($rule->single_score ?? '0'),
            'less' => 'Skor < ' . ($rule->single_score ?? '0'),
            default => 'Kondisi tidak diketahui',
        };
    }

    /**
     * Helper method untuk sanitize filename
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove HTML tags
        $filename = strip_tags($filename);

        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);

        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);

        // Trim underscores from start and end
        $filename = trim($filename, '_');

        // Limit length
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }

        return $filename ?: 'form';
    }

    /**
     * Generate chart image and return temporary file path
     */
    private function generateChartImage(array $chartData, int $questionId): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null; // GD library not available
        }

        $type = $chartData['type'] ?? 'pie';
        $labels = $chartData['labels'] ?? [];
        $values = $chartData['values'] ?? [];

        if (empty($labels) || empty($values)) {
            return null;
        }

        $width = 600;
        $height = 400;
        $image = imagecreatetruecolor($width, $height);

        // Colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $gray = imagecolorallocate($image, 200, 200, 200);
        $colors = [
            imagecolorallocate($image, 248, 113, 113), // #F87171
            imagecolorallocate($image, 251, 191, 36), // #FBBF24
            imagecolorallocate($image, 52, 211, 153), // #34D399
            imagecolorallocate($image, 96, 165, 250), // #60A5FA
            imagecolorallocate($image, 167, 139, 250), // #A78BFA
            imagecolorallocate($image, 244, 114, 182), // #F472B6
            imagecolorallocate($image, 249, 115, 22), // #F97316
            imagecolorallocate($image, 45, 212, 191), // #2DD4BF
        ];

        // Fill background
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        if ($type === 'pie') {
            $this->drawPieChart($image, $labels, $values, $colors, $black, $width, $height);
        } else {
            $this->drawBarChart($image, $labels, $values, $colors, $black, $gray, $width, $height);
        }

        // Save to temporary file as PNG
        $tempFile = sys_get_temp_dir() . '/chart_' . $questionId . '_' . time() . '.png';
        imagepng($image, $tempFile);
        // imagedestroy() is deprecated in PHP 8.0+ - resources are automatically destroyed

        return $tempFile;
    }

    private function drawPieChart($image, array $labels, array $values, array $colors, $textColor, int $width, int $height): void
    {
        $centerX = $width / 2;
        $centerY = $height / 2;
        $radius = min($width, $height) / 3;
        $startAngle = 0;

        $total = array_sum($values);
        if ($total == 0) return;

        $labelY = 50;
        $legendX = 50;
        $legendSpacing = 25;

        foreach ($values as $index => $value) {
            if ($value == 0) continue;

            $percentage = ($value / $total) * 100;
            $angle = ($value / $total) * 360;

            $color = $colors[$index % count($colors)];

            // Draw pie slice
            imagefilledarc(
                $image,
                $centerX,
                $centerY,
                $radius * 2,
                $radius * 2,
                $startAngle,
                $startAngle + $angle,
                $color,
                IMG_ARC_PIE
            );

            // Draw legend
            $label = strip_tags($labels[$index] ?? 'Label ' . ($index + 1));
            if (strlen($label) > 30) {
                $label = substr($label, 0, 27) . '...';
            }
            $legendText = $label . ' (' . $value . ')';

            // Color box
            imagefilledrectangle($image, $legendX, $labelY - 10, $legendX + 15, $labelY + 5, $color);
            imagerectangle($image, $legendX, $labelY - 10, $legendX + 15, $labelY + 5, $textColor);

            // Text
            imagestring($image, 3, $legendX + 20, $labelY - 8, $legendText, $textColor);
            $labelY += $legendSpacing;

            $startAngle += $angle;
        }
    }

    private function drawBarChart($image, array $labels, array $values, array $colors, $textColor, $gridColor, int $width, int $height): void
    {
        $margin = 60;
        $chartWidth = $width - ($margin * 2);
        $chartHeight = $height - ($margin * 2);
        $barWidth = $chartWidth / max(count($values), 1);
        $maxValue = max($values) ?: 1;

        // Draw grid lines
        $gridLines = 5;
        for ($i = 0; $i <= $gridLines; $i++) {
            $y = $margin + ($chartHeight / $gridLines) * $i;
            imageline($image, $margin, $y, $width - $margin, $y, $gridColor);
            $value = $maxValue - (($maxValue / $gridLines) * $i);
            imagestring($image, 2, 10, $y - 7, (int)$value, $textColor);
        }

        // Draw bars
        foreach ($values as $index => $value) {
            $barHeight = ($value / $maxValue) * $chartHeight;
            $x = $margin + ($barWidth * $index) + ($barWidth * 0.1);
            $barActualWidth = $barWidth * 0.8;
            $y = $margin + $chartHeight - $barHeight;

            $color = $colors[$index % count($colors)];
            imagefilledrectangle($image, $x, $y, $x + $barActualWidth, $margin + $chartHeight, $color);
            imagerectangle($image, $x, $y, $x + $barActualWidth, $margin + $chartHeight, $textColor);

            // Value label on top of bar
            imagestring($image, 3, $x + ($barActualWidth / 2) - 10, $y - 20, (string)$value, $textColor);

            // Label below bar
            $label = strip_tags($labels[$index] ?? 'Label ' . ($index + 1));
            if (strlen($label) > 15) {
                $label = substr($label, 0, 12) . '...';
            }
            $labelX = $x + ($barActualWidth / 2) - (strlen($label) * 3);
            imagestring($image, 2, $labelX, $margin + $chartHeight + 5, $label, $textColor);
        }
    }
}
