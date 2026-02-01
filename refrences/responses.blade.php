@extends('layouts.app')

@section('title', 'Responses - ' . strip_tags($form->title))

@section('content')
<div class="py-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm text-gray-500">Jawaban untuk</p>
                <h1 class="text-3xl font-bold text-gray-900">{{ strip_tags($form->title) }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ strip_tags($form->description) }}</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-100">
                    ← Kembali ke Dashboard
                </a>
                <a href="{{ route('forms.edit', $form->id) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg shadow hover:bg-red-700">
                    Buka Form
                </a>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mb-4">
            <a href="{{ route('forms.export', ['form' => $form->id, 'type' => 'summary']) }}" 
               id="export-summary-btn"
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-red-600 border border-red-600 rounded-lg hover:bg-red-50">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export Summary
            </a>
            <a href="{{ route('forms.export', ['form' => $form->id, 'type' => 'individual']) }}" 
               id="export-individual-btn"
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-red-600 border border-red-600 rounded-lg hover:bg-red-50 hidden">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export Individual
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm text-gray-500">Total Jawaban</p>
                <p class="text-3xl font-semibold text-gray-900 mt-1">{{ $totalResponses }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm text-gray-500">Pertanyaan</p>
                <p class="text-3xl font-semibold text-gray-900 mt-1">{{ $form->questions->count() }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm text-gray-500">Terakhir diperbarui</p>
                <p class="text-xl font-semibold text-gray-900 mt-1">{{ optional($form->updated_at)->format('d M Y H:i') }}</p>
            </div>
        </div>

        <div>
            <div class="flex border-b border-gray-200">
                <button id="tab-summary" class="px-4 py-3 text-sm font-medium border-b-2 border-red-600 text-red-600">
                    Summary
                </button>
                <button id="tab-individual" class="px-4 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-red-600">
                    Individual
                </button>
            </div>

            <div id="summary-panel" class="pt-6">
                @if($totalResponses === 0)
                    <div class="bg-white rounded-xl border-2 border-dashed border-gray-200 p-10 text-center">
                        <p class="text-lg font-medium text-gray-900 mb-2">Belum ada jawaban</p>
                        <p class="text-sm text-gray-500">Bagikan link form kepada responden untuk mulai menerima jawaban.</p>
                    </div>
                @else
                    <div class="space-y-6">
                        @foreach($questionSummaries as $summary)
                            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                                <div class="flex items-start justify-between mb-4">
                                    <div>
                                        <p class="text-sm text-gray-500">Pertanyaan {{ $loop->iteration }}</p>
                                        <h3 class="text-lg font-semibold text-gray-900">{{ strip_tags($summary['title']) }}</h3>
                                    </div>
                                    <span class="text-sm text-gray-500">{{ $summary['total'] }} jawaban</span>
                                </div>

                                @if($summary['chart'])
                                    <div class="h-64">
                                        <canvas id="chart-{{ $summary['id'] }}"></canvas>
                                    </div>
                                @else
                                    <div class="space-y-3">
                                        @if(!empty($summary['text_answers']))
                                            @foreach($summary['text_answers'] as $answer)
                                                <div class="p-3 bg-gray-50 rounded-lg text-sm text-gray-700 border border-gray-100">{{ strip_tags($answer) }}</div>
                                            @endforeach
                                        @else
                                            <p class="text-sm text-gray-500">Belum ada jawaban untuk pertanyaan ini.</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div id="individual-panel" class="pt-6 hidden">
                @if($individualResponses->isEmpty())
                    <div class="bg-white rounded-xl border-2 border-dashed border-gray-200 p-10 text-center">
                        <p class="text-lg font-medium text-gray-900 mb-2">Belum ada jawaban</p>
                        <p class="text-sm text-gray-500">Setelah ada respon, Anda dapat melihat detail jawaban di sini.</p>
                    </div>
                @else
                    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Jawaban ke-<span id="response-position">1</span> dari {{ $individualResponses->count() }}</p>
                                <p class="text-lg font-semibold text-gray-900" id="response-email"></p>
                                <p class="text-sm text-gray-500" id="response-date"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Skor Total</p>
                                <p class="text-2xl font-bold text-gray-900" id="response-score">0</p>
                            </div>
                        </div>

                        <div id="response-answers" class="space-y-4">
                        </div>

                        <div class="flex items-center justify-between">
                            <button id="prev-response" class="px-4 py-2 text-sm font-medium border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">Sebelumnya</button>
                            <button id="next-response" class="px-4 py-2 text-sm font-medium border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">Berikutnya</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if($totalResponses > 0)
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Helper function to strip HTML tags and decode entities
        function stripHTMLAndDecode(htmlString) {
            if (!htmlString || typeof htmlString !== 'string') {
                return htmlString || '';
            }
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = htmlString;
            const textContent = tempDiv.textContent || tempDiv.innerText || '';
            const textarea = document.createElement('textarea');
            textarea.innerHTML = textContent;
            return textarea.value || textContent;
        }

        const summaryData = @json($questionSummaries);
        summaryData.forEach((item) => {
            if (!item.chart) return;

            const ctx = document.getElementById(`chart-${item.id}`);
            if (!ctx) return;

            const colors = ['#F87171', '#FBBF24', '#34D399', '#60A5FA', '#A78BFA', '#F472B6', '#F97316', '#2DD4BF'];
            const colorSet = item.chart.values.map((_, index) => colors[index % colors.length]);

            // Strip HTML from chart labels
            const cleanLabels = item.chart.labels.map(label => stripHTMLAndDecode(label));

            new Chart(ctx, {
                type: item.chart.type,
                data: {
                    labels: cleanLabels,
                    datasets: [{
                        label: 'Jumlah Jawaban',
                        data: item.chart.values,
                        backgroundColor: colorSet,
                        borderColor: '#ffffff',
                        borderWidth: 1,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                    },
                    scales: item.chart.type === 'bar' ? {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                            },
                        },
                    } : {},
                },
            });
        });

        const individualResponses = @json($individualResponses);
        let currentIndex = 0;

        // Helper function to strip HTML tags and decode entities
        function stripHTMLAndDecode(htmlString) {
            if (!htmlString || typeof htmlString !== 'string') {
                return htmlString || '';
            }
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = htmlString;
            const textContent = tempDiv.textContent || tempDiv.innerText || '';
            const textarea = document.createElement('textarea');
            textarea.innerHTML = textContent;
            return textarea.value || textContent;
        }

        function renderResponse(index) {
            const data = individualResponses[index];
            if (!data) return;

            document.getElementById('response-position').textContent = index + 1;
            document.getElementById('response-email').textContent = data.email || 'Anonim';
            document.getElementById('response-date').textContent = data.submitted_at || '-';
            document.getElementById('response-score').textContent = data.total_score ?? '-';

            const container = document.getElementById('response-answers');
            container.innerHTML = '';

            if (data.answers.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'text-sm text-gray-500';
                empty.textContent = 'Tidak ada jawaban yang tersedia.';
                container.appendChild(empty);
            } else {
                data.answers.forEach(answer => {
                    const block = document.createElement('div');
                    block.className = 'p-4 border border-gray-100 rounded-lg';

                    const title = document.createElement('p');
                    title.className = 'text-sm font-medium text-gray-900';
                    title.textContent = stripHTMLAndDecode(answer.question);

                    const value = document.createElement('p');
                    value.className = 'mt-1 text-sm text-gray-700';
                    value.textContent = stripHTMLAndDecode(answer.value || '-');

                    block.appendChild(title);
                    block.appendChild(value);
                    container.appendChild(block);
                });
            }

            document.getElementById('prev-response').disabled = index === 0;
            document.getElementById('next-response').disabled = index === individualResponses.length - 1;
        }

        const prevButton = document.getElementById('prev-response');
        const nextButton = document.getElementById('next-response');

        if (prevButton && nextButton) {
            prevButton.addEventListener('click', () => {
                if (currentIndex > 0) {
                    currentIndex -= 1;
                    renderResponse(currentIndex);
                }
            });

            nextButton.addEventListener('click', () => {
                if (currentIndex < individualResponses.length - 1) {
                    currentIndex += 1;
                    renderResponse(currentIndex);
                }
            });

            if (individualResponses.length > 0) {
                renderResponse(0);
            }
        }
    </script>
@endif

<script>
    const summaryTab = document.getElementById('tab-summary');
    const individualTab = document.getElementById('tab-individual');
    const summaryPanel = document.getElementById('summary-panel');
    const individualPanel = document.getElementById('individual-panel');

    summaryTab.addEventListener('click', () => {
        summaryTab.classList.add('border-red-600', 'text-red-600');
        summaryTab.classList.remove('text-gray-500');
        individualTab.classList.remove('border-red-600', 'text-red-600');
        individualTab.classList.add('text-gray-500');

        summaryPanel.classList.remove('hidden');
        individualPanel.classList.add('hidden');
    });

    individualTab.addEventListener('click', () => {
        individualTab.classList.add('border-red-600', 'text-red-600');
        individualTab.classList.remove('text-gray-500');
        summaryTab.classList.remove('border-red-600', 'text-red-600');
        summaryTab.classList.add('text-gray-500');

        individualPanel.classList.remove('hidden');
        summaryPanel.classList.add('hidden');
        
        // Toggle export buttons
        const exportSummaryBtn = document.getElementById('export-summary-btn');
        const exportIndividualBtn = document.getElementById('export-individual-btn');
        if (exportSummaryBtn) exportSummaryBtn.classList.add('hidden');
        if (exportIndividualBtn) exportIndividualBtn.classList.remove('hidden');
    });
</script>
@endpush

