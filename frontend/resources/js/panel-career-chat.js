function csrfHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    return token ? { 'X-CSRF-TOKEN': token } : {};
}

export function careerChat(initialMessages, sendUrl, clearUrl, labels) {
    return {
        messages: Array.isArray(initialMessages) ? initialMessages : [], text: '', sending: false, error: '', sendUrl, clearUrl, labels,
        async send() {
            const content = this.text.trim(); if (!content || this.sending) return;
            this.messages.push({ id: `pending-${Date.now()}`, role: 'user', content, meta: {} }); this.text = ''; this.sending = true; this.error = '';
            try {
                const response = await fetch(this.sendUrl, { method: 'POST', headers: { ...csrfHeaders(), 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ message: content }) });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || this.labels.failed);
                this.messages.push(payload);
            } catch (error) { this.error = error?.message || this.labels.failed; }
            finally { this.sending = false; }
        },
        async clear() {
            const response = await fetch(this.clearUrl, { method: 'DELETE', headers: { ...csrfHeaders(), Accept: 'application/json' } });
            if (response.ok) this.messages = [];
        },
    };
}
