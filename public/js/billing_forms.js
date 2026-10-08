document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-api-form]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            await handleFormSubmit(form);
        });
    });
});

async function handleFormSubmit(form) {
    const endpoint = form.dataset.apiEndpoint;
    if (!endpoint) {
        console.warn('Missing data-api-endpoint on form', form);
        return;
    }

    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn ? submitBtn.innerHTML : '';
    const feedback = ensureFeedbackElement(form);

    const payload = collectFormPayload(form);

    try {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Menyimpan...';
        }

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Terjadi kesalahan saat memproses permintaan.');
        }

        feedback.classList.remove('alert-danger');
        feedback.classList.add('alert', 'alert-success');
        feedback.textContent = data.message || 'Berhasil disimpan.';

        const reloadAfter = form.dataset.successReload === 'true';
        if (reloadAfter) {
            setTimeout(() => window.location.reload(), 1200);
        } else {
            form.reset();
        }
    } catch (error) {
        feedback.classList.remove('alert-success');
        feedback.classList.add('alert', 'alert-danger');
        feedback.textContent = error.message;
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
}

function collectFormPayload(form) {
    const payload = {};
    payload.action = form.dataset.apiAction || form.querySelector('[name="action"]').value || 'create';

    new FormData(form).forEach((value, key) => {
        if (key === 'action') {
            return;
        }
        if (payload.hasOwnProperty(key)) {
            if (!Array.isArray(payload[key])) {
                payload[key] = [payload[key]];
            }
            payload[key].push(value);
        } else {
            payload[key] = value;
        }
    });

    return payload;
}

function ensureFeedbackElement(form) {
    let feedback = form.querySelector('.form-feedback');
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'form-feedback';
        feedback.style.marginTop = '10px';
        form.prepend(feedback);
    }
    feedback.classList.remove('alert', 'alert-success', 'alert-danger');
    feedback.textContent = '';
    return feedback;
}
