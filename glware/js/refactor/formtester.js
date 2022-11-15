$(function () {
  if ($("form.request_form").length != 0) {
    var div = document.createElement("div"),
      style = [
        "background-color: #aac",
        "position: fixed",
        "top: 0",
        "right: 0",
        "width: 300px",
        "padding: 10px",
      ],
      button = document.createElement("button"),
      par = document.createElement("p");


    (inputs = $("form.request_form input").add("form.request_form select")),
      (formElements = {});

	var url = "/third_party/glware/ajhandler.php",
		config = {
		html: {
			notifyDiv: div,
			button: button,
			paragraph: par,	
			style: style
		},

		inputs: inputs,
		fields_from_rus: {
			"Название организации": "org_name",
			"Юридическое наименование": "org_legal_name",
			"Форма собственности": "org_ownertype",
			"Город": "org_city",
			"Телефонный код города": "phone_code",
			"Основной телефон": "phone_main",
			"Другие телефоны": "phone_others",
			"Контактное лицо": "person_contact",
			"Должность": "person_function",
			"E-mail для связи": "person_emil",
			"Сайт компании": "org_website",
			"Директор компании": "org_director",
			"Юридический адрес": "org_address_legal",
			"Почтовый адрес": "org_address_post",
			"География деятельности": "org_geo_type",
			"Выбранный регион": "org_geo_region",
			"Участие в номинациях": "nominant",
			"Дополнительная информация": "nominant_info",
			"сеть года: название сети": "nominant_network_name",
			"сеть года: тип": "nominant_network_type",
			"дебют года: тип": "nominant_debut_type",
			"маркетинговый проект года: тип": "nominant_marketnetwork_type",
			"рекламный макет года: тип": "nominant_advertising_type"
		}
	};

	var testConfig = {
      orgname: [
        "Вислоухие коромысла",
        "Почтенные следопыты Сибири",
        "Зонирование всей семьей",
        "Параходство Екатеринбургских окрестностей",
        "Оловянные солдатики Неверляндии",
        "Стройбытпромкусь",
        "Застроймыш",
        "Инфарма",
        "Зодчество Краснодара",
        "Пельменъ",
        "Калейдоскопов сотоварищи",
        "Янтарный мир 2D",
        "Щукинский молотильщик",
        "Промышленный репортер",
        "Фрикаделькин",
        "DEFF",
        "MYSHKin",
        "BAttt",
        "Издательский дом Пропеллер",
        "Вилки и ложки",
        "Четыре лапки",
        "Хвост коромыслом",
        "Севастопольский краевед",
      ],

      orglegalname: [
        "ПостОптика",
        "Оптические сплавы",
        "ОктоОптик",
        "Сведровлский завод оптики",
        "ОптОпт",
        "ЧерноОптика",
        "СтройОптЗавод",
        "Инфарма",
      ],

      orgtype: ["ООО", "ЗАО", "ОАО", "ЫЫЫ", "ИП", "ОППА", "ЖЖЖ"],

      city: [
        "Зареченск",
        "Мытищи",
        "Лавандово",
        "Гипернакулус",
        "Выпильсбург",
        "Трофаретинск",
        "Железногорск",
        "Пыльносбруево",
        "Скитлбич",
        "Паранормальнинск",
        "Охламоничи",
        "Нагайкина Выпь",
        "Кострюлинск",
        "Долгие мымры",
        "Шмельный",
      ],

      job: [
        "Собиратель ремесел",
        "Каскадер на полставки",
        "Лужевытератель",
        "опретаор шредера",
        "колесничий",
        "рикша",
        "старший замешиватель",
        "Разметчик",
        "заместитель робота",
        "Плакальщик",
        "исполнитель на флейте водосточных труб",
        "стажер",
        "шпион",
        "Актер больших и малых академических театров",
        "зачинщик беспорядков",
        "Учетчик-приходчик",
        "налетчик",
        "фарцовщик",
        "оператор калькулятора",
        "смотрящий за смотрителем",
        "контролер пробивных работ",
        "ответственный за тусовки",
        "автор потдельных писем читателей",
        "вычислитель",
        "полицейский в отставке",
      ],

      name: [
        "Трофим Лапоправивеч",
        "Константин Pоботович Гаромыкин",
        "Озорнов Виктор Елисеевич",
        "Приходилкин Саврас Макосинович",
        "Зоотопин Барат Евпатович",
        "Морро Альберт Проходимович",
        "Карпатенко Салейман Ибрагимович",
        "Пантелейпомойкин Линдсер Гарибальдович",
        "Шыстакойкин Маврос Павлович",
        "Шубин-Барбосовых Афанасий Узкобрюкович",
        "Не-Желей-Меняйло Аббат Самсонович",
        "Баскетбол Вельвет Однобратович",
        "Мокасинов Павел Валерьевич",
        "Постебайло Урам Юсупович",
        "Жекадим Балбес Ясаулович",
        "Кабыздох Шансон Петрович",
        "Каптеркин Станислав Домкратович",
      ],

      address: [
        "Подмостский проезд, дом 702",
        "Поспелкина Куча, строение сто семнадцать",
        "Вырвиглазные ухабищи, поселок третий, дом второй",
        "Пухова опушка, 43",
        "Селедкина круча, 1000",
        "Проезд долгих ночей, дом 800, подъезд отсутствует",
        "Парадное авеню, дом 2",
        "Улица Арестантов дела о пропавших скребках, дом 33",
        "Заречинский трамвайный проезд дом 7320",
        "Блюхеровский помост 100000",
        "Космических героев десантников дом 1",
        "Начального уравнения дом x",
        "Пуставлово шоссе дом 500",
        "Конекрадов проспект 32х",
        "ул. Горизонтальная с подъемом 10",
        "Продажных полецейских дом 2",
        "Гаражное строение дом 2",
        "Площадь Ювелира Побриткина дом 7",
        "Каслрок, владение 2",
        "Лапоголиков 2",
        "Семеновские начнинания дом 404/3",
      ],
    };
	
    function randomElement(arr) {
      var l = arr.length;
      return arr[Math.floor(l * Math.random())];
    }

    function numericString() {
      //	48-57
      var length = 3 + Math.floor(7 * Math.random()),
        str = [],
        code;

      for (var i = 0; i < length; ++i) {
        code = 48 + Math.floor(9 * Math.random());
        str.push(String.fromCharCode(code));
      }

      return str.join("");
    }

    function rndString(length) {
      //	97-122
      var latinRange = 122 - 97,
        length = Math.floor(3 * Math.random()) + 3,
        str = [],
        code;

      for (var i = 0; i < length; ++i) {
        code = 97 + Math.floor(latinRange * Math.random());
        str.push(String.fromCharCode(code));
      }

      return str.join("");
    }

    function fillText(element) {
      var type = element.getAttribute("data-thx-format"),
        fstZone = [
          "ru",
          "de",
          "com",
          "org",
          "net",
          "by",
          "io",
          "su",
          "gov",
          "рф",
        ];

      if (!type) {
        return;
      }

      switch (type) {
        case "randnumber":
          element.value = numericString();
          break;
        case "email":
          element.value =
            rndString() + "@" + rndString() + "." + randomElement(fstZone);
          break;
        case "website":
          element.value =
            "www." +
            rndString() +
            "." +
            randomElement(fstZone) +
            "/clients/business-page";
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

	function gatherFormElements(conf) {
		var tp, nm, inputs,
			formElements = {};

		inputs = conf.inputs;
				
		// let's rock babe
		for (i = 0, n = inputs.length; i < n; ++i) {
		  var tp = inputs[i].type,
			nm = inputs[i].name;

		  switch (tp) {
			case "textarea":
			case "text":
			  formElements[nm] = inputs[i];
			  break;

			case "radio":
			case "checkbox":
			  if (formElements[nm] === undefined) {
				formElements[nm] = [tp];
			  }

			  formElements[nm].push(inputs[i]);
			  break;

			case "select":
			  break;
		  }
		}

		return formElements;
	}


	function runTestFiller(conf) {
		function createNotify(html, elements) {
			// condition for test values filler appearing
			// FUTURE: if values is empty and test mode switched on - button appears
			var par, button, div, style, nm, tp;

			par = html.paragraph;
			button = html.button;
			div = html.notifydiv;
			style = html.style;
			
			par.innerText =
			  "Обнаружена форма, заполнить ее случайными значениями? (при повторном нажатии значения будут сгенерированы заново)";

			button.id = "fill_button";
			button.innerText = "заполнить";

			div.style = style.join(";");
			div.append(par);
			div.append(button);

			document.body.append(div);

		  $("#fill_button").on("click", function (e) {
			e.preventDefault();
			e.stopPropagation();

			for (var name in formElements) {
			  if (formElements[name].nodeName != undefined) {
				fillText(formElements[name]);
			  } else {
				if (formElements[name][0] == "radio") {
				  fillRadio(formElements[name]);
				} else if (formElements[name][0] == "checkbox") {
				  fillCheckbox(formElements[name]);
				}
			  }
			}
		  });
		}

		if (conf.inputs.length) {
			createNotify(conf.html, gatherFormElements(conf));
		}
		
	}

	function runOldRequestLoader(conf) {
		function separateFiles(set) {
			var updatedSet = set;
			if (set.form.pic != undefined) {
				delete updatedSet.form.pic;
			}
	
			if (set.form.press_release != undefined) {
				delete updatedSet.form.press_release;
			}
	
			if (set.form.logo != undefined) {
				delete updatedSet.form.logo;
			}
		
			if (set.form.dir_photo != undefined) {
				delete updatedSet.form.dir_photo;
			}
	
			return updatedSet;
		}

		function getRequestJSON(callback, conf) {
			var obj,
				config = conf;

			$.post(url, {glw_action: "get_request_json"}, function(resp) {
				var r;

				try {
					r = JSON.parse(resp);
				} catch (e) {
					r = {};
				}

				if (r.success) {
					r = separateFiles(r);
					callback(gatherFormElements(config), r);
					setupPreloadedPics(r.previews);
				}
			});
		}

		function notEmpty(arr) {
			var counter = 0;
			for (var name in arr) {
				counter++;
			}
			return counter > 0;
		}

		function setupPreloadedPics(previews) {
			var galLoader = false,
				loader = false,
				galPics = [];

			if (previews != undefined && notEmpty(previews.gallery)) {
				galLoader = window.getPicloader("gallery");

				for (var i = 0, n = previews.gallery.length; i < n; ++i) {
					galPics.push(previews.gallery[i]);
				}

				galLoader.store.add(galPics);
				delete previews.gallery;
			}

			for (var ftype in previews) {
				try {
					loader = window.getPicloader(ftype);
					loader.store.add(previews[ftype]);
				} catch (e) {
					console.log("File loaded by tag " + ftype + " not found!", e);
				}
			}

		//	presentationLoader = window.getPicloader("presentation");	
	//		presentationLoader.store.add(previews.presentation);
//			pressLoader = window.getPicloader("press");	
//			pressLoader.store.add(previews.pressrelease);

//			logoLoader = window.getPicloader("logo");	
//j			logoLoader.store.add(previews.logo);
//			photoLoader = window.getPicloader("photo");	
//			photoLoader.store.add(previews.photo);

//			insertbuttonandpic(window.getpicloader("logo"));
//			insertbuttonandpic(window.getpicloader("press"));
//			insertbuttonandpic(window.getpicloader("photo"));
			
			//presentation.controls.clicker.first().parent()[0]..nextsibling.insertbefore();
			//presentation.changestate();
//			gtg
			//logo.changestate();
			//press.changestate();
			//photo.changestate();
			//
			//
			window.getPicloader();
		}


		function fillInForm(elements, dataset) {
			var formName = ''
				val = '',
				form = dataset.form;

			for (var name in form) {
				formName = config.fields_from_rus[name];

				if (formName == undefined || form[name] == '') {
					continue;
				}
		
				if (typeof(form[name]) == "string") {
					//console.log("filling form, current name: ", name, formName);
					if (elements[formName] !== undefined) {
						elements[formName].value = form[name];
					}
				} else if (form[name].length == 1) {
					$("input[value^=\"" + form[name] + "\"]").click();
//					console.log(name, formName, form[name].length);
				} else if (form[name].length > 1) {
					for (var i = 0, n = form[name].length; i < n; ++i) {
						$("input[value^=\"" + form[name][i] + "\"]").click();
					}
//					console.log(name, formName, form[name].length);

				}
			}
			
		}
		
		//console.log("conf", conf);
		getRequestJSON(fillInForm, conf);

	}


	if (window.loadManager !== undefined) {
		var loadMgr;

		loadMgr = window.loadManager;
	}

	var initLoadAssistant = function() {
		loadMgr.handle({operation: "DOWNLOAD_PROFILE", object: false});

		$.ajax({
			async: false,
			url: url,
			data: {glw_action: "init_download"},
			success: function(resp) {
				var r;

				try {
					r = JSON.parse(resp);
				} catch (e) {
					r = {};
				}

				if (r.success && loadMgr) {
					loadMgr.setInitialQueue(r.queue);
					loadMgr.sync(r.loadid, r.operation_type);
				} else {
					loadMgr.closePopup();
				}
			}
		});
	}

	initLoadAssistant();

//	runTestFiller(config);
	runOldRequestLoader(config);

  }
});
