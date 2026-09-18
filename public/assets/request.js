const form = document.querySelector('#request-form');
const panels = [...form.querySelectorAll('.wizard-panel')];
const stepButtons = [...form.querySelectorAll('.wizard-step')];
const items = form.querySelector('#items');
const template = document.querySelector('#item-template');
const category = form.querySelector('#category');
const releaseDate = form.querySelector('[name="release_date"]');
const addButton = form.querySelector('#add-item');
const previousItem = form.querySelector('#previous-item');
const nextItem = form.querySelector('#next-item');
const backStep = form.querySelector('#back-step');
const nextStep = form.querySelector('#next-step');
const submitButton = form.querySelector('#submit-request');
let step = Number(form.dataset.startStep) || 0;
let itemIndex = 0;
let reviewPage = 0;
let nextIndex = Math.max(0, ...[...items.querySelectorAll('[data-item-index]')].map(row => Number(row.dataset.itemIndex))) + 1;
let stepInitialized = false;
let itemInitialized = false;

function cards() {
  return [...items.querySelectorAll('.wizard-item')];
}

function showItem(index) {
  const rows = cards();
  const previous = itemIndex;
  itemIndex = Math.max(0, Math.min(index, rows.length - 1));
  rows.forEach((row, position) => {
    row.hidden = position !== itemIndex;
    row.querySelectorAll('.item-number').forEach(number => {
      number.textContent = String(position + 1);
    });
    row.querySelector('.remove-item').disabled = rows.length === 1;
  });
  form.querySelector('#item-position').textContent = `Item ${itemIndex + 1} of ${rows.length}`;
  previousItem.disabled = itemIndex === 0;
  nextItem.disabled = itemIndex === rows.length - 1;
  addButton.disabled = rows.length >= 20;
  if (itemInitialized && previous !== itemIndex) {
    form.dispatchEvent(new CustomEvent('securepass:itemchange', { bubbles: true, detail: { item: rows[itemIndex] } }));
  }
  itemInitialized = true;
}

function showStep(target) {
  const previous = step;
  step = target;
  panels.forEach((panel, index) => {
    panel.hidden = index !== step;
    panel.classList.toggle('is-active', index === step);
  });
  stepButtons.forEach((button, index) => {
    button.classList.toggle('is-active', index === step);
    if (index === step) button.setAttribute('aria-current', 'step');
    else button.removeAttribute('aria-current');
  });
  backStep.hidden = step === 0;
  nextStep.hidden = step === 2;
  submitButton.hidden = step !== 2;
  form.querySelector('#step-indicator').textContent = `Step ${step + 1} of 3`;
  if (step === 1) showItem(itemIndex);
  if (step === 2) renderReview();
  panels[step].querySelector('h2')?.focus({ preventScroll: true });
  if (stepInitialized && previous !== step) {
    form.dispatchEvent(new CustomEvent('securepass:stepchange', { bubbles: true, detail: { step, previous, panel: panels[step] } }));
  }
  stepInitialized = true;
}

function refreshCategory() {
  const other = form.querySelector('.other-category-field');
  const input = other.querySelector('input');
  other.hidden = category.value !== 'other';
  input.required = category.value === 'other';
}

function validateFields(container) {
  for (const input of container.querySelectorAll('input, select, textarea')) {
    if (input.disabled) continue;
    input.setCustomValidity('');
    if (input.type === 'file') {
      const count = input.files.length;
      if (count < 1 || count > 5) input.setCustomValidity('Attach between 1 and 5 files to this item.');
      else if ([...input.files].some(file => file.size < 1 || file.size > 10 * 1024 * 1024)) {
        input.setCustomValidity('Each attachment must be between 1 byte and 10 MB.');
      }
    }
    if (input.name.endsWith('[return_date]') && input.value && releaseDate.value && input.value < releaseDate.value) {
      input.setCustomValidity('The return date cannot be before the release date.');
    }
    if (!input.checkValidity()) {
      input.reportValidity();
      return false;
    }
  }
  return true;
}

function validateDetails() {
  showStep(0);
  return validateFields(panels[0]);
}

function validateItems() {
  showStep(1);
  const rows = cards();
  const selected = itemIndex;
  for (let index = 0; index < rows.length; index += 1) {
    showItem(index);
    if (!validateFields(rows[index])) return false;
  }
  showItem(Math.min(selected, rows.length - 1));
  return true;
}

function renderReview() {
  form.querySelector('#review-company').textContent = form.querySelector('[name="company"]').value.trim() || '—';
  const categoryName = category.selectedOptions[0]?.textContent || '—';
  const otherCategory = form.querySelector('[name="other_category"]').value.trim();
  form.querySelector('#review-category').textContent = category.value === 'other' && otherCategory
    ? `Other: ${otherCategory}` : categoryName;
  form.querySelector('#review-date').textContent = releaseDate.value || '—';
  const list = form.querySelector('#review-items');
  list.replaceChildren();
  const rows = cards();
  const pageCount = Math.max(1, Math.ceil(rows.length / 5));
  reviewPage = Math.min(reviewPage, pageCount - 1);
  rows.slice(reviewPage * 5, reviewPage * 5 + 5).forEach((card, index) => {
    const line = document.createElement('li');
    const name = document.createElement('strong');
    const detail = document.createElement('span');
    name.textContent = card.querySelector('[name$="[name]"]').value.trim() || `Item ${reviewPage * 5 + index + 1}`;
    const quantity = card.querySelector('[name$="[quantity]"]').value;
    const unit = card.querySelector('[name$="[unit]"]').value;
    const files = card.querySelector('input[type="file"]').files.length;
    detail.textContent = `${quantity} ${unit} · ${files} ${files === 1 ? 'attachment' : 'attachments'}`;
    line.append(name, detail);
    list.append(line);
  });
  form.querySelector('#review-count').textContent = String(rows.length);
  form.querySelector('#review-page').textContent = `${reviewPage + 1} of ${pageCount}`;
  form.querySelector('#review-prev').disabled = reviewPage === 0;
  form.querySelector('#review-next').disabled = reviewPage === pageCount - 1;
}

function goTo(target) {
  if (target === 0) return showStep(0);
  if (!validateDetails()) return;
  if (target === 1) return showStep(1);
  if (!validateItems()) return;
  showStep(2);
}

stepButtons.forEach(button => button.addEventListener('click', () => goTo(Number(button.dataset.stepTarget))));
nextStep.addEventListener('click', () => goTo(step + 1));
backStep.addEventListener('click', () => showStep(step - 1));
category.addEventListener('change', refreshCategory);
releaseDate.addEventListener('change', () => {
  cards().forEach(card => { card.querySelector('[name$="[return_date]"]').min = releaseDate.value; });
});
previousItem.addEventListener('click', () => showItem(itemIndex - 1));
nextItem.addEventListener('click', () => showItem(itemIndex + 1));
form.querySelector('#review-prev').addEventListener('click', () => { reviewPage -= 1; renderReview(); });
form.querySelector('#review-next').addEventListener('click', () => { reviewPage += 1; renderReview(); });
addButton.addEventListener('click', () => {
  if (cards().length >= 20 || !validateFields(cards()[itemIndex])) return;
  const markup = template.innerHTML.replaceAll('999999', String(nextIndex++));
  items.insertAdjacentHTML('beforeend', markup);
  showItem(cards().length - 1);
  cards()[itemIndex].querySelector('[name$="[return_date]"]').min = releaseDate.value;
  cards()[itemIndex].querySelector('[name$="[name]"]').focus();
});
items.addEventListener('click', event => {
  if (!event.target.classList.contains('remove-item') || cards().length === 1) return;
  cards()[itemIndex].remove();
  showItem(itemIndex);
});
form.addEventListener('submit', event => {
  if (step !== 2) {
    event.preventDefault();
    goTo(Math.min(step + 1, 2));
    return;
  }
  if (!validateDetails() || !validateItems()) {
    event.preventDefault();
    return;
  }
  showStep(2);
});

refreshCategory();
releaseDate.dispatchEvent(new Event('change'));
showItem(0);
showStep(Math.max(0, Math.min(step, 2)));
