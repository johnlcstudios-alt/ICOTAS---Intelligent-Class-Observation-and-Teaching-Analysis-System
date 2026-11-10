/**
 * ICOTAS survey builder.
 * Drag-and-drop question ordering, type-aware editors and JSON serialization
 * into the server-validated `payload` field. Vanilla JS, no build step.
 */
(function () {
    'use strict';

    var dataElement = document.getElementById('surveyBuilderData');
    var form = document.getElementById('builderForm');
    if (!dataElement || !form) {
        return;
    }

    var initial;
    try {
        initial = JSON.parse(dataElement.textContent || '{}');
    } catch (error) {
        return;
    }

    var editable = !!initial.editable;
    var types = initial.types || {};
    var themes = initial.themes || [];
    var list = document.getElementById('questionList');
    var emptyState = document.getElementById('builderEmpty');
    var countElement = document.getElementById('builderQuestionCount');
    var payloadField = document.getElementById('builderPayload');
    var addButton = document.getElementById('builderAddQuestion');
    if (!list || !payloadField) {
        return;
    }

    var dragCard = null;
    var SCALE_TYPES = ['likert5', 'agreement'];
    var NUMERIC_TYPES = ['numeric'];
    var CHOICE_TYPES = ['single_choice', 'multiple_choice'];
    var DEFAULT_OPTIONS = [
        { label: 'Option 1', value: 'option_1' },
        { label: 'Option 2', value: 'option_2' }
    ];

    function slug(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '') || 'option';
    }

    function makeCard() {
        var wrapper = document.createElement('div');
        wrapper.innerHTML =
            '<article class="question-card">' +
            '  <div class="question-card-header">' +
            '    <button type="button" class="drag-handle" draggable="true" aria-label="Drag to reorder" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></button>' +
            '    <span class="question-number">Q1</span>' +
            '    <select class="form-select form-select-sm question-type" aria-label="Question type"></select>' +
            '    <div class="question-tools">' +
            '      <button type="button" class="btn btn-sm btn-ghost" data-action="up" aria-label="Move question up" title="Move up"><i class="bi bi-arrow-up"></i></button>' +
            '      <button type="button" class="btn btn-sm btn-ghost" data-action="down" aria-label="Move question down" title="Move down"><i class="bi bi-arrow-down"></i></button>' +
            '      <button type="button" class="btn btn-sm btn-ghost" data-action="duplicate" aria-label="Duplicate question" title="Duplicate"><i class="bi bi-files"></i></button>' +
            '      <button type="button" class="btn btn-sm btn-ghost text-danger" data-action="delete" aria-label="Delete question" title="Delete"><i class="bi bi-trash"></i></button>' +
            '    </div>' +
            '  </div>' +
            '  <div class="question-card-body">' +
            '    <label class="form-label small">Question text <span class="text-danger">*</span></label>' +
            '    <textarea class="form-control question-text" rows="2" maxlength="500" required placeholder="Write a clear, neutral question."></textarea>' +
            '    <input class="form-control form-control-sm mt-2 question-description" maxlength="500" placeholder="Optional clarifying note shown under the question">' +
            '    <div class="row g-2 mt-1">' +
            '      <div class="col-md-7">' +
            '        <label class="form-label small">Theme / category</label>' +
            '        <select class="form-select form-select-sm question-category"></select>' +
            '      </div>' +
            '      <div class="col-md-5">' +
            '        <label class="form-label small d-block">Requirement</label>' +
            '        <div class="form-check form-switch">' +
            '          <input class="form-check-input question-required" type="checkbox" id="required_PLACEHOLDER">' +
            '          <label class="form-check-label small" for="required_PLACEHOLDER">Response is required</label>' +
            '        </div>' +
            '      </div>' +
            '    </div>' +
            '    <div class="question-scale row g-2 mt-2 d-none">' +
            '      <div class="col-md-6"><label class="form-label small">Low-end label (1)</label><input class="form-control form-control-sm question-min-label" maxlength="80" placeholder="e.g. Strongly Disagree"></div>' +
            '      <div class="col-md-6"><label class="form-label small">High-end label (5)</label><input class="form-control form-control-sm question-max-label" maxlength="80" placeholder="e.g. Strongly Agree"></div>' +
            '      <div class="col-12"><div class="form-text">The five response labels are defined once in the Likert labels section.</div></div>' +
            '    </div>' +
            '    <div class="question-numeric row g-2 mt-2 d-none">' +
            '      <div class="col-6"><label class="form-label small">Minimum value</label><input class="form-control form-control-sm question-min" type="number" min="1" max="10" step="1"></div>' +
            '      <div class="col-6"><label class="form-label small">Maximum value</label><input class="form-control form-control-sm question-max" type="number" min="1" max="10" step="1"></div>' +
            '      <div class="col-12"><div class="form-text">Numeric ratings are normalized to a five-point scale for aggregate reporting.</div></div>' +
            '    </div>' +
            '    <div class="question-options mt-3 d-none">' +
            '      <label class="form-label small">Answer options</label>' +
            '      <div class="option-rows"></div>' +
            '      <button type="button" class="btn btn-sm btn-outline-secondary mt-1" data-action="add-option"><i class="bi bi-plus-lg"></i> Add option</button>' +
            '    </div>' +
            '  </div>' +
            '</article>';
        return wrapper.firstElementChild;
    }

    function setSelectOptions(select, values, current) {
        select.innerHTML = '';
        values.forEach(function (value) {
            var option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
        });
        if (current && values.indexOf(current) === -1) {
            var extra = document.createElement('option');
            extra.value = current;
            extra.textContent = current;
            select.appendChild(extra);
        }
        select.value = current || values[0] || '';
    }

    function addOptionRow(container, option) {
        var row = document.createElement('div');
        row.className = 'option-row';
        var input = document.createElement('input');
        input.className = 'form-control form-control-sm option-label';
        input.maxLength = 180;
        input.placeholder = 'Option label';
        input.value = (option && option.label) || '';
        var value = document.createElement('input');
        value.type = 'hidden';
        value.className = 'option-value';
        value.value = (option && option.value) || '';
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-ghost text-danger';
        remove.setAttribute('data-action', 'remove-option');
        remove.setAttribute('aria-label', 'Remove option');
        remove.title = 'Remove option';
        remove.innerHTML = '<i class="bi bi-x-lg"></i>';
        row.appendChild(input);
        row.appendChild(value);
        row.appendChild(remove);
        container.appendChild(row);
    }

    function ensureOptions(card, options) {
        var container = card.querySelector('.option-rows');
        container.innerHTML = '';
        var items = Array.isArray(options) && options.length ? options : DEFAULT_OPTIONS;
        items.forEach(function (option) {
            addOptionRow(container, option);
        });
    }

    function updateTypeUI(card) {
        var type = card.querySelector('.question-type').value;
        card.querySelector('.question-scale').classList.toggle('d-none', SCALE_TYPES.indexOf(type) === -1);
        card.querySelector('.question-numeric').classList.toggle('d-none', NUMERIC_TYPES.indexOf(type) === -1);
        card.querySelector('.question-options').classList.toggle('d-none', CHOICE_TYPES.indexOf(type) === -1);
        if (CHOICE_TYPES.indexOf(type) !== -1) {
            var rows = card.querySelectorAll('.option-row');
            if (rows.length < 2) {
                ensureOptions(card, []);
            }
        }
        if (NUMERIC_TYPES.indexOf(type) !== -1) {
            var minInput = card.querySelector('.question-min');
            var maxInput = card.querySelector('.question-max');
            if (!minInput.value) {
                minInput.value = '1';
            }
            if (!maxInput.value || Number(maxInput.value) <= Number(minInput.value)) {
                maxInput.value = '5';
            }
        }
    }

    function createCard(question) {
        var card = makeCard();
        var requiredId = 'builderRequired_' + Math.random().toString(36).slice(2, 9);
        card.querySelector('label[for="required_PLACEHOLDER"]').setAttribute('for', requiredId);
        card.querySelector('.question-required').id = requiredId;

        setSelectOptions(card.querySelector('.question-type'), Object.keys(types), question.type || 'likert5');
        var categoryValues = themes.slice();
        if (question.category && categoryValues.indexOf(question.category) === -1) {
            categoryValues.push(question.category);
        }
        setSelectOptions(card.querySelector('.question-category'), categoryValues, question.category || 'General');
        card.querySelector('.question-text').value = question.text || '';
        card.querySelector('.question-description').value = question.description || '';
        card.querySelector('.question-required').checked = !!question.required;
        card.querySelector('.question-min-label').value = question.min_label || '';
        card.querySelector('.question-max-label').value = question.max_label || '';
        card.querySelector('.question-min').value = question.min !== null && question.min !== undefined ? question.min : 1;
        card.querySelector('.question-max').value = question.max !== null && question.max !== undefined ? question.max : 5;
        ensureOptions(card, question.options || []);

        card.querySelector('.question-type').addEventListener('change', function () {
            updateTypeUI(card);
        });
        updateTypeUI(card);
        return card;
    }

    function createReadOnlyCard(question) {
        var card = document.createElement('article');
        card.className = 'question-card question-card-readonly';

        var header = document.createElement('div');
        header.className = 'question-card-header';
        var number = document.createElement('span');
        number.className = 'question-number';
        var typeBadge = document.createElement('span');
        typeBadge.className = 'badge badge-soft';
        typeBadge.textContent = types[question.type] || question.type;
        header.appendChild(number);
        header.appendChild(typeBadge);
        if (question.required) {
            var required = document.createElement('span');
            required.className = 'badge badge-info';
            required.textContent = 'Required';
            header.appendChild(required);
        }
        card.appendChild(header);

        var body = document.createElement('div');
        body.className = 'question-card-body';
        var text = document.createElement('p');
        text.className = 'question-text-static fw-semibold mb-1';
        text.textContent = question.text || '(no question text)';
        body.appendChild(text);
        if (question.description) {
            var description = document.createElement('p');
            description.className = 'text-muted small mb-2';
            description.textContent = question.description;
            body.appendChild(description);
        }
        var meta = document.createElement('div');
        meta.className = 'text-tiny text-muted';
        meta.textContent = 'Theme: ' + (question.category || 'General');
        body.appendChild(meta);

        if (SCALE_TYPES.indexOf(question.type) !== -1) {
            var scale = document.createElement('div');
            scale.className = 'question-scale-preview mt-2';
            scale.textContent = (question.min_label || 'Low') + ' (1)  →  ' + (question.max_label || 'High') + ' (5)';
            body.appendChild(scale);
        }
        if (NUMERIC_TYPES.indexOf(question.type) !== -1) {
            var range = document.createElement('div');
            range.className = 'text-tiny text-muted mt-2';
            range.textContent = 'Numeric range: ' + (question.min !== null ? question.min : 1) + ' to ' + (question.max !== null ? question.max : 5);
            body.appendChild(range);
        }
        if (CHOICE_TYPES.indexOf(question.type) !== -1) {
            var optionList = document.createElement('ul');
            optionList.className = 'small mt-2 mb-0';
            (question.options || []).forEach(function (option) {
                var item = document.createElement('li');
                item.textContent = option.label;
                optionList.appendChild(item);
            });
            body.appendChild(optionList);
        }
        card.appendChild(body);
        return card;
    }

    function cards() {
        return Array.prototype.slice.call(list.querySelectorAll('.question-card'));
    }

    function renumber() {
        var items = cards();
        items.forEach(function (card, index) {
            var number = card.querySelector('.question-number');
            if (number) {
                number.textContent = 'Q' + (index + 1);
            }
        });
        if (countElement) {
            countElement.textContent = String(items.length);
        }
        if (emptyState) {
            emptyState.classList.toggle('d-none', items.length > 0);
        }
    }

    function appendCard(question, position) {
        var card = createCard(question || { type: 'likert5', category: 'General', required: true });
        if (typeof position === 'number' && list.children[position]) {
            list.insertBefore(card, list.children[position]);
        } else {
            list.appendChild(card);
        }
        renumber();
        return card;
    }

    function collectCard(card) {
        var type = card.querySelector('.question-type').value;
        var text = card.querySelector('.question-text').value.trim();
        var options = [];
        if (CHOICE_TYPES.indexOf(type) !== -1) {
            card.querySelectorAll('.option-row').forEach(function (row) {
                var label = row.querySelector('.option-label').value.trim();
                if (label === '') {
                    return;
                }
                var value = row.querySelector('.option-value').value.trim();
                options.push({ label: label, value: value || slug(label) });
            });
        }
        return {
            text: text,
            description: card.querySelector('.question-description').value.trim(),
            type: type,
            category: card.querySelector('.question-category').value,
            required: card.querySelector('.question-required').checked,
            min: SCALE_TYPES.indexOf(type) !== -1 ? 1 : (NUMERIC_TYPES.indexOf(type) !== -1 ? Number(card.querySelector('.question-min').value || 1) : null),
            max: SCALE_TYPES.indexOf(type) !== -1 ? 5 : (NUMERIC_TYPES.indexOf(type) !== -1 ? Number(card.querySelector('.question-max').value || 5) : null),
            min_label: card.querySelector('.question-min-label').value.trim(),
            max_label: card.querySelector('.question-max-label').value.trim(),
            options: options
        };
    }

    function clearError() {
        var existing = document.getElementById('builderFormError');
        if (existing) {
            existing.remove();
        }
    }

    function showError(message) {
        clearError();
        var alert = document.createElement('div');
        alert.id = 'builderFormError';
        alert.className = 'alert alert-danger app-alert';
        alert.innerHTML = '<i class="bi bi-exclamation-triangle"></i><span></span>';
        alert.querySelector('span').textContent = message;
        list.parentNode.insertBefore(alert, list);
        alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function serialize() {
        var titleInput = document.getElementById('builderTitle');
        var title = titleInput ? titleInput.value.trim() : '';
        if (title === '') {
            showError('The survey title is required.');
            if (titleInput) {
                titleInput.focus();
            }
            return false;
        }
        var items = cards();
        if (items.length === 0) {
            showError('Add at least one question before saving the draft.');
            return false;
        }

        var questions = [];
        for (var i = 0; i < items.length; i++) {
            var question = collectCard(items[i]);
            if (question.text === '') {
                showError('Question ' + (i + 1) + ' needs question text.');
                var textarea = items[i].querySelector('.question-text');
                if (textarea) {
                    textarea.focus();
                }
                return false;
            }
            if (CHOICE_TYPES.indexOf(question.type) !== -1 && question.options.length < 2) {
                showError('Choice question ' + (i + 1) + ' requires at least two non-empty options.');
                return false;
            }
            if (NUMERIC_TYPES.indexOf(question.type) !== -1 && (question.max <= question.min || question.min < 1 || question.max > 10)) {
                showError('Question ' + (i + 1) + ' has an invalid numeric range. Use values between 1 and 10, with the maximum greater than the minimum.');
                return false;
            }
            questions.push(question);
        }

        var ratingLabels = {};
        document.querySelectorAll('[data-rating-label]').forEach(function (input) {
            ratingLabels[input.getAttribute('data-rating-label')] = input.value.trim();
        });

        var description = document.getElementById('builderDescription');
        var instructions = document.getElementById('builderInstructions');
        var category = document.getElementById('builderCategory');

        payloadField.value = JSON.stringify({
            title: title,
            description: description ? description.value.trim() : '',
            instructions: instructions ? instructions.value.trim() : '',
            category: category ? category.value : 'Teaching Effectiveness',
            rating_labels: ratingLabels,
            questions: questions
        });
        clearError();
        return true;
    }

    // Initial render.
    var initialQuestions = Array.isArray(initial.questions) ? initial.questions : [];
    if (editable) {
        initialQuestions.forEach(function (question) {
            list.appendChild(createCard(question));
        });
        if (initialQuestions.length === 0) {
            renumber();
        }
    } else {
        initialQuestions.forEach(function (question) {
            list.appendChild(createReadOnlyCard(question));
        });
    }
    renumber();

    if (!editable) {
        return;
    }

    if (addButton) {
        addButton.addEventListener('click', function () {
            var card = appendCard({ type: 'likert5', category: 'General', required: true });
            card.querySelector('.question-text').focus();
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }

    list.addEventListener('click', function (event) {
        var button = event.target.closest('[data-action]');
        if (!button || !list.contains(button)) {
            return;
        }
        var action = button.getAttribute('data-action');
        var card = button.closest('.question-card');

        if (action === 'add-option' && card) {
            addOptionRow(card.querySelector('.option-rows'), { label: '', value: '' });
            var inputs = card.querySelectorAll('.option-label');
            if (inputs.length) {
                inputs[inputs.length - 1].focus();
            }
            return;
        }
        if (action === 'remove-option' && card) {
            var rows = card.querySelectorAll('.option-row');
            if (rows.length <= 2) {
                showError('A choice question needs at least two options.');
                return;
            }
            var row = button.closest('.option-row');
            if (row) {
                row.remove();
            }
            return;
        }
        if (!card) {
            return;
        }
        if (action === 'delete') {
            if (window.confirm('Delete this question from the draft?')) {
                var index = cards().indexOf(card);
                card.remove();
                renumber();
                var after = cards()[Math.min(index, cards().length - 1)];
                if (after) {
                    after.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
            return;
        }
        if (action === 'duplicate') {
            var copy = createCard(collectCard(card));
            card.parentNode.insertBefore(copy, card.nextSibling);
            renumber();
            copy.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        if (action === 'up') {
            var previous = card.previousElementSibling;
            if (previous && previous.classList.contains('question-card')) {
                list.insertBefore(card, previous);
                renumber();
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            return;
        }
        if (action === 'down') {
            var next = card.nextElementSibling;
            if (next && next.classList.contains('question-card')) {
                list.insertBefore(next, card);
                renumber();
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    });

    list.addEventListener('dragstart', function (event) {
        var handle = event.target.closest('.drag-handle');
        if (!handle) {
            event.preventDefault();
            return;
        }
        dragCard = handle.closest('.question-card');
        dragCard.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', 'question');
    });

    list.addEventListener('dragover', function (event) {
        if (!dragCard) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        var others = cards().filter(function (card) {
            return card !== dragCard;
        });
        var after = null;
        for (var i = 0; i < others.length; i++) {
            var box = others[i].getBoundingClientRect();
            if (event.clientY < box.top + box.height / 2) {
                after = others[i];
                break;
            }
        }
        if (after) {
            list.insertBefore(dragCard, after);
        } else {
            list.appendChild(dragCard);
        }
    });

    list.addEventListener('dragend', function () {
        if (dragCard) {
            dragCard.classList.remove('dragging');
            dragCard = null;
            renumber();
        }
    });

    list.addEventListener('drop', function (event) {
        event.preventDefault();
    });

    // Capture phase guarantees the payload is written before any generic
    // double-submit handler observes the submission.
    form.addEventListener('submit', function (event) {
        if (!serialize()) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
}());
