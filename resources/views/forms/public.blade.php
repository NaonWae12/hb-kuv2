<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ strip_tags($form->title) }} - hb-ku Form</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if(!empty($usedFonts))
    @php
    // Build Google Fonts URL - each font needs its own family parameter
    $fontParams = [];
    foreach ($usedFonts as $font) {
    $fontEscaped = str_replace(' ', '+', $font);
    $fontParams[] = 'family=' . urlencode($font) . ':wght@400;600;700';
    }
    $googleFontsUrl = 'https://fonts.googleapis.com/css2?' . implode('&', $fontParams) . '&display=swap';
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $googleFontsUrl }}" rel="stylesheet">
    @endif

    <style>
        .form-page {
            display: none;
        }

        .form-page.active {
            display: block;
        }
    </style>
</head>

@php
// Helper function to generate CSS style from formatting array
function generateFormattingStyle($formatting) {
if (!$formatting) return '';

$styles = [];

if (isset($formatting['text_align']) && $formatting['text_align']) {
$styles[] = 'text-align: ' . htmlspecialchars($formatting['text_align'], ENT_QUOTES, 'UTF-8');
}

if (isset($formatting['font_family']) && $formatting['font_family']) {
$fontFamily = trim($formatting['font_family']);
// Add fallback fonts for better compatibility
$systemFonts = ['Arial', 'Helvetica', 'Times New Roman', 'Courier New', 'Verdana', 'Georgia', 'Comic Sans MS'];
if (in_array($fontFamily, $systemFonts, true)) {
// System font, no need for quotes or fallback
$styles[] = 'font-family: ' . htmlspecialchars($fontFamily, ENT_QUOTES, 'UTF-8') . ', sans-serif';
} else {
// Google Font, add quotes and fallback
$styles[] = 'font-family: "' . htmlspecialchars($fontFamily, ENT_QUOTES, 'UTF-8') . '", sans-serif';
}
}

if (isset($formatting['font_size']) && $formatting['font_size']) {
$styles[] = 'font-size: ' . intval($formatting['font_size']) . 'px';
}

if (isset($formatting['font_weight']) && $formatting['font_weight'] && $formatting['font_weight'] !== 'normal') {
$styles[] = 'font-weight: ' . htmlspecialchars($formatting['font_weight'], ENT_QUOTES, 'UTF-8');
}

if (isset($formatting['font_style']) && $formatting['font_style'] && $formatting['font_style'] !== 'normal') {
$styles[] = 'font-style: ' . htmlspecialchars($formatting['font_style'], ENT_QUOTES, 'UTF-8');
}

if (isset($formatting['text_decoration']) && $formatting['text_decoration'] && $formatting['text_decoration'] !== 'none') {
$styles[] = 'text-decoration: ' . htmlspecialchars($formatting['text_decoration'], ENT_QUOTES, 'UTF-8');
}

return !empty($styles) ? implode('; ', $styles) . ';' : '';
}

$formTitleFormatting = $formattingMap['form_title'] ?? null;
$formDescriptionFormatting = $formattingMap['form_description'] ?? null;
$formTitleStyleValue = generateFormattingStyle($formTitleFormatting);
$formDescriptionStyleValue = generateFormattingStyle($formDescriptionFormatting);

// Build complete style attributes
$formTitleStyleAttr = $formTitleStyleValue ? ' style="' . htmlspecialchars($formTitleStyleValue, ENT_QUOTES, 'UTF-8') . '"' : '';
$formDescriptionStyleAttr = $formDescriptionStyleValue ? ' style="' . htmlspecialchars($formDescriptionStyleValue, ENT_QUOTES, 'UTF-8') . '"' : '';
@endphp

<body class="bg-gray-100 min-h-screen">
    <div class="max-w-3xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <div class="mb-6 text-center">
            <a href="{{ route('login') }}" class="inline-flex items-center space-x-2 text-red-600 font-semibold">
                <span class="w-10 h-10 bg-red-600 text-white rounded-full flex items-center justify-center font-bold">Hb</span>
                <span>Hb-ku</span>
            </a>
        </div>

        <!-- Header, Title and Description - Only shown on first page (hidden if form submitted) -->
        @if (!session('result_data') && !session('status'))
        <div id="form-header-card" class="bg-white rounded-2xl shadow-lg border border-gray-100 mb-6 overflow-hidden relative">
            <!-- Share Icon -->
            <button type="button"
                onclick="copyShareLink('{{ $shareUrl }}')"
                class="absolute top-4 right-4 p-2 bg-white/90 backdrop-blur-sm text-gray-500 hover:text-red-600 hover:bg-white rounded-lg shadow-sm transition-colors z-20"
                title="Bagikan tautan">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                </svg>
            </button>

            @if($form->header && $form->header->image_path)
            @php
            $headerImagePath = $form->header->image_path;
            if (!str_starts_with($headerImagePath, 'http://') && !str_starts_with($headerImagePath, 'https://')) {
            $headerImagePath = asset($headerImagePath);
            }

            $imageMode = $form->header->image_mode ?? 'cover';

            // Set header styles based on mode
            $headerBgStyleAttr = '';
            $headerImgStyleAttr = '';
            if (in_array($imageMode, ['repeat', 'no-repeat'])) {
            // Use background image for repeat modes
            switch($imageMode) {
            case 'repeat':
            $headerBgStyleAttr = ' style="background-image: url(\'' . htmlspecialchars($headerImagePath, ENT_QUOTES, 'UTF-8') . '\'); background-size: auto; background-position: top left; background-repeat: repeat;"';
            break;
            case 'no-repeat':
            $headerBgStyleAttr = ' style="background-image: url(\'' . htmlspecialchars($headerImagePath, ENT_QUOTES, 'UTF-8') . '\'); background-size: auto; background-position: center; background-repeat: no-repeat;"';
            break;
            }
            } else {
            // Use img tag for other modes
            $imgStyleValue = '';
            switch($imageMode) {
            case 'stretch':
            $imgStyleValue = 'width: 100%; height: 192px; object-fit: fill;';
            break;
            case 'cover':
            $imgStyleValue = 'width: 100%; height: 192px; object-fit: cover; object-position: center;';
            break;
            case 'contain':
            $imgStyleValue = 'width: 100%; height: 192px; object-fit: contain; object-position: center;';
            break;
            case 'center':
            $imgStyleValue = 'width: 100%; height: 192px; object-fit: cover; object-position: center;';
            break;
            default:
            $imgStyleValue = 'width: 100%; height: 192px; object-fit: cover; object-position: center;';
            }
            if ($imgStyleValue) {
            $headerImgStyleAttr = ' style="' . htmlspecialchars($imgStyleValue, ENT_QUOTES, 'UTF-8') . '"';
            }
            }
            @endphp
            <div class="form-header-image w-full h-48 mb-6 rounded-t-2xl overflow-hidden" {!! $headerBgStyleAttr !!}>
                @if(!in_array($imageMode, ['repeat', 'no-repeat']))
                <img src="{{ $headerImagePath }}" alt="Form Header" {!! $headerImgStyleAttr !!} class="w-full">
                @endif
            </div>
            @endif

            <div class="px-8 pt-1 pb-8">
                <h1 class="text-2xl font-semibold text-gray-900 mb-2" {!! $formTitleStyleAttr !!}>
                    {!! $form->title ?? '' !!}
                </h1>
                @if($form->description)
                <p class="text-sm text-gray-600" {!! $formDescriptionStyleAttr !!}>
                    {!! $form->description !!}
                </p>
                @endif
            </div>
        </div>
        @endif

        <!-- Appreciation Dialog -->
        <div id="appreciation-dialog" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
            <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md mx-4 text-center">
                <img src="{{ asset('assets/images/krk_gembira_1_2.png') }}" alt="Gembira" class="w-32 h-32 mx-auto mb-4">
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Terima Kasih!</h3>
                <p class="text-sm text-gray-600">Jawaban Anda telah berhasil dikirim.</p>
            </div>
        </div>

        <!-- Success Message (hidden initially, shown after dialog) -->
        @if (session('status') && !session('result_data'))
        <div id="success-message" class="bg-green-50 border border-green-200 text-green-800 rounded-xl p-4 mb-6 hidden">
            <p class="font-medium">{{ session('status') }}</p>
        </div>
        @endif

        <!-- Result Data (hidden initially, shown after dialog) -->
        @if (session('result_data'))
        <div id="result-container" class="hidden">
        @php
        $resultData = session('result_data');
        $textAlignment = $resultData['text_alignment'] ?? 'center';
        $imageAlignment = $resultData['image_alignment'] ?? 'center';
        $texts = $resultData['texts'] ?? [];
        $sectionScores = $resultData['section_scores'] ?? [];
        $derivedMetrics = $resultData['derived_metrics'] ?? [];
        @endphp

        {{-- Metrics Breakdown Card --}}
        @if(!empty($sectionScores) || !empty($derivedMetrics))
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Analisis Hasil</h2>

            @if(!empty($derivedMetrics))
            <div class="mb-8">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Metrik Kesehatan</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($derivedMetrics as $key => $metric)
                    @if(!is_array($metric) || !isset($metric['label'])) @continue @endif
                    <div class="bg-red-50 rounded-xl p-4 border border-red-100">
                        <p class="text-xs text-red-600 font-medium mb-1">{{ $metric['label'] }}</p>
                        <div class="flex items-end space-x-2">
                            <span class="text-2xl font-bold text-red-700">{{ $metric['value'] }}</span>
                            @if($key === 'bmi')
                            <span class="text-sm text-red-500 pb-1">kg/m²</span>
                            @endif
                        </div>
                        @if($key === 'bmi' && isset($metric['category']))
                        <p class="text-sm font-bold text-red-600 mt-1">{{ $metric['category'] }}</p>
                        @endif
                        @if($key === 'bmi' && isset($metric['weight']) && isset($metric['height']))
                        <p class="text-[10px] text-red-400 mt-2">Berdasarkan BB: {{ $metric['weight'] }}kg, TB: {{ $metric['height'] }}cm</p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!empty($sectionScores))
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Rincian Skor per Bagian</p>
                <div class="space-y-4">
                    @foreach($sectionScores as $sId => $sData)
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium text-gray-700">{{ strip_tags($sData['title']) }}</span>
                            <span class="text-sm font-bold text-red-600">{{ $sData['score'] }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            @php
                            // For visualization, assume a max score per section or just show relative to a fixed value
                            // Since we don't have max section score easily here, we'll just show it filled.
                            // In a real scenario, we might want to know the max possible score.
                            @endphp
                            <div class="bg-red-500 h-2 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif
        @php
        $textAlignClass = match($textAlignment) {
        'left' => 'text-left',
        'right' => 'text-right',
        default => 'text-center',
        };

        $imageAlignClass = match($imageAlignment) {
        'left' => 'text-left',
        'right' => 'text-right',
        default => 'text-center',
        };
        @endphp

        @if (!empty($texts))
        <div class="space-y-6">
            @if(count($texts) > 1)
            {{-- Multiple result texts - display each in separate card --}}
            @foreach($texts as $textItem)
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
                @if (!empty($textItem['title']))
                <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ $textItem['title'] }}</h2>
                @endif

                @if (!empty($textItem['image_url']))
                <div class="mb-4 {{ $imageAlignClass }}">
                    <img src="{{ $textItem['image_url'] }}" alt="{{ $textItem['title'] ?? 'Result image' }}"
                        class="inline-block max-w-full h-auto rounded-lg border border-gray-200"
                        style="max-height: 400px;">
                </div>
                @endif

                @if (!empty($textItem['result_text']))
                <div class="{{ $textAlignClass }} prose max-w-none text-gray-700">
                    {!! $textItem['result_text'] !!}
                </div>
                @endif
            </div>
            @endforeach
            @else
            {{-- Single result text or unified display --}}
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
                @foreach($texts as $textItem)
                <div class="space-y-4">
                    @if (!empty($textItem['title']))
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ $textItem['title'] }}</h2>
                    @elseif($loop->first)
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Hasil Interpretasi</h2>
                    @endif

                    @if (!empty($textItem['image_url']))
                    <div class="{{ $imageAlignClass }}">
                        <img src="{{ $textItem['image_url'] }}" alt="{{ $textItem['title'] ?? 'Result image' }}"
                            class="inline-block max-w-full h-auto rounded-lg border border-gray-200"
                            style="max-height: 400px;">
                    </div>
                    @endif

                    @if (!empty($textItem['result_text']))
                    <div class="{{ $textAlignClass }} prose max-w-none text-gray-700">
                        {!! $textItem['result_text'] !!}
                    </div>
                    @endif
                </div>
                @if(!$loop->last)
                <hr class="my-6 border-gray-100">
                @endif
                @endforeach
            </div>
            @endif
        </div>
        @endif
        </div>
        @endif

        <!-- Success View (hidden initially, shown after dialog if no result_data) -->
        @if (session('status') && !session('result_data'))
        <div id="success-view" class="hidden">
            <!-- Header, Title and Description -->
            <div id="form-header-card-success" class="bg-white rounded-2xl shadow-lg border border-gray-100 mb-6 overflow-hidden relative">
                @if($form->header && $form->header->image_path)
                @php
                $headerImagePath = $form->header->image_path;
                if (!str_starts_with($headerImagePath, 'http://') && !str_starts_with($headerImagePath, 'https://')) {
                $headerImagePath = asset($headerImagePath);
                }
                $imageMode = $form->header->image_mode ?? 'cover';
                $headerBgStyleAttr = '';
                $headerImgStyleAttr = '';
                if (in_array($imageMode, ['repeat', 'no-repeat'])) {
                switch($imageMode) {
                case 'repeat':
                $headerBgStyleAttr = ' style="background-image: url(\'' . htmlspecialchars($headerImagePath, ENT_QUOTES, 'UTF-8') . '\'); background-size: auto; background-position: top left; background-repeat: repeat;"';
                break;
                case 'no-repeat':
                $headerBgStyleAttr = ' style="background-image: url(\'' . htmlspecialchars($headerImagePath, ENT_QUOTES, 'UTF-8') . '\'); background-size: auto; background-position: center; background-repeat: no-repeat;"';
                break;
                }
                } else {
                $imgStyleValue = '';
                switch($imageMode) {
                case 'stretch':
                $imgStyleValue = 'width: 100%; height: 192px; object-fit: fill;';
                break;
                case 'cover':
                $imgStyleValue = 'width: 100%; height: 192px; object-fit: cover; object-position: center;';
                break;
                case 'contain':
                $imgStyleValue = 'width: 100%; height: 192px; object-fit: contain; object-position: center;';
                break;
                case 'center':
                $imgStyleValue = 'width: 100%; height: 192px; object-fit: cover; object-position: center;';
                break;
                default:
                $imgStyleValue = 'width: 100%; height: 192px; object-fit: cover; object-position: center;';
                }
                if ($imgStyleValue) {
                $headerImgStyleAttr = ' style="' . htmlspecialchars($imgStyleValue, ENT_QUOTES, 'UTF-8') . '"';
                }
                }
                @endphp
                <div class="form-header-image w-full h-48 mb-6 rounded-t-2xl overflow-hidden" {!! $headerBgStyleAttr !!}>
                    @if(!in_array($imageMode, ['repeat', 'no-repeat']))
                    <img src="{{ $headerImagePath }}" alt="Form Header" {!! $headerImgStyleAttr !!} class="w-full">
                    @endif
                </div>
                @endif

                <div class="px-8 pt-1 pb-8">
                    <h1 class="text-2xl font-semibold text-gray-900 mb-2" {!! $formTitleStyleAttr !!}>
                        {!! $form->title ?? '' !!}
                    </h1>
                    @if($form->description)
                    <p class="text-sm text-gray-600" {!! $formDescriptionStyleAttr !!}>
                        {!! $form->description !!}
                    </p>
                    @endif
                </div>
            </div>

            <!-- Success Information Card -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Formulir Berhasil Dikirim</h2>
                    <p class="text-sm text-gray-600">Terima kasih telah meluangkan waktu untuk mengisi formulir ini.</p>
                </div>
            </div>
        </div>
        @endif

        @if (!session('result_data') && !session('status'))
        <form method="POST" action="{{ route('forms.public.submit', $form) }}" id="public-form" class="space-y-6">
            @csrf
            
            @if($form->show_progress_bar && count($pages) > 1)
            <div class="mb-6">
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span>Progress Pengisian</span>
                    <span id="progress-text">Halaman 1 dari {{ count($pages) }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="progress-bar" class="bg-red-600 h-2 rounded-full transition-all duration-300" style="width: {{ 100 / count($pages) }}%"></div>
                </div>
            </div>
            @endif

            @if($form->collect_email)
            <div class="bg-white rounded-2xl shadow border border-gray-100 p-6">
                <label for="email" class="block text-sm font-medium text-gray-900 mb-1">
                    Alamat Email @if($form->collect_email)<span class="text-red-600">*</span>@endif
                </label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500 @error('email') border-red-500 @enderror"
                    placeholder="nama@contoh.com" {{ $form->collect_email ? 'required' : '' }}>
                @error('email')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            @endif

            @foreach($pages as $pageIndex => $page)
            <div class="form-page {{ $pageIndex === 0 ? 'active' : '' }}" data-page="{{ $pageIndex }}">
                @if($page['section'])
                @php
                $section = $page['section'];
                // Get title and description directly from model (like question title)
                $sectionTitle = $section->title ?? '';
                $sectionDescription = $section->description ?? '';
                $hasImage = !empty($section->image);

                // DEBUG: Log section data (commented out for production)
                // \Log::info('Section Debug', [
                // 'section_id' => $section->id ?? null,
                // 'title_raw' => $sectionTitle,
                // 'title_type' => gettype($sectionTitle),
                // 'title_length' => strlen($sectionTitle ?? ''),
                // 'title_first_50' => substr($sectionTitle ?? '', 0, 50),
                // 'description_raw' => $sectionDescription,
                // ]);

                // Check if section has been edited (not default "Bagian X" pattern)
                // Strip HTML tags for validation check only
                $titlePlainText = strip_tags($sectionTitle);
                // If title has HTML tags, it means it's been formatted, so it's valid
                $hasHtmlTags = $sectionTitle !== $titlePlainText;
                // Check if it's not a default "Bagian X" pattern
                $isNotDefaultPattern = !preg_match('/^Bagian\s+\d+$/i', trim($titlePlainText));
                // Title is valid if it has content AND (has HTML tags OR is not default pattern)
                $hasValidTitle = $sectionTitle !== '' && ($hasHtmlTags || $isNotDefaultPattern);
                // Only show card if there's actual content to display
                $hasContent = $hasValidTitle || $sectionDescription !== '' || $hasImage;
                @endphp
                @if($hasContent)
                @php
                $imageAlignment = $section->image_alignment ?? 'center';
                $alignmentClass = match ($imageAlignment) {
                'left' => 'text-left',
                'right' => 'text-right',
                default => 'text-center',
                };
                $imageWrapMode = $section->image_wrap_mode ?? 'fixed';
                $imageStyle = $imageWrapMode === 'fit'
                ? 'width: 100%; max-width: 100%; height: auto; object-fit: cover;'
                : 'max-width: 100%; height: auto;';

                $sectionTitleFormatting = $formattingMap["section_title_{$section->id}"] ?? null;
                $sectionDescriptionFormatting = $formattingMap["section_description_{$section->id}"] ?? null;
                $sectionTitleStyleValue = generateFormattingStyle($sectionTitleFormatting);
                $sectionDescriptionStyleValue = generateFormattingStyle($sectionDescriptionFormatting);

                // Build complete style attributes
                $sectionTitleStyleAttr = $sectionTitleStyleValue ? ' style="' . htmlspecialchars($sectionTitleStyleValue, ENT_QUOTES, 'UTF-8') . '"' : '';
                $sectionDescriptionStyleAttr = $sectionDescriptionStyleValue ? ' style="' . htmlspecialchars($sectionDescriptionStyleValue, ENT_QUOTES, 'UTF-8') . '"' : '';
                @endphp
                <div class="bg-white rounded-2xl shadow border border-gray-100 p-6 mb-6">
                    @if($hasValidTitle || $sectionDescription !== '')
                    <div class="mb-4">
                        @if($hasValidTitle)
                        <h2 class="text-lg font-semibold text-gray-900" {!! $sectionTitleStyleAttr !!}>
                            {!! $section->title ?? '' !!}
                        </h2>
                        @endif
                        @if($sectionDescription !== '')
                        <p class="text-sm text-gray-600 mt-1" {!! $sectionDescriptionStyleAttr !!}>
                            {!! $section->description ?? '' !!}
                        </p>
                        @endif
                    </div>
                    @endif

                    @if($section->image)
                    <div class="{{ $hasValidTitle || $description !== '' ? 'mt-4' : 'mb-4' }} {{ $alignmentClass }}">
                        @php
                        $imageUrl = $section->image;
                        if (!str_starts_with($imageUrl, 'http://') && !str_starts_with($imageUrl, 'https://')) {
                        $imageUrl = asset($imageUrl);
                        }
                        $finalImageStyle = $imageStyle ?? 'max-width: 100%; height: auto;';
                        $styleAttr = $finalImageStyle ? ' style="' . htmlspecialchars($finalImageStyle, ENT_QUOTES, 'UTF-8') . '"' : '';
                        @endphp
                        <img src="{{ $imageUrl }}" alt="Gambar bagian"
                            class="rounded-xl border border-gray-200 object-contain inline-block" {!! $styleAttr !!}
                            onerror="this.style.display='none';">
                    </div>
                    @endif
                </div>
                @endif
                @endif

                @foreach($page['questions'] as $question)
                <div class="bg-white rounded-2xl shadow border border-gray-100 p-6">
                    @include('forms.partials.public-question', [
                    'question' => $question,
                    'formattingMap' => $formattingMap
                    ])
                </div>
                @endforeach
            </div>
            @endforeach

            <div class="flex justify-between items-center mt-6">
                <button type="button" id="prev-page-btn" class="items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg shadow transition hidden">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Sebelumnya
                </button>
                <div class="flex-1 text-center">
                    <span class="text-sm text-gray-600">
                        Halaman <span id="current-page">1</span> dari <span id="total-pages">{{ $totalPages }}</span>
                    </span>
                </div>
                <button type="button" id="next-page-btn" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg shadow transition">
                    Selanjutnya
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
                <button type="submit" id="submit-btn" class="items-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg shadow transition hidden">
                    Kirim Jawaban
                </button>
            </div>
        </form>
        @endif
    </div>

    <script>
        // Copy share link function
        function copyShareLink(url) {
            // Try to use the modern Clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    showShareNotification('Tautan berhasil disalin!');
                }).catch(function(err) {
                    console.error('Failed to copy:', err);
                    fallbackCopyText(url);
                });
            } else {
                // Fallback for older browsers
                fallbackCopyText(url);
            }
        }

        // Fallback copy function for older browsers
        function fallbackCopyText(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showShareNotification('Tautan berhasil disalin!');
                } else {
                    showShareNotification('Gagal menyalin tautan. Silakan salin manual: ' + text, 'error');
                }
            } catch (err) {
                console.error('Fallback copy failed:', err);
                showShareNotification('Gagal menyalin tautan. Silakan salin manual: ' + text, 'error');
            }

            document.body.removeChild(textArea);
        }

        // Show notification
        function showShareNotification(message, type = 'success') {
            // Remove existing notification if any
            const existing = document.getElementById('share-notification');
            if (existing) {
                existing.remove();
            }

            // Create notification element
            const notification = document.createElement('div');
            notification.id = 'share-notification';
            notification.className = `fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50 transition-all transform ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
            }`;
            notification.textContent = message;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateY(0)';
            }, 10);

            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('public-form');
            if (!form) {
                return;
            }

            const pages = document.querySelectorAll('.form-page');
            const prevBtn = document.getElementById('prev-page-btn');
            const nextBtn = document.getElementById('next-page-btn');
            const submitBtn = document.getElementById('submit-btn');
            const currentPageSpan = document.getElementById('current-page');
            const totalPagesSpan = document.getElementById('total-pages');

            if (!pages.length || !prevBtn || !nextBtn || !submitBtn || !currentPageSpan || !totalPagesSpan) {
                return;
            }

            let currentPage = 0;
            const totalPages = pages.length;

            function updatePageDisplay() {
                // Hide all pages
                pages.forEach((page, index) => {
                    page.classList.remove('active');
                    if (index === currentPage) {
                        page.classList.add('active');
                    }
                });

                // Hide header, title and description after first page
                const headerCard = document.getElementById('form-header-card');
                if (headerCard) {
                    if (currentPage === 0) {
                        headerCard.style.display = 'block';
                    } else {
                        headerCard.style.display = 'none';
                    }
                }

                // Update page number
                currentPageSpan.textContent = currentPage + 1;

                // Show/hide navigation buttons
                if (currentPage === 0) {
                    prevBtn.classList.add('hidden');
                    prevBtn.classList.remove('inline-flex');
                } else {
                    prevBtn.classList.remove('hidden');
                    prevBtn.classList.add('inline-flex');
                }

                if (currentPage === totalPages - 1) {
                    nextBtn.classList.add('hidden');
                    nextBtn.classList.remove('inline-flex');
                    submitBtn.classList.remove('hidden');
                    submitBtn.classList.add('inline-flex');
                } else {
                    nextBtn.classList.remove('hidden');
                    nextBtn.classList.add('inline-flex');
                    submitBtn.classList.add('hidden');
                    submitBtn.classList.remove('inline-flex');
                }

                // Update progress bar
                const progressBar = document.getElementById('progress-bar');
                const progressText = document.getElementById('progress-text');
                if (progressBar && progressText) {
                    const progress = Math.round(((currentPage + 1) / totalPages) * 100);
                    progressBar.style.width = `${progress}%`;
                    progressText.textContent = `Halaman ${currentPage + 1} dari ${totalPages}`;
                }
            }

            prevBtn.addEventListener('click', function() {
                if (currentPage > 0) {
                    currentPage--;
                    updatePageDisplay();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });

            nextBtn.addEventListener('click', function() {
                // Validate current page before proceeding
                const currentPageElement = pages[currentPage];
                const requiredFields = currentPageElement.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value || (field.type === 'checkbox' && !field.checked)) {
                        isValid = false;
                        field.classList.add('border-red-500');
                    } else {
                        field.classList.remove('border-red-500');
                    }
                });

                if (isValid && currentPage < totalPages - 1) {
                    currentPage++;
                    updatePageDisplay();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                } else if (!isValid) {
                    alert('Silakan lengkapi semua field yang wajib diisi.');
                }
            });

            // Initialize
            updatePageDisplay();
        });

        // Handle appreciation dialog and result display
        document.addEventListener('DOMContentLoaded', function() {
            const appreciationDialog = document.getElementById('appreciation-dialog');
            const resultContainer = document.getElementById('result-container');
            const successView = document.getElementById('success-view');
            const successMessage = document.getElementById('success-message');
            
            // Check if form was just submitted (has result_data or status)
            @php
            $hasResultData = session('result_data') ? true : false;
            $hasStatus = session('status') ? true : false;
            @endphp
            const hasResultData = {{ $hasResultData ? 'true' : 'false' }};
            const hasStatus = {{ $hasStatus ? 'true' : 'false' }};
            
            if (hasResultData || hasStatus) {
                // Show appreciation dialog
                if (appreciationDialog) {
                    appreciationDialog.classList.remove('hidden');
                    
                    // Hide dialog and show result/success after 2.5 seconds
                    setTimeout(function() {
                        appreciationDialog.classList.add('hidden');
                        
                        // Show result if exists
                        if (hasResultData && resultContainer) {
                            resultContainer.classList.remove('hidden');
                        }
                        
                        // Show success view if no result data
                        if (!hasResultData && successView) {
                            successView.classList.remove('hidden');
                        }
                        
                        // Show success message if exists
                        if (successMessage) {
                            successMessage.classList.remove('hidden');
                        }
                        
                        // Scroll to top
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }, 2500);
                } else {
                    // If dialog doesn't exist, show result/success immediately
                    if (hasResultData && resultContainer) {
                        resultContainer.classList.remove('hidden');
                    }
                    if (!hasResultData && successView) {
                        successView.classList.remove('hidden');
                    }
                    if (successMessage) {
                        successMessage.classList.remove('hidden');
                    }
                }
            }
        });
    </script>
</body>

</html>