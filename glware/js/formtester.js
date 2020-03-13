$(function() {
	if ($('form.request_form').length != 0) {
		var div = document.createElement('div'),
			style = [
				'background-color: #aac',
				'position: fixed',
				'top: 0',
				'right: 0',
				'width: 300px',
				'padding: 10px'
			],

			button = document.createElement('button'),
			par = document.createElement('p')
			inputs = $('form.request_form input').add('form.request_form select'),
			formElements = {};

			config = {
				'orgname': ['Вислоухие коромысла', 'Почтенные следопыты Сибири', 'Зонирование всей семьей', "Параходство Екатеринбургских окрестностей", "Оловянные солдатики Неверляндии", "Стройбытпромкусь", "Застроймыш", "Инфарма", "Зодчество Краснодара", "Пельменъ", "Калейдоскопов сотоварищи", "Янтарный мир 2D", "Щукинский молотильщик", "Промышленный репортер", "Фрикаделькин", "DEFF", "MYSHKin", "BAttt", "Издательский дом Пропеллер", "Вилки и ложки", "Четыре лапки", "Хвост коромыслом", "Севастопольский краевед"],

				'orgtype': ['ООО', 'ЗАО', 'ОАО', 'ЫЫЫ', 'ИП', 'ОППА', 'ЖЖЖ'],

				'city': ['Зареченск', 'Мытищи', 'Лавандово', 'Гипернакулус', 'Выпильсбург', 'Трофаретинск', 'Железногорск', 'Пыльносбруево', 'Скитлбич', 'Паранормальнинск', 'Охламоничи', 'Нагайкина Выпь', 'Кострюлинск', 'Долгие мымры', 'Шмельный'],

				'job': ['Собиратель ремесел', 'Каскадер на полставки', 'Лужевытератель', 'опретаор шредера', 'колесничий', 'рикша', 'старший замешиватель', 'Разметчик', 'заместитель робота', 'Плакальщик', 'исполнитель на флейте водосточных труб','стажер', 'шпион', 'Актер больших и малых академических театров', 'зачинщик беспорядков', 'Учетчик-приходчик', 'налетчик', 'фарцовщик', 'оператор калькулятора', 'смотрящий за смотрителем', 'контролер пробивных работ', 'ответственный за тусовки', 'автор потдельных писем читателей', 'вычислитель', 'полицейский в отставке'],

				'name': ['Трофим Лапоправивеч', 'Константин Pоботович Гаромыкин', 'Озорнов Виктор Елисеевич', 'Приходилкин Саврас Макосинович', 'Зоотопин Барат Евпатович', 'Морро Альберт Проходимович', 'Карпатенко Салейман Ибрагимович', 'Пантелейпомойкин Линдсер Гарибальдович', 'Шыстакойкин Маврос Павлович', 'Шубин-Барбосовых Афанасий Узкобрюкович', 'Не-Желей-Меняйло Аббат Самсонович', 'Баскетбол Вельвет Однобратович', 'Мокасинов Павел Валерьевич', 'Постебайло Урам Юсупович', 'Жекадим Балбес Ясаулович', 'Кабыздох Шансон Петрович', "Каптеркин Станислав Домкратович"],

				'address': ['Подмостский проезд, дом 702', 'Поспелкина Куча, строение сто семнадцать', 'Вырвиглазные ухабищи, поселок третий, дом второй','Пухова опушка, 43','Селедкина круча, 1000','Проезд долгих ночей, дом 800, подъезд отсутствует','Парадное авеню, дом 2','Улица Арестантов дела о пропавших скребках, дом 33','Заречинский трамвайный проезд дом 7320','Блюхеровский помост 100000','Космических героев десантников дом 1','Начального уравнения дом x','Пуставлово шоссе дом 500','Конекрадов проспект 32х', 'ул. Горизонтальная с подъемом 10', 'Продажных полецейских дом 2', 'Гаражное строение дом 2', 'Площадь Ювелира Побриткина дом 7', 'Каслрок, владение 2', 'Лапоголиков 2', 'Семеновские начнинания дом 404/3']
			};
	
		function randomElement(arr) {
			var l = arr.length;
			return arr[(Math.floor(l * Math.random()))];
		}

		function numericString() {
		//	48-57
			var length = 3 + Math.floor(7 * Math.random()),
				str = [], code;

			for (var i = 0; i < length; ++i) {
				code = 48 + Math.floor(9 * Math.random());
				str.push(String.fromCharCode(code));
			}
			
			return str.join('');
		}

		function rndString(length) {
		//	97-122
			var latinRange = 122 - 97,
				length = Math.floor(3 * Math.random()) + 3,
				str = [], code;
	
			for (var i = 0; i < length; ++i) {
				code = 97 + Math.floor(latinRange * Math.random());
				str.push(String.fromCharCode(code));
			}
	
			return str.join('');
		}

		function fillText(element) {
			var type = element.getAttribute('data-thx-format'),
				fstZone = ['ru', 'de', 'com', 'org', 'net', 'by', 'io', 'su', 'gov', 'рф'];

			if (!type) {
				return;
			}

			switch (type) {
				case 'randnumber':
					element.value = numericString();	
					break;
				case 'email':
					element.value = rndString() + '@' + rndString() + '.'
					+ randomElement(fstZone);
					break;
				case 'website':
					element.value = 'www.' + rndString() + '.' + randomElement(fstZone) + '/clients/business-page';
					break;
				default:
					element.value = randomElement(config[type]);
			}
			
		}

		function fillRadio(elements) {
			var el = randomElement(elements);
			if (!el.disabled) { 
				randomElement(elements).checked = true;
			}
		}

		function fillCheckbox(elements) {
			var len = Math.floor(elements.length * Math.random()),
				el = randomElement(elements);

			if (el.disabled) {
				return;
			}
			
			for (var i = 0; i < len; ++i) {
				randomElement(elements).checked = randomElement([true, false]);
			}
		}

		par.innerText = "Обнаружена форма, заполнить ее случайными значениями? (при повторном нажатии значения будут сгенерированы заново)";

		button.id = 'fill_button';
		button.innerText = 'Заполнить';

		div.style = style.join(';');
		div.append(par);
		div.append(button);
		
		// let's rock babe
		for (i = 0, n = inputs.length; i < n; ++i) {
			var tp = inputs[i].type,
				nm = inputs[i].name;

			switch (tp) {
				case 'textarea':
				case 'text':
					formElements[nm] = inputs[i];
					break;

				case 'radio':
				case 'checkbox':
					if (formElements[nm] === undefined) {
						formElements[nm] = [tp];
					}

					formElements[nm].push(inputs[i]);
					break;

				case 'select':
					break;
			}
		}

		if (inputs.length) {
			document.body.append(div);

			$('#fill_button').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();

				for (var name in formElements) {
					if (formElements[name].nodeName != undefined) {
						fillText(formElements[name]);
					} else {
						if (formElements[name][0] == 'radio') {

							fillRadio(formElements[name]);	

						} else if (formElements[name][0] == 'checkbox') {

							fillCheckbox(formElements[name]);	
						}
					}
				}
			});
		}
	}
});
