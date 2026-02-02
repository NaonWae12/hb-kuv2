// Form Builder JavaScript
console.log('Form Builder JS loaded');
let questionCounter = 0;
let sectionCounter = 0;
let requestBuilderResponsesData = null;
let chartJsLoadingPromise = null;

let formRuleGroups = {}; // Store rule group titles
const dragAndDropState = {
    container: null,
    source: null,
    placeholder: null,
};

function getMetaContent(name) {
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') || '';
}

function setMetaContent(name, value) {
    const meta = document.querySelector(`meta[name="${name}"]`);
    if (meta) {
        meta.setAttribute('content', value);
    }
}

function normalizeMediaUrl(src) {
    if (!src) {
        return '';
    }

    if (/^(https?:)?\/\//i.test(src) || src.startsWith('data:')) {
        return src;
    }

    const trimmed = src.replace(/^\/+/, '');
    return `${window.location.origin}/${trimmed}`;
}

function getAnswerTemplatesPlaceholder() {
    return '<div class="answer-templates-placeholder text-sm text-gray-500 italic">Belum ada template jawaban. Klik "Tambah Jawaban" untuk menambahkan.</div>';
}

function getResultRulesPlaceholder() {
    return '<div class="result-rules-placeholder text-sm text-gray-500 italic">Belum ada aturan hasil. Klik "Tambah Aturan" untuk menambahkan.</div>';
}

// Function to update main buttons visibility
function updateMainButtonsVisibility() {
    const addQuestionBtn = document.getElementById('add-question-btn');
    const addSectionBtn = document.getElementById('add-section-btn');
    const buttonsContainer = addQuestionBtn?.parentElement;
    const questionsContainer = document.getElementById('questions-container');

    if (!questionsContainer) return;

    const questionCards = questionsContainer.querySelectorAll('.question-card');
    const hasQuestions = questionCards.length > 0;

    if (buttonsContainer) {
        if (hasQuestions) {
            buttonsContainer.classList.add('hidden');
        } else {
            buttonsContainer.classList.remove('hidden');
        }
    }
}

function resetFormRulesBuilder() {
    const answerTemplatesContainer = document.getElementById('answer-templates-container');
    if (answerTemplatesContainer) {
        answerTemplatesContainer.innerHTML = getAnswerTemplatesPlaceholder();
    }

    const resultRulesContainer = document.getElementById('result-rules-container');
    if (resultRulesContainer) {
        resultRulesContainer.innerHTML = getResultRulesPlaceholder();
    }

    const ruleGroupTitleInput = document.getElementById('rule-group-title-input');
    if (ruleGroupTitleInput) {
        ruleGroupTitleInput.value = '';
    }

    answerTemplateCounter = 0;
    resultRuleCounter = 0;
    updateRuleSaveControlsVisibility();
}

function setResultSettingTextValues(card, textData = []) {
    const display = card.querySelector('.result-setting-text-display');
    if (!display) {
        return;
    }

    // textData should be array of objects: [{result_rule_text_id, result_rule_id, result_text, title, image, image_url}]
    const sanitized = Array.isArray(textData) ? textData.filter(item => item && item.result_text) : [];

    if (!sanitized.length) {
        display.innerHTML = '<p class="text-sm text-gray-400 italic">Pilih aturan untuk melihat teks hasil.</p>';
    } else {
        // Group texts by result_rule_id
        const groupedByRuleId = sanitized.reduce((acc, item) => {
            const ruleId = item.result_rule_id || 'unknown';
            if (!acc[ruleId]) {
                acc[ruleId] = [];
            }
            acc[ruleId].push(item);
            return acc;
        }, {});

        // Render grouped containers
        display.innerHTML = Object.entries(groupedByRuleId)
            .map(([ruleId, texts], containerIndex) => {
                // Render individual forms for each text in this rule group
                const formsHTML = texts
                    .map((item, textIndex) => {
                        const resultRuleTextId = item.result_rule_text_id || item.id || `text-${textIndex}`;
                        const title = item.title || '';
                        const image = item.image || item.image_url || '';
                        const normalizedImage = normalizeMediaUrl(image);
                        const resultTextRaw = item.result_text ?? item.text ?? '';
                        const resultText = typeof resultTextRaw === 'string'
                            ? resultTextRaw
                            : extractResultTextValue(resultTextRaw);

                        return `
                            <div class="result-text-form border border-gray-200 rounded-lg p-4 bg-white ${textIndex > 0 ? 'mt-4' : ''}" data-result-rule-text-id="${resultRuleTextId}" data-result-rule-id="${ruleId}">
                                <!-- Title Input -->
                                <div class="mb-3">
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1 block">Judul (opsional)</label>
                                    <input type="text" class="result-text-form-title w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500" placeholder="Masukkan judul" value="${title}">
                                </div>
                                
                                <!-- Image Upload -->
                                <div class="mb-3">
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1 block">Gambar (opsional)</label>
                                    <div class="result-text-form-image-area ${normalizedImage ? '' : 'hidden'} mb-2">
                                        <div class="relative inline-block">
                                            <img src="${normalizedImage}" alt="Text form image" class="result-text-form-image max-w-full h-auto rounded-lg border border-gray-200" style="max-height: 200px;">
                                            <button type="button" class="remove-result-text-form-image-btn absolute top-2 right-2 p-1.5 bg-red-600 text-white rounded-full hover:bg-red-700 transition-colors" title="Hapus gambar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <input type="file" accept="image/*" class="hidden result-text-form-image-file-input" data-result-rule-text-id="${resultRuleTextId}">
                                    <input type="hidden" class="result-text-form-image-value" data-result-rule-text-id="${resultRuleTextId}" value="${image}">
                                    <button type="button" class="add-result-text-form-image-btn px-3 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                        ${image ? 'Ganti Gambar' : 'Tambahkan Gambar'}
                                    </button>
                                </div>
                                
                                <!-- Result Text (ReadOnly) -->
                                <div>
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1 block">Teks Hasil</label>
                                    <textarea readonly class="result-text-form-text w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 text-gray-700 resize-none" rows="3">${resultText}</textarea>
                                </div>
                            </div>
                        `;
                    })
                    .join('');

                // Container untuk setiap result_rule_id
                return `
                    <div class="result-rule-container border-2 border-blue-200 rounded-lg p-4 mb-4 bg-blue-50" data-result-rule-id="${ruleId}">
                        <div class="text-xs font-semibold text-blue-700 mb-3 flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Aturan ${ruleId !== 'unknown' ? ruleId : 'Tidak Diketahui'}</span>
                            <span class="text-blue-500 font-normal">(${texts.length} teks)</span>
                        </div>
                        ${formsHTML}
                    </div>
                `;
            })
            .join('');

        // Attach events to image upload buttons
        display.querySelectorAll('.add-result-text-form-image-btn').forEach(btn => {
            const formContainer = btn.closest('.result-text-form');
            const fileInput = formContainer.querySelector('.result-text-form-image-file-input');
            const imageValueInput = formContainer.querySelector('.result-text-form-image-value');
            const imageArea = formContainer.querySelector('.result-text-form-image-area');
            const imageEl = formContainer.querySelector('.result-text-form-image');
            const removeBtn = formContainer.querySelector('.remove-result-text-form-image-btn');

            if (fileInput && imageValueInput) {
                btn.addEventListener('click', () => fileInput.click());

                fileInput.addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    const reader = new FileReader();
                    reader.onload = function (event) {
                        const base64 = event.target.result;
                        imageValueInput.value = base64;
                        if (imageEl) imageEl.src = base64;
                        if (imageArea) imageArea.classList.remove('hidden');
                        btn.textContent = 'Ganti Gambar';
                    };
                    reader.readAsDataURL(file);
                });
            }

            if (removeBtn && imageArea && imageValueInput && imageEl) {
                removeBtn.addEventListener('click', function () {
                    imageValueInput.value = '';
                    imageEl.src = '';
                    imageArea.classList.add('hidden');
                    btn.textContent = 'Tambahkan Gambar';
                });
            }
        });
    }

    // Store data for form submission
    card.dataset.resultTexts = JSON.stringify(sanitized);
}

function extractResultTextValue(text) {
    if (!text) {
        return '';
    }
    if (typeof text === 'string') {
        return text;
    }
    if (typeof text === 'object') {
        if (typeof text.result_text === 'string') {
            return text.result_text;
        }
        if (typeof text.text === 'string') {
            return text.text;
        }
    }
    return '';
}

function normalizeResultRuleTexts(texts) {
    if (!Array.isArray(texts)) {
        return [];
    }
    return texts
        .map(extractResultTextValue)
        .filter((value) => typeof value === 'string' && value.trim() !== '');
}

function cloneResultTextItems(items = []) {
    const timestamp = Date.now();
    return items
        .map((item, index) => {
            if (!item) {
                return null;
            }

            if (typeof item === 'string') {
                const text = item.trim();
                if (!text) {
                    return null;
                }
                return {
                    result_rule_text_id: `lookup-${timestamp}-${index}`,
                    result_text: text,
                    title: null,
                    image: null,
                    text_alignment: 'center',
                    image_alignment: 'center',
                };
            }

            if (typeof item === 'object') {
                const text = item.result_text || item.text || '';
                if (!text || typeof text !== 'string' || text.trim() === '') {
                    return null;
                }

                return {
                    result_rule_text_id: item.result_rule_text_id
                        || item.id
                        || item.temp_id
                        || `lookup-${timestamp}-${index}`,
                    result_rule_id: item.result_rule_id || null, // Tambahkan result_rule_id jika ada
                    result_text: text,
                    title: item.title || null,
                    image: item.image || item.image_url || null,
                    text_alignment: item.text_alignment || 'center',
                    image_alignment: item.image_alignment || 'center',
                };
            }

            return null;
        })
        .filter(Boolean);
}

function buildRuleGroupTextLookup(data) {
    const lookup = {};
    if (!data || !Array.isArray(data.result_rules)) {
        return lookup;
    }

    data.result_rules.forEach((rule) => {
        const groupId = rule.rule_group_id;
        if (!groupId) {
            return;
        }

        // Clone texts and add result_rule_id to each text
        const texts = (rule.texts || []).map((text) => {
            const cloned = typeof text === 'object'
                ? { ...text, result_rule_id: rule.id || null }
                : text;
            return cloned;
        });

        const clonedTexts = cloneResultTextItems(texts);
        if (!clonedTexts.length) {
            return;
        }

        if (!lookup[groupId]) {
            lookup[groupId] = [];
        }

        // Merge texts instead of replacing (in case multiple rules have same groupId)
        lookup[groupId] = [...(lookup[groupId] || []), ...clonedTexts];
    });

    return lookup;
}

function updateRuleGroupTextLookup(ruleGroupId, items = []) {
    if (!ruleGroupId) {
        return;
    }

    const clones = cloneResultTextItems(items);
    if (!clones.length) {
        return;
    }

    // Merge dengan data yang sudah ada (jika ada) untuk memastikan tidak ada yang terlewat
    // Tapi untuk form yang sudah ada di database, lookup sudah lengkap dari initialData
    // Jadi ini lebih untuk form baru yang belum disimpan
    if (ruleGroupTextLookup[ruleGroupId] && ruleGroupTextLookup[ruleGroupId].length > 0) {
        // Merge: gabungkan dengan data yang sudah ada, hindari duplikat berdasarkan result_rule_text_id
        const existingIds = new Set(ruleGroupTextLookup[ruleGroupId].map(item => item.result_rule_text_id));
        const newItems = clones.filter(item => !existingIds.has(item.result_rule_text_id));
        ruleGroupTextLookup[ruleGroupId] = [...ruleGroupTextLookup[ruleGroupId], ...newItems];
    } else {
        // Jika belum ada, langsung set
        ruleGroupTextLookup[ruleGroupId] = clones;
    }
}

function getRuleGroupTextFromLookup(ruleGroupId) {
    if (!ruleGroupId || !ruleGroupTextLookup[ruleGroupId]) {
        return [];
    }

    return cloneResultTextItems(ruleGroupTextLookup[ruleGroupId]);
}

function enterRulesEditMode(rule) {
    editingRuleGroupId = rule?.rule_group_id || null;
    const saveBtn = document.getElementById('save-form-rules-btn');
    const cancelBtn = document.getElementById('cancel-form-rules-btn');
    if (saveBtn) {
        saveBtn.textContent = 'Update Aturan';
        saveBtn.hidden = false;
    }
    if (cancelBtn) {
        cancelBtn.hidden = false;
    }
}

function exitRulesEditMode() {
    editingRuleGroupId = null;
    const saveBtn = document.getElementById('save-form-rules-btn');
    const cancelBtn = document.getElementById('cancel-form-rules-btn');
    if (saveBtn) {
        saveBtn.textContent = defaultSaveRulesLabel;
    }
    if (cancelBtn) {
        cancelBtn.hidden = true;
    }
}

function ensureChartJsLoaded() {
    if (window.Chart) {
        return Promise.resolve();
    }

    if (!chartJsLoadingPromise) {
        chartJsLoadingPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Gagal memuat Chart.js'));
            document.head.appendChild(script);
        });
    }

    return chartJsLoadingPromise;
}

window.triggerResponsesFetch = triggerResponsesFetch;
function triggerResponsesFetch() {
    if (typeof requestBuilderResponsesData === 'function') {
        requestBuilderResponsesData();
    }
}

function enableDragHandle(element) {
    if (!element || element.dataset.dragHandleInit === 'true') {
        return;
    }

    const handle = element.querySelector('[data-drag-handle]');
    if (!handle) {
        return;
    }

    element.dataset.dragHandleInit = 'true';
    element.dataset.dragReady = 'false';
    element.dataset.dragging = 'false';
    element.setAttribute('draggable', 'true');

    handle.setAttribute('draggable', 'false');
    handle.addEventListener('dragstart', (event) => event.preventDefault());

    const enable = () => {
        element.dataset.dragReady = 'true';
    };

    const disable = () => {
        if (element.dataset.dragging !== 'true') {
            element.dataset.dragReady = 'false';
        }
    };

    ['mousedown', 'touchstart'].forEach((eventName) => {
        handle.addEventListener(eventName, enable, { passive: true });
    });

    ['mouseup', 'touchend', 'touchcancel'].forEach((eventName) => {
        handle.addEventListener(eventName, disable, { passive: true });
    });
}

function createDragPlaceholder(target) {
    const isSection = target.classList.contains('section-divider');
    const placeholder = document.createElement('div');
    placeholder.className = isSection
        ? 'section-divider placeholder border-2 border-dashed border-red-300 rounded-lg my-2'
        : 'question-card placeholder border-2 border-dashed border-red-300 rounded-lg my-2';
    placeholder.style.height = `${target.getBoundingClientRect().height}px`;
    placeholder.style.backgroundColor = '#fff';
    return placeholder;
}

function handleDragStart(event) {
    const target = event.currentTarget;
    dragAndDropState.source = target;
    target.classList.add('opacity-60', 'ring-2', 'ring-red-300');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', '');

    dragAndDropState.placeholder = createDragPlaceholder(target);
}

function handleDragEnd() {
    if (dragAndDropState.source) {
        dragAndDropState.source.classList.remove('opacity-60', 'ring-2', 'ring-red-300');
    }
    if (dragAndDropState.placeholder && dragAndDropState.placeholder.parentNode) {
        dragAndDropState.placeholder.parentNode.removeChild(dragAndDropState.placeholder);
    }
    dragAndDropState.source = null;
    dragAndDropState.placeholder = null;
    updateSectionNumbers();
    updateQuestionNumbers();
}

function handleDragOver(event) {
    if (!dragAndDropState.container || !dragAndDropState.placeholder) {
        return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';

    const targetCard = event.target.closest('.question-card, .section-divider');
    const container = dragAndDropState.container;

    if (!targetCard || targetCard === dragAndDropState.source || targetCard === dragAndDropState.placeholder) {
        if (!targetCard && dragAndDropState.placeholder.parentNode !== container) {
            container.appendChild(dragAndDropState.placeholder);
        }
        return;
    }

    const bounding = targetCard.getBoundingClientRect();
    const offset = bounding.y + (bounding.height / 2);
    const parent = targetCard.parentNode || container;

    if (!dragAndDropState.placeholder.parentNode) {
        parent.insertBefore(dragAndDropState.placeholder, targetCard);
    } else if (event.clientY > offset) {
        parent.insertBefore(dragAndDropState.placeholder, targetCard.nextSibling);
    } else {
        parent.insertBefore(dragAndDropState.placeholder, targetCard);
    }
}

function handleDrop(event) {
    event.preventDefault();
    if (dragAndDropState.placeholder && dragAndDropState.source && dragAndDropState.placeholder.parentNode === dragAndDropState.container) {
        dragAndDropState.container.insertBefore(dragAndDropState.source, dragAndDropState.placeholder);
    }
}

function initializeDraggable(element) {
    if (element.dataset.dragEnabled === 'true') {
        return;
    }
    element.dataset.dragEnabled = 'true';
    element.setAttribute('draggable', 'true');
    enableDragHandle(element);
    element.addEventListener('dragstart', handleDragStart);
    element.addEventListener('dragend', handleDragEnd);
}

function refreshDraggableElements(container) {
    container.querySelectorAll('.question-card, .section-divider').forEach(initializeDraggable);
}

function initSortable() {
    const container = document.getElementById('questions-container');
    if (!container) {
        return;
    }

    if (dragAndDropState.container !== container) {
        dragAndDropState.container = container;
        container.addEventListener('dragover', handleDragOver);
        container.addEventListener('drop', handleDrop);
    }

    refreshDraggableElements(container);
}

function renderQuestionsIncrementally({ questions, sections, container, onComplete, batchSize = 3, textFormattingMap = {} }) {
    const questionList = Array.isArray(questions) ? questions : [];
    const sectionList = Array.isArray(sections) ? sections : [];

    if (!container) {
        if (typeof onComplete === 'function') {
            onComplete();
        }
        return;
    }

    let questionIndex = 0;
    let currentSectionIndex = -1;
    const totalQuestions = questionList.length;

    const renderSingleQuestion = (questionData = {}) => {
        if (
            questionData.section_id !== undefined &&
            questionData.section_id !== null &&
            questionData.section_id !== currentSectionIndex
        ) {
            currentSectionIndex = questionData.section_id;
            const sectionInfo = sectionList[currentSectionIndex] || null;
            const sectionDivider = createSectionDivider(sectionInfo?.id);
            container.appendChild(sectionDivider);
            attachSectionEvents(sectionDivider);

            // Using existing sectionInfo
            const sectionTitleInput = sectionDivider.querySelector('.section-title-input');
            const sectionDescInput = sectionDivider.querySelector('.section-description-input');
            const sectionImage = sectionDivider.querySelector('.section-image');
            const sectionImageArea = sectionDivider.querySelector('.section-image-area');
            const sectionImageValueInput = sectionDivider.querySelector('.section-image-value');
            const sectionImageSettings = sectionDivider.querySelector('.section-image-settings');
            const sectionAlignmentSelect = sectionDivider.querySelector('.section-image-alignment-select');
            const sectionWrapModeSelect = sectionDivider.querySelector('.section-image-wrap-mode-select');

            if (sectionTitleInput) {
                // Store the HTML content first before conversion
                const titleValue = sectionInfo && sectionInfo.title
                    ? sectionInfo.title
                    : `Bagian ${currentSectionIndex + 1}`;
                let editableTitle = sectionTitleInput;

                // Convert to contenteditable if it's still an input element (like question title)
                if (sectionTitleInput.tagName === 'INPUT' || sectionTitleInput.tagName === 'TEXTAREA') {
                    // Convert to contenteditable - HTML will be set after conversion
                    editableTitle = convertToContentEditable(sectionTitleInput);
                }

                // Set HTML content (will render HTML properly, not as text)
                if (editableTitle.contentEditable === 'true') {
                    editableTitle.innerHTML = titleValue;
                    // Apply formatting if available - same approach as form_title
                    const formatting = textFormattingMap[`section_title_${currentSectionIndex}`];
                    if (formatting) {
                        applyFormattingToElement(editableTitle, formatting);
                    }
                } else {
                    editableTitle.value = titleValue;
                }
            }

            // Load Section Scoring Settings
            const calculateSubtotalCheckbox = sectionDivider.querySelector('.calculate-subtotal-checkbox');
            const includeInTotalCheckbox = sectionDivider.querySelector('.include-in-total-checkbox');

            if (calculateSubtotalCheckbox) {
                calculateSubtotalCheckbox.checked = sectionInfo ? Boolean(sectionInfo.calculate_subtotal) : false;
            }
            if (includeInTotalCheckbox) {
                includeInTotalCheckbox.checked = (sectionInfo && sectionInfo.include_in_total !== undefined)
                    ? Boolean(sectionInfo.include_in_total)
                    : true;
            }

            if (sectionDescInput && sectionInfo && sectionInfo.description) {
                // Store the HTML content first before conversion
                const descValue = sectionInfo.description;
                let editableDesc = sectionDescInput;

                // Convert to contenteditable if it's still a textarea element (like question title)
                if (sectionDescInput.tagName === 'INPUT' || sectionDescInput.tagName === 'TEXTAREA') {
                    // Convert to contenteditable - HTML will be set after conversion
                    editableDesc = convertToContentEditable(sectionDescInput);
                }

                // Set HTML content (will render HTML properly, not as text)
                if (editableDesc.contentEditable === 'true') {
                    editableDesc.innerHTML = descValue;
                    // Apply formatting if available
                    const formatting = textFormattingMap[`section_description_${currentSectionIndex}`];
                    if (formatting) {
                        applyFormattingToElement(editableDesc, formatting);
                        // Ensure text-align is applied (use setTimeout to ensure it's applied after DOM update)
                        if (formatting.text_align) {
                            setTimeout(() => {
                                editableDesc.style.textAlign = formatting.text_align;
                                // Also ensure display is block for text-align to work properly
                                if (window.getComputedStyle(editableDesc).display === 'inline') {
                                    editableDesc.style.display = 'block';
                                }
                            }, 0);
                        }
                    }
                } else {
                    editableDesc.value = descValue;
                }
            }

            // Handle section image
            const existingImageSrc = sectionInfo?.image_url || sectionInfo?.image || '';
            const normalizedSectionImageSrc = normalizeMediaUrl(existingImageSrc);
            const existingImagePath = sectionInfo?.image || '';

            if (normalizedSectionImageSrc && sectionImage && sectionImageArea) {
                sectionImage.src = normalizedSectionImageSrc;
                sectionImageArea.classList.remove('hidden');
                sectionImageSettings?.classList.remove('hidden');
            }

            if (sectionImageValueInput) {
                let imagePath = existingImagePath;
                if (imagePath && (imagePath.startsWith('http://') || imagePath.startsWith('https://'))) {
                    try {
                        const urlPath = new URL(imagePath).pathname;
                        imagePath = urlPath.startsWith('/') ? urlPath.substring(1) : urlPath;
                    } catch (e) {
                        const match = imagePath.match(/\/storage\/.+$/);
                        if (match) {
                            imagePath = match[0].substring(1);
                        }
                    }
                }
                sectionImageValueInput.value = imagePath || '';
            }

            if (sectionAlignmentSelect) {
                sectionAlignmentSelect.value = sectionInfo?.image_alignment ?? 'center';
            }

            if (sectionWrapModeSelect) {
                sectionWrapModeSelect.value = sectionInfo?.image_wrap_mode ?? 'fixed';
            }

            // Attach section events (this will set up updateImageStyle function and event listeners)
            attachSectionEvents(sectionDivider);

            // Apply image style after loading - trigger change event to apply style
            if (normalizedSectionImageSrc && sectionImage) {
                // Wait a bit for attachSectionEvents to finish setting up
                setTimeout(() => {
                    // Trigger change events to apply style
                    if (sectionAlignmentSelect) {
                        sectionAlignmentSelect.dispatchEvent(new Event('change'));
                    }
                    if (sectionWrapModeSelect) {
                        sectionWrapModeSelect.dispatchEvent(new Event('change'));
                    }
                }, 50);
            }

            const deleteBtn = sectionDivider.querySelector('.delete-section-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function () {
                    sectionDivider.remove();
                    updateSectionNumbers();
                    updateMainButtonsVisibility();
                });
            }
        }

        const questionType = questionData.type || 'short-answer';
        const questionCard = createQuestionCard(questionType, questionData.id);
        container.appendChild(questionCard);

        const titleInput = questionCard.querySelector('.question-title');
        if (titleInput) {
            // Convert to contenteditable if it's still an input element
            // Store the HTML content first before conversion
            const htmlContent = questionData.title || '';
            let editableTitle = titleInput;

            if (titleInput.tagName === 'INPUT' || titleInput.tagName === 'TEXTAREA') {
                // Convert to contenteditable - HTML will be set after conversion
                editableTitle = convertToContentEditable(titleInput);
            }

            // Set HTML content (will render HTML properly, not as text)
            if (editableTitle.contentEditable === 'true') {
                editableTitle.innerHTML = htmlContent;
                // Apply formatting if available
                // Use questionIndex (not questionData.id) because formatting is mapped by index
                const formatting = textFormattingMap[`question_title_${questionIndex}`];
                if (formatting) {
                    applyFormattingToElement(editableTitle, formatting);
                }
            } else {
                editableTitle.value = htmlContent;
            }
        }

        const requiredCheckbox = questionCard.querySelector('.required-checkbox');
        if (requiredCheckbox) {
            requiredCheckbox.checked = Boolean(questionData.is_required);
        }

        const questionImage = questionCard.querySelector('.question-image');
        const imageArea = questionCard.querySelector('.question-image-area');
        const imageValueInput = questionCard.querySelector('.question-image-value');
        const imageSettings = questionCard.querySelector('.question-image-settings');
        const alignmentSelect = questionCard.querySelector('.image-alignment-select');
        const widthRange = questionCard.querySelector('.image-width-range');
        const widthDisplay = questionCard.querySelector('.image-width-display');
        const existingImageSrc = questionData.image_url || questionData.image || '';
        const normalizedImageSrc = normalizeMediaUrl(existingImageSrc);
        const existingImagePath = questionData.image || '';

        // Set image display (use full URL for display)
        if (normalizedImageSrc && questionImage && imageArea) {
            questionImage.src = normalizedImageSrc;
            imageArea.classList.remove('hidden');
            imageSettings?.classList.remove('hidden');
        } else if (questionImage && imageArea) {
            questionImage.removeAttribute('src');
            imageArea.classList.add('hidden');
            imageSettings?.classList.add('hidden');
        }

        // Set image value input (use storage path, not full URL)
        if (imageValueInput) {
            // If image is a full URL, extract the path part
            let imagePath = existingImagePath;
            if (imagePath && (imagePath.startsWith('http://') || imagePath.startsWith('https://'))) {
                try {
                    const urlPath = new URL(imagePath).pathname;
                    imagePath = urlPath.startsWith('/') ? urlPath.substring(1) : urlPath;
                } catch (e) {
                    // If URL parsing fails, try to extract path manually
                    const match = imagePath.match(/\/storage\/.+$/);
                    if (match) {
                        imagePath = match[0].substring(1); // Remove leading /
                    }
                }
            }
            imageValueInput.value = imagePath || '';
        }

        // Set alignment
        if (alignmentSelect) {
            alignmentSelect.value = questionData.image_alignment ?? 'center';
        }

        // Set width
        if (widthRange) {
            const widthValue = questionData.image_width ?? 100;
            widthRange.value = widthValue;
            if (widthDisplay) {
                widthDisplay.textContent = `${widthValue}%`;
            }
        }

        const extraSettings = questionData.extra_settings || {};
        const validationInput = questionCard.querySelector('.question-validation-input');
        const validationMessageInput = questionCard.querySelector('.question-validation-message');
        const extraNotesInput = questionCard.querySelector('.question-extra-notes');
        const minLengthInput = questionCard.querySelector('.question-min-length');
        const maxLengthInput = questionCard.querySelector('.question-max-length');
        const advancedSettingsPanel = questionCard.querySelector('.question-advanced-settings');

        const hasExtraSettings = [
            extraSettings.validation,
            extraSettings.validation_message,
            extraSettings.extra_notes,
            extraSettings.min_length,
            extraSettings.max_length,
        ].some(value => value !== null && value !== undefined && value !== '');

        if (validationInput && extraSettings.validation) {
            validationInput.value = extraSettings.validation;
        }
        if (validationMessageInput && extraSettings.validation_message) {
            validationMessageInput.value = extraSettings.validation_message;
        }
        if (extraNotesInput && extraSettings.extra_notes) {
            extraNotesInput.value = extraSettings.extra_notes;
        }
        if (minLengthInput && extraSettings.min_length !== null && extraSettings.min_length !== undefined) {
            minLengthInput.value = extraSettings.min_length;
        }
        if (maxLengthInput && extraSettings.max_length !== null && extraSettings.max_length !== undefined) {
            maxLengthInput.value = extraSettings.max_length;
        }
        if (hasExtraSettings && advancedSettingsPanel) {
            advancedSettingsPanel.classList.remove('hidden');
        }

        if (['multiple-choice', 'checkbox', 'dropdown'].includes(questionType)) {
            const options = Array.isArray(questionData.options) ? questionData.options : [];
            setQuestionOptions(questionCard, questionType, options);
        }

        if (questionData.saved_rule) {
            applySavedRuleToQuestion(questionCard, questionData.saved_rule);
        }

        attachQuestionCardEvents(questionCard);

        const requiredCheckboxEl = questionCard.querySelector('.required-checkbox');
        if (requiredCheckboxEl) {
            requiredCheckboxEl.dispatchEvent(new Event('change'));
        }
    };

    const processBatch = () => {
        let processed = 0;
        while (questionIndex < totalQuestions && processed < batchSize) {
            renderSingleQuestion(questionList[questionIndex]);
            questionIndex += 1;
            processed += 1;
        }

        if (questionIndex < totalQuestions) {
            requestAnimationFrame(processBatch);
        } else if (typeof onComplete === 'function') {
            onComplete();
        }
    };

    if (totalQuestions === 0) {
        if (typeof onComplete === 'function') {
            onComplete();
        }
        return;
    }

    requestAnimationFrame(processBatch);
    initSortable();
}

function createOptionElementNode(type, text = '', index = 0, templateIndex = null, dbId = null) {
    const optionItem = document.createElement('div');
    optionItem.className = 'option-item flex items-center space-x-2';
    if (dbId) {
        optionItem.setAttribute('data-db-id', dbId);
    }

    if (type === 'multiple-choice') {
        const indicator = document.createElement('span');
        indicator.className = 'w-3 h-3 rounded-full border border-gray-300';
        optionItem.appendChild(indicator);
    } else if (type === 'checkbox') {
        const indicator = document.createElement('span');
        indicator.className = 'w-3 h-3 border border-gray-300 rounded mt-1';
        optionItem.appendChild(indicator);
    } else if (type === 'dropdown') {
        const order = document.createElement('span');
        order.className = 'text-sm text-gray-500 w-4';
        order.textContent = `${index + 1}.`;
        optionItem.appendChild(order);
    }

    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = `Opsi ${index + 1}`;
    input.value = text || '';
    input.className = 'flex-1 px-0 py-1 text-sm text-gray-500 border-none border-b border-transparent focus:border-red-600 focus:outline-none transition-colors option-input';
    if (templateIndex !== null && templateIndex !== undefined && templateIndex !== '') {
        input.dataset.templateIndex = templateIndex;
        optionItem.dataset.templateIndex = templateIndex;
    } else {
        delete input.dataset.templateIndex;
        optionItem.removeAttribute('data-template-index');
    }
    optionItem.appendChild(input);

    const removeBtn = document.createElement('button');
    removeBtn.className = 'remove-option-btn p-1 text-gray-400 hover:text-red-600 transition-colors opacity-0 group-hover:opacity-100';
    removeBtn.title = 'Hapus opsi';
    removeBtn.innerHTML = `
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    `;
    optionItem.appendChild(removeBtn);

    return optionItem;
}

function setQuestionOptions(card, questionType, options) {
    const container = card.querySelector('[data-option-container="true"]');
    if (!container) {
        return;
    }

    const controls = container.querySelector('.option-controls');
    const savedRulesWrapper = container.querySelector('.use-saved-rules-wrapper');
    const insertBeforeNode = controls || savedRulesWrapper || null;

    const existingOptions = container.querySelectorAll('.option-item');
    existingOptions.forEach(item => item.remove());

    const normalizedOptions = options.length
        ? options
        : (questionType === 'dropdown' ? [{}, {}] : [{}, {}]);

    normalizedOptions.forEach((option, idx) => {
        const templateIndex = option?.answer_template_index ?? option?.answer_template_id ?? null;
        const optionNode = createOptionElementNode(questionType, option?.text || '', idx, templateIndex, option?.id);
        if (insertBeforeNode) {
            container.insertBefore(optionNode, insertBeforeNode);
        } else {
            container.appendChild(optionNode);
        }
    });
}

function hydrateRuleBuilderFromPreset(preset) {
    resetFormRulesBuilder();

    // Load title to input
    const titleInput = document.getElementById('rule-group-title-input');
    if (titleInput && preset) {
        titleInput.value = preset.title || preset.rule_group_title || '';
    }
    const normalized = normalizeSavedRule(preset);
    const answerTemplatesContainer = document.getElementById('answer-templates-container');
    const resultRulesContainer = document.getElementById('result-rules-container');
    if (!answerTemplatesContainer || !resultRulesContainer) {
        return;
    }

    if (normalized.templates?.length) {
        answerTemplatesContainer.innerHTML = '';
        normalized.templates.forEach((template) => {
            const card = appendAnswerTemplateCard(answerTemplatesContainer);
            const textInput = card.querySelector('.answer-template-text');
            const scoreInput = card.querySelector('.answer-template-score');
            if (textInput) {
                textInput.value = template.answer_text || '';
            }
            if (scoreInput) {
                scoreInput.value = template.score ?? 0;
            }
        });
    }

    if (normalized.result_rules?.length) {
        resultRulesContainer.innerHTML = '';
        normalized.result_rules.forEach((rule) => {
            const ruleCard = appendResultRuleCard(resultRulesContainer);
            const conditionSelect = ruleCard.querySelector('.rule-condition-type');
            if (conditionSelect) {
                conditionSelect.value = rule.condition_type || 'range';
                conditionSelect.dispatchEvent(new Event('change'));
            }

            if ((rule.condition_type || 'range') === 'range') {
                const minInput = ruleCard.querySelector('.rule-min-score');
                const maxInput = ruleCard.querySelector('.rule-max-score');
                if (minInput) {
                    minInput.value = rule.min_score ?? '';
                }
                if (maxInput) {
                    maxInput.value = rule.max_score ?? '';
                }
            } else {
                const singleInput = ruleCard.querySelector('.rule-single-score');
                if (singleInput) {
                    singleInput.value = rule.single_score ?? '';
                }
            }

            const resultTextsContainer = ruleCard.querySelector('.rule-result-texts');
            const addTextBtn = ruleCard.querySelector('.add-result-text-btn');
            const normalizedTexts = normalizeResultRuleTexts(rule.texts);
            const texts = normalizedTexts.length ? normalizedTexts : [''];
            texts.forEach((text, idx) => {
                if (idx === 0) {
                    const textarea = resultTextsContainer?.querySelector('.rule-result-text');
                    if (textarea) {
                        textarea.value = text || '';
                    }
                } else if (addTextBtn) {
                    addTextBtn.click();
                    const textareas = resultTextsContainer?.querySelectorAll('.rule-result-text');
                    const textarea = textareas ? textareas[textareas.length - 1] : null;
                    if (textarea) {
                        textarea.value = text || '';
                    }
                }
            });
        });
    }

    updateRuleSaveControlsVisibility();
}

// Fungsi untuk membuat section divider
function createSectionDivider(dbId = null) {
    sectionCounter++;
    const sectionDivider = document.createElement('div');
    sectionDivider.className = 'section-divider bg-white rounded-lg shadow-sm border-2 border-dashed border-gray-300 p-6 my-6 group';
    sectionDivider.setAttribute('data-section-id', sectionCounter);
    if (dbId) {
        sectionDivider.setAttribute('data-db-id', dbId);
    }
    sectionDivider.innerHTML = `
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center space-x-3 mb-4">
                    <button type="button" class="drag-handle p-2 text-gray-400 hover:text-red-600 rounded-full bg-white/80 shadow cursor-grab focus:outline-none" data-drag-handle>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 12h18M3 6h18M3 18h18"></path>
                        </svg>
                    </button>
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div 
                            contenteditable="true"
                            data-placeholder="Bagian ${sectionCounter}" 
                            class="section-title-input w-full text-lg font-medium text-gray-900 border-none outline-none focus:ring-0 pb-1 border-b-2 border-transparent focus:border-red-600 transition-colors empty:before:content-[attr(data-placeholder)] empty:before:text-gray-400"
                        ></div>
                        <div 
                            contenteditable="true"
                            data-placeholder="Deskripsi bagian (opsional)" 
                            class="section-description-input w-full mt-2 text-sm text-gray-500 border-none outline-none focus:ring-0 pb-1 border-b border-transparent focus:border-red-600 transition-colors empty:before:content-[attr(data-placeholder)] empty:before:text-gray-400"
                            style="min-height: 1.5em;"
                        ></div>
                    </div>
                </div>
                
                <!-- Image Display Area -->
                <div class="section-image-area mb-4 hidden">
                    <div class="relative inline-block">
                        <img src="" alt="Section image" class="max-w-full h-auto rounded-lg border border-gray-200 section-image" style="max-height: 300px;">
                        <button class="remove-section-image-btn absolute top-2 right-2 p-1.5 bg-red-600 text-white rounded-full hover:bg-red-700 transition-colors" title="Hapus gambar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Hidden File Input -->
                <input type="file" accept="image/*" class="hidden section-image-file-input" data-section-id="${sectionCounter}">
                <input type="hidden" class="section-image-value">
                
                <!-- Image Settings -->
                <div class="section-image-settings hidden mt-4 space-y-3 border-t border-gray-100 pt-4">
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Posisi gambar</label>
                        <select class="section-image-alignment-select mt-1 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500">
                            <option value="left">Kiri</option>
                            <option value="center" selected>Tengah</option>
                            <option value="right">Kanan</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Wrapping gambar</label>
                        <select class="section-image-wrap-mode-select mt-1 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500">
                            <option value="fixed" selected>Ukuran asli (Fixed)</option>
                            <option value="fit">Sesuai kartu (Fit dengan scale-up)</option>
                        </select>
                    </div>
                </div>

                <!-- Section Scoring Settings -->
                <div class="section-scoring-settings mt-6 pt-4 border-t border-gray-100">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Pengaturan Skor Bagian</p>
                    <div class="space-y-3">
                        <label class="flex items-center space-x-3 cursor-pointer group/label">
                            <div class="relative inline-block w-11 h-6">
                                <input type="checkbox" class="calculate-subtotal-checkbox sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer-checked:bg-red-600 transition-colors"></div>
                                <div class="absolute top-[2px] left-[2px] bg-white border border-gray-300 rounded-full h-5 w-5 transition-transform peer-checked:translate-x-full peer-checked:border-white"></div>
                            </div>
                            <span class="text-sm text-gray-700 group-hover/label:text-gray-900 transition-colors">Hitung Subtotal Skor untuk Bagian ini</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer group/label">
                            <div class="relative inline-block w-11 h-6">
                                <input type="checkbox" class="include-in-total-checkbox sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer-checked:bg-red-600 transition-colors"></div>
                                <div class="absolute top-[2px] left-[2px] bg-white border border-gray-300 rounded-full h-5 w-5 transition-transform peer-checked:translate-x-full peer-checked:border-white"></div>
                            </div>
                            <span class="text-sm text-gray-700 group-hover/label:text-gray-900 transition-colors">Sertakan skor bagian ini ke Total Skor Form</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="flex flex-col items-end space-y-2 ml-4">
                <button class="add-section-image-btn p-2 text-gray-500 hover:bg-gray-100 rounded-full transition-colors" title="Tambahkan gambar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </button>
                <button class="delete-section-btn p-2 text-gray-500 hover:bg-gray-100 rounded-full transition-colors" title="Hapus bagian">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    `;
    return sectionDivider;
}

// Function to attach events to section divider
function attachSectionEvents(sectionDivider) {
    const addImageBtn = sectionDivider.querySelector('.add-section-image-btn');
    const imageFileInput = sectionDivider.querySelector('.section-image-file-input');
    const imageArea = sectionDivider.querySelector('.section-image-area');
    const sectionImage = sectionDivider.querySelector('.section-image');
    const removeImageBtn = sectionDivider.querySelector('.remove-section-image-btn');
    const imageValueInput = sectionDivider.querySelector('.section-image-value');
    const imageSettings = sectionDivider.querySelector('.section-image-settings');
    const alignmentSelect = sectionDivider.querySelector('.section-image-alignment-select');
    const wrapModeSelect = sectionDivider.querySelector('.section-image-wrap-mode-select');

    // Function to update image style based on alignment and wrap mode
    const updateImageStyle = () => {
        if (!sectionImage || !imageArea) return;

        const alignment = alignmentSelect?.value || 'center';
        const wrapMode = wrapModeSelect?.value || 'fixed';

        // Update alignment class
        imageArea.classList.remove('text-left', 'text-center', 'text-right');
        imageArea.classList.add(
            alignment === 'left' ? 'text-left' :
                alignment === 'right' ? 'text-right' :
                    'text-center'
        );

        // Update wrap mode style
        if (wrapMode === 'fit') {
            sectionImage.style.width = '100%';
            sectionImage.style.maxWidth = '100%';
            sectionImage.style.height = 'auto';
            sectionImage.style.objectFit = 'cover';
        } else {
            sectionImage.style.width = 'auto';
            sectionImage.style.maxWidth = '100%';
            sectionImage.style.height = 'auto';
            sectionImage.style.objectFit = 'contain';
        }
    };

    const showImageControls = () => {
        if (imageArea) {
            imageArea.classList.remove('hidden');
        }
        if (imageSettings) {
            imageSettings.classList.remove('hidden');
        }
        updateImageStyle();
    };

    const hideImageControls = () => {
        if (imageArea) {
            imageArea.classList.add('hidden');
        }
        if (imageSettings) {
            imageSettings.classList.add('hidden');
        }
    };

    if (addImageBtn && imageFileInput) {
        addImageBtn.addEventListener('click', function () {
            imageFileInput.click();
        });
    }

    if (imageFileInput) {
        imageFileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    if (sectionImage) {
                        sectionImage.src = e.target.result;
                        // Wait for image to load then apply style
                        sectionImage.addEventListener('load', function () {
                            updateImageStyle();
                        }, { once: true });
                    }
                    if (imageValueInput) {
                        imageValueInput.value = e.target.result;
                    }
                    showImageControls();
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (sectionImage) {
        sectionImage.addEventListener('error', function () {
            sectionImage.removeAttribute('src');
            hideImageControls();
            if (imageValueInput) {
                imageValueInput.value = '';
            }
        });
    }

    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', function () {
            hideImageControls();
            if (sectionImage) {
                sectionImage.src = '';
            }
            if (imageFileInput) {
                imageFileInput.value = '';
            }
            if (imageValueInput) {
                imageValueInput.value = '';
            }
            if (alignmentSelect) {
                alignmentSelect.value = 'center';
            }
            if (wrapModeSelect) {
                wrapModeSelect.value = 'fixed';
            }
        });
    }

    // Update image style when alignment changes
    if (alignmentSelect) {
        alignmentSelect.addEventListener('change', function () {
            updateImageStyle();
        });
    }

    // Update image style when wrap mode changes
    if (wrapModeSelect) {
        wrapModeSelect.addEventListener('change', function () {
            updateImageStyle();
        });
    }

    // Initial style update if image already exists
    if (sectionImage && sectionImage.src) {
        updateImageStyle();
    }
}

// Function to create result setting card
let resultSettingCounter = 0;
function createResultSettingCard() {
    resultSettingCounter++;
    const card = document.createElement('div');
    card.className = 'result-setting-card bg-white rounded-lg shadow-sm border-2 border-gray-200 p-6 my-6 transition-all';
    card.setAttribute('data-result-setting-id', resultSettingCounter);

    // Get result rules from settings tab and saved rules
    const resultRulesContainer = document.getElementById('result-rules-container');
    const resultRules = resultRulesContainer ? Array.from(resultRulesContainer.querySelectorAll('.result-rule-card')) : [];
    const savedRules = loadSavedRules();

    // Get rule_groups data from form data (if available)
    const rootElement = document.getElementById('form-builder-root');
    const initialDataAttr = rootElement?.getAttribute('data-initial');
    let ruleGroupsData = {};
    if (initialDataAttr) {
        try {
            const initialData = JSON.parse(initialDataAttr);
            // Safely access rule_groups, default to empty object if null/undefined
            ruleGroupsData = (initialData && initialData.rule_groups) ? initialData.rule_groups : {};
        } catch (e) {
            console.warn('Failed to parse initial data:', e);
            ruleGroupsData = {};
        }
    }

    // PRIORITAS 1: Build options from initialData.rule_groups first (data dari database)
    // Ini penting untuk memastikan semua rule_group_id yang ada di result_settings tersedia di dropdown
    const allRuleGroups = new Map();
    if (ruleGroupsData && Object.keys(ruleGroupsData).length > 0) {
        Object.entries(ruleGroupsData).forEach(([ruleGroupId, title]) => {
            if (ruleGroupId && title) {
                allRuleGroups.set(ruleGroupId, title);
            }
        });
    }

    // PRIORITAS 2: Group active rules by rule_group_id and get unique titles (dari DOM)
    const activeRuleGroups = new Map();
    resultRules.forEach((ruleCard) => {
        const ruleGroupId = ruleCard.getAttribute('data-rule-group-id');
        if (ruleGroupId && !activeRuleGroups.has(ruleGroupId) && !allRuleGroups.has(ruleGroupId)) {
            // Try to get title from rule_groups data first
            let title = ruleGroupsData[ruleGroupId] || null;

            // If not found, try from saved rules
            if (!title) {
                const matchingRule = savedRules.find(r => {
                    const normalized = normalizeSavedRule(r);
                    return normalized.rule_group_id === ruleGroupId;
                });
                if (matchingRule) {
                    title = normalizeSavedRule(matchingRule).title;
                }
            }

            // If still not found, try to get from rule-group-title-input (for new rules being edited)
            if (!title) {
                const titleInput = document.getElementById('rule-group-title-input');
                if (titleInput && titleInput.value.trim()) {
                    title = titleInput.value.trim();
                }
            }

            activeRuleGroups.set(ruleGroupId, title || `Aturan ${activeRuleGroups.size + 1}`);
        }
    });

    // PRIORITAS 3: Add saved rules options (only title, grouped by rule_group_id)
    const savedRuleGroups = new Map();
    savedRules.forEach((savedRule) => {
        const normalized = normalizeSavedRule(savedRule);
        if (normalized.rule_group_id && !savedRuleGroups.has(normalized.rule_group_id) && !allRuleGroups.has(normalized.rule_group_id)) {
            const ruleTitle = normalized.title || `Aturan Tersimpan ${savedRuleGroups.size + 1}`;
            savedRuleGroups.set(normalized.rule_group_id, ruleTitle);
        }
    });

    // Build options: mulai dari initialData.rule_groups, lalu active rules, lalu saved rules
    let resultRuleOptions = '';

    // Add from initialData.rule_groups (prioritas tertinggi - data dari database)
    allRuleGroups.forEach((title, ruleGroupId) => {
        resultRuleOptions += `<option value="active-${ruleGroupId}" data-rule-type="active" data-rule-group-id="${ruleGroupId}">${title}</option>`;
    });

    // Add from active rule groups (dari DOM)
    activeRuleGroups.forEach((title, ruleGroupId) => {
        resultRuleOptions += `<option value="active-${ruleGroupId}" data-rule-type="active" data-rule-group-id="${ruleGroupId}">${title}</option>`;
    });

    // Add from saved rules
    savedRuleGroups.forEach((title, ruleGroupId) => {
        resultRuleOptions += `<option value="saved-${ruleGroupId}" data-rule-type="saved" data-rule-group-id="${ruleGroupId}">${title}</option>`;
    });

    card.innerHTML = `
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1 block">Pilih Aturan Hasil</label>
                            <select class="result-setting-rule-select w-full text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500">
                                <option value="">-- Pilih Aturan --</option>
                                <option value="active-default" data-rule-type="active" data-rule-group-id="default">Aturan Umum (Skor Total)</option>
                                ${resultRuleOptions}
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Image Display Area -->
                <div class="result-setting-image-area mb-4 hidden">
                    <div class="relative inline-block">
                        <img src="" alt="Result image" class="max-w-full h-auto rounded-lg border border-gray-200 result-setting-image" style="max-height: 300px;">
                        <button class="remove-result-setting-image-btn absolute top-2 right-2 p-1.5 bg-red-600 text-white rounded-full hover:bg-red-700 transition-colors" title="Hapus gambar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Hidden File Input -->
                <input type="file" accept="image/*" class="hidden result-setting-image-file-input">
                <input type="hidden" class="result-setting-image-value">
                
                <!-- Image Settings -->
                <div class="result-setting-image-settings hidden mt-4 space-y-3 border-t border-gray-100 pt-4">
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Posisi gambar</label>
                        <select class="result-setting-image-alignment-select mt-1 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500">
                            <option value="left">Kiri</option>
                            <option value="center" selected>Tengah</option>
                            <option value="right">Kanan</option>
                        </select>
                    </div>
                </div>
                
                <!-- Text Display Area -->
                <div class="mt-4">
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2 block">Teks Hasil</label>
                    <div class="result-setting-text-display min-h-[80px] border border-gray-200 rounded-lg p-3 bg-gray-50 space-y-3 text-sm text-gray-700">
                        <p class="text-sm text-gray-400 italic">Pilih aturan untuk melihat teks hasil.</p>
                    </div>
                </div>
                
                <!-- Text Alignment -->
                <div class="mt-4">
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2 block">Posisi teks</label>
                    <div class="flex items-center space-x-3">
                        <select class="result-setting-text-alignment-select flex-1 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500">
                            <option value="left">Kiri</option>
                            <option value="center" selected>Tengah</option>
                            <option value="right">Kanan</option>
                        </select>
                        <button type="button" class="add-new-result-setting-btn px-3 py-1.5 text-xs font-medium text-red-600 border border-red-600 rounded-lg hover:bg-red-50 transition-colors">
                            Tambah
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="flex flex-col items-end space-y-2 ml-4">
                <button class="add-result-setting-image-btn p-2 text-gray-500 hover:bg-gray-100 rounded-full transition-colors" title="Tambahkan gambar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </button>
                <button class="delete-result-setting-btn p-2 text-gray-500 hover:bg-gray-100 rounded-full transition-colors" title="Hapus setup hasil">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    `;
    return card;
}

// Function to attach events to result setting card
function attachResultSettingEvents(card) {
    const ruleSelect = card.querySelector('.result-setting-rule-select');
    const addImageBtn = card.querySelector('.add-result-setting-image-btn');
    const imageFileInput = card.querySelector('.result-setting-image-file-input');
    const imageArea = card.querySelector('.result-setting-image-area');
    const resultImage = card.querySelector('.result-setting-image');
    const removeImageBtn = card.querySelector('.remove-result-setting-image-btn');
    const imageValueInput = card.querySelector('.result-setting-image-value');
    const imageSettings = card.querySelector('.result-setting-image-settings');
    const alignmentSelect = card.querySelector('.result-setting-image-alignment-select');
    const textAlignmentSelect = card.querySelector('.result-setting-text-alignment-select');
    const textDisplay = card.querySelector('.result-setting-text-display');
    const deleteBtn = card.querySelector('.delete-result-setting-btn');

    // Add outline on click (similar to question card)
    card.addEventListener('click', function (e) {
        const shouldSkipFocus = e.target.closest('button')
            || e.target.closest('input')
            || e.target.closest('textarea')
            || e.target.closest('select')
            || e.target.closest('.result-text-form');

        if (shouldSkipFocus) {
            return;
        }

        // Remove active from all result setting cards
        document.querySelectorAll('.result-setting-card').forEach(c => {
            c.classList.remove('ring-2', 'ring-red-600', 'border-red-600');
            c.classList.add('border-gray-200');
        });

        // Add active to this card
        card.classList.add('ring-2', 'ring-red-600', 'border-red-600');
        card.classList.remove('border-gray-200');
    });

    const getRuleTextsFromSettings = () => {
        const selectedOption = ruleSelect?.selectedOptions[0];
        if (!selectedOption) {
            return [];
        }

        const ruleGroupId = selectedOption.getAttribute('data-rule-group-id');
        const ruleType = selectedOption.getAttribute('data-rule-type');

        if (!ruleGroupId) {
            return [];
        }

        const logResultTexts = (source, texts) => {
            if (!Array.isArray(texts)) {
                return;
            }
            console.log('[ResultSetting] Loaded texts', {
                source,
                ruleGroupId,
                count: texts.length,
                texts,
            });
        };

        // Get texts from active rules - langsung dari lookup table (data dari database)
        // Tidak perlu ambil dari DOM karena result_text readonly dan data sudah lengkap di lookup table
        if (ruleType === 'active') {
            // Prioritaskan lookup table yang sudah di-build dari initialData (database)
            // Lookup table sudah lengkap dengan semua result_text dari semua result_rules dalam rule_group_id
            const textsFromLookup = getRuleGroupTextFromLookup(ruleGroupId);

            if (textsFromLookup.length) {
                logResultTexts('active-lookup', textsFromLookup);
                return textsFromLookup;
            }

            // Fallback: coba ambil dari DOM jika lookup kosong (untuk form baru yang belum disimpan)
            const resultRulesContainer = document.getElementById('result-rules-container');
            if (resultRulesContainer) {
                const ruleCards = Array.from(resultRulesContainer.querySelectorAll('.result-rule-card'));
                const matchingRules = ruleCards.filter(card => card.getAttribute('data-rule-group-id') === ruleGroupId);

                // Collect all texts from all matching rules with temporary IDs
                const allTexts = [];
                matchingRules.forEach((ruleCard, ruleIndex) => {
                    // Get result_rule_id from data-db-id (database ID) or use temporary ID
                    const resultRuleId = ruleCard.getAttribute('data-db-id') || `temp-rule-${ruleIndex}`;
                    const textareas = Array.from(ruleCard.querySelectorAll('.rule-result-text'));
                    textareas.forEach((textarea, textIndex) => {
                        const text = textarea.value.trim();
                        if (text) {
                            allTexts.push({
                                result_rule_text_id: `temp-${ruleGroupId}-${ruleIndex}-${textIndex}`, // Temporary ID for active rules
                                result_rule_id: resultRuleId, // Tambahkan result_rule_id untuk grouping
                                result_text: text,
                                title: null,
                                image: null,
                            });
                        }
                    });
                });

                if (allTexts.length) {
                    // Update lookup table untuk form baru
                    updateRuleGroupTextLookup(ruleGroupId, allTexts);
                    logResultTexts('active-builder-fallback', allTexts);
                    return allTexts;
                }
            }

            // Final fallback: return empty array
            logResultTexts('active-empty', []);
            return [];
        }

        // Get texts from saved rules
        if (ruleType === 'saved') {
            const savedRules = loadSavedRules();
            const matchingRule = savedRules.find(r => {
                const normalized = normalizeSavedRule(r);
                return normalized.rule_group_id === ruleGroupId;
            });

            if (matchingRule) {
                const normalized = normalizeSavedRule(matchingRule);
                if (normalized.result_rules && Array.isArray(normalized.result_rules)) {
                    // Collect all texts from all result rules in this group
                    const allTexts = [];
                    normalized.result_rules.forEach((rule, ruleIndex) => {
                        // Get result_rule_id from rule.id or use ruleIndex as fallback
                        const resultRuleId = rule.id || ruleIndex || `saved-rule-${ruleIndex}`;
                        if (Array.isArray(rule.texts)) {
                            rule.texts.forEach((text, textIndex) => {
                                const textValue = typeof text === 'string'
                                    ? text
                                    : (text && typeof text === 'object' ? (text.result_text || text.text || '') : '');
                                if (textValue) {
                                    allTexts.push({
                                        result_rule_text_id: (text && typeof text === 'object' && text.id)
                                            ? text.id
                                            : (rule.text_ids && rule.text_ids[textIndex]
                                                ? rule.text_ids[textIndex]
                                                : `saved-${ruleGroupId}-${ruleIndex}-${textIndex}`),
                                        result_rule_id: resultRuleId, // Tambahkan result_rule_id untuk grouping
                                        result_text: textValue,
                                        title: text && typeof text === 'object' ? (text.title || null) : null,
                                        image: text && typeof text === 'object'
                                            ? (text.image || text.image_url || null)
                                            : null,
                                    });
                                }
                            });
                        }
                    });
                    if (allTexts.length) {
                        updateRuleGroupTextLookup(ruleGroupId, allTexts);
                        logResultTexts('saved-builder', allTexts);
                        return allTexts;
                    }
                }
            }

            const fallbackSaved = getRuleGroupTextFromLookup(ruleGroupId);
            if (fallbackSaved.length) {
                logResultTexts('saved-fallback', fallbackSaved);
                return fallbackSaved;
            }
        }

        const fallback = getRuleGroupTextFromLookup(ruleGroupId);
        logResultTexts('general-fallback', fallback);
        return fallback;
    };

    const updateImageAlignment = () => {
        if (!imageArea) {
            return;
        }
        const alignment = alignmentSelect?.value || 'center';
        imageArea.style.textAlign = alignment;
    };

    const updateTextAlignment = () => {
        if (!textDisplay) {
            return;
        }
        const alignment = textAlignmentSelect?.value || 'center';
        textDisplay.style.textAlign = alignment;
    };

    // Update text from selected rule
    if (ruleSelect) {
        ruleSelect.addEventListener('change', function () {
            const ruleTexts = getRuleTextsFromSettings();
            setResultSettingTextValues(card, ruleTexts);
            updateTextAlignment();
        });
    }

    setResultSettingTextValues(card, []);
    updateImageAlignment();
    updateTextAlignment();

    // Image upload
    if (addImageBtn && imageFileInput) {
        addImageBtn.addEventListener('click', () => imageFileInput.click());

        imageFileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (event) {
                const base64 = event.target.result;
                if (imageValueInput) {
                    imageValueInput.value = base64;
                }
                if (resultImage) {
                    resultImage.src = base64;
                }
                if (imageArea) {
                    imageArea.classList.remove('hidden');
                }
                if (imageSettings) {
                    imageSettings.classList.remove('hidden');
                }
                updateImageAlignment();
            };
            reader.readAsDataURL(file);
        });
    }

    // Remove image
    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', function () {
            if (imageValueInput) {
                imageValueInput.value = '';
            }
            if (resultImage) {
                resultImage.src = '';
            }
            if (imageArea) {
                imageArea.classList.add('hidden');
            }
            if (imageSettings) {
                imageSettings.classList.add('hidden');
            }
            if (imageFileInput) {
                imageFileInput.value = '';
            }
            updateImageAlignment();
        });
    }

    updateImageAlignment();
    updateTextAlignment();

    // Delete card (only delete setting_results on server, keep rules)
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function (e) {
            e.stopPropagation();
            const selectedOption = ruleSelect?.selectedOptions?.[0] || null;
            const ruleGroupId = selectedOption ? selectedOption.getAttribute('data-rule-group-id') : null;

            // If no rule group selected, just remove the card locally
            if (!ruleGroupId) {
                card.remove();
                return;
            }

            const formId = document.getElementById('form-builder-root')?.getAttribute('data-form-id');
            if (!formId) {
                card.remove();
                return;
            }

            const csrfToken = getMetaContent('csrf-token');
            const url = `/forms/${formId}/setting-results/${encodeURIComponent(ruleGroupId)}`;

            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Gagal menghapus setup hasil.');
                }
            } catch (err) {
                console.warn('[ResultSetting] Delete failed, removing locally only:', err);
            } finally {
                // Remove the card from UI regardless, since rules remain usable later
                card.remove();
            }
        });
    }

    // Add new result setting card
    const addNewBtn = card.querySelector('.add-new-result-setting-btn');
    if (addNewBtn) {
        addNewBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const questionsContainer = document.getElementById('questions-container');
            if (questionsContainer) {
                const newResultSettingCard = createResultSettingCard();
                attachResultSettingEvents(newResultSettingCard);

                // Insert after current card
                if (card.nextSibling) {
                    questionsContainer.insertBefore(newResultSettingCard, card.nextSibling);
                } else {
                    questionsContainer.appendChild(newResultSettingCard);
                }

                updateMainButtonsVisibility();

                // Scroll to the new card
                newResultSettingCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
}

// Question templates untuk setiap tipe
const questionTemplates = {
    'short-answer': {
        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>`,
        label: 'Jawaban singkat',
        input: `
            <div class="max-w-md mt-4">
                <input 
                    type="text" 
                    placeholder="Jawaban singkat" 
                    class="w-full px-0 py-2 text-sm text-gray-500 border-none border-b border-gray-300 focus:border-red-600 focus:outline-none transition-colors"
                    disabled
                >
            </div>
        `
    },
    'paragraph': {
        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>`,
        label: 'Paragraf',
        input: `
            <div class="max-w-md mt-4">
                <textarea 
                    placeholder="Jawaban panjang" 
                    rows="4"
                    class="w-full px-0 py-2 text-sm text-gray-500 border-none border-b border-gray-300 focus:border-red-600 focus:outline-none transition-colors resize-none"
                    disabled
                ></textarea>
            </div>
        `
    },
    'multiple-choice': {
        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`,
        label: 'Pilihan ganda',
        input: `
            <div class="question-options-wrapper space-y-2 max-w-md mt-4" data-option-container="true">
                <div class="option-item flex items-center space-x-2">
                    <input type="radio" class="text-red-600 focus:ring-red-500 mt-1">
                    <input 
                        type="text" 
                        placeholder="Opsi 1" 
                        class="flex-1 px-0 py-1 text-sm text-gray-500 border-none border-b border-transparent focus:border-red-600 focus:outline-none transition-colors option-input"
                    >
                    <button class="remove-option-btn p-1 text-gray-400 hover:text-red-600 transition-colors opacity-0 group-hover:opacity-100" title="Hapus opsi">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="option-item flex items-center space-x-2">
                    <input type="radio" class="text-red-600 focus:ring-red-500 mt-1">
                    <input 
                        type="text" 
                        placeholder="Opsi 2" 
                        class="flex-1 px-0 py-1 text-sm text-gray-500 border-none border-b border-transparent focus:border-red-600 focus:outline-none transition-colors option-input"
                    >
                    <button class="remove-option-btn p-1 text-gray-400 hover:text-red-600 transition-colors opacity-0 group-hover:opacity-100" title="Hapus opsi">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="option-controls mt-2 space-y-1">
                    <button class="add-option-btn text-sm text-gray-600 hover:text-red-600 flex items-center space-x-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Tambahkan opsi</span>
                    </button>
                    <span class="text-sm text-gray-500"> atau </span>
                    <button class="add-other-btn text-sm text-red-600 hover:text-red-700">
                        tambahkan "Lainnya"
                    </button>
                </div>
                <div class="use-saved-rules-wrapper mt-3 hidden">
                    <button type="button" class="use-saved-rule-btn inline-flex items-center px-3 py-1 text-xs font-medium text-red-600 border border-red-200 rounded-full hover:bg-red-50 transition-colors space-x-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Gunakan aturan tersimpan</span>
                    </button>
                </div>
            </div>
        `
    },
    'checkbox': {
        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`,
        label: 'Kotak centang',
        input: `
            <div class="question-options-wrapper space-y-2 max-w-md mt-4" data-option-container="true">
                <div class="option-item flex items-center space-x-2">
                    <input type="checkbox" class="rounded border-gray-300 text-red-600 focus:ring-red-500 mt-1">
                    <input 
                        type="text" 
                        placeholder="Opsi 1" 
                        class="flex-1 px-0 py-1 text-sm text-gray-500 border-none border-b border-transparent focus:border-red-600 focus:outline-none transition-colors option-input"
                    >
                    <button class="remove-option-btn p-1 text-gray-400 hover:text-red-600 transition-colors opacity-0 group-hover:opacity-100" title="Hapus opsi">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="option-item flex items-center space-x-2">
                    <input type="checkbox" class="rounded border-gray-300 text-red-600 focus:ring-red-500 mt-1">
                    <input 
                        type="text" 
                        placeholder="Opsi 2" 
                        class="flex-1 px-0 py-1 text-sm text-gray-500 border-none border-b border-transparent focus:border-red-600 focus:outline-none transition-colors option-input"
                    >
                    <button class="remove-option-btn p-1 text-gray-400 hover:text-red-600 transition-colors opacity-0 group-hover:opacity-100" title="Hapus opsi">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="option-controls mt-2 space-y-1">
                    <button class="add-option-btn text-sm text-gray-600 hover:text-red-600 flex items-center space-x-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Tambahkan opsi</span>
                    </button>
                    <span class="text-sm text-gray-500"> atau </span>
                    <button class="add-other-btn text-sm text-red-600 hover:text-red-700">
                        tambahkan "Lainnya"
                    </button>
                </div>
            </div>
        `
    },
    'dropdown': {
        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>`,
        label: 'Dropdown',
        input: `
            <div class="question-options-wrapper space-y-2 max-w-md mt-4" data-option-container="true">
                <div class="option-item flex items-center space-x-2">
                    <span class="text-sm text-gray-500 w-4">1.</span>
                    <input 
                        type="text" 
                        placeholder="Opsi 1" 
                        class="flex-1 px-0 py-1 text-sm text-gray-500 border-none border-b border-transparent focus:border-red-600 focus:outline-none transition-colors option-input"
                    >
                    <button class="remove-option-btn p-1 text-gray-400 hover:text-red-600 transition-colors opacity-0 group-hover:opacity-100" title="Hapus opsi">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="option-item flex items-center space-x-2">
                    <span class="text-sm text-gray-500 w-4">2.</span>
                    <input 
                        type="text" 
                        placeholder="Opsi 2" 
                        class="flex-1 px-0 py-1 text-sm text-gray-500 border-none border-b border-transparent focus:border-red-600 focus:outline-none transition-colors option-input"
                    >
                    <button class="remove-option-btn p-1 text-gray-400 hover:text-red-600 transition-colors opacity-0 group-hover:opacity-100" title="Hapus opsi">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="option-controls mt-2 space-y-1">
                    <button class="add-option-btn text-sm text-gray-600 hover:text-red-600 flex items-center space-x-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Tambahkan opsi</span>
                    </button>
                    <span class="text-sm text-gray-500"> atau </span>
                    <button class="add-other-btn text-sm text-red-600 hover:text-red-700">
                        tambahkan "Lainnya"
                    </button>
                </div>
            </div>
        `
    }
};

// Fungsi untuk membuat question card
function createQuestionCard(type = 'short-answer', dbId = null) {
    questionCounter++;
    const template = questionTemplates[type];

    const questionCard = document.createElement('div');
    questionCard.className = 'question-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-all relative group';
    questionCard.setAttribute('data-question-id', questionCounter);
    questionCard.setAttribute('data-question-type', type);
    if (dbId) {
        questionCard.setAttribute('data-db-id', dbId);
    }
    questionCard.innerHTML = `
        <!-- Drag Handle -->
        <button type="button" class="drag-handle absolute top-2 left-1/2 -translate-x-1/2 p-1.5 text-gray-400 hover:text-red-600 rounded-full bg-white/80 shadow opacity-0 group-hover:opacity-100 transition-all cursor-grab focus:outline-none" data-drag-handle>
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M3 12h18M3 6h18M3 18h18"></path>
            </svg>
        </button>

        <div class="flex items-start space-x-4">
            <div class="flex-1 min-w-0">
                <!-- Question Title Input -->
                <div class="mb-2">
                    <div 
                        contenteditable="true"
                        data-placeholder="Pertanyaan"
                        class="w-full text-base font-normal text-gray-900 border-none outline-none focus:ring-0 pb-2 border-b-2 border-transparent focus:border-red-600 transition-colors question-title empty:before:content-[attr(data-placeholder)] empty:before:text-gray-400"
                        style="min-height: 1.5em;"
                    ></div>
                </div>

                <!-- Formatting Toolbar -->
                <div class="formatting-toolbar flex items-center space-x-1 mb-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button class="format-btn p-1.5 hover:bg-gray-100 rounded font-bold" title="Bold" data-format="bold">
                        <span class="text-sm">B</span>
                    </button>
                    <button class="format-btn p-1.5 hover:bg-gray-100 rounded italic" title="Italic" data-format="italic">
                        <span class="text-sm">I</span>
                    </button>
                    <button class="format-btn p-1.5 hover:bg-gray-100 rounded underline" title="Underline" data-format="underline">
                        <span class="text-sm">U</span>
                    </button>
                </div>

                <!-- Image Display Area -->
                <div class="question-image-area mb-4 hidden">
                    <div class="relative inline-block">
                        <img src="" alt="Question image" class="max-w-full h-auto rounded-lg border border-gray-200 question-image" style="max-height: 300px;">
                        <button class="remove-image-btn absolute top-2 right-2 p-1.5 bg-red-600 text-white rounded-full hover:bg-red-700 transition-colors" title="Hapus gambar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Hidden File Input -->
                <input type="file" accept="image/*" class="hidden image-file-input" data-question-id="${questionCounter}">
                <input type="hidden" class="question-image-value">
                <div class="question-image-settings hidden mt-4 space-y-3 border-t border-gray-100 pt-4">
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Posisi gambar</label>
                        <select class="image-alignment-select mt-1 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500">
                            <option value="left">Kiri</option>
                            <option value="center" selected>Tengah</option>
                            <option value="right">Kanan</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Lebar gambar</label>
                        <div class="flex items-center space-x-3 mt-1">
                            <input type="range" min="30" max="100" step="5" value="100" class="image-width-range flex-1 accent-red-600">
                            <span class="image-width-display text-xs text-gray-500 w-12 text-right">100%</span>
                        </div>
                    </div>
                </div>

                <!-- Question Input Area -->
                <div class="question-input-area">
                    ${template.input}
                </div>

                <!-- Bottom Actions -->
                <div class="mt-4 space-y-4 pt-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <button class="duplicate-question-btn p-2 text-gray-500 hover:bg-gray-100 rounded-full transition-colors" title="Duplikat">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </button>
                            <button class="delete-question-btn p-2 text-gray-500 hover:bg-gray-100 rounded-full transition-colors" title="Hapus">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center space-x-2 text-sm text-gray-600 cursor-pointer">
                                <div class="relative inline-block w-11 h-6">
                                    <input type="checkbox" class="required-checkbox sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer-checked:bg-red-600 transition-colors"></div>
                                    <div class="absolute top-[2px] left-[2px] bg-white border border-gray-300 rounded-full h-5 w-5 transition-transform peer-checked:translate-x-full peer-checked:border-white"></div>
                                </div>
                                <span class="select-none">Wajib diisi</span>
                            </label>
                            <button class="more-options-btn p-2 text-gray-500 hover:bg-gray-100 rounded-full transition-colors" title="Setelan pertanyaan">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="question-advanced-settings hidden bg-gray-50 rounded-lg border border-gray-200 p-4 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Validasi Jawaban</p>
                                <p class="text-xs text-gray-500 mt-1">Gunakan regex sederhana untuk memvalidasi jawaban.</p>
                            </div>
                            <input type="text" class="question-validation-input mt-2 sm:mt-0 sm:max-w-xs px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500" placeholder="contoh: ^[0-9]+$">
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Pesan Validasi</p>
                                <p class="text-xs text-gray-500 mt-1">Tampilkan pesan kustom saat jawaban tidak valid.</p>
                            </div>
                            <input type="text" class="question-validation-message mt-2 sm:mt-0 sm:max-w-xs px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500" placeholder="Silakan masukkan angka saja">
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Deskripsi Tambahan</p>
                                <p class="text-xs text-gray-500 mt-1">Sampaikan petunjuk tambahan untuk pertanyaan ini.</p>
                            </div>
                            <textarea rows="2" class="question-extra-notes mt-2 sm:mt-0 sm:max-w-xs px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500" placeholder="Contoh: Gunakan format tanggal dd/mm/yyyy"></textarea>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Batas Karakter (opsional)</p>
                                <p class="text-xs text-gray-500 mt-1">Tentukan batas minimal & maksimal untuk jawaban teks.</p>
                            </div>
                            <div class="flex items-center space-x-2 mt-2 sm:mt-0">
                                <input type="number" min="0" class="question-min-length w-24 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500" placeholder="Min">
                                <span class="text-sm text-gray-500">s.d</span>
                                <input type="number" min="0" class="question-max-length w-24 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500" placeholder="Maks">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="shrink-0 flex flex-col items-end space-y-2">
                <button class="add-image-btn p-2 text-gray-500 hover:bg-gray-100 rounded-full transition-colors" title="Tambahkan gambar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </button>
                <div class="relative">
                    <button class="question-type-dropdown-btn flex items-center space-x-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors" data-type="${type}">
                        <span class="question-type-icon">${template.icon}</span>
                        <span class="question-type-label">${template.label}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="question-type-dropdown hidden absolute right-0 mt-1 bg-white rounded-lg shadow-lg border border-gray-200 z-10 min-w-[200px]">
                        <div class="py-1">
                            ${Object.keys(questionTemplates).map(key => `
                                <button class="change-question-type-btn w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-3" data-type="${key}">
                                    <span class="text-gray-500">${questionTemplates[key].icon}</span>
                                    <span class="text-sm text-gray-700">${questionTemplates[key].label}</span>
                                </button>
                            `).join('')}
                        </div>
                    </div>
                </div>
                
                <!-- Quick Add Buttons (shown when card is active) -->
                <div class="question-quick-add-buttons hidden flex-col items-end space-y-2 mt-2">
                    <button class="quick-add-question-btn flex items-center space-x-1 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors" title="Tambah pertanyaan">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Tambah pertanyaan</span>
                    </button>
                    <button class="quick-add-section-btn flex items-center space-x-1 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors" title="Tambahkan bagian">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>Tambahkan bagian</span>
                    </button>
                    <button class="quick-add-result-setting-btn hidden items-center space-x-1 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors" title="Setup Hasil">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Setup Hasil</span>
                    </button>
                </div>
            </div>
        </div>
    `;

    return questionCard;
}

// Tab Management
function initTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            const targetTab = this.getAttribute('data-tab');

            // Remove active dari semua tabs
            tabButtons.forEach(btn => {
                btn.classList.remove('active', 'text-red-600', 'border-red-600');
                btn.classList.add('text-gray-600', 'border-transparent');
            });

            // Hide semua tab content
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });

            // Add active ke tab yang diklik
            this.classList.add('active', 'text-red-600', 'border-red-600');
            this.classList.remove('text-gray-600', 'border-transparent');

            // Show target tab content
            const targetContent = document.getElementById(`tab-${targetTab}`);
            if (targetContent) {
                targetContent.classList.remove('hidden');
                if (targetTab === 'responses') {
                    triggerResponsesFetch();
                }
            }
        });
    });

    // Initialize toggle switches di tab Setelan
    initSettingsToggles();
}

// Initialize toggle switches untuk settings
// Initialize toggle switches untuk settings
function initSettingsToggles() {
    const settingsToggles = document.querySelectorAll('#tab-settings input[type="checkbox"]');

    settingsToggles.forEach(toggle => {
        // Listen for changes
        toggle.addEventListener('change', () => {
            // Specific logic for BMI toggle
            if (toggle.id === 'use-bmi-formula') {
                if (toggle.checked) {
                    ensureBmiQuestionsExist();
                } else {
                    removeBmiQuestions();
                }
            }
        });
    });

    // Initial check for BMI
    const bmiToggle = document.getElementById('use-bmi-formula');
    if (bmiToggle && bmiToggle.checked) {
        ensureBmiQuestionsExist();
    }
}


/**
 * Memastikan pertanyaan untuk BMI (BB & TB) ada di dalam form.
 * Jika tidak ada, tambahkan secara otomatis.
 */
function ensureBmiQuestionsExist() {
    const questionsContainer = document.getElementById('questions-container');
    if (!questionsContainer) return;

    const allTitles = Array.from(questionsContainer.querySelectorAll('.question-title'))
        .map(el => {
            const val = el.contentEditable === 'true' ? el.innerText : el.value;
            return (val || '').trim().toLowerCase();
        });

    const hasWeight = allTitles.includes('berat badan (kg)');
    const hasHeight = allTitles.includes('tinggi badan (cm)');

    if (!hasWeight) {
        addBmiQuestion('Berat Badan (kg)');
    }

    if (!hasHeight) {
        addBmiQuestion('Tinggi Badan (cm)');
    }
}

function addBmiQuestion(title) {
    const questionsContainer = document.getElementById('questions-container');
    const questionCard = createQuestionCard('short-answer');
    questionsContainer.appendChild(questionCard);

    const titleInput = questionCard.querySelector('.question-title');
    if (titleInput) {
        if (titleInput.contentEditable === 'true') {
            titleInput.innerText = title;
        } else {
            titleInput.value = title;
        }
    }

    // Set is_required by default for BMI questions
    const requiredCheckbox = questionCard.querySelector('.required-checkbox');
    if (requiredCheckbox) {
        requiredCheckbox.checked = true;
    }

    updateQuestionNumbers();
    attachQuestionCardEvents(questionCard);
    initSortable();
    updateMainButtonsVisibility();
}

/**
 * Remove BMI questions if they exist.
 */
function removeBmiQuestions() {
    const questionsContainer = document.getElementById('questions-container');
    if (!questionsContainer) return;

    const cards = Array.from(questionsContainer.querySelectorAll('.question-card'));
    let removed = false;

    cards.forEach(card => {
        const titleInput = card.querySelector('.question-title');
        if (!titleInput) return;

        const val = (titleInput.contentEditable === 'true' ? titleInput.innerText : titleInput.value) || '';
        const title = val.trim().toLowerCase();

        if (title === 'berat badan (kg)' || title === 'tinggi badan (cm)') {
            card.remove();
            removed = true;
        }
    });

    if (removed) {
        updateQuestionNumbers();
        updateMainButtonsVisibility();
    }
}


// Form Rules Management
let answerTemplateCounter = 0;
let resultRuleCounter = 0;

// Fungsi untuk membuat answer template card
function createAnswerTemplateCard(dbId = null, ruleGroupId = null) {
    answerTemplateCounter++;
    const templateCard = document.createElement('div');
    templateCard.className = 'answer-template-card bg-gray-50 rounded-lg border border-gray-200 p-4';
    templateCard.setAttribute('data-template-id', answerTemplateCounter);
    if (dbId) {
        templateCard.setAttribute('data-db-id', dbId);
    }
    if (ruleGroupId) {
        templateCard.setAttribute('data-rule-group-id', ruleGroupId);
    }
    templateCard.innerHTML = `
        <div class="flex items-center space-x-3">
            <div class="flex-1">
                <label class="text-xs font-medium text-gray-700 mb-1 block">Jawaban</label>
                <input type="text" placeholder="Masukkan jawaban" class="answer-template-text w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none">
            </div>
            <div class="w-32">
                <label class="text-xs font-medium text-gray-700 mb-1 block">Skor</label>
                <input type="number" placeholder="0" class="answer-template-score w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none" min="0" value="0">
            </div>
            <div class="flex items-end">
                <button class="delete-answer-template-btn p-2 text-gray-400 hover:text-red-600 transition-colors" title="Hapus jawaban">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    `;
    return templateCard;
}

function appendAnswerTemplateCard(container, dbId = null, ruleGroupId = null) {
    const templateCard = createAnswerTemplateCard(dbId, ruleGroupId);
    container.appendChild(templateCard);

    const deleteBtn = templateCard.querySelector('.delete-answer-template-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function () {
            const dbId = templateCard.getAttribute('data-db-id');
            const formId = getMetaContent('form-id');

            // If form is saved and template has database ID, delete from database
            if (formId && dbId) {
                try {
                    const response = await fetch(`/forms/${formId}/answer-templates/${dbId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': getMetaContent('csrf-token'),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Gagal menghapus template.');
                    }
                } catch (error) {
                    console.error('Error deleting template:', error);
                    alert('Gagal menghapus template dari database: ' + error.message);
                    return; // Don't remove from DOM if database delete failed
                }
            }

            // Remove from DOM
            templateCard.remove();
            if (container.children.length === 0) {
                container.innerHTML = getAnswerTemplatesPlaceholder();
            }
            updateRuleSaveControlsVisibility();
        });
    }

    return templateCard;
}

function createResultTextItem(value = '', dbId = null) {
    const textItem = document.createElement('div');
    textItem.className = 'result-text-item flex items-start space-x-2';
    if (dbId) {
        textItem.setAttribute('data-db-id', dbId);
    }
    textItem.innerHTML = `
        <textarea placeholder="Masukkan teks hasil yang akan ditampilkan" rows="2" class="rule-result-text flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none resize-none">${value}</textarea>
        <button class="delete-result-text-btn p-2 text-gray-400 hover:text-red-600 transition-colors shrink-0" title="Hapus teks">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    return textItem;
}

// Fungsi untuk membuat result rule card
function createResultRuleCard(dbId = null, ruleGroupId = null) {
    resultRuleCounter++;
    const ruleCard = document.createElement('div');
    ruleCard.className = 'result-rule-card bg-gray-50 rounded-lg border border-gray-200 p-4';
    ruleCard.setAttribute('data-rule-id', resultRuleCounter);
    if (dbId) {
        ruleCard.setAttribute('data-db-id', dbId);
    }
    if (ruleGroupId) {
        ruleCard.setAttribute('data-rule-group-id', ruleGroupId);
    }
    ruleCard.innerHTML = `
        <div class="flex items-start justify-between mb-3">
            <h5 class="text-sm font-medium text-gray-900">Aturan ${resultRuleCounter}</h5>
            <button class="delete-result-rule-btn p-1 text-gray-400 hover:text-red-600 transition-colors" title="Hapus aturan">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-xs font-medium text-gray-700 mb-1 block">Kondisi Skor</label>
                <select class="rule-condition-type w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none">
                    <option value="range">Range (Min - Max)</option>
                    <option value="equal">Sama dengan (=)</option>
                    <option value="greater">Lebih dari (>)</option>
                    <option value="less">Kurang dari (<)</option>
                </select>
            </div>
            <div id="rule-condition-inputs-${resultRuleCounter}" class="space-y-2">
                <div class="rule-range-inputs flex items-center space-x-2">
                    <input type="number" placeholder="Min" class="rule-min-score w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none" min="0">
                    <span class="text-sm text-gray-500">sampai</span>
                    <input type="number" placeholder="Max" class="rule-max-score w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none" min="0">
                </div>
                <div class="rule-single-inputs hidden">
                    <input type="number" placeholder="Nilai" class="rule-single-score w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none" min="0">
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-medium text-gray-700">Hasil Teks</label>
                    <button class="add-result-text-btn px-2 py-1 text-xs font-medium text-red-600 border border-red-600 rounded hover:bg-red-50 transition-colors" data-rule-id="${resultRuleCounter}">
                        + Tambah Teks
                    </button>
                </div>
                <div class="rule-result-texts space-y-2" data-rule-id="${resultRuleCounter}">
                    <!-- Initial text item will be added via JS if needed -->
                </div>
            </div>
        </div>
    `;

    return ruleCard;
}

function appendResultRuleCard(container, dbId = null, ruleGroupId = null) {
    const ruleCard = createResultRuleCard(dbId, ruleGroupId);
    container.appendChild(ruleCard);

    // Add initial text item if empty
    const resultTextsContainer = ruleCard.querySelector('.rule-result-texts');
    if (resultTextsContainer && resultTextsContainer.children.length === 0) {
        const textItem = createResultTextItem();
        resultTextsContainer.appendChild(textItem);
    }

    // Attach all events (including delete behavior)
    attachResultRuleEvents(ruleCard, container);

    return ruleCard;
}

// Function to attach events to result rule card (used when loading from database)
function attachResultRuleEvents(ruleCard, container) {
    const conditionSelect = ruleCard.querySelector('.rule-condition-type');
    const rangeInputs = ruleCard.querySelector('.rule-range-inputs');
    const singleInput = ruleCard.querySelector('.rule-single-inputs');

    if (conditionSelect && !conditionSelect.hasAttribute('data-listener')) {
        conditionSelect.setAttribute('data-listener', 'true');
        conditionSelect.addEventListener('change', function () {
            const conditionType = this.value;
            const singleScoreInput = singleInput.querySelector('.rule-single-score');

            if (conditionType === 'range') {
                rangeInputs.classList.remove('hidden');
                singleInput.classList.add('hidden');
            } else {
                rangeInputs.classList.add('hidden');
                singleInput.classList.remove('hidden');

                if (singleScoreInput) {
                    if (conditionType === 'equal') {
                        singleScoreInput.placeholder = 'Nilai (sama dengan)';
                    } else if (conditionType === 'greater') {
                        singleScoreInput.placeholder = 'Nilai (lebih dari)';
                    } else if (conditionType === 'less') {
                        singleScoreInput.placeholder = 'Nilai (kurang dari)';
                    }
                }
            }
        });
    }

    const addResultTextBtn = ruleCard.querySelector('.add-result-text-btn');
    const resultTextsContainer = ruleCard.querySelector('.rule-result-texts');

    if (addResultTextBtn && resultTextsContainer) {
        if (!addResultTextBtn.hasAttribute('data-listener')) {
            addResultTextBtn.setAttribute('data-listener', 'true');
            addResultTextBtn.addEventListener('click', function () {
                const textItem = createResultTextItem();
                resultTextsContainer.appendChild(textItem);

                const deleteBtn = textItem.querySelector('.delete-result-text-btn');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function () {
                        textItem.remove();
                        const remaining = resultTextsContainer.querySelectorAll('.rule-result-text');
                        if (!remaining.length) {
                            addResultTextBtn.click();
                        }
                    });
                }
            });
        }
    }

    const deleteTextBtns = ruleCard.querySelectorAll('.delete-result-text-btn');
    deleteTextBtns.forEach(btn => {
        if (!btn.hasAttribute('data-listener')) {
            btn.setAttribute('data-listener', 'true');
            btn.addEventListener('click', function () {
                const textItem = this.closest('.result-text-item');
                if (textItem) {
                    textItem.remove();
                    const remaining = ruleCard.querySelectorAll('.rule-result-text');
                    if (!remaining.length && addResultTextBtn) {
                        addResultTextBtn.click();
                    }
                }
            });
        }
    });

    const deleteRuleBtn = ruleCard.querySelector('.delete-result-rule-btn');
    if (deleteRuleBtn && !deleteRuleBtn.hasAttribute('data-listener')) {
        deleteRuleBtn.setAttribute('data-listener', 'true');
        deleteRuleBtn.addEventListener('click', async function () {
            const dbId = ruleCard.getAttribute('data-db-id');
            const formId = getMetaContent('form-id');

            // If form is saved and rule has database ID, delete from database
            if (formId && dbId) {
                try {
                    const response = await fetch(`/forms/${formId}/result-rules/${dbId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': getMetaContent('csrf-token'),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Gagal menghapus aturan.');
                    }
                } catch (error) {
                    console.error('Error deleting rule:', error);
                    alert('Gagal menghapus aturan dari database: ' + error.message);
                    return; // Don't remove from DOM if database delete failed
                }
            }

            // Remove from DOM
            ruleCard.remove();
            if (container.children.length === 0) {
                container.innerHTML = getResultRulesPlaceholder();
            }
            renderSavedRulesChips();
            updateUseRuleButtonsVisibility();
        });
    }
}

let savedRulesState = [];
let ruleGroupTextLookup = {};
let sortableInstance = null;
let sortableInitialized = false;
let editingRuleGroupId = null;
let defaultSaveRulesLabel = 'Simpan Aturan';

function hasFormTemplates() {
    return document.querySelectorAll('.answer-template-card').length > 0;
}

function updateRuleSaveControlsVisibility() {
    const saveBtn = document.getElementById('save-form-rules-btn');
    const savedRulesContainer = document.getElementById('saved-rules-container');
    const hasTemplates = hasFormTemplates();
    if (saveBtn) {
        saveBtn.hidden = !hasTemplates;
        saveBtn.disabled = !hasTemplates;
    }
    if (savedRulesContainer && !savedRulesState.length) {
        savedRulesContainer.classList.add('hidden');
    }
}

function generateRuleGroupId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }

    return `rule-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function normalizeSavedRule(rule) {
    if (!rule) {
        return {
            templates: [],
            result_rules: [],
            rule_group_id: generateRuleGroupId(),
            title: null,
            text_settings: [],
        };
    }

    const normalized = {
        id: rule.id ?? Date.now(),
        rule_group_id: rule.rule_group_id ?? rule.id ?? generateRuleGroupId(),
        title: rule.title ?? rule.rule_group_title ?? null,
        templates: Array.isArray(rule.templates) ? rule.templates.slice() : Array.isArray(rule.answer_templates) ? rule.answer_templates.slice() : [],
        result_rules: Array.isArray(rule.result_rules) ? rule.result_rules.slice() : [],
        text_settings: cloneResultTextItems(rule.text_settings || rule.result_text_settings || []),
    };

    if (!normalized.result_rules.length && rule.condition_type) {
        normalized.result_rules.push({
            condition_type: rule.condition_type,
            min_score: rule.min_score ?? null,
            max_score: rule.max_score ?? null,
            single_score: rule.single_score ?? null,
            texts: normalizeResultRuleTexts(rule.texts),
        });
    }

    if (!normalized.templates.length && rule.answer_template) {
        normalized.templates.push(rule.answer_template);
    }

    return normalized;
}

function loadSavedRules() {
    return savedRulesState.map(normalizeSavedRule);
}

function saveRulesToState(rules) {
    savedRulesState = Array.isArray(rules) ? rules.map(normalizeSavedRule) : [];
}

function renderSavedRulesChips() {
    const container = document.getElementById('saved-rules-container');
    const chipsWrapper = document.getElementById('saved-rules-chips');
    if (!container || !chipsWrapper) {
        return;
    }

    const savedRules = loadSavedRules();
    chipsWrapper.innerHTML = '';

    if (!savedRules.length) {
        container.classList.add('hidden');
        updateRuleSaveControlsVisibility();
        return;
    }

    container.classList.remove('hidden');

    savedRules.forEach((rawRule, index) => {
        const rule = normalizeSavedRule(rawRule);
        const templates = Array.isArray(rule.templates) ? rule.templates : [];
        const resultRules = Array.isArray(rule.result_rules) ? rule.result_rules : [];
        const isEditing = rule.rule_group_id && rule.rule_group_id === editingRuleGroupId;

        const chip = document.createElement('div');
        chip.className = 'saved-rule-chip inline-flex items-center space-x-2 px-3 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-full';
        chip.setAttribute('data-rule-index', index);
        if (rule.rule_group_id) {
            chip.setAttribute('data-rule-group-id', rule.rule_group_id);
        }

        const descriptionParts = [];
        if (templates.length) {
            descriptionParts.push(`${templates.length} opsi`);
            const preview = templates.slice(0, 2).map(t => t.answer_text || t.text || '').filter(Boolean);
            if (preview.length) {
                descriptionParts.push(preview.join(', '));
            }
        } else {
            descriptionParts.push('0 opsi');
        }

        if (resultRules.length) {
            descriptionParts.push(`${resultRules.length} hasil`);
        }

        const displayText = rule.title || descriptionParts.join(' • ');

        let buttonsHTML = `
            <span class="truncate max-w-[200px]">${displayText}</span>
            <button type="button" class="edit-saved-rule-btn text-blue-600 hover:text-blue-800" title="Edit Aturan">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                </svg>
            </button>
        `;

        if (isEditing) {
            buttonsHTML += `
                <button type="button" class="dispose-saved-rule-btn text-orange-500 hover:text-orange-700" title="Tutup / Bersihkan Editor">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            `;
        }

        buttonsHTML += `
            <button type="button" class="remove-saved-rule-btn text-red-500 hover:text-red-700" title="Hapus Aturan Permanen">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        `;

        chip.innerHTML = buttonsHTML;

        chipsWrapper.appendChild(chip);

        const editBtn = chip.querySelector('.edit-saved-rule-btn');
        if (editBtn) {
            editBtn.addEventListener('click', function () {
                hydrateRuleBuilderFromPreset(rule);
                enterRulesEditMode(rule);
                // Re-render to show Dispose button on this chip
                renderSavedRulesChips();
            });
        }

        const disposeBtn = chip.querySelector('.dispose-saved-rule-btn');
        if (disposeBtn) {
            disposeBtn.addEventListener('click', function () {
                exitRulesEditMode();
                resetFormRulesBuilder();
                renderSavedRulesChips();
            });
        }

        const removeBtn = chip.querySelector('.remove-saved-rule-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                showDialog({
                    title: 'Hapus Aturan?',
                    message: 'Apakah Anda yakin ingin menghapus aturan ini? Tindakan ini tidak dapat dibatalkan.',
                    type: 'danger',
                    confirmText: 'Ya, Hapus',
                    cancelText: 'Batal',
                    onConfirm: () => {
                        const formId = document.getElementById('form-builder-root')?.getAttribute('data-form-id');
                        const ruleGroupId = rule.rule_group_id;

                        if (!formId || !ruleGroupId) {
                            removeLocalRule();
                            return;
                        }

                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                        fetch(`/forms/${formId}/rule-groups/${ruleGroupId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            }
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    removeLocalRule();
                                } else {
                                    showDialog({
                                        title: 'Gagal',
                                        message: 'Gagal menghapus aturan: ' + (data.message || 'Unknown error'),
                                        type: 'danger'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showDialog({
                                    title: 'Error',
                                    message: 'Terjadi kesalahan sistem saat menghapus aturan.',
                                    type: 'danger'
                                });
                            });
                    }
                });

                function removeLocalRule() {
                    const indexToRemove = parseInt(chip.getAttribute('data-rule-index'), 10);
                    const current = loadSavedRules();
                    const removed = current.splice(indexToRemove, 1)[0];
                    saveRulesToState(current);
                    renderSavedRulesChips();
                    updateUseRuleButtonsVisibility();
                    if (removed && removed.rule_group_id && removed.rule_group_id === editingRuleGroupId) {
                        exitRulesEditMode();
                        resetFormRulesBuilder();
                    }
                }
            });
        }
    });

    updateUseRuleButtonsVisibility();
    updateRuleSaveControlsVisibility();
}

function updateUseRuleButtonsVisibility() {
    const savedRules = loadSavedRules();
    const useRuleWrappers = document.querySelectorAll('.use-saved-rules-wrapper');
    useRuleWrappers.forEach(wrapper => {
        const card = wrapper.closest('.question-card');
        const button = wrapper.querySelector('.use-saved-rule-btn');
        if (!card || !button) {
            return;
        }

        if (savedRules.length && card.getAttribute('data-question-type') === 'multiple-choice') {
            wrapper.classList.remove('hidden');
        } else {
            wrapper.classList.add('hidden');
        }

        if (card.querySelector('.saved-rule-payload')) {
            button.classList.remove('text-red-600', 'border', 'border-red-200');
            button.classList.add('bg-red-600', 'text-white');
        } else {
            button.classList.remove('bg-red-600', 'text-white');
            button.classList.add('text-red-600', 'border', 'border-red-200');
        }
    });

    attachSavedRuleButtons();
}

function gatherRulesFromSettings() {
    const titleInput = document.getElementById('rule-group-title-input');
    const title = titleInput?.value?.trim() || null;

    const templates = [];
    const templateCards = document.querySelectorAll('.answer-template-card');
    templateCards.forEach((card) => {
        const answerText = card.querySelector('.answer-template-text')?.value?.trim();
        const scoreValue = card.querySelector('.answer-template-score')?.value;
        if (answerText) {
            templates.push({
                answer_text: answerText,
                score: Number(scoreValue ?? 0) || 0,
            });
        }
    });

    if (!templates.length) {
        return null;
    }

    const resultRules = [];
    const ruleCards = document.querySelectorAll('.result-rule-card');
    ruleCards.forEach((card) => {
        const conditionType = card.querySelector('.rule-condition-type')?.value || 'range';
        const ruleData = {
            condition_type: conditionType,
            min_score: null,
            max_score: null,
            single_score: null,
            texts: [],
        };

        if (conditionType === 'range') {
            ruleData.min_score = parseInt(card.querySelector('.rule-min-score')?.value || '0', 10);
            ruleData.max_score = parseInt(card.querySelector('.rule-max-score')?.value || '0', 10);
        } else {
            ruleData.single_score = parseInt(card.querySelector('.rule-single-score')?.value || '0', 10);
        }

        const textareas = card.querySelectorAll('.rule-result-text');
        textareas.forEach((textarea) => {
            if (textarea.value && textarea.value.trim() !== '') {
                ruleData.texts.push(textarea.value.trim());
            }
        });

        if (ruleData.texts.length) {
            resultRules.push(ruleData);
        }
    });

    const ruleGroupId = generateRuleGroupId();

    const enrichedTemplates = templates.map((template) => ({
        ...template,
        rule_group_id: template.rule_group_id ?? ruleGroupId,
    }));

    const enrichedRules = resultRules.map((rule) => ({
        ...rule,
        rule_group_id: rule.rule_group_id ?? ruleGroupId,
    }));

    return normalizeSavedRule({
        id: Date.now(),
        rule_group_id: ruleGroupId,
        title: title,
        templates: enrichedTemplates,
        result_rules: enrichedRules,
    });
}

// Helper function to strip HTML tags and decode entities
function stripHTMLAndDecode(htmlString) {
    if (!htmlString || typeof htmlString !== 'string') {
        return htmlString || '';
    }

    // Create a temporary div element
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = htmlString;

    // Get text content (automatically strips HTML tags)
    const textContent = tempDiv.textContent || tempDiv.innerText || '';

    // Decode HTML entities
    const textarea = document.createElement('textarea');
    textarea.innerHTML = textContent;
    return textarea.value || textContent;
}

function setupBuilderResponses(options) {
    const {
        responsesUrl = '',
        totalResponses = 0,
    } = options || {};

    const summaryEmpty = document.getElementById('builder-summary-empty');
    const summaryLoading = document.getElementById('builder-summary-loading');
    const summaryContent = document.getElementById('builder-summary-content');
    const individualEmpty = document.getElementById('builder-individual-empty');
    const individualLoading = document.getElementById('builder-individual-loading');
    const individualToolbar = document.getElementById('builder-individual-toolbar');
    const individualContent = document.getElementById('builder-individual-content');
    const individualList = document.getElementById('builder-individual-list');
    const responsesError = document.getElementById('builder-responses-error');
    const totalResponsesEl = document.getElementById('builder-total-responses');
    const latestResponseEl = document.getElementById('builder-latest-response');
    const questionCountEl = document.getElementById('builder-question-count');
    const positionEl = document.getElementById('builder-response-position');
    const emailEl = document.getElementById('builder-response-email');
    const dateEl = document.getElementById('builder-response-date');
    const scoreEl = document.getElementById('builder-response-score');
    const answersContainer = document.getElementById('builder-response-answers');
    const prevBtn = document.getElementById('builder-prev-response');
    const nextBtn = document.getElementById('builder-next-response');

    // Export Logic
    const exportIndividualGroup = document.getElementById('builder-export-individual-group');
    const exportIndividualDropdownBtn = document.getElementById('builder-export-individual-dropdown-btn');
    const exportIndividualMenu = document.getElementById('builder-export-individual-menu');
    const exportCurrentBtn = document.getElementById('builder-export-current-btn');

    // View Mode Logic
    const viewModeDetailBtn = document.getElementById('view-mode-detail');
    const viewModeListBtn = document.getElementById('view-mode-list');

    if (!responsesUrl || totalResponses === 0) {
        requestBuilderResponsesData = null;
        return;
    }

    let responsesLoaded = false;
    let responsesLoading = false;
    let summaryData = [];
    let individualData = [];
    let currentResponseIndex = 0;
    let currentViewMode = 'detail'; // 'detail' or 'list'

    function setLoadingState(isLoading) {
        if (isLoading) {
            summaryLoading?.classList.remove('hidden');
            individualLoading?.classList.remove('hidden');
            summaryContent?.classList.add('hidden');
            individualContent?.classList.add('hidden');
            individualList?.classList.add('hidden');
            individualToolbar?.classList.add('hidden');
            responsesError?.classList.add('hidden');
            exportIndividualGroup?.classList.add('hidden');
        } else {
            summaryLoading?.classList.add('hidden');
            individualLoading?.classList.add('hidden');
        }
    }

    function showError(message) {
        if (responsesError) {
            responsesError.textContent = message;
            responsesError.classList.remove('hidden');
        }
    }

    function hideError() {
        responsesError?.classList.add('hidden');
    }

    function updateOverviewFromData(data) {
        if (typeof data.totalResponses === 'number' && totalResponsesEl) {
            totalResponsesEl.textContent = data.totalResponses;
        }
        if (Array.isArray(data.questionSummaries) && questionCountEl) {
            questionCountEl.textContent = data.questionSummaries.length;
        }
        if (latestResponseEl) {
            const latest = data.latestResponseAt
                || (Array.isArray(data.individualResponses) && data.individualResponses[0]
                    ? data.individualResponses[0].submitted_at
                    : null);
            latestResponseEl.textContent = latest || latestResponseEl.textContent || 'Belum ada data';
        }
    }

    function renderSummaryCards(items) {
        if (!summaryContent) {
            return;
        }

        summaryContent.innerHTML = '';

        if (!items.length) {
            summaryContent.classList.add('hidden');
            summaryEmpty?.classList.remove('hidden');
            return;
        }

        summaryEmpty?.classList.add('hidden');
        summaryContent.classList.remove('hidden');

        const chartItems = [];
        const colors = ['#F87171', '#FBBF24', '#34D399', '#60A5FA', '#A78BFA', '#F472B6', '#F97316', '#2DD4BF'];

        items.forEach((item, index) => {
            const card = document.createElement('div');
            card.className = 'border border-gray-100 rounded-xl p-5 shadow-sm';

            const header = document.createElement('div');
            header.className = 'flex items-start justify-between mb-4';

            const headerLeft = document.createElement('div');
            const label = document.createElement('p');
            label.className = 'text-xs text-gray-500 uppercase tracking-wide';
            label.textContent = `Pertanyaan ${index + 1}`;
            const title = document.createElement('h3');
            title.className = 'text-lg font-semibold text-gray-900';
            title.textContent = stripHTMLAndDecode(item.title || 'Pertanyaan');
            headerLeft.appendChild(label);
            headerLeft.appendChild(title);

            const totalPill = document.createElement('span');
            totalPill.className = 'text-xs font-medium text-gray-600 bg-gray-100 px-3 py-1 rounded-full';
            totalPill.textContent = `${item.total ?? 0} jawaban`;

            header.appendChild(headerLeft);
            header.appendChild(totalPill);
            card.appendChild(header);

            if (item.chart) {
                const chartWrapper = document.createElement('div');
                chartWrapper.className = 'h-64';
                const canvas = document.createElement('canvas');
                canvas.id = `builder-chart-${item.id}`;
                chartWrapper.appendChild(canvas);
                card.appendChild(chartWrapper);
                chartItems.push({ item, canvasId: canvas.id, colors });
            } else {
                const answersWrapper = document.createElement('div');
                answersWrapper.className = 'space-y-3';
                if (Array.isArray(item.text_answers) && item.text_answers.length) {
                    item.text_answers.forEach((answerText) => {
                        const answerEl = document.createElement('p');
                        answerEl.className = 'p-3 bg-gray-50 border border-gray-100 rounded-lg text-sm text-gray-700';
                        answerEl.textContent = stripHTMLAndDecode(answerText);
                        answersWrapper.appendChild(answerEl);
                    });
                } else {
                    const emptyEl = document.createElement('p');
                    emptyEl.className = 'text-sm text-gray-500';
                    emptyEl.textContent = 'Belum ada jawaban untuk pertanyaan ini.';
                    answersWrapper.appendChild(emptyEl);
                }
                card.appendChild(answersWrapper);
            }

            summaryContent.appendChild(card);
        });

        if (chartItems.length) {
            ensureChartJsLoaded()
                .then(() => {
                    chartItems.forEach(({ item, canvasId, colors: baseColors }) => {
                        const canvas = document.getElementById(canvasId);
                        if (!canvas || !window.Chart || !item.chart) {
                            return;
                        }
                        const datasetColors = item.chart.values.map((_, idx) => baseColors[idx % baseColors.length]);
                        // Strip HTML from chart labels
                        const cleanLabels = item.chart.labels.map(label => stripHTMLAndDecode(label));

                        new Chart(canvas, {
                            type: item.chart.type,
                            data: {
                                labels: cleanLabels,
                                datasets: [{
                                    label: 'Jumlah Jawaban',
                                    data: item.chart.values,
                                    backgroundColor: datasetColors,
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
                                scales: item.chart.type === 'bar'
                                    ? {
                                        y: {
                                            beginAtZero: true,
                                            ticks: { stepSize: 1 },
                                        },
                                    }
                                    : {},
                            },
                        });
                    });
                })
                .catch((error) => {
                    console.error(error);
                    showError('Grafik tidak dapat ditampilkan.');
                });
        }
    }

    function switchViewMode(mode) {
        currentViewMode = mode;
        if (mode === 'list') {
            viewModeListBtn?.classList.add('bg-white', 'text-red-600', 'shadow-sm');
            viewModeListBtn?.classList.remove('text-gray-500');
            viewModeDetailBtn?.classList.remove('bg-white', 'text-red-600', 'shadow-sm');
            viewModeDetailBtn?.classList.add('text-gray-500');

            individualContent?.classList.add('hidden');
            individualList?.classList.remove('hidden');
            renderResponsesList();
        } else {
            viewModeDetailBtn?.classList.add('bg-white', 'text-red-600', 'shadow-sm');
            viewModeDetailBtn?.classList.remove('text-gray-500');
            viewModeListBtn?.classList.remove('bg-white', 'text-red-600', 'shadow-sm');
            viewModeListBtn?.classList.add('text-gray-500');

            individualList?.classList.add('hidden');
            individualContent?.classList.remove('hidden');
            renderIndividualResponse(currentResponseIndex);
        }
    }

    function renderResponsesList() {
        if (!individualList) return;
        individualList.innerHTML = '';

        individualData.forEach((response, index) => {
            const card = document.createElement('div');
            card.className = 'bg-white border border-gray-100 rounded-xl p-5 shadow-sm hover:border-red-300 hover:shadow-md transition-all cursor-pointer group';
            card.innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Jawaban ke-${response.position || (index + 1)}</p>
                        <p class="text-lg font-bold text-gray-900 group-hover:text-red-600 transition-colors">${response.email || 'Anonim'}</p>
                        <p class="text-sm text-gray-400">${response.submitted_at || '-'}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 uppercase tracking-wider">Skor Total</p>
                        <p class="text-2xl font-black text-gray-900">${response.total_score ?? 0}</p>
                    </div>
                </div>
            `;
            card.addEventListener('click', () => {
                currentResponseIndex = index;
                switchViewMode('detail');
            });
            individualList.appendChild(card);
        });
    }

    function renderIndividualResponse(index) {
        if (!answersContainer) {
            return;
        }
        const data = individualData[index];
        if (!data) {
            return;
        }

        if (positionEl) {
            positionEl.textContent = data.position || (index + 1);
        }
        if (emailEl) {
            emailEl.textContent = data.email || 'Anonim';
        }
        if (dateEl) {
            dateEl.textContent = data.submitted_at || '-';
        }
        if (scoreEl) {
            scoreEl.textContent = data.total_score ?? '-';
        }

        answersContainer.innerHTML = '';

        // Render Interpreted Result (Rich Structure)
        if (data.derived_metrics && data.derived_metrics.result_details) {
            const resultDetails = data.derived_metrics.result_details;
            if (resultDetails.texts && resultDetails.texts.length > 0) {
                const resultsWrapper = document.createElement('div');
                resultsWrapper.className = 'mb-6 space-y-4';

                resultDetails.texts.forEach(text => {
                    const card = document.createElement('div');
                    card.className = 'p-5 bg-green-50 border border-green-100 rounded-xl shadow-sm';

                    const ruleGroupTitle = formRuleGroups[text.rule_group_id];
                    console.log('Rendering Result Text:', {
                        text_rule_group_id: text.rule_group_id,
                        ruleGroupTitle: ruleGroupTitle,
                        allGroups: formRuleGroups
                    });
                    const displayTitle = ruleGroupTitle || text.title || 'Interpretasi Hasil';

                    let content = `
                        <div class="flex items-center space-x-2 mb-3">
                            <div class="w-2 h-4 bg-green-500 rounded-full"></div>
                            <p class="text-xs font-bold text-green-700 uppercase tracking-widest">${displayTitle}</p>
                        </div>
                    `;

                    if (text.image_url) {
                        content += `
                            <div class="mb-4 flex justify-center">
                                <img src="${text.image_url}" class="max-w-full h-auto rounded-lg border border-green-100 shadow-sm" style="max-height: 250px;">
                            </div>
                        `;
                    }

                    content += `<div class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">${text.result_text}</div>`;

                    card.innerHTML = content;
                    resultsWrapper.appendChild(card);
                });

                answersContainer.appendChild(resultsWrapper);
            }
        } else if (data.result_text) {
            // Fallback for legacy plain text results
            const resultWrapper = document.createElement('div');
            resultWrapper.className = 'mb-6 p-5 bg-green-50 border border-green-100 rounded-xl shadow-sm';
            resultWrapper.innerHTML = `
                <div class="flex items-center space-x-2 mb-3">
                    <div class="w-2 h-4 bg-green-500 rounded-full"></div>
                    <p class="text-xs font-bold text-green-700 uppercase tracking-widest">Interpretasi Hasil</p>
                </div>
                <div class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">${data.result_text}</div>
            `;
            answersContainer.appendChild(resultWrapper);
        }

        // Render Derived Metrics (BMI, etc.)
        if (data.derived_metrics && Object.keys(data.derived_metrics).length > 0) {
            const metricsWrapper = document.createElement('div');
            metricsWrapper.className = 'mb-6 p-4 bg-red-50 border border-red-100 rounded-xl';
            metricsWrapper.innerHTML = `<p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-3">Analisis Kesehatan</p>`;

            const grid = document.createElement('div');
            grid.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';

            Object.entries(data.derived_metrics).forEach(([key, metric]) => {
                const card = document.createElement('div');
                card.className = 'bg-white p-3 rounded-lg border border-red-100 shadow-sm';
                card.innerHTML = `
                    <p class="text-[10px] text-gray-500 font-medium uppercase">${metric.label}</p>
                    <div class="flex items-baseline space-x-1">
                        <span class="text-xl font-bold text-red-700">${metric.value}</span>
                        <span class="text-xs text-red-400">${key === 'bmi' ? 'kg/m²' : ''}</span>
                    </div>
                    ${metric.category ? `<p class="text-xs font-bold text-red-600 mt-1">${metric.category}</p>` : ''}
                    ${key === 'bmi' && metric.weight && metric.height ? `<p class="text-[9px] text-gray-400 mt-1">BB: ${metric.weight}kg, TB: ${metric.height}cm</p>` : ''}
                `;
                grid.appendChild(card);
            });
            metricsWrapper.appendChild(grid);
            answersContainer.appendChild(metricsWrapper);
        }

        // Render Section Scores
        if (data.section_scores && Object.keys(data.section_scores).length > 0) {
            const scoresWrapper = document.createElement('div');
            scoresWrapper.className = 'mb-6 p-4 bg-gray-50 border border-gray-100 rounded-xl';
            scoresWrapper.innerHTML = `<p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Skor per Bagian</p>`;

            const list = document.createElement('div');
            list.className = 'space-y-3';

            Object.entries(data.section_scores).forEach(([id, sData]) => {
                const item = document.createElement('div');
                item.innerHTML = `
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-medium text-gray-700">${stripHTMLAndDecode(sData.title || 'Bagian')}</span>
                        <span class="text-xs font-bold text-red-600">${sData.score}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        <div class="bg-red-500 h-1.5 rounded-full" style="width: 100%"></div>
                    </div>
                `;
                list.appendChild(item);
            });
            scoresWrapper.appendChild(list);
            answersContainer.appendChild(scoresWrapper);
        }

        const answersLabel = document.createElement('p');
        answersLabel.className = 'text-xs font-bold text-gray-500 uppercase tracking-wider mb-3';
        answersLabel.textContent = 'Rincian Jawaban';
        answersContainer.appendChild(answersLabel);
        if (!Array.isArray(data.answers) || !data.answers.length) {
            const empty = document.createElement('p');
            empty.className = 'text-sm text-gray-500';
            empty.textContent = 'Tidak ada jawaban yang tersedia.';
            answersContainer.appendChild(empty);
        } else {
            data.answers.forEach((answer) => {
                const block = document.createElement('div');
                block.className = 'p-4 border border-gray-100 rounded-lg';

                const title = document.createElement('p');
                title.className = 'text-sm font-medium text-gray-900';
                title.textContent = stripHTMLAndDecode(answer.question || 'Pertanyaan');

                const value = document.createElement('p');
                value.className = 'mt-1 text-sm text-gray-700';
                value.textContent = stripHTMLAndDecode(answer.value || '-');

                block.appendChild(title);
                block.appendChild(value);
                answersContainer.appendChild(block);
            });
        }

        if (prevBtn) {
            prevBtn.disabled = index === 0;
        }
        if (nextBtn) {
            nextBtn.disabled = index === individualData.length - 1;
        }
    }

    function initializeIndividualSection() {
        if (!individualContent) {
            return;
        }

        if (!individualData.length) {
            individualEmpty?.classList.remove('hidden');
            individualToolbar?.classList.add('hidden');
            individualContent?.classList.add('hidden');
            individualList?.classList.add('hidden');
            exportIndividualGroup?.classList.add('hidden');
            return;
        }

        individualEmpty?.classList.add('hidden');
        individualToolbar?.classList.remove('hidden');
        exportIndividualGroup?.classList.remove('hidden');

        switchViewMode(currentViewMode);
    }

    // List view and Export listeners
    if (viewModeDetailBtn) {
        viewModeDetailBtn.addEventListener('click', () => switchViewMode('detail'));
    }
    if (viewModeListBtn) {
        viewModeListBtn.addEventListener('click', () => switchViewMode('list'));
    }

    if (exportIndividualDropdownBtn) {
        exportIndividualDropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            exportIndividualMenu?.classList.toggle('hidden');
        });
        document.addEventListener('click', () => {
            exportIndividualMenu?.classList.add('hidden');
        });
    }

    if (exportCurrentBtn) {
        exportCurrentBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const response = individualData[currentResponseIndex];
            if (!response || !response.id) return;

            const formId = document.getElementById('form-builder-root')?.getAttribute('data-form-id');
            if (!formId) return;

            // Redirect to single export route
            window.location.href = `/forms/${formId}/export-individual-single/${response.id}`;
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentResponseIndex > 0) {
                currentResponseIndex -= 1;
                renderIndividualResponse(currentResponseIndex);
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentResponseIndex < individualData.length - 1) {
                currentResponseIndex += 1;
                renderIndividualResponse(currentResponseIndex);
            }
        });
    }

    requestBuilderResponsesData = async function () {
        if (responsesLoaded || responsesLoading) {
            return;
        }

        responsesLoading = true;
        hideError();
        setLoadingState(true);

        try {
            const response = await fetch(responsesUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Gagal memuat data jawaban.');
            }

            const payload = await response.json();
            if (!payload.success || !payload.data) {
                throw new Error(payload.message || 'Gagal memuat data jawaban.');
            }

            const data = payload.data;
            summaryData = Array.isArray(data.questionSummaries) ? data.questionSummaries : [];
            individualData = Array.isArray(data.individualResponses) ? data.individualResponses : [];

            updateOverviewFromData(data);

            if (summaryData.length) {
                renderSummaryCards(summaryData);
            } else {
                summaryContent?.classList.add('hidden');
                summaryEmpty?.classList.remove('hidden');
            }

            if (individualData.length) {
                initializeIndividualSection();
            } else {
                individualContent?.classList.add('hidden');
                individualEmpty?.classList.remove('hidden');
            }

            responsesLoaded = true;
        } catch (error) {
            console.error(error);
            showError(error.message || 'Tidak dapat memuat data jawaban.');
        } finally {
            responsesLoading = false;
            setLoadingState(false);
        }
    };
}

function applySavedRuleToQuestion(questionCard, savedRule) {
    if (!questionCard || !savedRule) {
        return;
    }

    const rule = normalizeSavedRule(savedRule);
    const metaKey = 'savedRuleApplied';
    const templates = Array.isArray(rule.templates) ? rule.templates : [];

    if (questionCard.getAttribute('data-question-type') === 'multiple-choice' && templates.length) {
        const normalizedOptions = templates.map(template => ({
            text: template.answer_text || template.text || '',
        }));
        setQuestionOptions(questionCard, 'multiple-choice', normalizedOptions);
        attachOptionEvents(questionCard);
    }

    let ruleBadgesContainer = questionCard.querySelector('.saved-rule-badges');
    if (!ruleBadgesContainer) {
        ruleBadgesContainer = document.createElement('div');
        ruleBadgesContainer.className = 'saved-rule-badges flex flex-wrap gap-2 mt-2';
        const wrapper = questionCard.querySelector('.use-saved-rules-wrapper');
        if (wrapper) {
            wrapper.insertAdjacentElement('afterend', ruleBadgesContainer);
        } else {
            questionCard.appendChild(ruleBadgesContainer);
        }
    }
    ruleBadgesContainer.innerHTML = '';

    const badge = document.createElement('span');
    badge.className = 'inline-flex items-center px-3 py-1 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded-full space-x-2';
    let label = rule.title;
    if (!label) {
        const parts = [];
        const resultRules = Array.isArray(rule.result_rules) ? rule.result_rules : [];
        if (templates.length) {
            parts.push(`${templates.length} opsi`);
        }
        if (resultRules.length) {
            parts.push(`${resultRules.length} hasil`);
        }
        label = parts.join(' • ');
    }

    badge.innerHTML = `
        <span>Aturan: ${label}</span>
        <button type="button" class="remove-applied-rule text-red-500 hover:text-red-700">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    ruleBadgesContainer.appendChild(badge);

    questionCard.setAttribute(metaKey, 'true');

    const ruleInput = document.createElement('input');
    ruleInput.type = 'hidden';
    ruleInput.className = 'saved-rule-payload';
    ruleInput.value = JSON.stringify(rule);

    const existingPayload = questionCard.querySelector('.saved-rule-payload');
    if (existingPayload) {
        existingPayload.remove();
    }

    questionCard.appendChild(ruleInput);

    const useRuleBtn = questionCard.querySelector('.use-saved-rule-btn');
    if (useRuleBtn) {
        useRuleBtn.classList.remove('text-red-600', 'border', 'border-red-200');
        useRuleBtn.classList.add('bg-red-600', 'text-white');
    }

    const removeAppliedBtn = badge.querySelector('.remove-applied-rule');
    if (removeAppliedBtn) {
        removeAppliedBtn.addEventListener('click', () => {
            const payload = questionCard.querySelector('.saved-rule-payload');
            if (payload) {
                payload.remove();
            }
            badge.remove();
            questionCard.removeAttribute(metaKey);
            if (useRuleBtn) {
                useRuleBtn.classList.remove('bg-red-600', 'text-white');
                useRuleBtn.classList.add('text-red-600', 'border', 'border-red-200');
            }
            updateUseRuleButtonsVisibility();
        });
    }

    updateUseRuleButtonsVisibility();
}

function attachSavedRuleButtons() {
    const savedRules = loadSavedRules();
    const questionCards = document.querySelectorAll('.question-card');

    questionCards.forEach((card) => {
        const type = card.getAttribute('data-question-type');
        const wrapper = card.querySelector('.use-saved-rules-wrapper');
        if (!wrapper) {
            return;
        }

        if (type === 'multiple-choice' && savedRules.length) {
            wrapper.classList.remove('hidden');
        } else {
            wrapper.classList.add('hidden');
            return;
        }

        let button = wrapper.querySelector('.use-saved-rule-btn');
        if (button && !button.hasAttribute('data-listener')) {
            button.setAttribute('data-listener', 'true');
            button.addEventListener('click', function () {
                const bundles = loadSavedRules();
                if (!bundles.length) {
                    return;
                }

                const payload = card.querySelector('.saved-rule-payload');
                if (payload) {
                    payload.remove();
                    const badgeContainer = card.querySelector('.saved-rule-badges');
                    if (badgeContainer) {
                        badgeContainer.remove();
                    }
                    card.removeAttribute('savedRuleApplied');
                    updateUseRuleButtonsVisibility();
                    return;
                }

                const menu = document.createElement('div');
                menu.className = 'absolute z-50 mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg p-2 space-y-1';
                bundles.forEach((bundle, index) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md';
                    const normalized = normalizeSavedRule(bundle);
                    if (normalized.title) {
                        option.textContent = normalized.title;
                    } else {
                        const parts = [];
                        if (normalized.templates?.length) {
                            parts.push(`${normalized.templates.length} opsi`);
                        }
                        if (normalized.result_rules?.length) {
                            parts.push(`${normalized.result_rules.length} hasil`);
                        }
                        option.textContent = parts.join(' • ') || `Aturan ${index + 1}`;
                    }
                    option.addEventListener('click', () => {
                        applySavedRuleToQuestion(card, normalized);
                        menu.remove();
                    });
                    menu.appendChild(option);
                });

                const existingMenu = wrapper.querySelector('.saved-rules-menu');
                if (existingMenu) {
                    existingMenu.remove();
                }
                menu.classList.add('saved-rules-menu');
                wrapper.style.position = 'relative';
                wrapper.appendChild(menu);

                setTimeout(() => {
                    document.addEventListener('click', function handleClickOutside(e) {
                        if (!menu.contains(e.target)) {
                            menu.remove();
                            document.removeEventListener('click', handleClickOutside);
                        }
                    });
                }, 0);
            });
        }
    });
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function () {
    // Initialize tabs
    initTabs();

    // Initialize card formatting toolbar
    initCardFormattingToolbar();

    // Initialize header setup
    initHeaderSetup();

    const rootElement = document.getElementById('form-builder-root');
    let initialData = null;
    let formMode = 'create';
    let formId = getMetaContent('form-id');
    let saveFormUrl = getMetaContent('save-form-url') || '/forms';
    let saveFormMethod = (getMetaContent('save-form-method') || 'POST').toUpperCase();
    let formRulesSaveUrl = getMetaContent('form-rules-save-url') || '';
    const shareLinkBtn = document.getElementById('share-link-btn');
    let shareLinkUrl = rootElement?.getAttribute('data-share-url') || '';
    const responsesDataUrl = rootElement?.getAttribute('data-responses-url') || '';
    const totalResponsesHint = Number(rootElement?.getAttribute('data-total-responses') || '0');

    if (rootElement) {
        const initialAttr = rootElement.getAttribute('data-initial');
        if (initialAttr) {
            try {
                initialData = JSON.parse(initialAttr);
                ruleGroupTextLookup = buildRuleGroupTextLookup(initialData);
            } catch (error) {
                console.error('Failed to parse initial form data:', error);
            }
        }

        formMode = rootElement.getAttribute('data-mode') || 'create';

        const savedRulesAttr = rootElement.getAttribute('data-saved-rules');
        if (savedRulesAttr) {
            try {
                const parsedSavedRules = JSON.parse(savedRulesAttr) || [];
                saveRulesToState(parsedSavedRules);
            } catch (error) {
                console.error('Failed to parse saved rules data:', error);
            }
        }
    }

    const updateShareButtonVisibility = () => {
        if (!shareLinkBtn) {
            return;
        }

        if (shareLinkUrl) {
            shareLinkBtn.classList.remove('hidden');
            shareLinkBtn.disabled = false;
        } else {
            shareLinkBtn.classList.add('hidden');
            shareLinkBtn.disabled = true;
        }
    };

    updateShareButtonVisibility();

    if (shareLinkBtn) {
        shareLinkBtn.addEventListener('click', async function () {
            if (shareLinkUrl) {
                // Langsung copy link ke clipboard saat tombol diklik
                try {
                    if (navigator.clipboard?.writeText) {
                        await navigator.clipboard.writeText(shareLinkUrl);
                    } else {
                        // Fallback untuk browser lama
                        const textArea = document.createElement('textarea');
                        textArea.value = shareLinkUrl;
                        textArea.style.position = 'fixed';
                        textArea.style.left = '-999999px';
                        textArea.style.top = '-999999px';
                        document.body.appendChild(textArea);
                        textArea.focus();
                        textArea.select();
                        document.execCommand('copy');
                        textArea.remove();
                    }
                    // Tampilkan dialog informasi bahwa link sudah disalin
                    openShareModal(shareLinkUrl);
                } catch (error) {
                    console.error('Failed to copy link:', error);
                    // Jika gagal copy, tampilkan dialog dengan tombol salin manual
                    openShareModal(shareLinkUrl, true);
                }
            }
        });
    }

    const themeColorButtons = document.querySelectorAll('[data-theme-color]');
    themeColorButtons.forEach((button) => {
        button.addEventListener('click', function () {
            const color = this.getAttribute('data-theme-color');
            updateThemeColorSelection(color);
        });
    });

    if (formMode === 'edit') {
        const saveBtnLabel = document.querySelector('#save-form-btn span');
        if (saveBtnLabel) {
            saveBtnLabel.textContent = 'Update Form';
        }
    }

    if (initialData) {
        if (initialData.saved_rules) {
            saveRulesToState(initialData.saved_rules);
        }
        populateFormBuilder(initialData);
    } else {
        updateThemeColorSelection('red');
        updateMainButtonsVisibility();
    }

    updateRuleSaveControlsVisibility();
    setupBuilderResponses({
        responsesUrl: responsesDataUrl,
        totalResponses: totalResponsesHint,
    });

    // Initialize answer templates
    const addAnswerTemplateBtn = document.getElementById('add-answer-template-btn');
    const answerTemplatesContainer = document.getElementById('answer-templates-container');

    if (addAnswerTemplateBtn && answerTemplatesContainer) {
        addAnswerTemplateBtn.addEventListener('click', function () {
            if (answerTemplatesContainer.querySelector('.answer-templates-placeholder')) {
                answerTemplatesContainer.innerHTML = '';
            }
            appendAnswerTemplateCard(answerTemplatesContainer);
            updateRuleSaveControlsVisibility();
        });
    }

    const saveRulesBtn = document.getElementById('save-form-rules-btn');
    const cancelRulesBtn = document.getElementById('cancel-form-rules-btn');
    defaultSaveRulesLabel = saveRulesBtn?.textContent?.trim() || defaultSaveRulesLabel;

    if (cancelRulesBtn) {
        cancelRulesBtn.addEventListener('click', () => {
            exitRulesEditMode();
            resetFormRulesBuilder();
        });
    }

    exitRulesEditMode();

    if (saveRulesBtn) {
        saveRulesBtn.addEventListener('click', async function () {
            if (!formRulesSaveUrl) {
                alert('Simpan form terlebih dahulu sebelum menyimpan aturan.');
                return;
            }

            const formSnapshot = collectFormData();
            const answerTemplatesPayload = formSnapshot.answer_templates || [];
            const resultRulesPayload = formSnapshot.result_rules || [];

            if (!answerTemplatesPayload.length || !resultRulesPayload.length) {
                alert('Tambahkan template jawaban dan aturan hasil terlebih dahulu.');
                return;
            }

            saveRulesBtn.disabled = true;
            saveRulesBtn.textContent = 'Menyimpan...';

            try {
                const response = await fetch(formRulesSaveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getMetaContent('csrf-token'),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        answer_templates: answerTemplatesPayload,
                        result_rules: resultRulesPayload,
                        rule_group_id: editingRuleGroupId,
                        rule_group_title: document.getElementById('rule-group-title-input')?.value?.trim() || null,
                    }),
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Gagal menyimpan aturan.');
                }

                const bundle = data.bundle || {
                    rule_group_id: data.rule_group_id || editingRuleGroupId,
                    templates: data.answer_templates || answerTemplatesPayload,
                    result_rules: data.result_rules || resultRulesPayload,
                };
                const normalizedRule = normalizeSavedRule(bundle);

                if (data.form_rules_save_url) {
                    formRulesSaveUrl = data.form_rules_save_url;
                    setMetaContent('form-rules-save-url', data.form_rules_save_url);
                }

                const currentRules = loadSavedRules();
                if (editingRuleGroupId) {
                    const existingIndex = currentRules.findIndex((rule) => rule.rule_group_id === editingRuleGroupId);
                    if (existingIndex !== -1) {
                        currentRules[existingIndex] = normalizedRule;
                    } else {
                        currentRules.push(normalizedRule);
                    }
                } else {
                    currentRules.push(normalizedRule);
                }
                saveRulesToState(currentRules);
                renderSavedRulesChips();
                updateUseRuleButtonsVisibility();
                resetFormRulesBuilder();
                exitRulesEditMode();
                showSuccessDialog(data.message || 'Aturan form berhasil disimpan.');
            } catch (error) {
                console.error(error);
                alert(error.message || 'Terjadi kesalahan saat menyimpan aturan.');
            } finally {
                saveRulesBtn.disabled = false;
                saveRulesBtn.textContent = editingRuleGroupId ? 'Update Aturan' : defaultSaveRulesLabel;
            }
        });
    }

    renderSavedRulesChips();
    updateUseRuleButtonsVisibility();

    // Initialize result rules
    const addResultRuleBtn = document.getElementById('add-result-rule-btn');
    const resultRulesContainer = document.getElementById('result-rules-container');

    if (addResultRuleBtn && resultRulesContainer) {
        addResultRuleBtn.addEventListener('click', function () {
            if (resultRulesContainer.querySelector('.result-rules-placeholder')) {
                resultRulesContainer.innerHTML = '';
            }
            appendResultRuleCard(resultRulesContainer);
        });
    }

    const addQuestionBtn = document.getElementById('add-question-btn');
    const questionTypesMenu = document.getElementById('question-types-menu');
    const questionsContainer = document.getElementById('questions-container');
    const questionTypeButtons = document.querySelectorAll('.question-type-btn');

    // Langsung tambahkan pertanyaan default saat klik tombol

    // Sama seperti quick-add button di dalam kartu
    if (addQuestionBtn && !addQuestionBtn.hasAttribute('data-listener')) {
        addQuestionBtn.setAttribute('data-listener', 'true');
        addQuestionBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const questionCard = createQuestionCard('short-answer');
            questionsContainer.appendChild(questionCard);
            updateQuestionNumbers();
            attachQuestionCardEvents(questionCard);
            initSortable();
            updateMainButtonsVisibility();

            // Focus ke input pertanyaan
            setTimeout(() => {
                const titleInput = questionCard.querySelector('.question-title');
                if (titleInput) {
                    titleInput.focus();
                }
            }, 100);
        });
    }

    // Tambahkan section divider
    const addSectionBtn = document.getElementById('add-section-btn');
    if (addSectionBtn) {
        addSectionBtn.addEventListener('click', function () {
            const sectionDivider = createSectionDivider();
            questionsContainer.appendChild(sectionDivider);
            attachSectionEvents(sectionDivider);

            // Attach delete event
            const deleteBtn = sectionDivider.querySelector('.delete-section-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function () {
                    sectionDivider.remove();
                    updateSectionNumbers();
                    updateMainButtonsVisibility();
                });
            }

            initSortable();
            updateMainButtonsVisibility();
        });
    }

    // Initial check for main buttons visibility
    updateMainButtonsVisibility();

    // Tutup menu saat klik di luar (untuk modal yang mungkin masih digunakan di tempat lain)
    if (questionTypesMenu) {
        questionTypesMenu.addEventListener('click', function (e) {
            if (e.target === questionTypesMenu) {
                questionTypesMenu.classList.add('hidden');
                questionTypesMenu.classList.remove('flex', 'items-center', 'justify-center');
            }
        });
    }

    // Handle pemilihan jenis pertanyaan dari modal (jika masih digunakan)
    questionTypeButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const type = this.getAttribute('data-type');
            const questionCard = createQuestionCard(type);
            questionsContainer.appendChild(questionCard);
            questionTypesMenu.classList.add('hidden');
            questionTypesMenu.classList.remove('flex', 'items-center', 'justify-center');
            updateQuestionNumbers();
            attachQuestionCardEvents(questionCard);
            initSortable();

        });
    });

    // Attach events untuk question cards yang sudah ada
    document.querySelectorAll('.question-card').forEach(card => {
        attachQuestionCardEvents(card);
    });

    // Save Form Button
    const saveFormBtn = document.getElementById('save-form-btn');
    if (saveFormBtn) {
        saveFormBtn.addEventListener('click', function () {
            const originalButtonHtml = saveFormBtn.getAttribute('data-original-html') || saveFormBtn.innerHTML;
            saveFormBtn.setAttribute('data-original-html', originalButtonHtml);

            saveFormBtn.disabled = true;
            saveFormBtn.innerHTML = `
                <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span class="text-sm font-medium">Menyimpan...</span>
            `;

            const formData = collectFormData({ includeRules: false });
            const csrfToken = getMetaContent('csrf-token');

            const savedRules = loadSavedRules();
            formData.saved_rules = savedRules;

            // Debug: log result_text_settings and text_formatting
            console.log('[FormBuilder] Saving form data:', {
                result_text_settings_count: formData.result_text_settings?.length || 0,
                result_text_settings: formData.result_text_settings,
                text_formatting_count: formData.text_formatting?.length || 0,
                text_formatting: formData.text_formatting,
            });

            fetch(saveFormUrl, {
                method: saveFormMethod,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(formData)
            })
                .then(async (response) => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) {
                        const message = data && data.message ? data.message : 'Gagal menyimpan form.';
                        throw new Error(message);
                    }
                    return data;
                })
                .then(data => {
                    if (data.form_id) {
                        formId = data.form_id;
                        setMetaContent('form-id', formId);
                        if (rootElement) {
                            rootElement.setAttribute('data-form-id', formId);
                        }
                    }

                    if (data.update_url) {
                        saveFormUrl = data.update_url;
                        setMetaContent('save-form-url', saveFormUrl);
                    }

                    if (data.save_method) {
                        saveFormMethod = data.save_method.toUpperCase();
                        setMetaContent('save-form-method', saveFormMethod);
                    } else if (saveFormMethod !== 'PUT') {
                        saveFormMethod = 'PUT';
                        setMetaContent('save-form-method', 'PUT');
                    }

                    if (data.share_url) {
                        shareLinkUrl = data.share_url;
                        if (rootElement) {
                            rootElement.setAttribute('data-share-url', shareLinkUrl);
                        }
                        updateShareButtonVisibility();
                    }

                    if (data.form_rules_save_url) {
                        formRulesSaveUrl = data.form_rules_save_url;
                        setMetaContent('form-rules-save-url', data.form_rules_save_url);
                    }

                    formMode = 'edit';
                    const saveBtnLabel = saveFormBtn.querySelector('span');
                    if (saveBtnLabel) {
                        saveBtnLabel.textContent = 'Update Form';
                    }

                    showSuccessDialog(data.message || 'Form berhasil disimpan!');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(error.message || 'Terjadi kesalahan saat menyimpan form. Silakan coba lagi.');
                })
                .finally(() => {
                    saveFormBtn.disabled = false;
                    saveFormBtn.innerHTML = saveFormBtn.getAttribute('data-original-html') || originalButtonHtml;
                });
        });
    }
});

// Fungsi untuk attach events ke question card
function attachQuestionCardEvents(card) {
    // Delete question
    const deleteBtn = card.querySelector('.delete-question-btn');
    if (deleteBtn && !deleteBtn.hasAttribute('data-listener')) {
        deleteBtn.setAttribute('data-listener', 'true');
        deleteBtn.addEventListener('click', function () {
            card.remove();
            updateQuestionNumbers();
            updateMainButtonsVisibility();
        });
    }

    // Duplicate question
    const duplicateBtn = card.querySelector('.duplicate-question-btn');
    if (duplicateBtn && !duplicateBtn.hasAttribute('data-listener')) {
        duplicateBtn.setAttribute('data-listener', 'true');
        duplicateBtn.addEventListener('click', function () {
            const type = card.getAttribute('data-question-type');
            const newCard = createQuestionCard(type);
            const titleElement = card.querySelector('.question-title');
            const title = titleElement ? getHTMLFromContentEditable(titleElement) : '';
            if (title) {
                const newTitleElement = newCard.querySelector('.question-title');
                if (newTitleElement) {
                    if (newTitleElement.contentEditable === 'true') {
                        newTitleElement.innerHTML = title;
                    } else {
                        newTitleElement.value = title;
                    }
                }
            }
            card.parentNode.insertBefore(newCard, card.nextSibling);
            updateQuestionNumbers();
            attachQuestionCardEvents(newCard);
            updateMainButtonsVisibility();
        });
    }

    // More options panel
    const moreOptionsBtn = card.querySelector('.more-options-btn');
    const advancedSettings = card.querySelector('.question-advanced-settings');
    if (moreOptionsBtn && advancedSettings) {
        moreOptionsBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            advancedSettings.classList.toggle('hidden');
        });
    }

    // Question type dropdown
    const typeDropdownBtn = card.querySelector('.question-type-dropdown-btn');
    const typeDropdown = card.querySelector('.question-type-dropdown');
    if (typeDropdownBtn && typeDropdown) {
        typeDropdownBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            typeDropdown.classList.toggle('hidden');
        });

        // Close dropdown saat klik di luar
        document.addEventListener('click', function (e) {
            if (!typeDropdownBtn.contains(e.target) && !typeDropdown.contains(e.target)) {
                typeDropdown.classList.add('hidden');
            }
        });

        // Change question type
        const changeTypeBtns = card.querySelectorAll('.change-question-type-btn');
        changeTypeBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const newType = this.getAttribute('data-type');
                const template = questionTemplates[newType];
                const inputArea = card.querySelector('.question-input-area');
                inputArea.innerHTML = template.input;

                // Update icon and label
                const iconEl = card.querySelector('.question-type-icon');
                const labelEl = card.querySelector('.question-type-label');
                if (iconEl) iconEl.innerHTML = template.icon;
                if (labelEl) labelEl.textContent = template.label;

                card.setAttribute('data-question-type', newType);
                typeDropdown.classList.add('hidden');

                attachOptionEvents(card);

                if (newType !== 'multiple-choice') {
                    const wrapper = card.querySelector('.use-saved-rules-wrapper');
                    if (wrapper) {
                        wrapper.classList.add('hidden');
                    }
                    const payload = card.querySelector('.saved-rule-payload');
                    if (payload) {
                        payload.remove();
                    }
                    const badgeContainer = card.querySelector('.saved-rule-badges');
                    if (badgeContainer) {
                        badgeContainer.remove();
                    }
                    card.removeAttribute('savedRuleApplied');
                }

                updateUseRuleButtonsVisibility();
                attachSavedRuleButtons();
            });
        });
    }

    // Attach option events
    attachOptionEvents(card);

    // Quick add buttons
    const quickAddButtons = card.querySelector('.question-quick-add-buttons');
    const quickAddQuestionBtn = card.querySelector('.quick-add-question-btn');
    const quickAddSectionBtn = card.querySelector('.quick-add-section-btn');
    const quickAddResultSettingBtn = card.querySelector('.quick-add-result-setting-btn');

    const hasResultRules = () => {
        const resultRulesContainer = document.getElementById('result-rules-container');
        if (!resultRulesContainer) return false;
        const placeholder = resultRulesContainer.querySelector('.result-rules-placeholder');
        const ruleCards = resultRulesContainer.querySelectorAll('.result-rule-card');
        return !placeholder && ruleCards.length > 0;
    };

    // Function to show/hide quick add buttons
    const showQuickAddButtons = () => {
        if (quickAddButtons) {
            quickAddButtons.classList.remove('hidden');
            // Show/hide setup hasil button based on result rules
            if (quickAddResultSettingBtn) {
                if (hasResultRules()) {
                    quickAddResultSettingBtn.classList.remove('hidden');
                    quickAddResultSettingBtn.classList.add('flex');
                } else {
                    quickAddResultSettingBtn.classList.add('hidden');
                    quickAddResultSettingBtn.classList.remove('flex');
                }
            }
        }
    };

    const hideQuickAddButtons = () => {
        if (quickAddButtons) {
            quickAddButtons.classList.add('hidden');
        }
    };

    // Quick add question button
    if (quickAddQuestionBtn && !quickAddQuestionBtn.hasAttribute('data-listener')) {
        quickAddQuestionBtn.setAttribute('data-listener', 'true');
        quickAddQuestionBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const questionsContainer = document.getElementById('questions-container');
            if (questionsContainer) {
                const questionCard = createQuestionCard('short-answer');
                questionsContainer.insertBefore(questionCard, card.nextSibling);
                updateQuestionNumbers();
                attachQuestionCardEvents(questionCard);
                initSortable();
                updateMainButtonsVisibility();

                setTimeout(() => {
                    const titleInput = questionCard.querySelector('.question-title');
                    if (titleInput) {
                        titleInput.focus();
                    }
                }, 100);
            }
        });
    }

    if (quickAddResultSettingBtn && !quickAddResultSettingBtn.hasAttribute('data-listener')) {
        quickAddResultSettingBtn.setAttribute('data-listener', 'true');
        quickAddResultSettingBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const questionsContainer = document.getElementById('questions-container');
            if (!questionsContainer) {
                return;
            }
            const resultSettingCard = createResultSettingCard();
            attachResultSettingEvents(resultSettingCard);
            if (card.nextSibling) {
                questionsContainer.insertBefore(resultSettingCard, card.nextSibling);
            } else {
                questionsContainer.appendChild(resultSettingCard);
            }
            updateMainButtonsVisibility();
            resultSettingCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }

    // Quick add section button
    if (quickAddSectionBtn) {
        quickAddSectionBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const questionsContainer = document.getElementById('questions-container');
            if (questionsContainer) {
                const sectionDivider = createSectionDivider();
                questionsContainer.insertBefore(sectionDivider, card.nextSibling);
                attachSectionEvents(sectionDivider);

                const deleteBtn = sectionDivider.querySelector('.delete-section-btn');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function () {
                        sectionDivider.remove();
                        updateSectionNumbers();
                        updateMainButtonsVisibility();
                    });
                }

                initSortable();
                updateMainButtonsVisibility();
            }
        });
    }

    // Active state highlight saat focus pada input pertanyaan
    const questionTitle = card.querySelector('.question-title');
    if (questionTitle) {
        questionTitle.addEventListener('focus', function () {
            // Remove active dari semua cards
            document.querySelectorAll('.question-card').forEach(c => {
                c.classList.remove('ring-2', 'ring-red-600', 'border-red-600');
                c.classList.add('border-gray-200');
                const quickAdd = c.querySelector('.question-quick-add-buttons');
                if (quickAdd) {
                    quickAdd.classList.add('hidden');
                }
            });
            // Add active ke card ini
            card.classList.add('ring-2', 'ring-red-600', 'border-red-600');
            card.classList.remove('border-gray-200');
            showQuickAddButtons();
        });
    }

    // Click pada card juga trigger highlight
    card.addEventListener('click', function (e) {
        const shouldSkipFocus = e.target.closest('button')
            || e.target.closest('input')
            || e.target.closest('textarea')
            || e.target.closest('select')
            || e.target.closest('.question-type-dropdown')
            || e.target.closest('.question-advanced-settings')
            || e.target.closest('.option-item')
            || e.target.closest('.question-quick-add-buttons');

        if (shouldSkipFocus) {
            return;
        }

        // Remove active dari semua cards
        document.querySelectorAll('.question-card').forEach(c => {
            c.classList.remove('ring-2', 'ring-red-600', 'border-red-600');
            c.classList.add('border-gray-200');
            const quickAdd = c.querySelector('.question-quick-add-buttons');
            if (quickAdd) {
                quickAdd.classList.add('hidden');
            }
        });

        // Add active ke card ini
        card.classList.add('ring-2', 'ring-red-600', 'border-red-600');
        card.classList.remove('border-gray-200');
        showQuickAddButtons();

        if (questionTitle) {
            questionTitle.focus();
        }
    });

    // Hide quick add buttons when clicking outside
    document.addEventListener('click', function (e) {
        if (!card.contains(e.target)) {
            hideQuickAddButtons();
        }
    });



    // Add image button
    const addImageBtn = card.querySelector('.add-image-btn');
    const imageFileInput = card.querySelector('.image-file-input');
    const imageArea = card.querySelector('.question-image-area');
    const questionImage = card.querySelector('.question-image');
    const removeImageBtn = card.querySelector('.remove-image-btn');
    const imageValueInput = card.querySelector('.question-image-value');
    const imageSettings = card.querySelector('.question-image-settings');
    const alignmentSelect = card.querySelector('.image-alignment-select');
    const widthRange = card.querySelector('.image-width-range');
    const widthDisplay = card.querySelector('.image-width-display');

    const updateImageWidthDisplay = () => {
        if (widthDisplay && widthRange) {
            widthDisplay.textContent = `${widthRange.value}%`;
        }
    };

    const showImageControls = () => {
        if (imageArea) {
            imageArea.classList.remove('hidden');
        }
        if (imageSettings) {
            imageSettings.classList.remove('hidden');
        }
        updateImageWidthDisplay();
    };

    const hideImageControls = () => {
        if (imageArea) {
            imageArea.classList.add('hidden');
        }
        if (imageSettings) {
            imageSettings.classList.add('hidden');
        }
    };

    if (widthRange) {
        widthRange.addEventListener('input', updateImageWidthDisplay);
    }

    if (addImageBtn && imageFileInput) {
        addImageBtn.addEventListener('click', function () {
            imageFileInput.click();
        });
    }

    if (imageFileInput) {
        imageFileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    if (questionImage) {
                        questionImage.src = e.target.result;
                    }
                    if (imageValueInput) {
                        imageValueInput.value = e.target.result;
                    }
                    showImageControls();
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (questionImage) {
        questionImage.addEventListener('error', function () {
            questionImage.removeAttribute('src');
            hideImageControls();
            if (imageValueInput) {
                imageValueInput.value = '';
            }
        });
    }

    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', function () {
            hideImageControls();
            if (questionImage) {
                questionImage.src = '';
            }
            if (imageFileInput) {
                imageFileInput.value = '';
            }
            if (imageValueInput) {
                imageValueInput.value = '';
            }
            if (alignmentSelect) {
                alignmentSelect.value = 'center';
            }
            if (widthRange) {
                widthRange.value = 100;
                updateImageWidthDisplay();
            }
        });
    }

    const useRuleWrapper = card.querySelector('.use-saved-rules-wrapper');
    const questionTypeContainer = card.querySelector('.question-type-dropdown')?.parentElement;
    if (useRuleWrapper && questionTypeContainer) {
        questionTypeContainer.insertAdjacentElement('afterend', useRuleWrapper);
    }

    updateUseRuleButtonsVisibility();
    attachSavedRuleButtons();
}

// Fungsi untuk attach events ke opsi
function attachOptionEvents(card) {
    // Remove option buttons
    const removeOptionBtns = card.querySelectorAll('.remove-option-btn');
    removeOptionBtns.forEach(btn => {
        if (!btn.hasAttribute('data-listener')) {
            btn.setAttribute('data-listener', 'true');
            btn.addEventListener('click', function () {
                const optionItem = this.closest('.option-item');
                if (optionItem) {
                    optionItem.remove();
                }
            });
        }
    });

    // Show remove button on hover untuk option items
    const optionItems = card.querySelectorAll('.option-item');
    optionItems.forEach(item => {
        if (!item.hasAttribute('data-hover-listener')) {
            item.setAttribute('data-hover-listener', 'true');
            item.addEventListener('mouseenter', function () {
                const removeBtn = this.querySelector('.remove-option-btn');
                if (removeBtn) {
                    removeBtn.classList.remove('opacity-0');
                    removeBtn.classList.add('opacity-100');
                }
            });
            item.addEventListener('mouseleave', function () {
                const removeBtn = this.querySelector('.remove-option-btn');
                if (removeBtn) {
                    removeBtn.classList.add('opacity-0');
                    removeBtn.classList.remove('opacity-100');
                }
            });
        }
    });

    // Add option button
    const addOptionBtns = card.querySelectorAll('.add-option-btn');
    addOptionBtns.forEach(btn => {
        if (!btn.hasAttribute('data-listener')) {
            btn.setAttribute('data-listener', 'true');
            btn.addEventListener('click', function () {
                const container = card.querySelector('[data-option-container="true"]');
                const controls = card.querySelector('.option-controls');
                if (!container) {
                    return;
                }
                const optionCount = container.querySelectorAll('.option-item').length;
                const type = card.getAttribute('data-question-type');
                const newOptionEl = createOptionElementNode(type, '', optionCount);
                if (newOptionEl) {
                    if (controls) {
                        container.insertBefore(newOptionEl, controls);
                    } else {
                        container.appendChild(newOptionEl);
                    }
                    attachOptionEvents(card);
                    updateQuestionNumbers();
                }
            });
        }
    });

    // Add "Lainnya" button
    const addOtherBtns = card.querySelectorAll('.add-other-btn');
    addOtherBtns.forEach(btn => {
        if (!btn.hasAttribute('data-listener')) {
            btn.setAttribute('data-listener', 'true');
            btn.addEventListener('click', function () {
                const container = card.querySelector('[data-option-container="true"]');
                if (!container) {
                    return;
                }
                const controls = card.querySelector('.option-controls');
                const optionCount = container.querySelectorAll('.option-item').length;
                const type = card.getAttribute('data-question-type');
                const newOptionEl = createOptionElementNode(type, 'Lainnya', optionCount);
                if (newOptionEl) {
                    if (controls) {
                        container.insertBefore(newOptionEl, controls);
                    } else {
                        container.appendChild(newOptionEl);
                    }
                    attachOptionEvents(card);
                }
            });
        }
    });
}

// Update nomor pertanyaan
function updateQuestionNumbers() {
    const questionCards = document.querySelectorAll('.question-card');
    questionCards.forEach((card, index) => {
        // No need to update numbers since we removed the number display
    });
}

// Update nomor section
function updateSectionNumbers() {
    const sections = document.querySelectorAll('.section-divider');
    sections.forEach((section, index) => {
        const titleEl = section.querySelector('.section-title');
        if (titleEl) {
            titleEl.textContent = `Bagian ${index + 1}`;
        }
    });
}

// Fungsi untuk mengumpulkan data form
function collectFormData(options = {}) {
    const { includeRules = true } = options;

    // Get title HTML - backend will strip HTML for validation but save HTML to database
    const titleHTML = getHTMLFromContentEditable(document.getElementById('form-title')) || 'Formulir tanpa judul';
    // Check plain text length - if too long, truncate the HTML content
    const titlePlainText = stripHTMLTags(titleHTML);
    let finalTitle = titleHTML;
    if (titlePlainText.length > 255) {
        // If plain text is too long, truncate HTML by removing characters from the end
        // This is a simple approach - for better results, we'd need to parse HTML properly
        const maxPlainLength = 255;
        let truncatedPlain = titlePlainText.substring(0, maxPlainLength);
        // Try to preserve some HTML structure by finding a good truncation point
        finalTitle = truncatedPlain;
    }

    const useBmiFormula = document.getElementById('use-bmi-formula')?.checked || false;

    const formData = {
        title: finalTitle || 'Formulir tanpa judul',
        description: getHTMLFromContentEditable(document.getElementById('form-description')) || '',
        theme_color: (() => {
            const selectedThemeButton = document.querySelector('[data-theme-color][data-selected="true"]');
            return selectedThemeButton ? selectedThemeButton.getAttribute('data-theme-color') : 'red';
        })(),
        collect_email: document.getElementById('collect-email')?.checked || false,
        limit_one_response: document.getElementById('limit-one-response')?.checked || false,
        show_progress_bar: document.getElementById('show-progress-bar')?.checked || false,
        shuffle_questions: document.getElementById('shuffle-questions')?.checked || false,
        use_bmi_formula: useBmiFormula,
        bmi_mapping: {},
        sections: [],
        questions: [],
        answer_templates: [],
        result_rules: [],
        result_text_settings: [], // Struktur baru untuk result text settings
        text_formatting: []
    };

    // Auto-map BMI questions if enabled
    if (useBmiFormula) {
        const questionCards = document.querySelectorAll('.question-card');
        questionCards.forEach(card => {
            const titleInput = card.querySelector('.question-title');
            const title = (titleInput ? (titleInput.contentEditable === 'true' ? titleInput.innerText : titleInput.value) : '').trim().toLowerCase();
            const dbId = card.getAttribute('data-db-id');

            if (title === 'berat badan (kg)') {
                formData.bmi_mapping.weight_question_id = dbId;
            } else if (title === 'tinggi badan (cm)') {
                formData.bmi_mapping.height_question_id = dbId;
            }
        });
    }

    // Collect sections
    const sections = document.querySelectorAll('.section-divider');
    sections.forEach((section, index) => {
        const titleInput = section.querySelector('.section-title-input');
        const descInput = section.querySelector('.section-description-input');
        const imageValueInput = section.querySelector('.section-image-value');
        const alignmentSelect = section.querySelector('.section-image-alignment-select');
        const wrapModeSelect = section.querySelector('.section-image-wrap-mode-select');

        formData.sections.push({
            id: section.getAttribute('data-db-id') || null,
            title: getHTMLFromContentEditable(titleInput)?.trim() || null,
            description: getHTMLFromContentEditable(descInput)?.trim() || null,
            image: imageValueInput?.value?.trim() || null,
            image_alignment: alignmentSelect?.value || 'center',
            image_wrap_mode: wrapModeSelect?.value || 'fixed',
            calculate_subtotal: section.querySelector('.calculate-subtotal-checkbox')?.checked === true,
            include_in_total: section.querySelector('.include-in-total-checkbox')?.checked !== false,
        });
    });

    if (includeRules) {
        // Generate rule_group_id untuk mengelompokkan semua aturan form yang satu paket
        const generateRuleGroupId = () => {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        };

        // Collect answer templates (pastikan query selector menemukan elemen meskipun tab tersembunyi)
        const answerTemplates = document.querySelectorAll('.answer-template-card');
        const resultRulesElements = document.querySelectorAll('.result-rule-card');
        const hasAnswerTemplates = answerTemplates.length > 0;
        const hasResultRules = resultRulesElements.length > 0;

        // Cari rule_group_id yang sudah ada dari card pertama (jika edit mode)
        let existingRuleGroupId = null;
        if (answerTemplates.length > 0) {
            const firstTemplate = answerTemplates[0];
            existingRuleGroupId = firstTemplate.getAttribute('data-rule-group-id');
        }
        if (!existingRuleGroupId && hasResultRules) {
            const firstRule = document.querySelector('.result-rule-card');
            if (firstRule) {
                existingRuleGroupId = firstRule.getAttribute('data-rule-group-id');
            }
        }

        // Use existing group ID if available, otherwise use current editing group ID or null
        const ruleGroupId = existingRuleGroupId || editingRuleGroupId || null;

        answerTemplates.forEach((template) => {
            const answerText = template.querySelector('.answer-template-text')?.value;
            const score = template.querySelector('.answer-template-score')?.value || 0;
            if (answerText) {
                formData.answer_templates.push({
                    id: template.getAttribute('data-db-id') || null,
                    answer_text: answerText,
                    score: parseInt(score) || 0,
                    rule_group_id: ruleGroupId
                });
            }
        });

        // Collect result rules
        const resultRules = document.querySelectorAll('.result-rule-card');
        resultRules.forEach((ruleCard) => {
            const conditionType = ruleCard.querySelector('.rule-condition-type')?.value || 'range';
            const ruleData = {
                id: ruleCard.getAttribute('data-db-id') || null,
                condition_type: conditionType,
                texts: [],
                rule_group_id: ruleGroupId
            };

            if (conditionType === 'range') {
                ruleData.min_score = parseInt(ruleCard.querySelector('.rule-min-score')?.value) || null;
                ruleData.max_score = parseInt(ruleCard.querySelector('.rule-max-score')?.value) || null;
            } else {
                ruleData.single_score = parseInt(ruleCard.querySelector('.rule-single-score')?.value) || null;
            }

            // Collect result texts
            const textContainers = ruleCard.querySelectorAll('.rule-result-texts .result-text-item');
            textContainers.forEach((container) => {
                const textarea = container.querySelector('.rule-result-text');
                const dbId = container.getAttribute('data-db-id');
                if (textarea && textarea.value.trim()) {
                    ruleData.texts.push({
                        id: dbId || null,
                        result_text: textarea.value.trim()
                    });
                }
            });

            if (ruleData.texts.length > 0) {
                formData.result_rules.push(ruleData);
            }
        });
    }

    // Collect questions from questions container
    const questionsContainer = document.getElementById('questions-container');
    if (!questionsContainer) {
        return formData;
    }

    const questions = questionsContainer.querySelectorAll('.question-card, .section-divider');
    let currentSectionIndex = -1;

    questions.forEach((questionCard) => {
        // Check if this is a section divider
        if (questionCard.classList.contains('section-divider')) {
            currentSectionIndex++;
            return;
        }

        const extraSettingsPayload = {
            validation: questionCard.querySelector('.question-validation-input')?.value?.trim() || null,
            validation_message: questionCard.querySelector('.question-validation-message')?.value?.trim() || null,
            extra_notes: questionCard.querySelector('.question-extra-notes')?.value?.trim() || null,
            min_length: questionCard.querySelector('.question-min-length')?.value || null,
            max_length: questionCard.querySelector('.question-max-length')?.value || null,
        };

        if (extraSettingsPayload.min_length !== null && extraSettingsPayload.min_length !== '') {
            extraSettingsPayload.min_length = parseInt(extraSettingsPayload.min_length, 10);
        } else {
            extraSettingsPayload.min_length = null;
        }

        if (extraSettingsPayload.max_length !== null && extraSettingsPayload.max_length !== '') {
            extraSettingsPayload.max_length = parseInt(extraSettingsPayload.max_length, 10);
        } else {
            extraSettingsPayload.max_length = null;
        }

        const questionData = {
            id: questionCard.getAttribute('data-db-id') || null,
            type: questionCard.getAttribute('data-question-type') || 'short-answer',
            title: getHTMLFromContentEditable(questionCard.querySelector('.question-title')) || '',
            description: questionCard.querySelector('.question-description')?.value || '',
            is_required: questionCard.querySelector('.required-checkbox')?.checked || false,
            options: [],
            extra_settings: extraSettingsPayload,
        };

        const extraValues = Object.values(questionData.extra_settings);
        if (extraValues.every(value => value === null || value === '')) {
            delete questionData.extra_settings;
        }

        if (currentSectionIndex >= 0) {
            questionData.section_id = currentSectionIndex;
        }

        const imageValueInput = questionCard.querySelector('.question-image-value');
        const imageAlignmentSelect = questionCard.querySelector('.image-alignment-select');
        const imageWidthRange = questionCard.querySelector('.image-width-range');
        const imageArea = questionCard.querySelector('.question-image-area');
        const questionImageEl = questionCard.querySelector('.question-image');

        // Prioritize imageValueInput (contains base64 for new uploads or storage path for existing)
        if (imageValueInput && imageValueInput.value && imageValueInput.value.trim() !== '') {
            questionData.image = imageValueInput.value.trim();
        } else if (imageArea && questionImageEl && !imageArea.classList.contains('hidden')) {
            // Fallback to src attribute if imageValueInput is empty
            const src = questionImageEl.getAttribute('src');
            if (src && src.trim() !== '') {
                questionData.image = src;
            }
        } else {
            questionData.image = null;
        }

        // Always set alignment and width from controls
        questionData.image_alignment = imageAlignmentSelect?.value || 'center';
        questionData.image_width = imageWidthRange ? parseInt(imageWidthRange.value, 10) : null;

        if (['multiple-choice', 'checkbox', 'dropdown'].includes(questionData.type)) {
            const optionItems = questionCard.querySelectorAll('.option-item');
            optionItems.forEach((optionItem) => {
                const optionInput = optionItem.querySelector('.option-input');
                const optionText = optionInput ? optionInput.value : '';
                if (optionText && optionText.trim() !== '') {
                    const templateDataset = optionInput?.dataset?.templateIndex ?? optionItem?.dataset?.templateIndex;
                    const templateIndex = templateDataset !== undefined && templateDataset !== null && templateDataset !== ''
                        ? parseInt(templateDataset, 10)
                        : null;

                    questionData.options.push({
                        id: optionItem.getAttribute('data-db-id') || null,
                        text: optionText.trim(),
                        answer_template_index: Number.isInteger(templateIndex) ? templateIndex : null,
                    });
                }
            });
        }

        if (!questionData.image) {
            delete questionData.image;
        }

        const savedRulePayload = questionCard.querySelector('.saved-rule-payload');
        if (savedRulePayload && savedRulePayload.value) {
            try {
                questionData.saved_rule = JSON.parse(savedRulePayload.value);
            } catch (error) {
                console.error('Failed to parse saved rule payload:', error);
            }
        }

        // Always push question regardless of title to avoid data loss
        // The backend will handle empty titles by defaulting to 'Pertanyaan tanpa judul'
        formData.questions.push(questionData);
    });

    // Collect result settings and result text settings (new structure)
    // Get all items in order (questions, sections, result settings) to calculate correct position
    const allItems = Array.from(questionsContainer.querySelectorAll('.question-card, .section-divider, .result-setting-card'));

    // Collect result setting cards with their position index
    const resultSettingCards = [];
    allItems.forEach((item, index) => {
        if (item.classList.contains('result-setting-card')) {
            resultSettingCards.push({ card: item, position: index });
        }
    });

    // Process each result setting card
    resultSettingCards.forEach(({ card, position }) => {
        const ruleSelect = card.querySelector('.result-setting-rule-select');
        const textAlignmentSelect = card.querySelector('.result-setting-text-alignment-select');
        const imageAlignmentSelect = card.querySelector('.result-setting-image-alignment-select');
        const imageValueInput = card.querySelector('.result-setting-image-value');

        const selectedOption = ruleSelect?.selectedOptions[0];
        const ruleGroupId = selectedOption ? selectedOption.getAttribute('data-rule-group-id') : null;
        const textAlignment = textAlignmentSelect ? textAlignmentSelect.value : 'center';
        const imageAlignment = imageAlignmentSelect ? imageAlignmentSelect.value : 'center';

        if (ruleGroupId) {
            // Collect result text forms (each with title, image, and result_text)
            const resultTextForms = card.querySelectorAll('.result-text-form');
            const textSettings = [];

            resultTextForms.forEach((form, index) => {
                const resultRuleTextId = form.getAttribute('data-result-rule-text-id');
                const titleInput = form.querySelector('.result-text-form-title');
                const imageValueInput = form.querySelector('.result-text-form-image-value');
                const resultTextTextarea = form.querySelector('.result-text-form-text');

                const title = titleInput ? titleInput.value.trim() : null;
                const image = imageValueInput ? imageValueInput.value.trim() : null;
                const resultText = resultTextTextarea ? resultTextTextarea.value.trim() : null;

                if (resultRuleTextId && resultText) {
                    textSettings.push({
                        result_rule_text_id: resultRuleTextId.startsWith('temp-') || resultRuleTextId.startsWith('saved-') ? null : resultRuleTextId, // Only send real IDs
                        temp_id: resultRuleTextId.startsWith('temp-') || resultRuleTextId.startsWith('saved-') ? resultRuleTextId : null, // For mapping
                        title: title || null,
                        image: image || null,
                        result_text: resultText,
                        order: index,
                    });
                }
            });

            const cardImageValue = imageValueInput && imageValueInput.value && imageValueInput.value.trim()
                ? imageValueInput.value.trim()
                : null;

            formData.result_text_settings.push({
                rule_group_id: ruleGroupId,
                text_alignment: textAlignment,
                image_alignment: imageAlignment,
                card_order: position,
                card_image: cardImageValue,
                text_settings: textSettings,
            });
        }
    });

    // Collect header data
    if (headerState && headerState.imageUrl) {
        formData.header = {
            image_path: headerState.imageUrl,
            image_mode: headerState.imageMode || 'cover',
            source: headerState.source || null,
        };
    }

    // Collect text formatting data
    formData.text_formatting = [];

    // Form title and description formatting
    const formTitle = document.getElementById('form-title');
    const formDescription = document.getElementById('form-description');

    if (formTitle) {
        // Collect formatting directly from the element itself, not from parent card
        const formatting = collectElementFormatting(formTitle, 'form_title');
        if (formatting) {
            formData.text_formatting.push(formatting);
        }
    }

    if (formDescription) {
        // Collect formatting directly from the element itself, not from parent card
        const formatting = collectElementFormatting(formDescription, 'form_description');
        if (formatting) {
            formData.text_formatting.push(formatting);
        }
    }

    // Question title formatting
    document.querySelectorAll('.question-card').forEach((questionCard) => {
        const questionTitle = questionCard.querySelector('.question-title');
        if (questionTitle) {
            // Use the index (order) as question_id - backend will map it to database ID
            const questionIndex = Array.from(document.querySelectorAll('.question-card')).indexOf(questionCard);
            // Collect formatting directly from the question title element, not from parent card
            const formatting = collectElementFormatting(questionTitle, 'question_title', questionIndex);
            if (formatting) {
                formData.text_formatting.push(formatting);
            }
        }
    });

    // Section title and description formatting
    document.querySelectorAll('.section-divider').forEach((sectionDivider) => {
        const sectionTitle = sectionDivider.querySelector('.section-title-input');
        const sectionDescription = sectionDivider.querySelector('.section-description-input');
        const sectionIndex = Array.from(document.querySelectorAll('.section-divider')).indexOf(sectionDivider);

        if (sectionTitle) {
            // Pass the actual element (section-title-input), not the parent card
            const formatting = collectElementFormatting(sectionTitle, 'section_title', null, sectionIndex);
            if (formatting) {
                formData.text_formatting.push(formatting);
            }
        }

        if (sectionDescription) {
            // Pass the actual element (section-description-input), not the parent card
            const formatting = collectElementFormatting(sectionDescription, 'section_description', null, sectionIndex);
            if (formatting) {
                formData.text_formatting.push(formatting);
            }
        }
    });

    return formData;
}

// Helper function to apply formatting to an element
function applyFormattingToElement(element, formatting) {
    if (!element || !formatting) return;

    // For contenteditable elements, ensure display is block for text-align to work properly
    if (element.contentEditable === 'true') {
        const computedDisplay = window.getComputedStyle(element).display;
        if (computedDisplay === 'inline' || computedDisplay === 'inline-block') {
            element.style.display = 'block';
        }
    }

    // Apply text-align first, then other properties
    // Use setProperty with important to ensure it overrides any inline styles in HTML content
    if (formatting.text_align) {
        element.style.setProperty('text-align', formatting.text_align, 'important');
        element.style.textAlign = formatting.text_align;
    } else {
        element.style.textAlign = 'left';
    }

    element.style.fontFamily = formatting.font_family || 'Arial';
    element.style.fontSize = `${formatting.font_size || 12}px`;
    element.style.fontWeight = formatting.font_weight || 'normal';
    element.style.fontStyle = formatting.font_style || 'normal';
    element.style.textDecoration = formatting.text_decoration || 'none';

    // Store in data attributes for persistence
    // For form_title, form_description, question_title, and section_title/description, store directly on element
    // This is consistent with how applyCardFormatting stores data attributes
    const isFormTitleOrDescription = element.id === 'form-title' || element.id === 'form-description';
    const isQuestionTitle = element.classList.contains('question-title');
    const isSectionTitleOrDesc = element.classList.contains('section-title-input') || element.classList.contains('section-description-input');

    if (isFormTitleOrDescription || isQuestionTitle || isSectionTitleOrDesc) {
        // Store directly on element for form/question/section title/description to avoid conflicts
        element.setAttribute('data-textAlign', formatting.text_align || 'left');
        element.setAttribute('data-fontFamily', formatting.font_family || 'Arial');
        element.setAttribute('data-fontSize', formatting.font_size || 12);
        element.setAttribute('data-fontWeight', formatting.font_weight || 'normal');
        element.setAttribute('data-fontStyle', formatting.font_style || 'normal');
        element.setAttribute('data-textDecoration', formatting.text_decoration || 'none');
    } else {
        // For other elements, store on parent card
        const parentCard = element.closest('.question-card, .section-divider');
        const target = parentCard || element;
        target.setAttribute('data-textAlign', formatting.text_align || 'left');
        target.setAttribute('data-fontFamily', formatting.font_family || 'Arial');
        target.setAttribute('data-fontSize', formatting.font_size || 12);
        target.setAttribute('data-fontWeight', formatting.font_weight || 'normal');
        target.setAttribute('data-fontStyle', formatting.font_style || 'normal');
        target.setAttribute('data-textDecoration', formatting.text_decoration || 'none');
    }
}

// Helper function to extract formatting from HTML content (inline spans)
function extractFormattingFromHTML(element) {
    if (!element) return {};

    // Get all spans with inline styles in the element
    const spans = element.querySelectorAll('span[style]');
    const formatting = {
        font_family: null,
        font_size: null,
        font_weight: null,
        font_style: null,
        text_decoration: null,
    };

    // Check if there are any spans with formatting
    if (spans.length > 0) {
        // Get formatting from spans - prioritize spans with more formatting properties
        // or use the first span that has the property we're looking for
        spans.forEach(span => {
            const style = span.style;
            const styleText = span.getAttribute('style') || '';

            // Extract font-family
            if (style.fontFamily || styleText.includes('font-family')) {
                const fontFamilyValue = style.fontFamily ||
                    (styleText.match(/font-family:\s*([^;]+)/i) ? styleText.match(/font-family:\s*([^;]+)/i)[1].trim() : null);
                if (fontFamilyValue && !formatting.font_family) {
                    formatting.font_family = fontFamilyValue.split(',')[0].replace(/['"]/g, '').trim();
                }
            }

            // Extract font-size
            if (style.fontSize || styleText.includes('font-size')) {
                const fontSizeValue = style.fontSize ||
                    (styleText.match(/font-size:\s*([^;]+)/i) ? styleText.match(/font-size:\s*([^;]+)/i)[1].trim() : null);
                if (fontSizeValue && !formatting.font_size) {
                    const parsed = parseInt(fontSizeValue.replace('px', '').replace('pt', '')) || null;
                    if (parsed) formatting.font_size = parsed;
                }
            }

            // Extract font-weight
            if (style.fontWeight || styleText.includes('font-weight')) {
                const fontWeightValue = style.fontWeight ||
                    (styleText.match(/font-weight:\s*([^;]+)/i) ? styleText.match(/font-weight:\s*([^;]+)/i)[1].trim() : null);
                if (fontWeightValue && !formatting.font_weight) {
                    formatting.font_weight = (fontWeightValue === 'bold' || parseInt(fontWeightValue) >= 700) ? 'bold' : 'normal';
                }
            }

            // Extract font-style
            if (style.fontStyle || styleText.includes('font-style')) {
                const fontStyleValue = style.fontStyle ||
                    (styleText.match(/font-style:\s*([^;]+)/i) ? styleText.match(/font-style:\s*([^;]+)/i)[1].trim() : null);
                if (fontStyleValue && !formatting.font_style) {
                    formatting.font_style = (fontStyleValue === 'italic') ? 'italic' : 'normal';
                }
            }

            // Extract text-decoration
            if (style.textDecoration || styleText.includes('text-decoration')) {
                const textDecorationValue = style.textDecoration ||
                    (styleText.match(/text-decoration:\s*([^;]+)/i) ? styleText.match(/text-decoration:\s*([^;]+)/i)[1].trim() : null);
                if (textDecorationValue && !formatting.text_decoration) {
                    formatting.text_decoration = (textDecorationValue.includes('underline')) ? 'underline' : 'none';
                }
            }
        });
    }

    return formatting;
}

// Helper function to collect formatting from an element
function collectElementFormatting(element, elementType, questionId = null, sectionIndex = null) {
    if (!element) return null;

    // For form_title, form_description, question_title, and section_title/description, get formatting directly from element
    // Data attributes are stored on the element itself for all these types
    const isFormTitleOrDescription = elementType === 'form_title' || elementType === 'form_description';
    const isQuestionTitle = elementType === 'question_title';
    const isSectionTitleOrDesc = elementType === 'section_title' || elementType === 'section_description';

    // Determine which element to get formatting from
    // For all title/description elements, use element itself (data attributes are stored on element)
    // For other elements, get from parent card if available
    let targetElement = element;
    if (!isFormTitleOrDescription && !isQuestionTitle && !isSectionTitleOrDesc) {
        // For other elements, get from parent card if available
        targetElement = element.closest('.section-divider') || element;
    }

    // Get formatting from the target element (element itself for form/question/section, parent for others)
    const computedStyle = window.getComputedStyle(element);

    // Extract formatting from HTML content first (for inline styles in spans)
    const htmlFormatting = extractFormattingFromHTML(element);

    // Get formatting from data attributes, inline styles, or computed styles
    // Priority: HTML inline spans (from toolbar) > data attributes > element style > computed style
    const textAlign = element.getAttribute('data-textAlign') || element.style.textAlign || computedStyle.textAlign || 'left';

    // Font family: prioritize HTML spans (from toolbar changes) over data attributes
    let fontFamily = htmlFormatting.font_family
        || element.getAttribute('data-fontFamily')
        || element.style.fontFamily
        || computedStyle.fontFamily
        || 'Arial';
    // Extract first font family from computed style
    if (fontFamily) {
        fontFamily = fontFamily.split(',')[0].replace(/['"]/g, '').trim();
    }

    // Font size: check HTML spans first, then data attribute, then element style, then computed
    let fontSizeValue = htmlFormatting.font_size
        ? `${htmlFormatting.font_size}px`
        : (element.getAttribute('data-fontSize') || element.style.fontSize || computedStyle.fontSize || '12px');
    // Parse fontSize - remove 'px' if present
    const fontSize = parseInt(fontSizeValue.toString().replace('px', '')) || 12;

    // Font weight: check HTML spans first, then data attribute, then element style, then computed
    const fontWeight = htmlFormatting.font_weight
        || element.getAttribute('data-fontWeight')
        || element.style.fontWeight
        || computedStyle.fontWeight
        || 'normal';

    // Font style: check HTML spans first, then data attribute, then element style, then computed
    const fontStyle = htmlFormatting.font_style
        || element.getAttribute('data-fontStyle')
        || element.style.fontStyle
        || computedStyle.fontStyle
        || 'normal';

    // Text decoration: check HTML spans first, then data attribute, then element style, then computed
    const textDecoration = htmlFormatting.text_decoration
        || element.getAttribute('data-textDecoration')
        || element.style.textDecoration
        || computedStyle.textDecoration
        || 'none';

    const formatting = {
        element_type: elementType,
        text_align: textAlign,
        font_family: fontFamily,
        font_size: fontSize,
        font_weight: fontWeight === 'bold' || fontWeight >= 700 ? 'bold' : 'normal',
        font_style: fontStyle === 'italic' ? 'italic' : 'normal',
        text_decoration: textDecoration === 'underline' ? 'underline' : 'none',
    };

    // Add appropriate ID based on element type
    if (elementType === 'question_title' && questionId !== null && questionId !== undefined) {
        // We'll need to map question_id later when saving
        // Note: questionId can be 0 (first question), so we check for null/undefined, not truthy
        formatting.question_id = questionId;
    } else if ((elementType === 'section_title' || elementType === 'section_description') && sectionIndex !== null) {
        // We'll need to map section_id later when saving
        formatting.section_index = sectionIndex;
    }

    return formatting;
}

// Fungsi untuk menampilkan dialog sukses
function showSuccessDialog(message) {
    // Create modal overlay
    const modal = document.createElement('div');
    modal.id = 'success-modal';
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
            <div class="flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mx-auto mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 text-center mb-2">Berhasil!</h3>
            <p class="text-sm text-gray-600 text-center mb-6">${message}</p>
            <div class="flex justify-center">
                <button id="close-success-modal" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Close modal handlers
    const closeBtn = modal.querySelector('#close-success-modal');
    closeBtn.addEventListener('click', () => {
        modal.remove();
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });

    // Auto close after 3 seconds
    setTimeout(() => {
        if (document.getElementById('success-modal')) {
            modal.remove();
        }
    }, 3000);
}

function openShareModal(link, showManualCopy = false) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4';

    // Jika showManualCopy true, tampilkan dialog dengan tombol salin manual (fallback)
    if (showManualCopy) {
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Bagikan Formulir</h3>
                <p class="text-sm text-gray-600 mb-4">Salin tautan berikut dan bagikan kepada responden Anda.</p>
                <div class="flex items-center space-x-2">
                    <input type="text" readonly class="share-link-input flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none" value="${link}">
                    <button type="button" data-share-copy class="px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg">Salin</button>
                </div>
                <p class="text-xs text-gray-500 mt-2">Tautan ini akan membawa responden ke halaman pengisian form.</p>
                <div class="flex justify-end mt-6">
                    <button type="button" data-share-close class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-red-600">Tutup</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        const input = modal.querySelector('.share-link-input');
        if (input) {
            input.focus();
            input.select();
        }

        const closeModal = () => {
            modal.remove();
        };

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        const closeBtn = modal.querySelector('[data-share-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        const copyBtn = modal.querySelector('[data-share-copy]');
        if (copyBtn) {
            copyBtn.addEventListener('click', async () => {
                try {
                    if (navigator.clipboard?.writeText) {
                        await navigator.clipboard.writeText(link);
                    } else if (input) {
                        input.select();
                        document.execCommand('copy');
                    }
                    copyBtn.textContent = 'Disalin!';
                    copyBtn.classList.remove('bg-red-600');
                    copyBtn.classList.add('bg-green-600');
                    setTimeout(() => {
                        copyBtn.textContent = 'Salin';
                        copyBtn.classList.remove('bg-green-600');
                        copyBtn.classList.add('bg-red-600');
                    }, 1500);
                } catch (error) {
                    alert('Gagal menyalin tautan. Silakan salin secara manual.');
                }
            });
        }
    } else {
        // Dialog informasi sukses (link sudah disalin)
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                <div class="flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 text-center mb-2">Link Disalin!</h3>
                <p class="text-sm text-gray-600 text-center mb-4">Tautan formulir sudah disalin ke clipboard.</p>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-4">
                    <p class="text-xs text-gray-500 mb-1">Tautan:</p>
                    <p class="text-sm text-gray-700 break-all font-mono">${link}</p>
                </div>
                <p class="text-xs text-gray-500 text-center mb-6">Bagikan tautan ini kepada responden Anda.</p>
                <div class="flex justify-center">
                    <button id="close-share-modal" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        const closeModal = () => {
            modal.remove();
        };

        const closeBtn = modal.querySelector('#close-share-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Auto close after 3 seconds
        setTimeout(() => {
            if (document.body.contains(modal)) {
                closeModal();
            }
        }, 3000);
    }
}

function updateThemeColorSelection(color) {
    const buttons = document.querySelectorAll('[data-theme-color]');
    if (!buttons.length) {
        return;
    }

    const borderClasses = {
        red: 'border-red-600',
        blue: 'border-blue-600',
        green: 'border-green-600',
        purple: 'border-purple-600',
    };

    const ringClasses = {
        red: 'ring-red-600',
        blue: 'ring-blue-600',
        green: 'ring-green-600',
        purple: 'ring-purple-600',
    };

    buttons.forEach((button) => {
        const buttonColor = button.getAttribute('data-theme-color');
        button.classList.remove(
            'ring-2',
            'ring-offset-2',
            'ring-red-600',
            'ring-blue-600',
            'ring-green-600',
            'ring-purple-600',
            'border-red-600',
            'border-blue-600',
            'border-green-600',
            'border-purple-600'
        );
        button.classList.add('border-transparent');
        button.removeAttribute('data-selected');

        if (buttonColor === color) {
            const borderClass = borderClasses[buttonColor] || 'border-red-600';
            const ringClass = ringClasses[buttonColor] || 'ring-red-600';

            button.classList.remove('border-transparent');
            button.classList.add(borderClass, 'ring-2', 'ring-offset-2', ringClass);
            button.setAttribute('data-selected', 'true');
        }
    });
}

function populateFormBuilder(data) {
    if (!data) {
        return;
    }

    // Clear all form data first to ensure no data from previous form remains
    const answerTemplatesContainer = document.getElementById('answer-templates-container');
    const resultRulesContainer = document.getElementById('result-rules-container');
    const questionsContainer = document.getElementById('questions-container');

    // Clear saved rules state (this is global, but we want to ensure clean state)
    // Note: savedRulesState is for global presets, not form-specific rules
    // Form-specific rules come from data.answer_templates and data.result_rules

    if (answerTemplatesContainer) {
        answerTemplatesContainer.innerHTML = getAnswerTemplatesPlaceholder();
    }

    if (resultRulesContainer) {
        resultRulesContainer.innerHTML = getResultRulesPlaceholder();
    }

    if (questionsContainer) {
        // Clear all questions, sections, and result settings
        questionsContainer.innerHTML = '';
    }

    // Populate rule groups map
    formRuleGroups = data.rule_groups || {};

    // Load header data
    if (data.header && typeof headerState !== 'undefined') {
        headerState.imageUrl = data.header.image_path || null;
        headerState.imageMode = data.header.image_mode || 'cover';
        headerState.source = data.header.source || null;

        // Apply header to form if exists
        if (headerState.imageUrl && typeof applyHeaderToForm === 'function') {
            setTimeout(() => applyHeaderToForm(), 100);
        }
    }

    // Load text formatting data
    const textFormattingMap = {};
    if (data.text_formatting && Array.isArray(data.text_formatting)) {
        data.text_formatting.forEach(formatting => {
            const key = `${formatting.element_type}_${formatting.question_id || formatting.section_index || ''}`;
            textFormattingMap[key] = formatting;
        });
    }

    const titleInput = document.getElementById('form-title');
    if (titleInput) {
        if (titleInput.contentEditable === 'true') {
            titleInput.innerHTML = data.title || '';
            // Apply formatting
            const formatting = textFormattingMap['form_title_'];
            if (formatting) {
                applyFormattingToElement(titleInput, formatting);
            }
            if (!data.title) {
                titleInput.classList.add('empty');
            } else {
                titleInput.classList.remove('empty');
            }
        } else {
            titleInput.value = data.title || '';
        }
    }

    const descriptionInput = document.getElementById('form-description');
    if (descriptionInput) {
        if (descriptionInput.contentEditable === 'true') {
            descriptionInput.innerHTML = data.description || '';
            // Apply formatting
            const formatting = textFormattingMap['form_description_'];
            if (formatting) {
                applyFormattingToElement(descriptionInput, formatting);
            }
            if (!data.description) {
                descriptionInput.classList.add('empty');
            } else {
                descriptionInput.classList.remove('empty');
            }
        } else {
            descriptionInput.value = data.description || '';
        }
    }

    updateThemeColorSelection(data.theme_color || 'red');

    const settingsCheckboxes = document.querySelectorAll('#tab-settings input[type="checkbox"]');
    const settingsValues = [
        Boolean(data.collect_email),
        Boolean(data.limit_one_response),
        Boolean(data.show_progress_bar),
        Boolean(data.shuffle_questions),
        Boolean(data.use_bmi_formula),
    ];

    settingsCheckboxes.forEach((checkbox, index) => {
        const value = settingsValues[index] ?? false;
        checkbox.checked = value;
        checkbox.dispatchEvent(new Event('change'));
    });

    // Load BMI Mapping
    if (data.bmi_mapping) {
        const weightSelect = document.getElementById('bmi-weight-question');
        const heightSelect = document.getElementById('bmi-height-question');
        if (weightSelect) weightSelect.value = data.bmi_mapping.weight_question_id || '';
        if (heightSelect) heightSelect.value = data.bmi_mapping.height_question_id || '';
    }

    if (answerTemplatesContainer) {
        // Ensure container is cleared first
        answerTemplatesContainer.innerHTML = '';

        const templates = Array.isArray(data.answer_templates) ? data.answer_templates : [];

        if (templates.length === 0) {
            answerTemplatesContainer.innerHTML = getAnswerTemplatesPlaceholder();
            updateRuleSaveControlsVisibility();
        } else {
            templates.forEach((template) => {
                const templateCard = createAnswerTemplateCard(template.id || null, template.rule_group_id || null);
                answerTemplatesContainer.appendChild(templateCard);

                const textInput = templateCard.querySelector('.answer-template-text');
                const scoreInput = templateCard.querySelector('.answer-template-score');
                if (textInput) {
                    textInput.value = template.answer_text || '';
                }
                if (scoreInput) {
                    scoreInput.value = template.score ?? 0;
                }

                // Delete button event listener is already attached in appendAnswerTemplateCard
                // But we need to re-attach it here since we're creating the card directly
                const deleteBtn = templateCard.querySelector('.delete-answer-template-btn');
                if (deleteBtn && !deleteBtn.hasAttribute('data-listener')) {
                    deleteBtn.setAttribute('data-listener', 'true');
                    deleteBtn.addEventListener('click', async function () {
                        const dbId = templateCard.getAttribute('data-db-id');
                        const formId = getMetaContent('form-id');

                        // If form is saved and template has database ID, delete from database
                        if (formId && dbId) {
                            try {
                                const response = await fetch(`/forms/${formId}/answer-templates/${dbId}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': getMetaContent('csrf-token'),
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json',
                                    },
                                });

                                const data = await response.json();
                                if (!response.ok || !data.success) {
                                    throw new Error(data.message || 'Gagal menghapus template.');
                                }
                            } catch (error) {
                                console.error('Error deleting template:', error);
                                alert('Gagal menghapus template dari database: ' + error.message);
                                return; // Don't remove from DOM if database delete failed
                            }
                        }

                        // Remove from DOM
                        templateCard.remove();
                        if (answerTemplatesContainer.children.length === 0) {
                            answerTemplatesContainer.innerHTML = getAnswerTemplatesPlaceholder();
                        }
                        updateRuleSaveControlsVisibility();
                    });
                }
            });
            updateRuleSaveControlsVisibility();
        }
    }

    if (resultRulesContainer) {
        // Ensure container is cleared first
        resultRulesContainer.innerHTML = '';

        const rules = Array.isArray(data.result_rules) ? data.result_rules : [];

        if (rules.length === 0) {
            resultRulesContainer.innerHTML = getResultRulesPlaceholder();
        } else {
            rules.forEach((rule) => {
                const ruleCard = createResultRuleCard(rule.id || null, rule.rule_group_id || null);
                resultRulesContainer.appendChild(ruleCard);

                const conditionSelect = ruleCard.querySelector('.rule-condition-type');
                if (conditionSelect) {
                    conditionSelect.value = rule.condition_type || 'range';
                    conditionSelect.dispatchEvent(new Event('change'));
                }

                if ((rule.condition_type || 'range') === 'range') {
                    const minInput = ruleCard.querySelector('.rule-min-score');
                    const maxInput = ruleCard.querySelector('.rule-max-score');
                    if (minInput) {
                        minInput.value = rule.min_score ?? '';
                    }
                    if (maxInput) {
                        maxInput.value = rule.max_score ?? '';
                    }
                } else {
                    const singleScoreInput = ruleCard.querySelector('.rule-single-score');
                    if (singleScoreInput) {
                        singleScoreInput.value = rule.single_score ?? '';
                    }
                }

                const resultTextsContainer = ruleCard.querySelector('.rule-result-texts');
                const addResultTextBtn = ruleCard.querySelector('.add-result-text-btn');
                const texts = Array.isArray(rule.texts) && rule.texts.length ? rule.texts : [''];

                if (resultTextsContainer) {
                    resultTextsContainer.innerHTML = ''; // Clear any default items
                    texts.forEach((text) => {
                        const textValue = extractResultTextValue(text);
                        const dbId = (typeof text === 'object') ? text.id : null;
                        const textItem = createResultTextItem(textValue, dbId);
                        resultTextsContainer.appendChild(textItem);

                        // Re-attach delete listener because it's dynamic
                        const deleteBtn = textItem.querySelector('.delete-result-text-btn');
                        if (deleteBtn) {
                            deleteBtn.addEventListener('click', function () {
                                textItem.remove();
                                if (resultTextsContainer.children.length === 0 && addResultTextBtn) {
                                    addResultTextBtn.click();
                                }
                            });
                        }
                    });
                }

                // Attach events to rule card (including delete button)
                attachResultRuleEvents(ruleCard, resultRulesContainer);
            });
        }
    }

    if (!questionsContainer) {
        return;
    }

    questionsContainer.innerHTML = '';

    const sections = Array.isArray(data.sections) ? data.sections : [];
    const questions = Array.isArray(data.questions) ? data.questions : [];

    const batchSize = questions.length > 8 ? 1 : 3;

    renderQuestionsIncrementally({
        questions,
        sections,
        container: questionsContainer,
        batchSize,
        textFormattingMap: textFormattingMap, // Pass formatting map
        onComplete: () => {
            // The original onComplete function for populateFormBuilder (if any)
            // is not directly passed here, so this call might be for an internal
            // onComplete within renderQuestionsIncrementally if it were to exist.
            // For now, we'll assume it's a placeholder or refers to a higher-scope onComplete.
            // If populateFormBuilder itself accepts an onComplete, it should be called here.
            // For this specific change, we'll add it as requested.
            if (typeof onComplete === 'function') {
                onComplete();
            }
            // Refresh BMI list after questions are loaded
            if (data.use_bmi_formula) {
                ensureBmiQuestionsExist();
            }

            // Re-select BMI mapping if questions were just loaded
            if (data.bmi_mapping) {
                const weightSelect = document.getElementById('bmi-weight-question');
                const heightSelect = document.getElementById('bmi-height-question');
                if (weightSelect) weightSelect.value = data.bmi_mapping.weight_question_id || '';
                if (heightSelect) heightSelect.value = data.bmi_mapping.height_question_id || '';
            }

            updateMainButtonsVisibility();
            if (questions.length === 0) {
                const defaultQuestion = createQuestionCard('short-answer');
                questionsContainer.appendChild(defaultQuestion);
                attachQuestionCardEvents(defaultQuestion);
            }

            // Load result settings - insert at correct position based on order
            const resultSettings = Array.isArray(data.result_settings) ? data.result_settings : [];

            console.log('[populateFormBuilder] Result settings data:', {
                hasResultSettings: !!data.result_settings,
                resultSettingsCount: resultSettings.length,
                resultSettings: resultSettings,
            });

            // Sort result settings by order to ensure correct sequence
            const sortedResultSettings = resultSettings.slice().sort((a, b) => {
                const orderA = a.order !== undefined ? a.order : 999;
                const orderB = b.order !== undefined ? b.order : 999;
                return orderA - orderB;
            });

            console.log('[populateFormBuilder] Processing result settings:', {
                sortedCount: sortedResultSettings.length,
                totalItems: questionsContainer.children.length,
            });

            sortedResultSettings.forEach((setting, index) => {
                console.log('[populateFormBuilder] Processing result setting:', {
                    index,
                    setting,
                    rule_group_id: setting.rule_group_id,
                    result_rule_id: setting.result_rule_id,
                });
                const resultSettingCard = createResultSettingCard();
                attachResultSettingEvents(resultSettingCard);

                // Determine insertion position based on order
                // If order is within range of current items, insert at that position
                // Otherwise, append at the end
                const currentItems = Array.from(questionsContainer.children);
                const totalItems = currentItems.length;
                const targetOrder = setting.order !== undefined ? setting.order : totalItems + index;
                let insertPosition = null;

                if (targetOrder < totalItems) {
                    // Insert at specific position
                    const targetItem = currentItems[targetOrder];
                    if (targetItem) {
                        questionsContainer.insertBefore(resultSettingCard, targetItem);
                        insertPosition = 'inserted';
                    }
                }

                // If not inserted yet, append at the end
                if (!insertPosition) {
                    questionsContainer.appendChild(resultSettingCard);
                }

                // Populate data
                const ruleSelect = resultSettingCard.querySelector('.result-setting-rule-select');
                const imageValueInput = resultSettingCard.querySelector('.result-setting-image-value');
                const imageArea = resultSettingCard.querySelector('.result-setting-image-area');
                const resultImage = resultSettingCard.querySelector('.result-setting-image');
                const imageSettings = resultSettingCard.querySelector('.result-setting-image-settings');
                const alignmentSelect = resultSettingCard.querySelector('.result-setting-image-alignment-select');
                const textAlignmentSelect = resultSettingCard.querySelector('.result-setting-text-alignment-select');

                // Find rule_group_id from result_rule_id or use rule_group_id directly
                let ruleGroupId = null;
                if (setting.rule_group_id) {
                    // New format: use rule_group_id directly
                    ruleGroupId = setting.rule_group_id;
                } else if (setting.result_rule_id) {
                    // Old format: find rule_group_id from result_rule_id
                    const resultRules = Array.isArray(data.result_rules) ? data.result_rules : [];
                    const matchingRule = resultRules.find(r => r.id === setting.result_rule_id);
                    if (matchingRule && matchingRule.rule_group_id) {
                        ruleGroupId = matchingRule.rule_group_id;
                    }
                }

                // Set selected option based on rule_group_id
                // Use setTimeout to ensure dropdown options are fully rendered
                setTimeout(() => {
                    if (ruleGroupId && ruleSelect) {
                        // Find option with matching rule_group_id
                        const options = Array.from(ruleSelect.options);
                        const matchingOption = options.find(opt => opt.getAttribute('data-rule-group-id') === ruleGroupId);
                        console.log('[populateFormBuilder] Setting rule select:', {
                            ruleGroupId,
                            optionsCount: options.length,
                            matchingOption: !!matchingOption,
                            allOptions: options.map(opt => ({
                                value: opt.value,
                                ruleGroupId: opt.getAttribute('data-rule-group-id'),
                                text: opt.textContent,
                            })),
                        });
                        if (matchingOption) {
                            ruleSelect.value = matchingOption.value;
                            // Trigger change event to load texts from rules (will get all texts from all rules in group)
                            ruleSelect.dispatchEvent(new Event('change'));
                        } else {
                            console.warn('[populateFormBuilder] No matching option found for rule_group_id:', ruleGroupId, {
                                availableRuleGroupIds: options.map(opt => opt.getAttribute('data-rule-group-id')).filter(Boolean),
                            });
                        }
                    } else {
                        console.warn('[populateFormBuilder] Missing ruleGroupId or ruleSelect:', {
                            ruleGroupId,
                            hasRuleSelect: !!ruleSelect,
                        });
                    }
                }, 100); // Small delay to ensure DOM is ready

                // Apply saved text settings (title, image per text) if available
                if (Array.isArray(setting.text_settings) && setting.text_settings.length) {
                    updateRuleGroupTextLookup(ruleGroupId, setting.text_settings);
                    setResultSettingTextValues(resultSettingCard, setting.text_settings);
                } else {
                    // Fallback: use saved result_text (legacy format)
                    const savedTexts = setting.result_text
                        ? setting.result_text.split(/\n{2,}/).map(text => text.trim()).filter(Boolean)
                        : [];
                    const fallbackTextObjects = savedTexts.map((text, index) => ({
                        result_rule_text_id: `legacy-${ruleGroupId || 'unknown'}-${index}`,
                        result_text: text,
                        title: null,
                        image: null,
                    }));
                    updateRuleGroupTextLookup(ruleGroupId, fallbackTextObjects);
                    setResultSettingTextValues(resultSettingCard, fallbackTextObjects);
                }

                // Populate image if exists
                if (setting.image || setting.image_url) {
                    const imageUrl = setting.image_url || setting.image;
                    if (imageValueInput) {
                        imageValueInput.value = imageUrl;
                    }
                    if (resultImage) {
                        resultImage.src = imageUrl;
                    }
                    if (imageArea) {
                        imageArea.classList.remove('hidden');
                    }
                    if (imageSettings) {
                        imageSettings.classList.remove('hidden');
                    }
                }

                if (textAlignmentSelect) {
                    if (setting.text_alignment) {
                        textAlignmentSelect.value = setting.text_alignment;
                    }
                    textAlignmentSelect.dispatchEvent(new Event('change'));
                }

                if (alignmentSelect) {
                    if (setting.image_alignment) {
                        alignmentSelect.value = setting.image_alignment;
                    }
                    alignmentSelect.dispatchEvent(new Event('change'));
                }
            });

            updateSectionNumbers();
            updateQuestionNumbers();
            updateUseRuleButtonsVisibility();
            initSortable();
            updateMainButtonsVisibility();
        },
    });
}

// Card Formatting Toolbar Management
let activeCardElement = null;
let activeInputElement = null; // Store the currently active input element
let cardFormattingState = {
    textAlign: 'left',
    fontFamily: 'Arial',
    fontSize: 12,
    fontWeight: 'normal',
    fontStyle: 'normal',
    textDecoration: 'none'
};

// Initialize card formatting toolbar
function initCardFormattingToolbar() {
    const toolbar = document.getElementById('card-formatting-toolbar');
    if (!toolbar) return;

    // List of allowed input elements for toolbar
    const allowedInputs = [
        '#form-title',                    // Judul form
        '#form-description',              // Deskripsi form
        '.question-title',                // Pertanyaan
        '.section-title-input',           // Judul bagian
        '.section-description-input'      // Deskripsi bagian
    ];

    // Show/hide toolbar based on cursor focus
    function handleFocus(e) {
        const target = e.target;
        const isAllowed = allowedInputs.some(selector => {
            if (selector.startsWith('#')) {
                return target.id === selector.substring(1);
            } else if (selector.startsWith('.')) {
                return target.classList.contains(selector.substring(1));
            }
            return false;
        });

        if (isAllowed) {
            // Convert to contenteditable if needed
            let editableElement = target;
            if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') {
                editableElement = convertToContentEditable(target);
            }

            // Store the active input element
            activeInputElement = editableElement;
            activeCardElement = editableElement.closest('.question-card, .section-divider') || editableElement.closest('.bg-white.rounded-lg');
            toolbar.classList.remove('opacity-0', 'pointer-events-none');
            toolbar.classList.add('opacity-100', 'pointer-events-auto');

            // Load existing formatting from data attributes or inline styles
            loadExistingFormatting(editableElement);

            // Listen for selection changes to update button states (only add once)
            if (!editableElement.hasAttribute('data-formatting-listeners')) {
                editableElement.setAttribute('data-formatting-listeners', 'true');
                editableElement.addEventListener('mouseup', () => {
                    setTimeout(updateFormattingButtonStates, 10);
                });
                editableElement.addEventListener('keyup', () => {
                    setTimeout(updateFormattingButtonStates, 10);
                });
                editableElement.addEventListener('keydown', (e) => {
                    // Update on arrow keys
                    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                        setTimeout(updateFormattingButtonStates, 10);
                    }
                });
            }
        }
    }

    // Load existing formatting from element
    function loadExistingFormatting(element) {
        const parentCard = element.closest('.question-card, .section-divider, .bg-white.rounded-lg');
        // For section title/description, check element itself first (data attributes are stored on element)
        // For question title, check parent card (data attributes are stored on parent)
        const isSectionTitleOrDesc = element.classList.contains('section-title-input') || element.classList.contains('section-description-input');
        const source = (isSectionTitleOrDesc ? element : parentCard) || element;

        // Check if there's a selection/block - prioritize formatting from selected text
        const selection = window.getSelection();
        let fontFamily = null;
        let fontSize = null;
        let fontWeight = null;
        let fontStyle = null;
        let textDecoration = null;

        if (selection && selection.rangeCount > 0) {
            const range = selection.getRangeAt(0);
            if (!range.collapsed) {
                // There's a selection - get formatting from selected text
                const container = range.commonAncestorContainer;
                let selectedElement = container.nodeType === Node.TEXT_NODE ? container.parentElement : container;

                // Traverse up to find formatting in spans
                while (selectedElement && selectedElement !== document.body && element.contains(selectedElement)) {
                    const computedStyle = window.getComputedStyle(selectedElement);

                    // Get font-family from span with inline style
                    if (!fontFamily && selectedElement.tagName === 'SPAN' && selectedElement.style.fontFamily) {
                        fontFamily = selectedElement.style.fontFamily.split(',')[0].replace(/['"]/g, '').trim();
                    } else if (!fontFamily && computedStyle.fontFamily && computedStyle.fontFamily !== 'Arial') {
                        fontFamily = computedStyle.fontFamily.split(',')[0].replace(/['"]/g, '').trim();
                    }

                    // Get font-size from span
                    if (!fontSize && selectedElement.tagName === 'SPAN' && selectedElement.style.fontSize) {
                        fontSize = parseInt(selectedElement.style.fontSize) || null;
                    } else if (!fontSize && computedStyle.fontSize) {
                        fontSize = parseInt(computedStyle.fontSize) || null;
                    }

                    // Get font-weight
                    if (!fontWeight && (selectedElement.tagName === 'STRONG' || selectedElement.tagName === 'B' ||
                        computedStyle.fontWeight === 'bold' || computedStyle.fontWeight === '700')) {
                        fontWeight = 'bold';
                    } else if (!fontWeight) {
                        fontWeight = computedStyle.fontWeight || 'normal';
                    }

                    // Get font-style
                    if (!fontStyle && (selectedElement.tagName === 'EM' || selectedElement.tagName === 'I' ||
                        computedStyle.fontStyle === 'italic')) {
                        fontStyle = 'italic';
                    } else if (!fontStyle) {
                        fontStyle = computedStyle.fontStyle || 'normal';
                    }

                    // Get text-decoration
                    if (!textDecoration && (selectedElement.tagName === 'U' ||
                        computedStyle.textDecoration.includes('underline'))) {
                        textDecoration = 'underline';
                    } else if (!textDecoration) {
                        textDecoration = computedStyle.textDecoration || 'none';
                    }

                    selectedElement = selectedElement.parentElement;
                }
            }
        }

        // If no selection or formatting not found from selection, get from element/parent
        if (!fontFamily) {
            fontFamily = source.getAttribute('data-fontFamily') || element.style.fontFamily || 'Arial';
            // If fontFamily is empty or default, try to get from computed style
            if (!fontFamily || fontFamily === 'Arial' || fontFamily === '') {
                const computedStyle = window.getComputedStyle(element);
                const computedFont = computedStyle.fontFamily;
                // Extract first font family from computed style (e.g., "Times New Roman, serif" -> "Times New Roman")
                if (computedFont) {
                    fontFamily = computedFont.split(',')[0].replace(/['"]/g, '').trim();
                }
            }
        }

        if (!fontSize) {
            fontSize = source.getAttribute('data-fontSize') || parseInt(element.style.fontSize) || 12;
        }
        if (!fontWeight) {
            fontWeight = source.getAttribute('data-fontWeight') || element.style.fontWeight || 'normal';
        }
        if (!fontStyle) {
            fontStyle = source.getAttribute('data-fontStyle') || element.style.fontStyle || 'normal';
        }
        if (!textDecoration) {
            textDecoration = source.getAttribute('data-textDecoration') || element.style.textDecoration || 'none';
        }

        const textAlign = source.getAttribute('data-textAlign') || element.style.textAlign || 'left';

        // Update state
        cardFormattingState = {
            textAlign: textAlign,
            fontFamily: fontFamily,
            fontSize: fontSize,
            fontWeight: fontWeight,
            fontStyle: fontStyle,
            textDecoration: textDecoration
        };

        // Update displays
        updateFontSizeDisplay(fontSize);
        updateFontFamilyDisplay(fontFamily);
        updateTextAlignIcon(textAlign);

        // Update manual input
        const fontSizeManualInput = document.querySelector('#font-size-manual');
        if (fontSizeManualInput) {
            fontSizeManualInput.value = fontSize;
        }

        // Update button states
        const toolbar = document.getElementById('card-formatting-toolbar');
        if (toolbar) {
            // Bold
            const boldBtn = toolbar.querySelector('[data-tool="bold"]');
            if (boldBtn) {
                if (fontWeight === 'bold' || fontWeight === '700') {
                    boldBtn.classList.add('bg-red-100');
                } else {
                    boldBtn.classList.remove('bg-red-100');
                }
            }

            // Italic
            const italicBtn = toolbar.querySelector('[data-tool="italic"]');
            if (italicBtn) {
                if (fontStyle === 'italic') {
                    italicBtn.classList.add('bg-red-100');
                } else {
                    italicBtn.classList.remove('bg-red-100');
                }
            }

            // Underline
            const underlineBtn = toolbar.querySelector('[data-tool="underline"]');
            if (underlineBtn) {
                if (textDecoration === 'underline') {
                    underlineBtn.classList.add('bg-red-100');
                } else {
                    underlineBtn.classList.remove('bg-red-100');
                }
            }
        }
    }

    function handleBlur(e) {
        // Delay to check if focus moved to another allowed input or toolbar
        setTimeout(() => {
            const activeElement = document.activeElement;

            // Check if focus moved to toolbar
            const toolbarElement = document.getElementById('card-formatting-toolbar');
            const isClickingToolbar = toolbarElement && (
                toolbarElement.contains(activeElement) ||
                activeElement.closest('#card-formatting-toolbar')
            );

            // If clicking toolbar, keep it visible and don't clear activeInputElement
            if (isClickingToolbar) {
                return; // Keep toolbar visible and activeInputElement intact
            }

            // Check if focus moved to another allowed input
            const isAllowed = allowedInputs.some(selector => {
                if (selector.startsWith('#')) {
                    return activeElement.id === selector.substring(1);
                } else if (selector.startsWith('.')) {
                    return activeElement.classList.contains(selector.substring(1));
                }
                return false;
            });

            // Only hide toolbar if focus is not on allowed input and not on toolbar
            if (!isAllowed && !isClickingToolbar) {
                activeCardElement = null;
                activeInputElement = null;
                toolbar.classList.add('opacity-0', 'pointer-events-none');
                toolbar.classList.remove('opacity-100', 'pointer-events-auto');
            } else if (isAllowed) {
                // Focus moved to another allowed input, update activeInputElement
                activeInputElement = activeElement;
                activeCardElement = activeElement.closest('.question-card, .section-divider') || activeElement.closest('.bg-white.rounded-lg');
                loadExistingFormatting(activeElement);
            }
        }, 100);
    }

    // Attach focus/blur listeners to all allowed inputs
    document.addEventListener('focusin', handleFocus);
    document.addEventListener('focusout', handleBlur);

    // Prevent toolbar from hiding when clicking inside it
    toolbar.addEventListener('mousedown', (e) => {
        e.preventDefault(); // Prevent input from losing focus
    });

    // Also handle clicks on toolbar to prevent blur
    toolbar.addEventListener('click', (e) => {
        e.stopPropagation();
        // Keep toolbar visible
        if (activeInputElement) {
            toolbar.classList.remove('opacity-0', 'pointer-events-none');
            toolbar.classList.add('opacity-100', 'pointer-events-auto');
        }
    });

    // Also check on initial load if any input is already focused
    const activeElement = document.activeElement;
    const isAllowed = allowedInputs.some(selector => {
        if (selector.startsWith('#')) {
            return activeElement.id === selector.substring(1);
        } else if (selector.startsWith('.')) {
            return activeElement.classList.contains(selector.substring(1));
        }
        return false;
    });

    if (isAllowed) {
        activeInputElement = activeElement;
        activeCardElement = activeElement.closest('.question-card, .section-divider') || activeElement.closest('.bg-white.rounded-lg');
        toolbar.classList.remove('opacity-0', 'pointer-events-none');
        toolbar.classList.add('opacity-100', 'pointer-events-auto');

        // Load existing formatting from data attributes or inline styles
        loadExistingFormatting(activeElement);
    }

    // Handle toolbar button clicks
    setupToolbarEvents();

    // Load Google Fonts (lazy load when font dropdown is opened)
}

// Setup toolbar event handlers
function setupToolbarEvents() {
    const toolbar = document.getElementById('card-formatting-toolbar');
    if (!toolbar) return;

    // Text Alignment
    const textAlignBtn = toolbar.querySelector('[data-tool="text-align"]');
    const textAlignDropdown = textAlignBtn?.nextElementSibling;
    const textAlignOptions = toolbar.querySelectorAll('.text-align-option');
    const textAlignIcon = toolbar.querySelector('#text-align-icon');

    if (textAlignBtn && textAlignDropdown) {
        textAlignBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            textAlignDropdown.classList.toggle('hidden');
        });

        textAlignOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const value = option.getAttribute('data-value');
                cardFormattingState.textAlign = value;
                applyCardFormatting('textAlign', value);
                updateTextAlignIcon(value);
                textAlignDropdown.classList.add('hidden');
            });
        });
    }

    // Font Family
    const fontFamilyBtn = toolbar.querySelector('[data-tool="font-family"]');
    const fontFamilyDropdown = fontFamilyBtn?.nextElementSibling;
    const fontFamilySearchInput = toolbar.querySelector('#font-family-search-input');
    const fontFamilyList = toolbar.querySelector('#font-family-list');

    if (fontFamilyBtn && fontFamilyDropdown) {
        fontFamilyBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            fontFamilyDropdown.classList.toggle('hidden');
            if (!fontFamilyDropdown.classList.contains('hidden')) {
                // Clear search input when opening
                if (fontFamilySearchInput) {
                    fontFamilySearchInput.value = '';
                }
                // Load fonts if not already loaded
                if (fontFamilyList.children.length === 0) {
                    loadGoogleFonts();
                } else {
                    // Reset to show recent + popular if search was active
                    filterFonts('');
                }
            }
        });

        if (fontFamilySearchInput) {
            fontFamilySearchInput.addEventListener('input', (e) => {
                filterFonts(e.target.value);
            });
        }
    }

    // Font Size - Manual Input
    const fontSizeManualInput = toolbar.querySelector('#font-size-manual');
    if (fontSizeManualInput) {
        fontSizeManualInput.addEventListener('change', (e) => {
            const value = parseInt(e.target.value) || 12;
            if (value >= 8 && value <= 72) {
                cardFormattingState.fontSize = value;
                applyCardFormatting('fontSize', value);
                updateFontSizeDisplay(value);
            }
        });
    }

    // Font Size - Increase/Decrease Buttons
    const increaseSizeBtn = toolbar.querySelector('[data-action="increase-size"]');
    const decreaseSizeBtn = toolbar.querySelector('[data-action="decrease-size"]');

    if (increaseSizeBtn) {
        increaseSizeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const newSize = Math.min(cardFormattingState.fontSize + 1, 72);
            cardFormattingState.fontSize = newSize;
            applyCardFormatting('fontSize', newSize);
            updateFontSizeDisplay(newSize);
            if (fontSizeManualInput) fontSizeManualInput.value = newSize;
        });
    }

    if (decreaseSizeBtn) {
        decreaseSizeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const newSize = Math.max(cardFormattingState.fontSize - 1, 8);
            cardFormattingState.fontSize = newSize;
            applyCardFormatting('fontSize', newSize);
            updateFontSizeDisplay(newSize);
            if (fontSizeManualInput) fontSizeManualInput.value = newSize;
        });
    }

    // Font Size - Template Options
    const fontSizeBtn = toolbar.querySelector('[data-tool="font-size"]');
    const fontSizeDropdown = fontSizeBtn?.nextElementSibling;
    const fontSizeTemplateOptions = toolbar.querySelectorAll('.font-size-template-option');

    if (fontSizeBtn && fontSizeDropdown) {
        fontSizeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            fontSizeDropdown.classList.toggle('hidden');
        });

        fontSizeTemplateOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const value = parseInt(option.getAttribute('data-value')) || 12;
                cardFormattingState.fontSize = value;
                applyCardFormatting('fontSize', value);
                updateFontSizeDisplay(value);
                if (fontSizeManualInput) fontSizeManualInput.value = value;
                fontSizeDropdown.classList.add('hidden');
            });
        });
    }

    // Bold Button
    const boldBtn = toolbar.querySelector('[data-action="bold"]');
    if (boldBtn) {
        boldBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            // Check current state first
            const currentState = checkFormattingState('fontWeight');
            const newState = currentState === 'bold' ? 'normal' : 'bold';
            cardFormattingState.fontWeight = newState;
            applyCardFormatting('fontWeight', newState);
            // Update button state after a short delay to ensure formatting is applied
            setTimeout(() => {
                updateFormattingButtonStates();
            }, 10);
        });
    }

    // Italic Button
    const italicBtn = toolbar.querySelector('[data-action="italic"]');
    if (italicBtn) {
        italicBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            // Check current state first
            const currentState = checkFormattingState('fontStyle');
            const newState = currentState === 'italic' ? 'normal' : 'italic';
            cardFormattingState.fontStyle = newState;
            applyCardFormatting('fontStyle', newState);
            // Update button state after a short delay to ensure formatting is applied
            setTimeout(() => {
                updateFormattingButtonStates();
            }, 10);
        });
    }

    // Underline Button
    const underlineBtn = toolbar.querySelector('[data-action="underline"]');
    if (underlineBtn) {
        underlineBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            // Check current state first
            const currentState = checkFormattingState('textDecoration');
            const newState = currentState === 'underline' ? 'none' : 'underline';
            cardFormattingState.textDecoration = newState;
            applyCardFormatting('textDecoration', newState);
            // Update button state after a short delay to ensure formatting is applied
            setTimeout(() => {
                updateFormattingButtonStates();
            }, 10);
        });
    }

    // Reset Button
    const resetBtn = toolbar.querySelector('[data-action="reset-formatting"]');
    if (resetBtn) {
        resetBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            resetCardFormatting();
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!toolbar.contains(e.target)) {
            toolbar.querySelectorAll('.card-toolbar-dropdown').forEach(dropdown => {
                dropdown.classList.add('hidden');
            });
        }
    });
}

// Update font size display
function updateFontSizeDisplay(size) {
    const display = document.querySelector('.font-size-display');
    if (display) {
        display.textContent = size;
    }
}

// Update font family display
function updateFontFamilyDisplay(fontFamily) {
    const display = document.querySelector('.font-family-display');
    if (display) {
        display.textContent = fontFamily;
    }
}

// Update text align icon based on selection
function updateTextAlignIcon(alignment) {
    const icon = document.querySelector('#text-align-icon');
    if (!icon) return;

    // Remove all existing paths
    icon.innerHTML = '';

    let pathD = '';
    switch (alignment) {
        case 'left':
            // Left: garis pertama penuh, garis kedua dan ketiga pendek (seperti referensi sebelumnya)
            pathD = 'M4 6h16M4 12h8M4 18h8';
            break;
        case 'center':
            // Center: top/bottom pendek, middle panjang, semua centered
            pathD = 'M6 6h12M4 10h16M6 14h12M4 18h16';
            break;
        case 'right':
            // Right: dua garis penuh di atas, satu garis pendek di kanan bawah
            pathD = 'M3.75 6.75h16.5M3.75 12h16.5M12 17.25h8.25';
            break;
        case 'justify':
            // Justify: semua garis penuh (rata kiri kanan)
            pathD = 'M4 6h16M4 10h16M4 14h16M4 18h16';
            break;
        default:
            pathD = 'M4 6h16M4 12h8M4 18h8'; // Default to left
    }

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    path.setAttribute('stroke-width', '2');
    path.setAttribute('d', pathD);
    icon.appendChild(path);
}

// Convert input/textarea to contenteditable div for rich text formatting
function convertToContentEditable(element) {
    if (!element || element.contentEditable === 'true') return element;

    // Create contenteditable div
    const contentEditable = document.createElement('div');
    contentEditable.contentEditable = 'true';
    contentEditable.className = element.className;
    contentEditable.style.cssText = element.style.cssText;

    // Copy value
    const text = element.value || element.textContent || '';
    contentEditable.textContent = text;

    // Copy attributes
    if (element.id) contentEditable.id = element.id;
    if (element.placeholder) contentEditable.setAttribute('data-placeholder', element.placeholder);

    // Add placeholder styling
    if (element.placeholder) {
        contentEditable.addEventListener('focus', function () {
            if (!this.textContent.trim()) {
                this.classList.add('empty');
            }
        });
        contentEditable.addEventListener('blur', function () {
            if (!this.textContent.trim()) {
                this.classList.add('empty');
            } else {
                this.classList.remove('empty');
            }
        });
        if (!text.trim()) {
            contentEditable.classList.add('empty');
        }
    }

    // Replace element
    element.parentNode.replaceChild(contentEditable, element);

    return contentEditable;
}

// Get plain text from contenteditable (for form submission)
function getPlainTextFromContentEditable(element) {
    if (!element) return '';
    if (element.contentEditable === 'true') {
        return element.textContent || element.innerText || '';
    }
    return element.value || '';
}

// Normalize HTML by removing unnecessary nested spans
function normalizeHTML(html) {
    if (!html) return '';

    // Create a temporary container to parse HTML
    const temp = document.createElement('div');
    temp.innerHTML = html;

    // Flatten nested spans
    const flattenSpans = (container) => {
        const spans = container.querySelectorAll('span');
        spans.forEach(span => {
            // If span is nested inside another span
            if (span.parentElement && span.parentElement.tagName === 'SPAN') {
                const parentSpan = span.parentElement;
                const parentStyle = parentSpan.getAttribute('style') || '';
                const spanStyle = span.getAttribute('style') || '';

                // If same style, remove inner span
                if (parentStyle === spanStyle) {
                    while (span.firstChild) {
                        parentSpan.insertBefore(span.firstChild, span);
                    }
                    parentSpan.removeChild(span);
                }
                // If different styles, merge into inner span and move out
                else if (spanStyle && parentStyle) {
                    // Merge styles (inner takes precedence)
                    const mergedStyle = mergeStyles(parentStyle, spanStyle);
                    span.setAttribute('style', mergedStyle);

                    // Move span out of parent
                    parentSpan.parentNode.insertBefore(span, parentSpan);

                    // Move parent's other children after span
                    while (parentSpan.firstChild) {
                        span.parentNode.insertBefore(parentSpan.firstChild, span.nextSibling);
                    }

                    // Remove empty parent
                    parentSpan.parentNode.removeChild(parentSpan);
                }
                // If parent has no style, just remove parent
                else if (!parentStyle && spanStyle) {
                    parentSpan.parentNode.insertBefore(span, parentSpan);
                    while (parentSpan.firstChild) {
                        span.parentNode.insertBefore(parentSpan.firstChild, span.nextSibling);
                    }
                    parentSpan.parentNode.removeChild(parentSpan);
                }
            }
        });
    };

    // Run multiple times to handle deeply nested spans
    for (let i = 0; i < 5; i++) {
        flattenSpans(temp);
    }

    // Remove empty spans
    const emptySpans = temp.querySelectorAll('span');
    emptySpans.forEach(span => {
        const style = span.getAttribute('style') || '';
        const text = span.textContent.trim();
        if (!style && !text) {
            while (span.firstChild) {
                span.parentNode.insertBefore(span.firstChild, span);
            }
            span.parentNode.removeChild(span);
        }
    });

    return temp.innerHTML;
}

// Merge two CSS style strings (inner style takes precedence)
function mergeStyles(parentStyle, innerStyle) {
    const parseStyle = (styleStr) => {
        const styles = {};
        styleStr.split(';').forEach(rule => {
            const trimmed = rule.trim();
            if (trimmed) {
                const [prop, value] = trimmed.split(':').map(s => s.trim());
                if (prop && value) {
                    styles[prop] = value;
                }
            }
        });
        return styles;
    };

    const parentStyles = parseStyle(parentStyle);
    const innerStyles = parseStyle(innerStyle);

    // Merge: inner takes precedence
    const merged = { ...parentStyles, ...innerStyles };

    // Convert back to style string
    return Object.entries(merged)
        .map(([prop, value]) => `${prop}: ${value}`)
        .join('; ');
}

// Get HTML content from contenteditable (preserves formatting like bold, italic, underline)
function getHTMLFromContentEditable(element) {
    if (!element) return '';

    // For contenteditable elements
    if (element.contentEditable === 'true' || element.getAttribute('contenteditable') === 'true') {
        const html = element.innerHTML || '';
        const normalized = normalizeHTML(html);

        // If normalization made it empty but there's raw text, fallback to text
        if (!normalized.trim() && element.innerText.trim()) {
            return `<span>${element.innerText.trim()}</span>`;
        }

        return normalized;
    }

    // For regular input/textarea
    const value = (element.value || '').trim();
    return value ? `<span>${value.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>` : '';
}

// Strip HTML tags from string
function stripHTMLTags(html) {
    if (!html) return '';
    const tmp = document.createElement('DIV');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

// Apply formatting to active card (FE ONLY - no database connection)
function applyCardFormatting(property, value) {
    // Use stored activeInputElement instead of document.activeElement
    // This ensures formatting is applied even when user clicks toolbar (which causes blur)
    if (!activeInputElement) return;

    let activeInput = activeInputElement;

    // Convert to contenteditable if not already
    if (activeInput.tagName === 'INPUT' || activeInput.tagName === 'TEXTAREA') {
        activeInput = convertToContentEditable(activeInput);
        activeInputElement = activeInput; // Update reference
    }

    // Check if there's a selection
    const selection = window.getSelection();
    const range = selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

    // If there's a selection, apply formatting to selection only
    if (range && !range.collapsed && activeInput.contains(range.commonAncestorContainer)) {
        // Apply formatting to selected text
        applyFormattingToSelection(property, value, range);
        return;
    }

    // If no selection, apply to entire element (for textAlign, fontSize, fontFamily)
    if (property === 'textAlign' || property === 'fontSize' || property === 'fontFamily') {
        activeInput.style[property] = property === 'fontSize' ? `${value}px` : value;
    }

    // Store formatting in data attribute for FE persistence (NOT saved to database)
    // Store on the input element itself to avoid conflicts
    const isFormTitleOrDescription = activeInput.id === 'form-title' || activeInput.id === 'form-description';
    const isQuestionTitle = activeInput.classList.contains('question-title');
    const isSectionTitleOrDesc = activeInput.classList.contains('section-title-input') || activeInput.classList.contains('section-description-input');

    if (isFormTitleOrDescription || isQuestionTitle || isSectionTitleOrDesc) {
        // Store directly on element for form/question/section title/description to avoid conflicts
        activeInput.setAttribute(`data-${property}`, value);
    } else {
        // For other elements, store on parent card
        const parentCard = activeInput.closest('.question-card, .section-divider');
        if (parentCard) {
            parentCard.setAttribute(`data-${property}`, value);
        } else {
            activeInput.setAttribute(`data-${property}`, value);
        }
    }
}

// Apply formatting to selected text only
function applyFormattingToSelection(property, value, range) {
    if (!range || range.collapsed) return;

    try {
        // Save selection
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range.cloneRange());

        // Apply formatting based on property
        switch (property) {
            case 'fontWeight':
                // Toggle bold - execCommand automatically toggles
                document.execCommand('bold', false, null);
                break;
            case 'fontStyle':
                // Toggle italic - execCommand automatically toggles
                document.execCommand('italic', false, null);
                break;
            case 'textDecoration':
                // Toggle underline - execCommand automatically toggles
                document.execCommand('underline', false, null);
                break;
            case 'fontSize':
                // Check if selection is already in a span, if so, update its style instead of wrapping
                let fontSizeContainer = range.commonAncestorContainer;
                if (fontSizeContainer.nodeType === Node.TEXT_NODE) {
                    fontSizeContainer = fontSizeContainer.parentElement;
                }
                // If already in a span, update its fontSize style
                if (fontSizeContainer && fontSizeContainer.tagName === 'SPAN' && fontSizeContainer.style.fontSize) {
                    fontSizeContainer.style.fontSize = `${value}px`;
                } else {
                    // Wrap selection in span with font size
                    const fontSizeSpan = document.createElement('span');
                    fontSizeSpan.style.fontSize = `${value}px`;
                    try {
                        range.surroundContents(fontSizeSpan);
                    } catch (e) {
                        // If surroundContents fails, use extractContents
                        const contents = range.extractContents();
                        fontSizeSpan.appendChild(contents);
                        range.insertNode(fontSizeSpan);
                    }
                }
                break;
            case 'fontFamily':
                // Check if selection is already in a span, if so, update its style instead of wrapping
                let fontFamilyContainer = range.commonAncestorContainer;
                if (fontFamilyContainer.nodeType === Node.TEXT_NODE) {
                    fontFamilyContainer = fontFamilyContainer.parentElement;
                }
                // If already in a span, update its fontFamily style
                if (fontFamilyContainer && fontFamilyContainer.tagName === 'SPAN' && fontFamilyContainer.style.fontFamily) {
                    fontFamilyContainer.style.fontFamily = value;
                } else {
                    // Wrap selection in span with font family
                    const fontFamilySpan = document.createElement('span');
                    fontFamilySpan.style.fontFamily = value;
                    try {
                        range.surroundContents(fontFamilySpan);
                    } catch (e) {
                        const contents = range.extractContents();
                        fontFamilySpan.appendChild(contents);
                        range.insertNode(fontFamilySpan);
                    }
                }
                break;
            case 'textAlign':
                // Text alignment applies to entire block, not selection
                const activeInput = activeInputElement;
                if (activeInput) {
                    activeInput.style.textAlign = value;
                }
                break;
        }

        // Update button states for bold/italic/underline
        if (property === 'fontWeight' || property === 'fontStyle' || property === 'textDecoration') {
            updateFormattingButtonStates();
        }

        // Update displays for fontSize and fontFamily
        if (property === 'fontSize') {
            updateFontSizeDisplay(value);
        }
        if (property === 'fontFamily') {
            updateFontFamilyDisplay(value);
        }
        if (property === 'textAlign') {
            updateTextAlignIcon(value);
        }

    } catch (e) {
        console.error('Error applying formatting to selection:', e);
    }
}

// Check formatting state at cursor/selection position
function checkFormattingState(property) {
    if (!activeInputElement) return null;

    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) return null;

    const range = selection.getRangeAt(0);
    const container = range.commonAncestorContainer;
    let element = container.nodeType === Node.TEXT_NODE ? container.parentElement : container;

    // Traverse up to find formatting
    while (element && element !== document.body && element !== activeInputElement) {
        const computedStyle = window.getComputedStyle(element);

        if (property === 'fontWeight') {
            if (element.tagName === 'STRONG' || element.tagName === 'B' ||
                computedStyle.fontWeight === 'bold' || computedStyle.fontWeight === '700' ||
                parseInt(computedStyle.fontWeight) >= 700) {
                return 'bold';
            }
        } else if (property === 'fontStyle') {
            if (element.tagName === 'EM' || element.tagName === 'I' ||
                computedStyle.fontStyle === 'italic') {
                return 'italic';
            }
        } else if (property === 'textDecoration') {
            if (element.tagName === 'U' ||
                computedStyle.textDecoration.includes('underline')) {
                return 'underline';
            }
        }

        element = element.parentElement;
    }

    return 'normal';
}

// Update formatting button states based on current selection or cursor position
function updateFormattingButtonStates() {
    if (!activeInputElement) return;

    const toolbar = document.getElementById('card-formatting-toolbar');
    if (!toolbar) return;

    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        // No selection, check at cursor position
        const boldState = checkFormattingState('fontWeight');
        const italicState = checkFormattingState('fontStyle');
        const underlineState = checkFormattingState('textDecoration');

        const boldBtn = toolbar.querySelector('[data-action="bold"]');
        if (boldBtn) {
            if (boldState === 'bold') {
                boldBtn.classList.add('bg-red-100');
            } else {
                boldBtn.classList.remove('bg-red-100');
            }
        }

        const italicBtn = toolbar.querySelector('[data-action="italic"]');
        if (italicBtn) {
            if (italicState === 'italic') {
                italicBtn.classList.add('bg-red-100');
            } else {
                italicBtn.classList.remove('bg-red-100');
            }
        }

        const underlineBtn = toolbar.querySelector('[data-action="underline"]');
        if (underlineBtn) {
            if (underlineState === 'underline') {
                underlineBtn.classList.add('bg-red-100');
            } else {
                underlineBtn.classList.remove('bg-red-100');
            }
        }

        // Update font-family and font-size display from element/data attributes (when no selection)
        const isSectionTitleOrDesc = activeInputElement.classList.contains('section-title-input') || activeInputElement.classList.contains('section-description-input');
        const source = isSectionTitleOrDesc ? activeInputElement : (activeInputElement.closest('.question-card, .section-divider') || activeInputElement);

        // Get font-family from data attribute or element style
        let fontFamily = activeInputElement.getAttribute('data-fontFamily') || activeInputElement.style.fontFamily;
        if (!fontFamily || fontFamily === 'Arial' || fontFamily === '') {
            const computedStyle = window.getComputedStyle(activeInputElement);
            const computedFont = computedStyle.fontFamily;
            if (computedFont) {
                fontFamily = computedFont.split(',')[0].replace(/['"]/g, '').trim();
            }
        } else {
            fontFamily = fontFamily.split(',')[0].replace(/['"]/g, '').trim();
        }
        if (fontFamily && fontFamily !== 'Arial') {
            cardFormattingState.fontFamily = fontFamily;
            updateFontFamilyDisplay(fontFamily);
        }

        // Get font-size from data attribute or element style
        let fontSize = activeInputElement.getAttribute('data-fontSize') || parseInt(activeInputElement.style.fontSize);
        if (!fontSize) {
            const computedStyle = window.getComputedStyle(activeInputElement);
            fontSize = parseInt(computedStyle.fontSize) || 12;
        }
        if (fontSize) {
            cardFormattingState.fontSize = fontSize;
            updateFontSizeDisplay(fontSize);
            const fontSizeManualInput = document.querySelector('#font-size-manual');
            if (fontSizeManualInput) {
                fontSizeManualInput.value = fontSize;
            }
        }

        // Get text-align from data attribute or element style
        const textAlign = activeInputElement.getAttribute('data-textAlign') || activeInputElement.style.textAlign || 'left';
        if (textAlign) {
            cardFormattingState.textAlign = textAlign;
            updateTextAlignIcon(textAlign);
        }

        return;
    }

    // There's a selection - update font-family and font-size from selected text
    const range = selection.getRangeAt(0);
    if (!range.collapsed) {
        const container = range.commonAncestorContainer;
        let selectedElement = container.nodeType === Node.TEXT_NODE ? container.parentElement : container;

        // Find font-family from selected span
        while (selectedElement && selectedElement !== document.body && activeInputElement.contains(selectedElement)) {
            if (selectedElement.tagName === 'SPAN' && selectedElement.style.fontFamily) {
                const fontFamily = selectedElement.style.fontFamily.split(',')[0].replace(/['"]/g, '').trim();
                if (fontFamily && fontFamily !== 'Arial') {
                    cardFormattingState.fontFamily = fontFamily;
                    updateFontFamilyDisplay(fontFamily);
                }
                break;
            }
            selectedElement = selectedElement.parentElement;
        }

        // Find font-size from selected span
        selectedElement = container.nodeType === Node.TEXT_NODE ? container.parentElement : container;
        while (selectedElement && selectedElement !== document.body && activeInputElement.contains(selectedElement)) {
            if (selectedElement.tagName === 'SPAN' && selectedElement.style.fontSize) {
                const fontSize = parseInt(selectedElement.style.fontSize) || null;
                if (fontSize) {
                    cardFormattingState.fontSize = fontSize;
                    updateFontSizeDisplay(fontSize);
                    const fontSizeManualInput = document.querySelector('#font-size-manual');
                    if (fontSizeManualInput) {
                        fontSizeManualInput.value = fontSize;
                    }
                }
                break;
            }
            selectedElement = selectedElement.parentElement;
        }
    }

    // Check if selection contains bold/italic/underline
    const selectionRange = selection.getRangeAt(0);
    const container = selectionRange.commonAncestorContainer;
    let element = container.nodeType === Node.TEXT_NODE ? container.parentElement : container;

    let isBold = false;
    let isItalic = false;
    let isUnderline = false;

    // Traverse up to find formatting
    while (element && element !== document.body && element !== activeInputElement) {
        const computedStyle = window.getComputedStyle(element);

        // Check bold
        if (!isBold && (element.tagName === 'STRONG' || element.tagName === 'B' ||
            computedStyle.fontWeight === 'bold' || computedStyle.fontWeight === '700' ||
            parseInt(computedStyle.fontWeight) >= 700)) {
            isBold = true;
        }

        // Check italic
        if (!isItalic && (element.tagName === 'EM' || element.tagName === 'I' ||
            computedStyle.fontStyle === 'italic')) {
            isItalic = true;
        }

        // Check underline
        if (!isUnderline && (element.tagName === 'U' ||
            computedStyle.textDecoration.includes('underline'))) {
            isUnderline = true;
        }

        element = element.parentElement;
    }

    // Update button states
    const boldBtn = toolbar.querySelector('[data-action="bold"]');
    if (boldBtn) {
        if (isBold) {
            boldBtn.classList.add('bg-red-100');
        } else {
            boldBtn.classList.remove('bg-red-100');
        }
    }

    const italicBtn = toolbar.querySelector('[data-action="italic"]');
    if (italicBtn) {
        if (isItalic) {
            italicBtn.classList.add('bg-red-100');
        } else {
            italicBtn.classList.remove('bg-red-100');
        }
    }

    const underlineBtn = toolbar.querySelector('[data-action="underline"]');
    if (underlineBtn) {
        if (isUnderline) {
            underlineBtn.classList.add('bg-red-100');
        } else {
            underlineBtn.classList.remove('bg-red-100');
        }
    }
}

// Font Cache Management
const FONT_CACHE_KEYS = {
    RECENT: 'formBuilder_recentFonts',
    POPULAR: 'formBuilder_popularFonts',
    ALL_FONTS: 'formBuilder_allFonts',
    CACHE_DATE: 'formBuilder_fontsCacheDate'
};

// Default popular fonts (15 fonts umum)
const DEFAULT_POPULAR_FONTS = [
    'Inter', 'Roboto', 'Open Sans', 'Poppins',
    'Lato', 'Montserrat', 'Raleway', 'Ubuntu',
    'Times New Roman', 'Arial', 'Helvetica', 'Georgia',
    'Verdana', 'Courier New', 'Comic Sans MS'
];

// Get recent fonts from localStorage (max 3)
function getRecentFonts() {
    try {
        const recent = localStorage.getItem(FONT_CACHE_KEYS.RECENT);
        return recent ? JSON.parse(recent) : [];
    } catch (e) {
        return [];
    }
}

// Save font to recent fonts (max 3)
function saveRecentFont(fontFamily) {
    try {
        let recent = getRecentFonts();
        // Remove if already exists
        recent = recent.filter(f => f !== fontFamily);
        // Add to beginning
        recent.unshift(fontFamily);
        // Keep only 3 most recent
        recent = recent.slice(0, 3);
        localStorage.setItem(FONT_CACHE_KEYS.RECENT, JSON.stringify(recent));
    } catch (e) {
        console.error('Error saving recent font:', e);
    }
}

// Get popular fonts from localStorage
function getPopularFonts() {
    try {
        const popular = localStorage.getItem(FONT_CACHE_KEYS.POPULAR);
        return popular ? JSON.parse(popular) : DEFAULT_POPULAR_FONTS;
    } catch (e) {
        return DEFAULT_POPULAR_FONTS;
    }
}

// Save popular fonts to localStorage
function savePopularFonts(fonts) {
    try {
        localStorage.setItem(FONT_CACHE_KEYS.POPULAR, JSON.stringify(fonts));
    } catch (e) {
        console.error('Error saving popular fonts:', e);
    }
}

// Get all fonts from cache
function getAllFontsFromCache() {
    try {
        const cached = localStorage.getItem(FONT_CACHE_KEYS.ALL_FONTS);
        const cacheDate = localStorage.getItem(FONT_CACHE_KEYS.CACHE_DATE);
        // Cache valid for 7 days
        if (cached && cacheDate) {
            const date = new Date(cacheDate);
            const now = new Date();
            const daysDiff = (now - date) / (1000 * 60 * 60 * 24);
            if (daysDiff < 7) {
                return JSON.parse(cached);
            }
        }
        return null;
    } catch (e) {
        return null;
    }
}

// Save all fonts to cache
function saveAllFontsToCache(fonts) {
    try {
        localStorage.setItem(FONT_CACHE_KEYS.ALL_FONTS, JSON.stringify(fonts));
        localStorage.setItem(FONT_CACHE_KEYS.CACHE_DATE, new Date().toISOString());
    } catch (e) {
        console.error('Error saving fonts cache:', e);
    }
}

// Create font option button
function createFontOption(fontFamily, fontList) {
    const fontOption = document.createElement('button');
    fontOption.className = 'font-option w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded';
    fontOption.style.fontFamily = fontFamily;
    fontOption.setAttribute('data-font', fontFamily);
    fontOption.textContent = fontFamily;
    fontOption.addEventListener('click', (e) => {
        e.stopPropagation();
        cardFormattingState.fontFamily = fontFamily;
        applyCardFormatting('fontFamily', fontFamily);
        saveRecentFont(fontFamily);

        // Update font family display in toolbar
        updateFontFamilyDisplay(fontFamily);

        // Load font if not already loaded (only for Google Fonts, skip system fonts)
        const systemFonts = ['Arial', 'Helvetica', 'Times New Roman', 'Courier New', 'Verdana', 'Georgia', 'Comic Sans MS'];
        if (!systemFonts.includes(fontFamily) && !document.querySelector(`link[href*="${fontFamily.replace(/\s+/g, '+')}"]`)) {
            const link = document.createElement('link');
            link.href = `https://fonts.googleapis.com/css2?family=${fontFamily.replace(/\s+/g, '+')}:wght@400;600;700&display=swap`;
            link.rel = 'stylesheet';
            document.head.appendChild(link);
        }

        document.querySelector('[data-tool="font-family"]').nextElementSibling.classList.add('hidden');
    });
    return fontOption;
}

// Render font list with sections
function renderFontList(fontList, recentFonts, popularFonts, allFonts = null) {
    // Clear existing
    fontList.innerHTML = '';

    // Recent fonts section (3 fonts)
    if (recentFonts.length > 0) {
        const recentSection = document.createElement('div');
        recentSection.className = 'mb-3';
        const recentLabel = document.createElement('div');
        recentLabel.className = 'text-xs font-semibold text-gray-500 uppercase tracking-wide px-3 py-1';
        recentLabel.textContent = 'Baru Digunakan';
        recentSection.appendChild(recentLabel);

        recentFonts.forEach(font => {
            recentSection.appendChild(createFontOption(font, fontList));
        });

        fontList.appendChild(recentSection);

        // Separator
        const separator = document.createElement('div');
        separator.className = 'border-t border-gray-200 my-2';
        fontList.appendChild(separator);
    }

    // Popular fonts section (8 fonts)
    const popularSection = document.createElement('div');
    popularSection.className = 'mb-3';
    const popularLabel = document.createElement('div');
    popularLabel.className = 'text-xs font-semibold text-gray-500 uppercase tracking-wide px-3 py-1';
    popularLabel.textContent = 'Font Populer';
    popularSection.appendChild(popularLabel);

    popularFonts.forEach(font => {
        // Skip if already in recent
        if (!recentFonts.includes(font)) {
            popularSection.appendChild(createFontOption(font, fontList));
        }
    });

    fontList.appendChild(popularSection);

    // Store all fonts for search
    if (allFonts) {
        fontList.setAttribute('data-all-fonts', JSON.stringify(allFonts));
    }
}

// Header Setup Management
let headerState = {
    imageUrl: null,
    imageMode: 'cover',
    source: null // 'template' or 'upload'
};

// Template images (will be loaded from assets - only files with prefix 'bc_')
// Paths will be set from Blade template in create.blade.php
// This is a global variable that will be populated by Blade template
window.HEADER_TEMPLATE_IMAGES = window.HEADER_TEMPLATE_IMAGES || [];

// Initialize header setup functionality
function initHeaderSetup() {
    const headerSetupBtn = document.getElementById('header-setup-btn');
    const headerModal = document.getElementById('header-setup-modal');
    const headerCloseBtn = document.getElementById('header-setup-close');
    const headerCancelBtn = document.getElementById('header-cancel-btn');
    const headerSaveBtn = document.getElementById('header-save-btn');
    const headerRemoveBtn = document.getElementById('header-remove-btn');
    const headerTemplateBtn = document.getElementById('header-template-btn');
    const headerUploadBtn = document.getElementById('header-upload-btn');
    const headerTemplateSection = document.getElementById('header-template-section');
    const headerUploadSection = document.getElementById('header-upload-section');
    const headerImageUpload = document.getElementById('header-image-upload');
    const headerPreview = document.getElementById('header-preview');
    const headerUploadPreview = document.getElementById('header-upload-preview');
    const headerUploadPreviewImg = document.getElementById('header-upload-preview-img');
    const headerModeBtns = document.querySelectorAll('.header-mode-btn');

    if (!headerSetupBtn || !headerModal) return;

    // Show/hide header button based on active tab
    function updateHeaderButtonVisibility() {
        const questionsTab = document.getElementById('tab-questions');
        if (questionsTab && !questionsTab.classList.contains('hidden')) {
            headerSetupBtn.classList.remove('hidden');
            headerSetupBtn.classList.add('flex');
        } else {
            headerSetupBtn.classList.add('hidden');
            headerSetupBtn.classList.remove('flex');
        }
    }

    // Watch for tab changes
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            setTimeout(updateHeaderButtonVisibility, 100);
        });
    });
    updateHeaderButtonVisibility();

    // Open modal
    headerSetupBtn.addEventListener('click', () => {
        headerModal.classList.remove('hidden');
        headerModal.classList.add('flex');
        loadHeaderTemplates();
        updateHeaderPreview();
    });

    // Close modal
    const closeModal = () => {
        headerModal.classList.add('hidden');
        headerModal.classList.remove('flex');
    };

    if (headerCloseBtn) headerCloseBtn.addEventListener('click', closeModal);
    if (headerCancelBtn) headerCancelBtn.addEventListener('click', closeModal);
    headerModal.addEventListener('click', (e) => {
        if (e.target === headerModal) closeModal();
    });

    // Source selection (Template/Upload)
    if (headerTemplateBtn) {
        headerTemplateBtn.addEventListener('click', () => {
            headerTemplateBtn.classList.add('border-red-600', 'bg-red-50', 'text-red-600');
            headerTemplateBtn.classList.remove('border-gray-300', 'bg-white', 'text-gray-700');
            headerUploadBtn.classList.remove('border-red-600', 'bg-red-50', 'text-red-600');
            headerUploadBtn.classList.add('border-gray-300', 'bg-white', 'text-gray-700');
            headerTemplateSection.classList.remove('hidden');
            headerUploadSection.classList.add('hidden');
            headerState.source = 'template';
        });
    }

    if (headerUploadBtn) {
        headerUploadBtn.addEventListener('click', () => {
            headerUploadBtn.classList.add('border-red-600', 'bg-red-50', 'text-red-600');
            headerUploadBtn.classList.remove('border-gray-300', 'bg-white', 'text-gray-700');
            headerTemplateBtn.classList.remove('border-red-600', 'bg-red-50', 'text-red-600');
            headerTemplateBtn.classList.add('border-gray-300', 'bg-white', 'text-gray-700');
            headerUploadSection.classList.remove('hidden');
            headerTemplateSection.classList.add('hidden');
            headerState.source = 'upload';
        });
    }

    // Image upload
    if (headerImageUpload) {
        headerImageUpload.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ukuran file maksimal 5MB');
                    return;
                }
                const reader = new FileReader();
                reader.onload = (event) => {
                    headerState.imageUrl = event.target.result;
                    headerUploadPreviewImg.src = event.target.result;
                    headerUploadPreview.classList.remove('hidden');
                    updateHeaderPreview();
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Image mode selection
    headerModeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            headerModeBtns.forEach(b => {
                b.classList.remove('border-red-600', 'text-red-600', 'bg-red-50');
                b.classList.add('border-gray-300', 'text-gray-700');
            });
            btn.classList.add('border-red-600', 'text-red-600', 'bg-red-50');
            btn.classList.remove('border-gray-300', 'text-gray-700');
            headerState.imageMode = btn.getAttribute('data-mode');
            updateHeaderPreview();
        });
    });

    // Save header
    if (headerSaveBtn) {
        headerSaveBtn.addEventListener('click', () => {
            if (!headerState.imageUrl) {
                alert('Pilih gambar terlebih dahulu');
                return;
            }
            applyHeaderToForm();
            closeModal();
        });
    }

    // Remove header
    if (headerRemoveBtn) {
        headerRemoveBtn.addEventListener('click', () => {
            if (confirm('Yakin ingin menghapus header?')) {
                headerState.imageUrl = null;
                headerState.imageMode = 'cover';
                headerState.source = null;
                removeHeaderFromForm();
                closeModal();
            }
        });
    }
}

// Load header templates
function loadHeaderTemplates() {
    const templateSection = document.getElementById('header-template-section');
    if (!templateSection) {
        console.error('Header template section not found');
        return;
    }

    const templateGrid = templateSection.querySelector('.grid');
    if (!templateGrid) {
        console.error('Header template grid not found');
        return;
    }

    // Clear existing
    templateGrid.innerHTML = '';

    // Use window variable to ensure we get the value from Blade template
    const templates = window.HEADER_TEMPLATE_IMAGES || HEADER_TEMPLATE_IMAGES || [];
    console.log('Loading header templates:', templates);
    console.log('Window HEADER_TEMPLATE_IMAGES:', window.HEADER_TEMPLATE_IMAGES);

    if (!templates || templates.length === 0) {
        console.warn('No header template images found');
        templateGrid.innerHTML = '<p class="text-sm text-gray-500 col-span-full text-center py-4">Tidak ada template tersedia</p>';
        return;
    }

    templates.forEach((template, index) => {
        console.log('Processing template:', template);
        const templateItem = document.createElement('div');
        templateItem.className = 'relative cursor-pointer group overflow-hidden rounded-lg bg-white';

        const img = document.createElement('img');
        // Normalize URL to ensure absolute path
        const imageUrl = normalizeMediaUrl(template.path);
        console.log('Template image URL:', imageUrl, 'Original:', template.path);
        img.src = imageUrl;
        img.alt = template.name;
        img.className = 'w-full h-24 object-cover rounded-lg border-2 border-gray-300 group-hover:border-red-600 transition-all duration-200';
        img.style.cssText = 'display: block !important; min-height: 96px; opacity: 1 !important; visibility: visible !important; background: transparent; position: relative; z-index: 1;';
        img.loading = 'lazy';
        img.onerror = function () {
            console.error('Failed to load template image:', imageUrl, 'Original path:', template.path);
            this.style.display = 'none';
            const errorDiv = document.createElement('div');
            errorDiv.className = 'w-full h-24 bg-gray-200 rounded-lg border-2 border-gray-300 flex items-center justify-center text-xs text-gray-500';
            errorDiv.textContent = 'Gambar tidak ditemukan';
            templateItem.appendChild(errorDiv);
        };
        img.onload = function () {
            console.log('Template image loaded successfully:', imageUrl);
            // Ensure image is visible and on top
            this.style.opacity = '1';
            this.style.visibility = 'visible';
            this.style.zIndex = '1';
        };

        // Overlay hanya muncul saat hover, tidak menutupi gambar
        // Gunakan pseudo-element atau overlay yang benar-benar tidak menutupi saat tidak hover
        const overlay = document.createElement('div');
        overlay.className = 'absolute inset-0 rounded-lg transition-opacity flex items-center justify-center pointer-events-none';
        // Set background hanya saat hover menggunakan CSS class, bukan inline style
        overlay.style.cssText = 'z-index: 2; pointer-events: none; background-color: transparent;';

        // Tambahkan event listener untuk hover
        templateItem.addEventListener('mouseenter', () => {
            overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.2)';
        });
        templateItem.addEventListener('mouseleave', () => {
            overlay.style.backgroundColor = 'transparent';
        });

        const checkIcon = document.createElement('svg');
        checkIcon.className = 'w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity';
        checkIcon.setAttribute('fill', 'none');
        checkIcon.setAttribute('stroke', 'currentColor');
        checkIcon.setAttribute('viewBox', '0 0 24 24');
        checkIcon.setAttribute('stroke-width', '2');
        checkIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>';
        overlay.appendChild(checkIcon);

        // Pastikan gambar di-render terlebih dahulu, baru overlay
        templateItem.appendChild(img);
        templateItem.appendChild(overlay);

        // Debug: Log untuk memastikan struktur benar (setelah DOM selesai)
        setTimeout(() => {
            const computedImgStyle = window.getComputedStyle(img);
            const computedOverlayStyle = window.getComputedStyle(overlay);
            console.log('Template item structure:', {
                hasImage: !!img,
                imageSrc: img.src,
                imageDisplay: computedImgStyle.display,
                imageOpacity: computedImgStyle.opacity,
                imageVisibility: computedImgStyle.visibility,
                imageZIndex: computedImgStyle.zIndex,
                imagePosition: computedImgStyle.position,
                hasOverlay: !!overlay,
                overlayOpacity: computedOverlayStyle.opacity,
                overlayBackgroundColor: computedOverlayStyle.backgroundColor,
                overlayZIndex: computedOverlayStyle.zIndex
            });
        }, 100);

        templateItem.addEventListener('click', () => {
            // Use normalized URL for header state
            headerState.imageUrl = imageUrl;
            headerState.source = 'template';
            updateHeaderPreview();
            // Update selected state
            templateGrid.querySelectorAll('img').forEach(el => {
                el.classList.remove('border-red-600');
                el.classList.add('border-gray-300');
            });
            img.classList.add('border-red-600');
            img.classList.remove('border-gray-300');
        });

        templateGrid.appendChild(templateItem);

        console.log('Added template:', template.name, 'Path:', template.path);
    });
}

// Update header preview
function updateHeaderPreview() {
    const headerPreview = document.getElementById('header-preview');
    if (!headerPreview) return;

    if (!headerState.imageUrl) {
        headerPreview.style.backgroundImage = 'none';
        headerPreview.querySelector('.absolute').classList.remove('hidden');
        return;
    }

    headerPreview.querySelector('.absolute').classList.add('hidden');

    // Apply image and mode
    headerPreview.style.backgroundImage = `url(${headerState.imageUrl})`;

    // Apply mode
    switch (headerState.imageMode) {
        case 'stretch':
            headerPreview.style.backgroundSize = '100% 100%';
            headerPreview.style.backgroundPosition = 'center';
            headerPreview.style.backgroundRepeat = 'no-repeat';
            break;
        case 'cover':
            headerPreview.style.backgroundSize = 'cover';
            headerPreview.style.backgroundPosition = 'center';
            headerPreview.style.backgroundRepeat = 'no-repeat';
            break;
        case 'contain':
            headerPreview.style.backgroundSize = 'contain';
            headerPreview.style.backgroundPosition = 'center';
            headerPreview.style.backgroundRepeat = 'no-repeat';
            break;
        case 'repeat':
            headerPreview.style.backgroundSize = 'auto';
            headerPreview.style.backgroundPosition = 'top left';
            headerPreview.style.backgroundRepeat = 'repeat';
            break;
        case 'center':
            headerPreview.style.backgroundSize = 'auto';
            headerPreview.style.backgroundPosition = 'center';
            headerPreview.style.backgroundRepeat = 'no-repeat';
            break;
        case 'no-repeat':
            headerPreview.style.backgroundSize = 'auto';
            headerPreview.style.backgroundPosition = 'center';
            headerPreview.style.backgroundRepeat = 'no-repeat';
            break;
    }
}

// Apply header to form
function applyHeaderToForm() {
    const formHeader = document.querySelector('#tab-questions .bg-white.rounded-lg.shadow-sm.border');
    if (!formHeader) return;

    // Create or update header element
    let headerElement = formHeader.querySelector('.form-header-image');
    if (!headerElement) {
        headerElement = document.createElement('div');
        headerElement.className = 'form-header-image w-full h-48 mb-6 rounded-t-lg overflow-hidden';
        formHeader.insertBefore(headerElement, formHeader.firstChild);
    }

    headerElement.style.backgroundImage = `url(${headerState.imageUrl})`;

    // Apply mode
    switch (headerState.imageMode) {
        case 'stretch':
            headerElement.style.backgroundSize = '100% 100%';
            headerElement.style.backgroundPosition = 'center';
            headerElement.style.backgroundRepeat = 'no-repeat';
            break;
        case 'cover':
            headerElement.style.backgroundSize = 'cover';
            headerElement.style.backgroundPosition = 'center';
            headerElement.style.backgroundRepeat = 'no-repeat';
            break;
        case 'contain':
            headerElement.style.backgroundSize = 'contain';
            headerElement.style.backgroundPosition = 'center';
            headerElement.style.backgroundRepeat = 'no-repeat';
            break;
        case 'repeat':
            headerElement.style.backgroundSize = 'auto';
            headerElement.style.backgroundPosition = 'top left';
            headerElement.style.backgroundRepeat = 'repeat';
            break;
        case 'center':
            headerElement.style.backgroundSize = 'auto';
            headerElement.style.backgroundPosition = 'center';
            headerElement.style.backgroundRepeat = 'no-repeat';
            break;
        case 'no-repeat':
            headerElement.style.backgroundSize = 'auto';
            headerElement.style.backgroundPosition = 'center';
            headerElement.style.backgroundRepeat = 'no-repeat';
            break;
    }
}

// Remove header from form
function removeHeaderFromForm() {
    const formHeader = document.querySelector('#tab-questions .bg-white.rounded-lg.shadow-sm.border');
    if (!formHeader) return;

    const headerElement = formHeader.querySelector('.form-header-image');
    if (headerElement) {
        headerElement.remove();
    }
}

// Load Google Fonts with cache
async function loadGoogleFonts() {
    const fontList = document.getElementById('font-family-list');
    if (!fontList) return;

    // If already rendered, don't reload
    if (fontList.children.length > 0) return;

    // Get recent and popular fonts
    const recentFonts = getRecentFonts();
    const popularFonts = getPopularFonts();

    // Try to get from cache first
    let allFonts = getAllFontsFromCache();

    if (!allFonts) {
        try {
            // Fetch from Google Fonts API
            const response = await fetch('https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity');
            const data = await response.json();
            allFonts = data.items || [];
            saveAllFontsToCache(allFonts);

            // Update popular fonts from fetched data (first 15 most popular)
            if (allFonts.length > 0) {
                const fetchedPopular = allFonts.slice(0, 15).map(font => font.family);
                // Ensure Times New Roman and other system fonts are included
                const systemFonts = ['Times New Roman', 'Arial', 'Helvetica', 'Georgia', 'Verdana', 'Courier New', 'Comic Sans MS'];
                systemFonts.forEach(font => {
                    if (!fetchedPopular.includes(font)) {
                        fetchedPopular.push(font);
                    }
                });
                // Keep only 15
                const finalPopular = fetchedPopular.slice(0, 15);
                savePopularFonts(finalPopular);
                // Update local variable
                popularFonts.splice(0, popularFonts.length, ...finalPopular);
            }
        } catch (error) {
            console.error('Error loading Google Fonts:', error);
            allFonts = [];
        }
    }

    // Render with recent and popular fonts
    renderFontList(fontList, recentFonts, popularFonts, allFonts);
}

// Filter fonts based on search (show 8 recommendations)
function filterFonts(searchTerm) {
    const fontList = document.getElementById('font-family-list');
    if (!fontList) return;

    const term = searchTerm.toLowerCase().trim();

    // If search is empty, show recent + popular
    if (!term) {
        const fontOptions = document.querySelectorAll('.font-option');
        fontOptions.forEach(option => {
            option.style.display = 'block';
        });
        // Show all sections
        fontList.querySelectorAll('.text-xs').forEach(label => {
            label.parentElement.style.display = 'block';
        });
        fontList.querySelectorAll('.border-t').forEach(separator => {
            separator.style.display = 'block';
        });
        return;
    }

    // Get all fonts from cache
    const allFontsData = fontList.getAttribute('data-all-fonts');
    if (!allFontsData) {
        // If no cache, just filter existing options
        const fontOptions = document.querySelectorAll('.font-option');
        let visibleCount = 0;
        fontOptions.forEach(option => {
            const fontName = option.textContent.toLowerCase();
            if (fontName.includes(term) && visibleCount < 8) {
                option.style.display = 'block';
                visibleCount++;
            } else {
                option.style.display = 'none';
            }
        });
        // Hide sections and separators
        fontList.querySelectorAll('.text-xs').forEach(label => {
            label.parentElement.style.display = 'none';
        });
        fontList.querySelectorAll('.border-t').forEach(separator => {
            separator.style.display = 'none';
        });
        return;
    }

    try {
        const allFonts = JSON.parse(allFontsData);

        // Filter fonts that match search term
        const matchingFonts = allFonts
            .filter(font => font.family.toLowerCase().includes(term))
            .slice(0, 8); // Limit to 8 recommendations

        // Clear and render recommendations
        fontList.innerHTML = '';

        if (matchingFonts.length > 0) {
            const recommendationsLabel = document.createElement('div');
            recommendationsLabel.className = 'text-xs font-semibold text-gray-500 uppercase tracking-wide px-3 py-1';
            recommendationsLabel.textContent = 'Rekomendasi';
            fontList.appendChild(recommendationsLabel);

            matchingFonts.forEach(font => {
                fontList.appendChild(createFontOption(font.family, fontList));
            });
        } else {
            const noResults = document.createElement('div');
            noResults.className = 'text-sm text-gray-500 px-3 py-4 text-center';
            noResults.textContent = 'Tidak ada font yang ditemukan';
            fontList.appendChild(noResults);
        }
    } catch (e) {
        console.error('Error filtering fonts:', e);
    }
}

// Reset card formatting to default (FE ONLY)
function resetCardFormatting() {
    // Use stored activeInputElement instead of document.activeElement
    // This ensures reset works even when user clicks toolbar (which causes blur)
    if (!activeInputElement) return;

    const activeInput = activeInputElement;

    // Reset to default values
    const defaults = {
        textAlign: 'left',
        fontFamily: 'Arial',
        fontSize: 12,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textDecoration: 'none'
    };

    // Update state
    cardFormattingState = { ...defaults };

    // Apply default formatting
    activeInput.style.textAlign = defaults.textAlign;
    activeInput.style.fontFamily = defaults.fontFamily;
    activeInput.style.fontSize = `${defaults.fontSize}px`;
    activeInput.style.fontWeight = defaults.fontWeight;
    activeInput.style.fontStyle = defaults.fontStyle;
    activeInput.style.textDecoration = defaults.textDecoration;

    // Update displays
    updateFontSizeDisplay(defaults.fontSize);
    updateFontFamilyDisplay(defaults.fontFamily);
    updateTextAlignIcon(defaults.textAlign);

    // Update manual input
    const fontSizeManualInput = document.querySelector('#font-size-manual');
    if (fontSizeManualInput) {
        fontSizeManualInput.value = defaults.fontSize;
    }

    // Remove active states from buttons
    const toolbar = document.getElementById('card-formatting-toolbar');
    if (toolbar) {
        toolbar.querySelectorAll('.bg-red-100').forEach(btn => {
            btn.classList.remove('bg-red-100');
        });
    }

    // Remove data attributes
    const parentCard = activeInput.closest('.question-card, .section-divider, .bg-white.rounded-lg');
    Object.keys(defaults).forEach(key => {
        if (parentCard) {
            parentCard.removeAttribute(`data-${key}`);
        } else {
            activeInput.removeAttribute(`data-${key}`);
        }
    });
}

// Custom Dialog / Modal function
function showDialog({ title, message, type = 'info', onConfirm, onCancel, confirmText = 'Ya', cancelText = 'Batal' }) {
    // Create modal container
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 transition-opacity duration-300 opacity-0';
    modal.id = 'custom-dialog-modal';

    // Determine icon and color based on type
    let icon = '';
    let confirmBtnClass = '';
    let iconClass = '';

    if (type === 'danger') {
        icon = '<svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
        confirmBtnClass = 'bg-red-600 hover:bg-red-700 text-white';
        iconClass = 'bg-red-100';
    } else if (type === 'warning') {
        icon = '<svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
        confirmBtnClass = 'bg-orange-600 hover:bg-orange-700 text-white';
        iconClass = 'bg-orange-100';
    } else {
        icon = '<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        confirmBtnClass = 'bg-blue-600 hover:bg-blue-700 text-white';
        iconClass = 'bg-blue-100';
    }

    // Modal Content
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl transform transition-all scale-95 opacity-0 sm:max-w-lg sm:w-full p-6">
            <div class="sm:flex sm:items-start">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full ${iconClass} sm:mx-0 sm:h-10 sm:w-10">
                    ${icon}
                </div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        ${title}
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500">
                            ${message}
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                <button type="button" id="dialog-confirm-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm ${confirmBtnClass}">
                    ${confirmText}
                </button>
                <button type="button" id="dialog-cancel-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:mt-0 sm:w-auto sm:text-sm">
                    ${cancelText}
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Animate in
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        const content = modal.querySelector('div');
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);

    const closeDialog = () => {
        modal.classList.add('opacity-0');
        const content = modal.querySelector('div');
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.remove();
        }, 300);
    };

    document.getElementById('dialog-confirm-btn').addEventListener('click', () => {
        if (onConfirm) onConfirm();
        closeDialog();
    });

    document.getElementById('dialog-cancel-btn').addEventListener('click', () => {
        if (onCancel) onCancel();
        closeDialog();
    });
}
