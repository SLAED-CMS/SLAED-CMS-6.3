/* Clear form */
function ClearForm(id) {
	window.setTimeout(function() {
		id.elements.text.value = '';
	}, 100);
}

/* Delete check info */
function DelCheck(form, text) {
	check = confirm(text);
	if (check == false) return false;
}

/* Open window */
function OpenWindow(url, title, x, y) {
	window.open(url, title, "toolbar=0, location=0, directories=0, status=0, scrollbars=0, resizable=1, copyhistory=0, width="+x+", height="+y+"");
}

/* Adding products in shop module */
var flyingSpeed = 10;
var shop_div = false;
var flyingDiv = false;
var currentProductDiv = false;
var shop_x = false;
var shop_y = false;
var slide_xFactor = false;
var slide_yFactor = false;
var diffX = false;
var diffY = false;
var currentXPos = false;
var currentYPos = false;

function ShopCartTop(inputObj) {
	var returnValue = inputObj.offsetTop;
	while ((inputObj = inputObj.offsetParent) != null) {
		if (inputObj.tagName != 'HTML') returnValue += inputObj.offsetTop;
	}
	return returnValue;
}

function ShopCartLeft(inputObj) {
	var returnValue = inputObj.offsetLeft;
	while ((inputObj = inputObj.offsetParent) != null) {
		if (inputObj.tagName != 'HTML')returnValue += inputObj.offsetLeft;
	}
	return returnValue;
}

function AddBasket(productId) {
	if (!shop_div)shop_div = document.getElementById('shop');
	if (!flyingDiv) {
		flyingDiv = document.createElement('DIV');
		flyingDiv.style.position = 'absolute';
		document.body.appendChild(flyingDiv);
	}
	shop_x = ShopCartLeft(shop_div);
	shop_y = ShopCartTop(shop_div);
	currentProductDiv = document.getElementById('sliding' + productId);
	currentXPos = ShopCartLeft(currentProductDiv);
	currentYPos = ShopCartTop(currentProductDiv);
	diffX = shop_x - currentXPos;
	diffY = shop_y - currentYPos;
	var shoppingContentCopy = currentProductDiv.cloneNode(true);
	shoppingContentCopy.id = '';
	flyingDiv.innerHTML = '';
	flyingDiv.style.left = currentXPos + 'px';
	flyingDiv.style.top = currentYPos + 'px';
	flyingDiv.appendChild(shoppingContentCopy);
	flyingDiv.style.display='block';
	flyingDiv.style.width = currentProductDiv.offsetWidth + 'px';
	FlyBasket(productId);
}

function FlyBasket(productId) {
	var maxDiff = Math.max(Math.abs(diffX),Math.abs(diffY));
	var moveX = (diffX / maxDiff) * flyingSpeed;
	var moveY = (diffY / maxDiff) * flyingSpeed;
	currentXPos = currentXPos + moveX;
	currentYPos = currentYPos + moveY;
	flyingDiv.style.left = Math.round(currentXPos) + 'px';
	flyingDiv.style.top = Math.round(currentYPos) + 'px';
	if (moveX > 0 && currentXPos > shop_x) flyingDiv.style.display='none';
	if (moveX < 0 && currentXPos < shop_x) flyingDiv.style.display='none';
	if (flyingDiv.style.display=='block') window.setTimeout(function () {
		FlyBasket(productId);
	}, 10);
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

function fetchUserSuggestions(input) {
	if (!input || !input.dataset || !input.dataset.slUserSearch) return;
	var listId = input.getAttribute('list');
	var minLength = parseInt(input.dataset.slUserMinlength || '1', 10);
	var value = input.value || '';
	if (!listId) return;
	var list = document.getElementById(listId);
	if (!list) return;
	if (value.length < minLength) {
		list.innerHTML = '';
		return;
	}
	var url = input.dataset.slUserSearch.replace(/&amp;/g, '&') + '&term=' + encodeURIComponent(value);
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
		.then(function (items) {
			if (input.value !== value || !Array.isArray(items)) return;
			list.innerHTML = '';
			for (var i = 0; i < items.length; i++) {
				var option = document.createElement('option');
				option.value = items[i];
				list.appendChild(option);
			}
		})
		.catch(function () {
			list.innerHTML = '';
		});
}

document.addEventListener('input', function (event) {
	var target = event.target;
	if (target && target.matches('input[data-sl-datetime-target]')) {
		syncNativeDateTimeInput(target);
	}
	if (target && target.matches('input[data-sl-user-search]')) {
		fetchUserSuggestions(target);
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
