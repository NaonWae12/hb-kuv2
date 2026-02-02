@extends('layouts.form-builder')

@section('title', 'Formulir Baru - hb-ku')

@section('content')
@php
$responsesStats = $responsesStats ?? [
'total_responses' => 0,
'question_count' => 0,
'latest_response_at' => null,
];
@endphp
<div class="min-h-screen bg-rose-50" id="form-builder-root"
    data-initial='{!! htmlspecialchars(json_encode($formData ? array_merge($formData, [' rule_groups'=> $formData['rule_groups'] ?? []]) : ['rule_groups' => []]), ENT_QUOTES, 'UTF-8', false) !!}'
    data-mode="{{ $formMode ?? 'create' }}"
    data-form-id="{{ $formId }}"
    data-share-url="{{ $shareUrl ?? '' }}"
    data-responses-url="{{ $formId ? route('forms.responses.data', $formId) : '' }}"
    data-total-responses="{{ $responsesStats['total_responses'] }}"
    data-saved-rules='{!! htmlspecialchars(json_encode($savedRules ?? []), ENT_QUOTES, 'UTF-8', false) !!}'>
    <!-- Top Bar dengan tombol -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Back Button -->
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-2 text-gray-700 hover:text-red-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span class="text-sm font-medium">Kembali</span>
                </a>

                <!-- Action Buttons -->
                <div class="flex items-center space-x-3">
                    <!-- Header Setup Button (only visible in questions tab) -->
                    <button id="header-setup-btn" type="button"
                        class="header-setup-btn items-center space-x-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors hidden">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-sm font-medium">Header</span>
                    </button>

                    <!-- Share Link Button -->
                    <button id="share-link-btn" type="button"
                        class="flex items-center space-x-2 px-4 py-2 bg-white border border-red-600 text-red-600 rounded-lg hover:bg-red-50 transition-colors {{ $shareUrl ? '' : 'hidden' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                        </svg>
                        <span class="text-sm font-medium">Share Link</span>
                    </button>

                    <!-- Save Form Button -->
                    <button id="save-form-btn" class="flex items-center space-x-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                        </svg>
                        <span class="text-sm font-medium">Save Form</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="bg-white border-b border-gray-200 sticky top-16 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <!-- Tab Buttons -->
                <div class="flex space-x-8">
                    <button class="tab-btn active px-4 py-3 text-sm font-medium text-red-600 border-b-2 border-red-600 transition-colors" data-tab="questions">
                        Pertanyaan
                    </button>
                    <button class="tab-btn px-4 py-3 text-sm font-medium text-gray-600 border-b-2 border-transparent hover:text-red-600 hover:border-red-300 transition-colors" data-tab="responses">
                        Jawaban
                    </button>
                    <button class="tab-btn px-4 py-3 text-sm font-medium text-gray-600 border-b-2 border-transparent hover:text-red-600 hover:border-red-300 transition-colors" data-tab="settings">
                        Setelan
                    </button>
                </div>

                <!-- Card Formatting Toolbar (appears when card is active) -->
                <div id="card-formatting-toolbar" class="flex items-center space-x-1 opacity-0 pointer-events-none transition-opacity duration-200 bg-gray-50 rounded-lg px-2 py-1">
                    <!-- Separator -->
                    <div class="w-px h-6 bg-gray-300 mx-1"></div>

                    <!-- Text Alignment -->
                    <div class="relative group">
                        <button class="card-toolbar-btn p-2 text-gray-700 hover:text-red-600 hover:bg-white rounded transition-colors" title="Perataan Teks" data-tool="text-align">
                            <svg id="text-align-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8M4 18h8"></path>
                            </svg>
                        </button>
                        <div class="card-toolbar-dropdown hidden absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg p-1 z-50 min-w-[160px]">
                            <button class="text-align-option w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded flex items-center space-x-2" data-value="left">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8M4 18h8"></path>
                                </svg>
                                <span>Rata Kiri</span>
                            </button>
                            <button class="text-align-option w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded flex items-center space-x-2" data-value="center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6h12M4 10h16M6 14h12M4 18h16"></path>
                                </svg>
                                <span>Rata Tengah</span>
                            </button>
                            <button class="text-align-option w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded flex items-center space-x-2" data-value="right">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M12 17.25h8.25" />
                                </svg>
                                <span>Rata Kanan</span>
                            </button>
                            <button class="text-align-option w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded flex items-center space-x-2" data-value="justify">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                </svg>
                                <span>Rata Kiri Kanan</span>
                            </button>
                        </div>
                    </div>

                    <!-- Separator -->
                    <div class="w-px h-6 bg-gray-300 mx-1"></div>

                    <!-- Font Family -->
                    <div class="relative group">
                        <button class="card-toolbar-btn px-3 py-2 text-gray-700 hover:text-red-600 hover:bg-white rounded transition-colors text-sm font-medium" title="Font" data-tool="font-family">
                            <span class="font-family-display">Arial</span>
                            <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="card-toolbar-dropdown hidden absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg p-2 z-50 min-w-[220px] max-h-[300px] overflow-y-auto">
                            <input type="text" id="font-family-search-input" placeholder="Cari font..." class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg mb-2 focus:outline-none focus:ring-1 focus:ring-red-500 relative z-10">
                            <div id="font-family-list" class="space-y-1">
                                <!-- Font options will be loaded dynamically from Google Fonts -->
                            </div>
                        </div>
                    </div>

                    <!-- Separator -->
                    <div class="w-px h-6 bg-gray-300 mx-1"></div>

                    <!-- Font Size (Manual Input + Template) -->
                    <div class="flex items-center space-x-1">
                        <button class="card-toolbar-btn p-2 text-gray-700 hover:text-red-600 hover:bg-white rounded transition-colors" title="Kurangi Ukuran" data-action="decrease-size">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                            </svg>
                        </button>
                        <div class="relative group">
                            <button class="card-toolbar-btn px-2 py-2 text-gray-700 hover:text-red-600 hover:bg-white rounded transition-colors text-sm font-medium min-w-[50px]" title="Ukuran Font" data-tool="font-size">
                                <span class="font-size-display">12</span>
                                <svg class="w-3 h-3 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="card-toolbar-dropdown hidden absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg p-2 z-50 min-w-[180px]">
                                <div class="mb-2">
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1 block">Ukuran Manual</label>
                                    <input type="number" id="font-size-manual" min="8" max="72" value="12" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500">
                                </div>
                                <div class="border-t border-gray-200 pt-2 mt-2">
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1 block">Template</label>
                                    <div class="space-y-1">
                                        <button class="font-size-template-option w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded" data-value="10">
                                            <span style="font-size: 10px;">Sangat Kecil (10px)</span>
                                        </button>
                                        <button class="font-size-template-option w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded" data-value="12">
                                            <span style="font-size: 12px;">Kecil (12px)</span>
                                        </button>
                                        <button class="font-size-template-option w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded" data-value="14">
                                            <span style="font-size: 14px;">Normal (14px)</span>
                                        </button>
                                        <button class="font-size-template-option w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded" data-value="16">
                                            <span style="font-size: 16px;">Besar (16px)</span>
                                        </button>
                                        <button class="font-size-template-option w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded" data-value="18">
                                            <span style="font-size: 18px;">Sangat Besar (18px)</span>
                                        </button>
                                        <button class="font-size-template-option w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded" data-value="24">
                                            <span style="font-size: 24px;">Judul (24px)</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="card-toolbar-btn p-2 text-gray-700 hover:text-red-600 hover:bg-white rounded transition-colors" title="Tambah Ukuran" data-action="increase-size">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Separator -->
                    <div class="w-px h-6 bg-gray-300 mx-1"></div>

                    <!-- Text Decoration: Bold -->
                    <button class="card-toolbar-btn p-2 text-gray-700 hover:text-red-600 hover:bg-white rounded transition-colors" title="Tebal (Bold)" data-action="bold">
                        <span class="font-bold text-base">B</span>
                    </button>

                    <!-- Text Decoration: Italic -->
                    <button class="card-toolbar-btn p-2 text-gray-700 hover:text-red-600 hover:bg-white rounded transition-colors" title="Miring (Italic)" data-action="italic">
                        <span class="italic text-base">I</span>
                    </button>

                    <!-- Text Decoration: Underline -->
                    <button class="card-toolbar-btn p-2 text-gray-700 hover:text-red-600 hover:bg-white rounded transition-colors" title="Garis Bawah (Underline)" data-action="underline">
                        <span class="underline text-base">U</span>
                    </button>

                    <!-- Separator -->
                    <div class="w-px h-6 bg-gray-300 mx-1"></div>

                    <!-- Reset Button -->
                    <button class="card-toolbar-btn p-2 text-gray-700 hover:text-red-600 hover:bg-white rounded transition-colors" title="Reset ke Default" data-action="reset-formatting">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Tab Content: Pertanyaan -->
        <div id="tab-questions" class="tab-content">
            <!-- Form Header -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="p-6">
                    <!-- Title Input -->
                    <div class="mb-4">
                        <div
                            contenteditable="true"
                            data-placeholder="Judul formulir tanpa judul"
                            class="w-full text-2xl font-normal text-gray-900 border-none outline-none focus:ring-0 pb-2 border-b-2 border-transparent focus:border-red-600 transition-colors empty:before:content-[attr(data-placeholder)] empty:before:text-gray-400"
                            id="form-title"
                            style="min-height: 1.5em;"></div>
                    </div>

                    <!-- Description Input -->
                    <div>
                        <div
                            contenteditable="true"
                            data-placeholder="Deskripsi formulir"
                            class="w-full text-sm text-gray-600 border-none outline-none focus:ring-0 pb-2 border-b-2 border-transparent focus:border-red-600 transition-colors empty:before:content-[attr(data-placeholder)] empty:before:text-gray-400"
                            id="form-description"
                            style="min-height: 1.5em;"></div>
                    </div>
                </div>
            </div>

            <!-- Questions Container -->
            <div id="questions-container" class="space-y-4 relative">
                <!-- Question akan ditambahkan di sini via JavaScript -->
            </div>

            <!-- Add Question Button -->
            <div class="mt-6 flex justify-center space-x-3">
                <button
                    id="add-question-btn"
                    class="flex items-center space-x-2 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Tambah pertanyaan</span>
                </button>
                <button
                    id="add-section-btn"
                    class="flex items-center space-x-2 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span>Tambahkan bagian</span>
                </button>
            </div>
        </div>

        <!-- Tab Content: Jawaban -->
        <div id="tab-responses" class="tab-content hidden">
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
                        <p class="text-sm text-gray-500">Total Jawaban</p>
                        <p id="builder-total-responses" class="text-3xl font-semibold text-gray-900 mt-1">{{ $responsesStats['total_responses'] }}</p>
                        <p class="text-xs text-gray-500 mt-2">Jawaban yang sudah terkumpul</p>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
                        <p class="text-sm text-gray-500">Pertanyaan Aktif</p>
                        <p id="builder-question-count" class="text-3xl font-semibold text-gray-900 mt-1">{{ $responsesStats['question_count'] }}</p>
                        <p class="text-xs text-gray-500 mt-2">Pertanyaan yang ditampilkan ke responden</p>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
                        <p class="text-sm text-gray-500">Jawaban Terbaru</p>
                        <p id="builder-latest-response" class="text-xl font-semibold text-gray-900 mt-1">{{ $responsesStats['latest_response_at'] ?? 'Belum ada data' }}</p>
                        @if($formId)
                        <a href="{{ route('forms.responses', $formId) }}" class="inline-flex items-center text-sm font-medium text-red-600 hover:text-red-700 mt-3">
                            Lihat halaman jawaban lengkap
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                        @else
                        <p class="text-xs text-gray-500 mt-2">Simpan form untuk mulai menerima jawaban.</p>
                        @endif
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6">
                        <div class="flex space-x-8">
                            <button id="builder-summary-tab" type="button" class="builder-response-tab px-4 py-4 text-sm font-medium text-red-600 border-b-2 border-red-600">
                                Summary
                            </button>
                            <button id="builder-individual-tab" type="button" class="builder-response-tab px-4 py-4 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-red-600">
                                Individual
                            </button>
                        </div>
                        @if($formId && $responsesStats['total_responses'] > 0)
                        <div class="flex items-center gap-2">
                            <a href="{{ route('forms.responses.export.summary', $formId) }}" id="builder-export-summary-btn" class="inline-flex items-center space-x-2 px-4 py-2 text-sm font-medium text-red-600 border border-red-600 rounded-lg hover:bg-red-50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <span>Export Summary</span>
                            </a>
                            <div id="builder-export-individual-group" class="relative hidden">
                                <button type="button" id="builder-export-individual-dropdown-btn" class="inline-flex items-center space-x-2 px-4 py-2 text-sm font-medium text-red-600 border border-red-600 rounded-lg hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span>Export Jawaban</span>
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <div id="builder-export-individual-menu" class="hidden absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-lg shadow-xl z-[60]">
                                    <a href="{{ route('forms.responses.export.individual', $formId) }}" class="block px-4 py-3 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 transition-colors border-b border-gray-100">
                                        <p class="font-semibold">Semua Jawaban</p>
                                        <p class="text-xs text-gray-500">Export semua data dalam satu file</p>
                                    </a>
                                    <a href="#" id="builder-export-current-btn" class="block px-4 py-3 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 transition-colors">
                                        <p class="font-semibold">Jawaban Saat Ini</p>
                                        <p class="text-xs text-gray-500">Hanya export data yang sedang dilihat</p>
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="px-6 pb-6">
                        <div id="builder-summary-panel" class="pt-6">
                            <div id="builder-summary-empty" class="{{ $responsesStats['total_responses'] > 0 ? 'hidden' : '' }}">
                                <div class="border-2 border-dashed border-gray-200 rounded-xl p-10 text-center">
                                    <p class="text-lg font-medium text-gray-900 mb-2">Belum ada jawaban</p>
                                    <p class="text-sm text-gray-500">Bagikan link form untuk mulai menerima jawaban responden.</p>
                                </div>
                            </div>
                            <div id="builder-summary-loading" class="hidden">
                                <div class="border border-gray-100 rounded-xl p-6 text-sm text-gray-500 bg-gray-50">Memuat ringkasan jawaban...</div>
                            </div>
                            <div id="builder-summary-content" class="space-y-6 hidden"></div>
                        </div>

                        <div id="builder-individual-panel" class="pt-6 hidden">
                            <div id="builder-individual-empty" class="{{ $responsesStats['total_responses'] > 0 ? 'hidden' : '' }}">
                                <div class="border-2 border-dashed border-gray-200 rounded-xl p-10 text-center">
                                    <p class="text-lg font-medium text-gray-900 mb-2">Belum ada jawaban</p>
                                    <p class="text-sm text-gray-500">Setelah ada respons, Anda dapat menelusuri jawaban satu per satu di sini.</p>
                                </div>
                            </div>
                            <div id="builder-individual-loading" class="hidden">
                                <div class="border border-gray-100 rounded-xl p-6 text-sm text-gray-500 bg-gray-50">Memuat jawaban individu...</div>
                            </div>

                            <!-- Individual Toolbar -->
                            <div id="builder-individual-toolbar" class="hidden mb-6 flex items-center justify-between">
                                <div class="flex items-center bg-gray-100 p-1 rounded-lg">
                                    <button id="view-mode-detail" class="px-4 py-1.5 text-xs font-semibold rounded-md transition-all bg-white text-red-600 shadow-sm">
                                        Detail
                                    </button>
                                    <button id="view-mode-list" class="px-4 py-1.5 text-xs font-semibold rounded-md transition-all text-gray-500 hover:text-gray-700">
                                        List
                                    </button>
                                </div>
                            </div>

                            <!-- List View Container -->
                            <div id="builder-individual-list" class="hidden space-y-3">
                                <!-- List items will be rendered here -->
                            </div>

                            <!-- Detail View Container -->
                            <div id="builder-individual-content" class="space-y-6 hidden">
                                <div class="flex items-center justify-between border border-gray-100 rounded-xl p-5">
                                    <div>
                                        <p class="text-sm text-gray-500">Jawaban ke-<span id="builder-response-position">1</span></p>
                                        <p class="text-lg font-semibold text-gray-900" id="builder-response-email">-</p>
                                        <p class="text-sm text-gray-500" id="builder-response-date">-</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-500">Skor Total</p>
                                        <p class="text-2xl font-bold text-gray-900" id="builder-response-score">-</p>
                                    </div>
                                </div>

                                <div id="builder-response-answers" class="space-y-4"></div>

                                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                                    <button id="builder-prev-response" class="px-4 py-2 text-sm font-medium border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">Sebelumnya</button>
                                    <button id="builder-next-response" class="px-4 py-2 text-sm font-medium border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">Berikutnya</button>
                                </div>
                            </div>
                            <div id="builder-responses-error" class="hidden mt-4 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl p-4"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Setelan -->
        <div id="tab-settings" class="tab-content hidden">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
                <!-- Form Settings -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Pengaturan Formulir</h3>

                    <div class="space-y-4">
                        <!-- Collect Email -->
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="text-sm font-medium text-gray-900">Kumpulkan alamat email</label>
                                <p class="text-xs text-gray-500 mt-1">Mengumpulkan alamat email responden</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="collect-email" name="collect_email" class="sr-only peer" {{ ($formData['collect_email'] ?? false) ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                            </label>
                        </div>

                        <!-- Limit to One Response -->
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="text-sm font-medium text-gray-900">Batasi ke 1 respons</label>
                                <p class="text-xs text-gray-500 mt-1">Membatasi setiap responden hanya bisa mengisi sekali</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="limit-one-response" name="limit_one_response" class="sr-only peer" {{ ($formData['limit_one_response'] ?? false) ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                            </label>
                        </div>

                        <!-- Show Progress Bar -->
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="text-sm font-medium text-gray-900">Tampilkan progress bar</label>
                                <p class="text-xs text-gray-500 mt-1">Menampilkan progress bar di bagian atas form</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="show-progress-bar" name="show_progress_bar" class="sr-only peer" {{ ($formData['show_progress_bar'] ?? true) ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                            </label>
                        </div>

                        <!-- Shuffle Question Order -->
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="text-sm font-medium text-gray-900">Acak urutan pertanyaan</label>
                                <p class="text-xs text-gray-500 mt-1">Menampilkan pertanyaan dalam urutan acak</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="shuffle-questions" name="shuffle_questions" class="sr-only peer" {{ ($formData['shuffle_questions'] ?? false) ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                            </label>
                        </div>

                        <!-- BMI Formula Toggle -->
                        <div class="pt-4 border-t border-gray-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-sm font-medium text-gray-900">Gunakan Rumus IMT (BMI)</label>
                                    <p class="text-xs text-gray-500 mt-1">Hitung Indeks Massa Tubuh otomatis dari jawaban responden</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="use-bmi-formula" name="use_bmi_formula" class="sr-only peer" {{ ($formData['use_bmi_formula'] ?? false) ? 'checked' : '' }}>
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                                </label>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Rules -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Aturan Form</h3>

                    <div class="space-y-6">
                        <!-- Title Input untuk Aturan -->
                        <div>
                            <label class="text-sm font-medium text-gray-900 mb-2 block">Judul Aturan</label>
                            <input type="text" id="rule-group-title-input" placeholder="Contoh: Aturan Penilaian Kinerja" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none">
                            <p class="text-xs text-gray-500 mt-1">Judul ini akan membantu Anda memilih aturan saat setup hasil di halaman pertanyaan</p>
                        </div>

                        <!-- Template Jawaban dengan Skor -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">Template Jawaban & Skor</h4>
                                    <p class="text-xs text-gray-500 mt-1">Buat template jawaban untuk pertanyaan pilihan ganda beserta skor masing-masing</p>
                                </div>
                                <button id="add-answer-template-btn" class="px-3 py-1.5 text-sm font-medium text-red-600 border border-red-600 rounded-lg hover:bg-red-50 transition-colors">
                                    + Tambah Jawaban
                                </button>
                            </div>

                            <div id="answer-templates-container" class="space-y-3 mt-4">
                                <!-- Answer templates akan ditambahkan di sini -->
                                <div class="text-sm text-gray-500 italic">Belum ada template jawaban. Klik "Tambah Jawaban" untuk menambahkan.</div>
                            </div>
                        </div>

                        <!-- Result Rules berdasarkan Range -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">Aturan Hasil Berdasarkan Range Skor</h4>
                                    <p class="text-xs text-gray-500 mt-1">Tentukan hasil yang ditampilkan berdasarkan total skor</p>
                                </div>
                                <button id="add-result-rule-btn" class="px-3 py-1.5 text-sm font-medium text-red-600 border border-red-600 rounded-lg hover:bg-red-50 transition-colors">
                                    + Tambah Aturan
                                </button>
                            </div>

                            <div id="result-rules-container" class="space-y-4 mt-4">
                                <!-- Result rules akan ditambahkan di sini -->
                                <div class="text-sm text-gray-500 italic">Belum ada aturan hasil. Klik "Tambah Aturan" untuk menambahkan.</div>
                            </div>

                            <div class="mt-6 flex items-center justify-between">
                                <p class="text-xs text-gray-500 max-w-md">Simpan aturan agar bisa dipakai ulang pada pertanyaan pilihan ganda.</p>
                                <div class="flex items-center space-x-3">
                                    <button id="save-form-rules-btn" hidden class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                                        Simpan Aturan
                                    </button>
                                    <button id="cancel-form-rules-btn" hidden type="button" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                                        Batal
                                    </button>
                                </div>
                            </div>

                            <div id="saved-rules-container" class="mt-4 space-y-2 hidden">
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">Aturan Tersimpan</p>
                                <div id="saved-rules-chips" class="flex flex-wrap gap-2">
                                    <!-- Chips akan ditambahkan melalui JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Question Types Menu (akan muncul saat klik add question) -->
<div id="question-types-menu" class="hidden fixed inset-0 z-50 bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Pilih jenis pertanyaan</h3>
        </div>
        <div class="p-2">
            <button class="question-type-btn w-full text-left px-4 py-3 hover:bg-gray-100 rounded-md transition-colors" data-type="short-answer">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">Jawaban singkat</span>
                </div>
            </button>
            <button class="question-type-btn w-full text-left px-4 py-3 hover:bg-gray-100 rounded-md transition-colors" data-type="paragraph">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">Paragraf</span>
                </div>
            </button>
            <button class="question-type-btn w-full text-left px-4 py-3 hover:bg-gray-100 rounded-md transition-colors" data-type="multiple-choice">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">Pilihan ganda</span>
                </div>
            </button>
            <button class="question-type-btn w-full text-left px-4 py-3 hover:bg-gray-100 rounded-md transition-colors" data-type="checkbox">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">Kotak centang</span>
                </div>
            </button>
            <button class="question-type-btn w-full text-left px-4 py-3 hover:bg-gray-100 rounded-md transition-colors" data-type="dropdown">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">Dropdown</span>
                </div>
            </button>
        </div>
    </div>
</div>

<!-- Header Setup Modal -->
<div id="header-setup-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50 px-4">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-gray-900">Setup Header</h3>
                <button type="button" id="header-setup-close" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Preview Header -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                <div id="header-preview" class="w-full h-48 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 overflow-hidden relative" style="background-size: cover; background-position: center; background-repeat: no-repeat;">
                    <div class="absolute inset-0 flex items-center justify-center text-gray-400">
                        <div class="text-center">
                            <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <p class="text-sm">Preview Header</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Image Source Selection -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">Pilih Sumber Gambar</label>
                <div class="flex space-x-4">
                    <button type="button" id="header-template-btn" class="header-source-btn flex-1 px-4 py-3 border-2 border-red-600 bg-red-50 text-red-600 rounded-lg font-medium hover:bg-red-100 transition-colors">
                        Template
                    </button>
                    <button type="button" id="header-upload-btn" class="header-source-btn flex-1 px-4 py-3 border-2 border-gray-300 bg-white text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors">
                        Upload Sendiri
                    </button>
                </div>
            </div>

            <!-- Template Selection -->
            <div id="header-template-section" class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">Pilih Template</label>
                <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    <!-- Template images will be loaded dynamically -->
                </div>
            </div>

            <!-- Upload Section -->
            <div id="header-upload-section" class="mb-6 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-3">Upload Gambar</label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                    <input type="file" id="header-image-upload" accept="image/*" class="hidden">
                    <label for="header-image-upload" class="cursor-pointer">
                        <svg class="w-12 h-12 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <p class="text-sm text-gray-600">Klik untuk memilih gambar</p>
                        <p class="text-xs text-gray-500 mt-1">PNG, JPG, atau GIF (maks. 5MB)</p>
                    </label>
                </div>
                <div id="header-upload-preview" class="mt-4 hidden">
                    <img id="header-upload-preview-img" src="" alt="Preview" class="max-w-full h-32 object-contain rounded-lg border border-gray-300">
                </div>
            </div>

            <!-- Image Mode Selection -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">Mode Gambar</label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <button type="button" class="header-mode-btn px-4 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-red-600 hover:text-red-600 transition-colors" data-mode="stretch">
                        Stretch
                    </button>
                    <button type="button" class="header-mode-btn px-4 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-red-600 hover:text-red-600 transition-colors" data-mode="cover">
                        Cover
                    </button>
                    <button type="button" class="header-mode-btn px-4 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-red-600 hover:text-red-600 transition-colors" data-mode="contain">
                        Contain
                    </button>
                    <button type="button" class="header-mode-btn px-4 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-red-600 hover:text-red-600 transition-colors" data-mode="repeat">
                        Repeat
                    </button>
                    <button type="button" class="header-mode-btn px-4 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-red-600 hover:text-red-600 transition-colors" data-mode="center">
                        Center
                    </button>
                    <button type="button" class="header-mode-btn px-4 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-red-600 hover:text-red-600 transition-colors" data-mode="no-repeat">
                        No Repeat
                    </button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3">
                <button type="button" id="header-remove-btn" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-red-600 transition-colors">
                    Hapus Header
                </button>
                <button type="button" id="header-cancel-btn" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                    Batal
                </button>
                <button type="button" id="header-save-btn" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                    Simpan
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
@php
$templateImages = [];
// Use header-templates folder if exists, otherwise fallback to images folder with bc_ prefix
$templatePath = public_path('assets/images/header-templates');
$fallbackPath = public_path('assets/images');

$useTemplateFolder = is_dir($templatePath);
$imagePath = $useTemplateFolder ? $templatePath : $fallbackPath;
$assetPath = $useTemplateFolder ? 'assets/images/header-templates' : 'assets/images';

if (is_dir($imagePath)) {
$files = scandir($imagePath);
$counter = 1;
foreach($files as $file) {
if ($file !== '.' && $file !== '..') {
// If using template folder, accept all image files. Otherwise, only files with 'bc_' prefix
$isTemplate = $useTemplateFolder || strpos($file, 'bc_') === 0;
if ($isTemplate) {
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
$templateImages[] = [
'name' => 'Background '.$counter,
'path' => asset($assetPath.'/'.$file)
];
$counter++;
}
}
}
}
}
@endphp
<script type="application/json" id="header-templates-json">
    @json($templateImages)
</script>
<script>
    // Set header template images from assets (only files with prefix 'bc_')
    // Assign to global variable (will override the let declaration in form-builder.js)
    window.HEADER_TEMPLATE_IMAGES = HEADER_TEMPLATE_IMAGES = JSON.parse(document.getElementById('header-templates-json').textContent);

    console.log('Header template images loaded:', HEADER_TEMPLATE_IMAGES);
    console.log('Template images count:', HEADER_TEMPLATE_IMAGES.length);

    document.addEventListener('DOMContentLoaded', function() {
        const summaryTab = document.getElementById('builder-summary-tab');
        const individualTab = document.getElementById('builder-individual-tab');
        const summaryPanel = document.getElementById('builder-summary-panel');
        const individualPanel = document.getElementById('builder-individual-panel');
        const builderExportSummaryBtn = document.getElementById('builder-export-summary-btn');
        const builderExportIndividualGroup = document.getElementById('builder-export-individual-group');

        // Initialize export buttons
        if (builderExportSummaryBtn) {
            builderExportSummaryBtn.classList.remove('hidden');
            builderExportSummaryBtn.classList.add('inline-flex');
        }
        if (builderExportIndividualGroup) {
            builderExportIndividualGroup.classList.add('hidden');
            // Not adding inline-flex to group because it's a relative container
        }

        if (summaryTab && individualTab && summaryPanel && individualPanel) {
            summaryTab.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Update tab styles
                summaryTab.classList.add('text-red-600', 'border-red-600');
                summaryTab.classList.remove('text-gray-500', 'border-transparent');
                individualTab.classList.remove('text-red-600', 'border-red-600');
                individualTab.classList.add('text-gray-500', 'border-transparent');

                // Update panels
                summaryPanel.classList.remove('hidden');
                individualPanel.classList.add('hidden');

                // Update export buttons
                if (builderExportSummaryBtn) {
                    builderExportSummaryBtn.classList.remove('hidden');
                    builderExportSummaryBtn.classList.add('inline-flex');
                }
                if (builderExportIndividualGroup) {
                    builderExportIndividualGroup.classList.add('hidden');
                }
            });

            individualTab.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Update tab styles
                individualTab.classList.add('text-red-600', 'border-red-600');
                individualTab.classList.remove('text-gray-500', 'border-transparent');
                summaryTab.classList.remove('text-red-600', 'border-red-600');
                summaryTab.classList.add('text-gray-500', 'border-transparent');

                // Update panels
                individualPanel.classList.remove('hidden');
                summaryPanel.classList.add('hidden');

                // Update export buttons
                if (builderExportSummaryBtn) {
                    builderExportSummaryBtn.classList.add('hidden');
                    builderExportSummaryBtn.classList.remove('inline-flex');
                }
                if (builderExportIndividualGroup) {
                    builderExportIndividualGroup.classList.remove('hidden');
                    // show individual responses data
                    if (window.triggerResponsesFetch) window.triggerResponsesFetch();
                }
            });
        }
    });
</script>
@endpush