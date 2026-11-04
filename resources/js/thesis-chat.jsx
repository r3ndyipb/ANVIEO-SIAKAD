import React, { startTransition, useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';

function approvalActions(status, contact) {
	if (contact.kind === 'pembimbing') {
		return [
			...(status === 1 ? ['[ACC] ACC Ujian Proposal'] : []),
			...(status === 2 ? ['[ACC] ACC Revisi Proposal'] : []),
			...([2, 3].includes(status) ? ['[ACC] ACC Ujian Hasil'] : []),
			...(status === 4 ? ['[ACC] ACC Revisi Hasil'] : []),
			...([4, 5].includes(status) ? ['[ACC] ACC Revisi Tutup'] : []),
		];
	}

	return [
		...(status === 2 ? ['[ACC] ACC Revisi Proposal'] : []),
		...(status === 4 ? ['[ACC] ACC Revisi Hasil'] : []),
		...(status === 5 ? ['[ACC] ACC Revisi Tutup'] : []),
	];
}

function messageContent(content) {
	const approval = content.match(/^\[ACC\]\s+([^|]+?)(?:\s*\|\s*([\s\S]*))?$/);
	if (approval) return { system: true, title: approval[1].trim(), text: approval[2] || '' };
	const revision = content.match(/^\[REVISI UJIAN ([^\]]+)\]\s*([\s\S]*)$/);
	if (revision) return { title: `Revisi Ujian ${revision[1]}`, text: revision[2] };
	return { title: '', text: content };
}

function renderMessageText(content) {
	const nodes = [];
	let boldNodes = null;
	const append = (node) => (boldNodes ?? nodes).push(node);

	String(content).split(/(<\/?b>|<br\s*\/?>)/gi).forEach((part, index) => {
		if (/^<b>$/i.test(part)) {
			boldNodes ??= [];
		} else if (/^<\/b>$/i.test(part)) {
			if (boldNodes) {
				nodes.push(React.createElement('strong', { key: `bold-${index}` }, ...boldNodes));
				boldNodes = null;
			} else {
				append(part);
			}
		} else if (/^<br\s*\/?>$/i.test(part)) {
			append(React.createElement('br', { key: `break-${index}` }));
		} else if (part !== '') {
			append(part);
		}
	});

	if (boldNodes) nodes.push(React.createElement('strong', { key: 'bold-trailing' }, ...boldNodes));

	return nodes;
}

function ThesisChat({ root, contacts }) {
	const role = root.dataset.role;
	const teacherId = root.dataset.teacherId || '';
	const workLabel = root.dataset.workLabel || 'skripsi';
	const [contactId, setContactId] = useState(contacts[0]?.id || '');
	const [messages, setMessages] = useState([]);
	const [status, setStatus] = useState(Number(root.dataset.status || 0));
	const canSend = (role === 'mahasiswa' || (role === 'dosen' && contactId === teacherId)) && status > 0;
	const [type, setType] = useState('biasa');
	const [text, setText] = useState('');
	const [fileUrl, setFileUrl] = useState('');
	const [loading, setLoading] = useState(false);
	const [sending, setSending] = useState(false);
	const [error, setError] = useState('');
	const listRef = useRef(null);
	const shouldScrollToBottomRef = useRef(true);
	const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
	const contact = contacts.find((item) => item.id === contactId);
	const actions = role === 'dosen' && contact?.id === teacherId ? approvalActions(status, contact) : [];

	const messagesUrl = new URL(root.dataset.messagesUrlTemplate.replace('__THESIS__', encodeURIComponent(root.dataset.thesisId || '')), window.location.origin);
	const sendUrl = root.dataset.sendUrlTemplate.replace('__THESIS__', encodeURIComponent(root.dataset.thesisId || ''));
	const loadMessages = async (signal) => {
		if (!contactId) return;
		const url = new URL(messagesUrl);
		url.searchParams.set('contact', contactId);
		const response = await fetch(url, {
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			signal,
		});
		const payload = await response.json();
		if (!response.ok) throw new Error(payload.message || `Pesan gagal dimuat (${response.status}).`);
		setStatus(Number(payload.status || 0));
		const messageList = listRef.current;
		shouldScrollToBottomRef.current = !messageList
			|| messageList.scrollHeight - messageList.scrollTop - messageList.clientHeight < 80;
		startTransition(() => setMessages(payload.messages || []));
	};

	useEffect(() => {
		if (!contactId) return undefined;
		let active = true;
		let timer;
		let controller;
		const poll = async () => {
			controller = new AbortController();
			try {
				await loadMessages(controller.signal);
				if (active) setError('');
			} catch (requestError) {
				if (active && requestError.name !== 'AbortError') setError(requestError.message);
			} finally {
				if (active) timer = window.setTimeout(poll, 3000);
			}
		};
		shouldScrollToBottomRef.current = true;
		setLoading(true);
		poll().finally(() => { if (active) setLoading(false); });
		return () => {
			active = false;
			window.clearTimeout(timer);
			controller?.abort();
		};
	}, [contactId]);

	useEffect(() => {
		if (shouldScrollToBottomRef.current && listRef.current) {
			listRef.current.scrollTop = listRef.current.scrollHeight;
		}
	}, [messages, contactId]);

	const submitMessage = async (event) => {
		event.preventDefault();
		if (!contactId || !text.trim()) return;
		setSending(true);
		setError('');
		try {
			const response = await fetch(sendUrl, {
				method: 'POST',
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': csrf,
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: JSON.stringify({ contact: contactId, message: text.trim(), type, file_url: fileUrl.trim() || null }),
			});
			const payload = await response.json();
			if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'Pesan gagal dikirim.');
			setText('');
			setFileUrl('');
			setType('biasa');
			shouldScrollToBottomRef.current = true;
			await loadMessages();
		} catch (requestError) {
			setError(requestError.message);
		} finally {
			setSending(false);
		}
	};

	return <>
		{contacts.length > 0 && <nav className="thesis-contact-tabs" role="tablist" aria-label="Pembimbing dan penguji">
			{contacts.map((item) => <button key={item.key} type="button" role="tab" aria-selected={contactId === item.id} className={contactId === item.id ? 'is-active' : ''} onClick={() => setContactId(item.id)}>
				<span>{item.label}</span><small>{item.name}</small>
			</button>)}
		</nav>}
		{error && <div className="calendar-modal-error" role="alert">{error}</div>}
		{!contact ? <div className="thesis-chat-empty">Belum ada pembimbing atau penguji yang ditugaskan.</div> : <>
			<div className="thesis-chat-heading"><div><strong>{contact.name}</strong><small>{contact.label} · Status {workLabel} {status}</small></div><span aria-live="polite">{loading ? 'Memuat…' : 'Terhubung · diperbarui otomatis'}</span></div>
			<div className="thesis-chat-messages" ref={listRef} aria-live="polite" aria-label={`Percakapan ${contact.label}`}>
				{messages.length === 0 ? <div className="thesis-chat-empty">Belum ada riwayat bimbingan.</div> : messages.map((message) => {
					const content = messageContent(message.content);
					const own = message.sender === role;
					return <article key={message.id} className={`thesis-message${content.system ? ' is-system' : (own ? ' is-own' : '')}`}>
						{content.system ? <div className="thesis-approval"><strong>{content.title}</strong>{content.text && <p>{content.text}</p>}<small>{message.sent_at}</small></div> : <div className="thesis-message-bubble">
							<strong>{content.title || message.sender_name}</strong><p>{renderMessageText(content.text)}</p>
							{message.file_url && <a href={message.file_url} target="_blank" rel="noopener noreferrer">Buka lampiran</a>}
							<small>{message.sent_at}</small>
						</div>}
					</article>;
				})}
			</div>
			{canSend ? <form className="thesis-chat-compose" onSubmit={submitMessage}>
				{role === 'dosen' && <label className="thesis-chat-type">Jenis respons<select value={type} onChange={(event) => setType(event.target.value)}><option value="biasa">Bimbingan / revisi biasa</option>{actions.map((action) => <option key={action} value={action}>{action.replace('[ACC] ', '')}</option>)}</select></label>}
				<textarea value={text} onChange={(event) => setText(event.target.value)} rows="3" maxLength="5000" placeholder={type === 'biasa' ? 'Tulis pesan bimbingan…' : 'Tulis catatan persetujuan…'} required />
				<div className="thesis-chat-compose-footer"><input type="url" value={fileUrl} maxLength={2000} onChange={(event) => setFileUrl(event.target.value)} placeholder="URL lampiran (maksimal 2.000 karakter, opsional)" /><button className="button button-primary" type="submit" disabled={sending || !text.trim()}>{sending ? 'Mengirim…' : 'Kirim'}</button></div>
			</form> : <div className="thesis-chat-readonly">{role === 'dosen' && contact?.id !== teacherId ? 'Riwayat dosen lain bersifat baca-saja. Balasan hanya dapat dikirim pada percakapan yang ditujukan kepada akun Anda.' : 'Riwayat percakapan bersifat baca-saja untuk akun ini.'}</div>}
		</>}
	</>;
}

export function mountThesisChat(root) {
	let contacts = [];
	try {
		contacts = JSON.parse(root.querySelector('[data-thesis-contacts]')?.textContent || '[]');
	} catch {
		contacts = [];
	}
	const mount = root.querySelector('[data-thesis-chat-mount]');
	if (mount) createRoot(mount).render(<ThesisChat root={root} contacts={contacts} />);
}