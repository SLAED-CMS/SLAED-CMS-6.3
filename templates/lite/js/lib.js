$(function() {
	// Слайдер в шапке
	$('#slider-head').bxSlider({
		mode: 'fade'
	});
	
	// Примеры внедрений
	$('#slaed_sites').bxSlider({
		minSlides: 4,
		maxSlides: 4,
		slideWidth: 260,
		slideMargin: 20,
		pager: false,
		nextSelector: '#carousel-left',
		prevSelector: '#carousel-right',
		nextText: '>',
		prevText: '<'
	});
});