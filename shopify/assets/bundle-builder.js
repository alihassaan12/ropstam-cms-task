(() => {
  if (customElements.get('bundle-builder')) return;

  const REQUEST_TIMEOUT = 15000;

  function formatMoney(cents, format) {
    const template = format || '${{amount}}';
    const value = Number(cents) || 0;

    const withDelimiters = (amount, decimals, thousands = ',', decimal = '.') => {
      const [whole, fraction] = (amount / 100).toFixed(decimals).split('.');
      const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
      return fraction ? grouped + decimal + fraction : grouped;
    };

    return template.replace(/\{\{\s*(\w+)\s*\}\}/, (match, key) => {
      switch (key) {
        case 'amount_no_decimals':
          return withDelimiters(value, 0);
        case 'amount_with_comma_separator':
          return withDelimiters(value, 2, '.', ',');
        case 'amount_no_decimals_with_comma_separator':
          return withDelimiters(value, 0, '.');
        case 'amount_with_apostrophe_separator':
          return withDelimiters(value, 2, "'");
        default:
          return withDelimiters(value, 2);
      }
    });
  }

  function createBundleId() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID().slice(0, 8);
    return Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
  }

  class BundleBuilder extends HTMLElement {
    connectedCallback() {
      this.form = this.querySelector('[data-bundle-form]');
      this.groups = Array.from(this.querySelectorAll('[data-bundle-group]'));
      this.submitButton = this.querySelector('[data-submit]');
      this.submitLabel = this.querySelector('[data-submit-label]');
      this.summaryList = this.querySelector('[data-summary-list]');
      this.statusEl = this.querySelector('[data-status]');
      this.errorEl = this.querySelector('[data-error]');
      this.successEl = this.querySelector('[data-success]');
      this.totalEl = this.querySelector('[data-total]');
      this.subtotalEl = this.querySelector('[data-subtotal]');
      this.subtotalRow = this.querySelector('[data-subtotal-row]');
      this.savingEl = this.querySelector('[data-saving]');
      this.savingRow = this.querySelector('[data-saving-row]');

      this.discount = Math.min(Math.max(parseInt(this.dataset.discount, 10) || 0, 0), 100);
      this.moneyFormat = this.dataset.moneyFormat;
      this.defaultLabel = this.submitLabel.textContent;
      this.loading = false;

      this.onChange = this.onChange.bind(this);
      this.onSubmit = this.onSubmit.bind(this);
      this.form.addEventListener('change', this.onChange);
      this.form.addEventListener('submit', this.onSubmit);

      this.buildSummary();
      this.update();
    }

    disconnectedCallback() {
      this.form.removeEventListener('change', this.onChange);
      this.form.removeEventListener('submit', this.onSubmit);
    }

    buildSummary() {
      this.summaryItems = this.groups.map((group) => {
        const li = document.createElement('li');
        li.className = 'bundle-summary__item';
        const name = document.createElement('span');
        name.className = 'bundle-summary__group';
        name.textContent = group.dataset.groupTitle;
        const value = document.createElement('span');
        value.className = 'bundle-summary__pick';
        li.append(name, value);
        this.summaryList.append(li);
        return value;
      });
    }

    onChange(event) {
      const select = event.target.closest('[data-variant-select]');
      if (select) this.syncVariant(select);
      this.hideMessages();
      this.update();
    }

    syncVariant(select) {
      const option = select.selectedOptions[0];
      const item = select.closest('[data-bundle-option]');
      const radio = item.querySelector('input[type="radio"]');
      if (!option || !radio) return;

      radio.value = option.value;
      radio.dataset.price = option.dataset.price;
      radio.dataset.variantTitle = option.dataset.title;
      radio.checked = true;

      const priceEl = item.querySelector('[data-option-price]');
      if (priceEl) priceEl.textContent = formatMoney(option.dataset.price, this.moneyFormat);
    }

    getSelections() {
      return this.groups.map((group) => group.querySelector('input[type="radio"]:checked:not([disabled])'));
    }

    update() {
      const selections = this.getSelections();
      const chosen = selections.filter(Boolean);
      const required = this.groups.length;
      const complete = required > 0 && chosen.length === required;

      selections.forEach((input, i) => {
        const text = input
          ? input.dataset.title + (input.dataset.variantTitle ? ` – ${input.dataset.variantTitle}` : '')
          : 'Not selected';
        if (this.summaryItems[i].textContent !== text) this.summaryItems[i].textContent = text;
        this.summaryItems[i].classList.toggle('is-empty', !input);
      });

      const subtotal = chosen.reduce((sum, input) => sum + (parseInt(input.dataset.price, 10) || 0), 0);
      const saving = complete ? Math.round((subtotal * this.discount) / 100) : 0;

      this.totalEl.textContent = formatMoney(subtotal - saving, this.moneyFormat);
      this.subtotalRow.hidden = saving === 0;
      if (saving) this.subtotalEl.textContent = formatMoney(subtotal, this.moneyFormat);
      if (this.savingRow) {
        this.savingRow.hidden = saving === 0;
        if (saving) this.savingEl.textContent = `−${formatMoney(saving, this.moneyFormat)}`;
      }

      const blockedGroup = this.groups.find((group) => !group.querySelector('input[type="radio"]:not([disabled])'));
      if (blockedGroup) {
        this.setStatus(`${blockedGroup.dataset.groupTitle} is sold out, so this bundle can't be completed right now.`);
      } else if (!complete) {
        this.setStatus(`${chosen.length} of ${required} selected`);
      } else {
        this.setStatus('Your bundle is ready.');
      }

      this.submitButton.disabled = this.loading || !complete || Boolean(blockedGroup);
    }

    setStatus(text) {
      if (this.statusEl.textContent !== text) this.statusEl.textContent = text;
    }

    hideMessages() {
      this.errorEl.hidden = true;
      this.successEl.hidden = true;
    }

    showError(message) {
      this.errorEl.textContent = message;
      this.errorEl.hidden = false;
    }

    setLoading(state) {
      this.loading = state;
      this.classList.toggle('is-loading', state);
      this.submitButton.setAttribute('aria-busy', String(state));
      this.submitLabel.textContent = state ? 'Adding…' : this.defaultLabel;
      this.groups.forEach((group) => {
        group.disabled = state;
      });
      this.update();
    }

    async onSubmit(event) {
      event.preventDefault();
      if (this.loading) return;

      const selections = this.getSelections();
      if (selections.some((input) => !input)) {
        this.showError('Please pick one item from each group.');
        return;
      }

      const bundleId = createBundleId();
      const bundleName = this.dataset.bundleName || 'Bundle';
      const items = selections.map((input) => ({
        id: Number(input.value),
        quantity: 1,
        properties: {
          Bundle: bundleName,
          _bundle_id: bundleId,
          _bundle_size: String(selections.length),
        },
      }));

      const body = { items };
      const sections = this.cartSectionsToRender();
      if (sections.length) {
        body.sections = sections.map((s) => s.section).join(',');
        body.sections_url = window.location.pathname;
      }

      this.hideMessages();
      this.setLoading(true);

      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT);
      let added = false;

      try {
        const root = (window.Shopify && window.Shopify.routes && window.Shopify.routes.root) || this.dataset.rootUrl || '/';
        const response = await fetch(`${root.replace(/\/?$/, '/')}cart/add.js`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify(body),
          signal: controller.signal,
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
          throw new Error(data.description || data.message || 'This bundle could not be added. Please try again.');
        }

        added = true;
        this.renderCartSections(sections, data.sections);
        this.successEl.hidden = false;

        document.dispatchEvent(
          new CustomEvent('bundle-builder:added', { bubbles: true, detail: { bundleId, items: data.items || [] } })
        );
        if (typeof window.publish === 'function' && window.PUB_SUB_EVENTS) {
          window.publish(window.PUB_SUB_EVENTS.cartUpdate, { source: 'bundle-builder', cartData: data });
        }
      } catch (error) {
        const message =
          error.name === 'AbortError'
            ? 'The request timed out. Please check your connection and try again.'
            : error instanceof TypeError
              ? 'We could not reach the store. Please check your connection and try again.'
              : error.message;
        this.showError(message);
      } finally {
        clearTimeout(timer);
        this.setLoading(false);
        if (added) this.setStatus('Bundle added to your cart.');
      }
    }

    cartSectionsToRender() {
      return [{ id: 'cart-icon-bubble', section: 'cart-icon-bubble', selector: '.shopify-section' }].filter((s) =>
        document.getElementById(s.id)
      );
    }

    renderCartSections(sections, html) {
      if (!html) return;
      sections.forEach(({ id, section, selector }) => {
        const target = document.getElementById(id);
        if (!target || !html[section]) return;
        const doc = new DOMParser().parseFromString(html[section], 'text/html');
        const source = doc.querySelector(selector);
        if (source) target.innerHTML = source.innerHTML;
      });
    }
  }

  customElements.define('bundle-builder', BundleBuilder);
})();
