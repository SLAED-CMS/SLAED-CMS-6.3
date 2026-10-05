/* Open window: still referenced by stored block content (javascript:OpenWindow links), do not remove */
function OpenWindow(url, title, x, y) {
	window.open(url, title, "toolbar=0, location=0, directories=0, status=0, scrollbars=0, resizable=1, copyhistory=0, width="+x+", height="+y+"");
}

function normalizeDateTimeValue(value, kind) {
	if (!value) return '';
	return kind === 'datetime-local' ? value.replace('T', ' ') : value;
}

function syncNativeDateTimeInput(input) {
	if (!input || !input.dataset || !input.dataset.slDatetimeTarget) return;
	var hidden = document.getElementById(input.dataset.slDatetimeTarget);
	if (!hidden) return;
	hidden.value = normalizeDateTimeValue(input.value, input.dataset.slDatetimeKind || '');
}

/* The card of the resolved account: the markup and its class names belong to the theme and stand on the page already, so only the named slots and the tone are written here */
/* An answer that carries no card hides the container again, because a card left standing under a changed name would describe somebody else */
function setUserCard(input, card) {
	var boxId = input.dataset.slUserCard || '';
	var box = boxId ? document.getElementById(boxId) : null;
	if (!box) return;
	box.setAttribute('data-sl-tone', card ? (card.tone || '') : '');
	box.hidden = !card;
	var slots = box.querySelectorAll('[data-sl-user-slot]');
	for (var i = 0; i < slots.length; i++) {
		var key = slots[i].getAttribute('data-sl-user-slot');
		var text = card ? (card[key] || '') : '';
		if (key !== 'avatar') {
			slots[i].textContent = text;
		} else if (text) {
			slots[i].src = text;
		} else {
			slots[i].removeAttribute('src');
		}
	}
}

function fetchUserSuggestions(input) {
	if (!input || !input.dataset || !input.dataset.slUserSearch) return;
	var listId = input.getAttribute('list');
	var minLength = parseInt(input.dataset.slUserMinlength || '1', 10);
	var rich = !!input.dataset.slUserCard;
	var value = input.value || '';
	if (!listId) return;
	var list = document.getElementById(listId);
	if (!list) return;
	if (value.length < minLength) {
		list.innerHTML = '';
		setUserCard(input, null);
		return;
	}
	var url = input.dataset.slUserSearch.replace(/&amp;/g, '&') + '&term=' + encodeURIComponent(value);
	if (rich) {
		url += '&rich=1';
	}
	if (input.dataset.slUserToken) {
		url += '&token=' + encodeURIComponent(input.dataset.slUserToken);
	}
	window.fetch(url, {
		credentials: 'same-origin',
		headers: {
			'X-Requested-With': 'XMLHttpRequest',
			'X-CSRF-TOKEN': input.dataset.slUserToken || ''
		}
	})
		.then(function (response) {
			return response.ok ? response.json() : [];
		})
		.then(function (answer) {
			if (input.value !== value) return;
			var data = answer || [];
			var items = rich ? data.items : data;
			list.innerHTML = '';
			if (Array.isArray(items)) {
				for (var i = 0; i < items.length; i++) {
					var option = document.createElement('option');
					option.value = items[i];
					list.appendChild(option);
				}
			}
			if (rich) setUserCard(input, data.card || null);
		})
		.catch(function () {
			list.innerHTML = '';
			setUserCard(input, null);
		});
}

/* The lookup waits for the typing to settle: every keystroke used to open a request of its own and only the answers were dropped, never the requests themselves */
var userSuggestTimers = new WeakMap();

function setUserSuggestTimer(input) {
	window.clearTimeout(userSuggestTimers.get(input));
	userSuggestTimers.set(input, window.setTimeout(function () {
		fetchUserSuggestions(input);
	}, 250));
}

document.addEventListener('input', function (event) {
	var target = event.target;
	if (target && target.matches('input[data-sl-datetime-target]')) {
		syncNativeDateTimeInput(target);
	}
	if (target && target.matches('input[data-sl-user-search]')) {
		setUserSuggestTimer(target);
	}
});

document.addEventListener('change', function (event) {
	var target = event.target;
	if (target && target.matches('input[data-sl-datetime-target]')) {
		syncNativeDateTimeInput(target);
	}
});

document.addEventListener('submit', function (event) {
	var fields = event.target.querySelectorAll('input[data-sl-datetime-target]');
	for (var i = 0; i < fields.length; i++) {
		syncNativeDateTimeInput(fields[i]);
	}
});
