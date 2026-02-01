<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormHeader;
use App\Models\FormResponse;
use App\Models\FormTextFormatting;
use App\Models\SettingResult;
use Carbon\Carbon;
use App\Services\FormService;
use App\Services\HtmlService;
use App\Services\ExportService;
use App\Services\ChartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class FormController extends Controller
{
    protected $formService;
    protected $htmlService;
    protected $exportService;
    protected $chartService;

    public function __construct(
        FormService $formService,
        HtmlService $htmlService,
        ExportService $exportService,
        ChartService $chartService
    ) {
        $this->formService = $formService;
        $this->htmlService = $htmlService;
        $this->exportService = $exportService;
        $this->chartService = $chartService;
    }
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
    /**
     * Membuat form draft baru dan redirect ke halaman edit
     */
    public function create()
    {
        // Auto-create draft form
        $form = Form::create([
            'user_id' => Auth::id(),
            'title' => 'Formulir Tanpa Judul',
            'description' => null,
            'slug' => Str::random(10), // Temporary slug
            'theme_color' => '#3b82f6', // Default blue
            'collect_email' => false,
            'limit_one_response' => false,
            'show_progress_bar' => true,
            'shuffle_questions' => false,
        ]);

        return redirect()->route('forms.edit', $form->id);
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
            'use_bmi_formula' => 'boolean',
            'bmi_mapping' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $payload = [
                'title' => $this->htmlService->sanitize($request->title),
                'description' => $request->description ? $this->htmlService->sanitize($request->description) : null,
                'theme_color' => $request->theme_color ?? '#3b82f6',
                'collect_email' => $request->boolean('collect_email'),
                'limit_one_response' => $request->boolean('limit_one_response'),
                'show_progress_bar' => $request->boolean('show_progress_bar'),
                'shuffle_questions' => $request->boolean('shuffle_questions'),
                'sections' => $request->input('sections', []),
                'answer_templates' => $request->input('answer_templates', []),
                'result_rules' => $request->input('result_rules', []),
                'questions' => $request->input('questions', []),
                'result_text_settings' => $request->input('result_text_settings', []),
                'header' => $request->input('header', []),
                'text_formatting' => $request->input('text_formatting', []),
                'use_bmi_formula' => $request->boolean('use_bmi_formula'),
                'bmi_mapping' => $request->input('bmi_mapping'),
            ];

            $form = Form::create([
                'user_id' => Auth::id(),
                'title' => $payload['title'],
                'description' => $payload['description'],
                'theme_color' => $payload['theme_color'],
                'collect_email' => $payload['collect_email'],
                'limit_one_response' => $payload['limit_one_response'],
                'show_progress_bar' => $payload['show_progress_bar'],
                'shuffle_questions' => $payload['shuffle_questions'],
                'use_bmi_formula' => $payload['use_bmi_formula'],
                'bmi_mapping' => $payload['bmi_mapping'],
            ]);

            $this->formService->syncRelations($form, $payload);

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
            'use_bmi_formula' => 'boolean',
            'bmi_mapping' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            // Clean and optimize HTML before storing
            $cleanedTitle = $this->htmlService->sanitize($request->title);
            $cleanedDescription = $request->description ? $this->htmlService->sanitize($request->description) : null;
            $form->update([
                'title' => $cleanedTitle,
                'description' => $cleanedDescription,
                'theme_color' => $request->theme_color ?? 'red',
                'collect_email' => $request->boolean('collect_email'),
                'limit_one_response' => $request->boolean('limit_one_response'),
                'show_progress_bar' => $request->boolean('show_progress_bar'),
                'shuffle_questions' => $request->boolean('shuffle_questions'),
                'use_bmi_formula' => $request->boolean('use_bmi_formula'),
                'bmi_mapping' => $request->input('bmi_mapping', []),
            ]);

            $payload = [
                'sections' => $request->input('sections', []),
                'answer_templates' => $request->input('answer_templates', []),
                'result_rules' => $request->input('result_rules', []),
                'questions' => $request->input('questions', []),
                'result_text_settings' => $request->input('result_text_settings', []),
                'header' => $request->input('header', []),
                'text_formatting' => $request->input('text_formatting', []),
            ];

            // Perform Smart Sync via FormService
            $this->formService->syncRelations($form, $payload);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Formulir berhasil diperbarui.',
                'redirect' => route('dashboard'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Form update failed', [
                'form_id' => $form->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui formulir: ' . $e->getMessage(),
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
            'result_rules.*.texts.*.result_text' => ['required', 'string'],
            'result_rules.*.texts.*.id' => ['nullable'],
            'rule_group_id' => ['nullable', 'string'],
            'rule_group_title' => ['nullable', 'string', 'max:255'],
        ]);

        $preferredGroupId = $data['rule_group_id'] ?? null;
        $ruleGroupId = $this->formService->normalizeRuleGroupId($data, $preferredGroupId);
        unset($data['rule_group_id']);

        $templateOrderBase = null;
        $ruleOrderBase = null;

        if ($preferredGroupId) {
            $deleteContext = $this->formService->deleteRuleGroup($form, $ruleGroupId);
            $templateOrderBase = $deleteContext['template_order_base'];
            $ruleOrderBase = $deleteContext['rule_order_base'];
        }

        try {
            DB::beginTransaction();

            $this->formService->persistFormRules($form, $data, true, $templateOrderBase, $ruleOrderBase, $ruleGroupId);

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

    public function destroyRuleGroup(Form $form, string $ruleGroupId)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            DB::beginTransaction();

            $this->formService->deleteRuleGroup($form, $ruleGroupId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Aturan form berhasil dihapus.',
            ]);
        } catch (\Throwable $throwable) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus aturan: ' . $throwable->getMessage(),
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
        return $this->formService->buildResponseData($form);
    }

    private function responseStats(?Form $form = null, int $questionCount = 0): array
    {
        return $this->formService->responseStats($form, $questionCount);
    }

    private function prepareFormBuilderData(Form $form): array
    {
        return $this->formService->prepareFormBuilderData($form);
    }

    private function buildSavedRules(Form $form): array
    {
        return $this->formService->buildSavedRules($form);
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
        return $this->exportService->exportSummary($form, $responseData);
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
        return $this->exportService->exportIndividual($form, $responseData);
    }

    /**
     * Export single individual response ke Word document
     */
    public function exportIndividualSingle(Form $form, $responseId)
    {
        if ($form->user_id !== Auth::id()) {
            abort(403);
        }

        $responseData = $this->buildResponseData($form);
        
        // Filter individualResponses to only include the one with responseId
        $responseData['individualResponses'] = collect($responseData['individualResponses'])
            ->filter(fn($r) => (string)$r['id'] === (string)$responseId)
            ->values()
            ->toArray();

        if (empty($responseData['individualResponses'])) {
            abort(404, 'Jawaban tidak ditemukan.');
        }

        return $this->exportService->exportIndividual($form, $responseData);
    }
}
