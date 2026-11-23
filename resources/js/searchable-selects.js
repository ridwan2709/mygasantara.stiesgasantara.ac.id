const nativeSelectClass = 'searchable-select-native';
const initializedAttribute = 'data-searchable-select-initialized';
const selectInstances = new Map();

function optionText(option) {
    return option.textContent.trim();
}

function initializeSelect(select) {
    if (select.hasAttribute(initializedAttribute)) {
        return;
    }

    select.setAttribute(initializedAttribute, 'true');
    const required = select.required;
    select.required = false;

    const wrapper = document.createElement('div');
    wrapper.className = 'searchable-select';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = select.className;
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-autocomplete', 'list');

    const label = select.labels?.[0];
    if (label && !label.htmlFor) {
        trigger.id = `searchable-select-${Math.random().toString(36).slice(2)}`;
        label.htmlFor = trigger.id;
    } else if (label) {
        trigger.setAttribute('aria-label', label.textContent.trim());
    } else if (select.getAttribute('aria-label')) {
        trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
    }

    const valueText = document.createElement('span');
    valueText.className = 'searchable-select-value';
    const indicator = document.createElement('span');
    indicator.className = 'searchable-select-indicator';
    indicator.setAttribute('aria-hidden', 'true');
    indicator.textContent = '▾';
    trigger.append(valueText, indicator);

    const popup = document.createElement('div');
    popup.className = 'searchable-select-popup';
    popup.hidden = true;

    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'searchable-select-search';
    search.placeholder = 'Cari pilihan...';
    search.autocomplete = 'off';
    search.setAttribute('aria-label', label ? `Cari ${label.textContent.trim()}` : 'Cari pilihan');

    const optionsList = document.createElement('div');
    optionsList.className = 'searchable-select-options';
    optionsList.id = `${trigger.id || `searchable-select-options-${Math.random().toString(36).slice(2)}`}-options`;
    optionsList.setAttribute('role', 'listbox');
    optionsList.setAttribute('aria-multiselectable', String(select.multiple));
    trigger.setAttribute('aria-controls', optionsList.id);

    popup.append(search, optionsList);
    wrapper.append(trigger, popup);

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.classList.add(nativeSelectClass);
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    const close = () => {
        popup.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        search.value = '';
        renderOptions();
    };

    const syncValue = () => {
        const selected = Array.from(select.selectedOptions).map(optionText);
        const placeholderOption = Array.from(select.options).find((option) => option.value === '' && optionText(option));
        valueText.textContent = selected.length
            ? selected.join(', ')
            : placeholderOption
                ? optionText(placeholderOption)
                : 'Pilih...';
        trigger.disabled = select.disabled;
        trigger.classList.toggle('searchable-select-disabled', select.disabled);
    };

    const renderOptions = () => {
        optionsList.replaceChildren();
        const query = search.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        Array.from(select.children).forEach((child) => {
            if (child instanceof HTMLOptGroupElement) {
                const group = document.createElement('div');
                group.className = 'searchable-select-option-group';
                const heading = document.createElement('div');
                heading.className = 'searchable-select-group';
                heading.textContent = child.label;
                let groupHasVisibleOption = false;

                Array.from(child.options).forEach((option) => {
                    if (option.hidden || !optionText(option).toLocaleLowerCase().includes(query)) {
                        return;
                    }

                    groupHasVisibleOption = true;
                    visibleCount++;
                    group.appendChild(createOption(option));
                });

                if (groupHasVisibleOption) {
                    group.prepend(heading);
                    optionsList.appendChild(group);
                }

                return;
            }

            if (!(child instanceof HTMLOptionElement) || child.hidden || !optionText(child).toLocaleLowerCase().includes(query)) {
                return;
            }

            visibleCount++;
            optionsList.appendChild(createOption(child));
        });

        if (visibleCount === 0) {
            const empty = document.createElement('div');
            empty.className = 'searchable-select-empty';
            empty.textContent = 'Tidak ada hasil.';
            optionsList.appendChild(empty);
        }
    };

    const createOption = (option) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'searchable-select-option';
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', String(option.selected));
        item.disabled = option.disabled || Boolean(option.parentElement?.disabled);
        item.textContent = optionText(option);
        item.addEventListener('click', () => {
            if (select.multiple) {
                option.selected = !option.selected;
            } else {
                select.value = option.value;
            }

            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncValue();

            if (!select.multiple) {
                close();
                trigger.focus();
            } else {
                search.focus();
            }
        });

        return item;
    };

    const open = () => {
        if (select.disabled) {
            return;
        }

        popup.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        renderOptions();
        search.focus();
    };

    trigger.addEventListener('click', () => {
        if (popup.hidden) {
            syncValue();
            open();
        } else {
            close();
        }
    });
    trigger.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open();
        } else if (event.key === 'Escape') {
            close();
        }
    });

    search.addEventListener('input', renderOptions);
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            trigger.focus();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            optionsList.querySelector('button:not(:disabled)')?.focus();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            optionsList.querySelector('button:not(:disabled)')?.click();
        }
    });

    optionsList.addEventListener('keydown', (event) => {
        const options = Array.from(optionsList.querySelectorAll('button:not(:disabled)'));
        const currentIndex = options.indexOf(document.activeElement);

        if (event.key === 'Escape') {
            close();
            trigger.focus();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            options[Math.min(currentIndex + 1, options.length - 1)]?.focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (currentIndex <= 0) {
                search.focus();
            } else {
                options[currentIndex - 1]?.focus();
            }
        }
    });

    select.addEventListener('change', () => {
        syncValue();
        renderOptions();
    });
    select.addEventListener('focus', () => trigger.focus());
    select.form?.addEventListener('reset', () => window.setTimeout(syncValue));
    select.form?.addEventListener('submit', (event) => {
        const hasSelection = select.multiple ? select.selectedOptions.length > 0 : select.value !== '';
        if (required && !hasSelection) {
            event.preventDefault();
            trigger.setAttribute('aria-invalid', 'true');
            open();
            search.focus();
        } else {
            trigger.removeAttribute('aria-invalid');
        }
    }, true);

    const observer = new MutationObserver(() => {
        syncValue();
        renderOptions();
    });
    observer.observe(select, { childList: true, subtree: true, attributes: true, characterData: true });

    selectInstances.set(select, syncValue);
    syncValue();
}

function initializeSearchableSelects(root = document) {
    root.querySelectorAll('select').forEach(initializeSelect);
}

initializeSearchableSelects();

document.addEventListener('click', (event) => {
    selectInstances.forEach((syncValue, select) => {
        const wrapper = select.parentElement;
        if (wrapper && !wrapper.contains(event.target)) {
            const popup = wrapper.querySelector('.searchable-select-popup');
            if (popup && !popup.hidden) {
                popup.hidden = true;
                wrapper.querySelector('[role="combobox"]')?.setAttribute('aria-expanded', 'false');
            }
        }
    });
});

document.addEventListener('click', () => {
    selectInstances.forEach((syncValue) => syncValue());
});

new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node instanceof HTMLSelectElement) {
                initializeSelect(node);
            } else if (node instanceof HTMLElement) {
                initializeSearchableSelects(node);
            }
        });
    });
}).observe(document.documentElement, { childList: true, subtree: true });
