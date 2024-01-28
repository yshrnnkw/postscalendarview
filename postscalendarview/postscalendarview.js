jQuery(function ($) {
	const ro = new ResizeObserver(function (entries, observer) {

		$(entries).each(function (index, element) {
			const tgt = $(element.target);
			const breakpoint = tgt.attr('data-breakpoint');
			const w = element.contentRect.width;
			const h = element.contentRect.height;

			if (w < breakpoint) {
				tgt.removeClass('grid').addClass('list');
				tgt.find('.pcve').removeAttr('style');
			} else {
				tgt.removeClass('list').addClass('grid');
				drawBand(tgt);
			}
		});
	});


	$('.postscalendarview').each(function(index, element) {

		if ($(element).hasClass('grid')) {
			ro.observe(element);
		}


		// navigation --------------------------------------------------------------
		if ($(element).find('.navi').length > 0) {
			$(element).on('click', '.navi a', function () {
				jQuery.get(
					postscalendarview.xhr_url,	// from wp_localize_script
					{
						target: $(this).attr('data-target'),
						config: $(element).attr('data-config')
					},
					function (data, status, obj) {
						$(element).html(data);
						drawBand(element);
					}
				);
			});
		}


		// display detial on tiny --------------------------------------------------
		if ($(element).hasClass('dispdetail')) {
			$(element).on('click', '.day.have_event', function () {
				const detailarea = $(element).find('.detail');

				detailarea.empty();
				$('.selected').removeClass('selected');
				$(this).addClass('selected').find('.pcve').clone()
					.appendTo(detailarea).css('visibility', 'visible');
			});
		}
	});

	// 複数日にまたがるイベントをバー表示 --------------------------------------
	// 週ごとに処理する
	function drawBand(element) {
		if (! $(element).hasClass('dispband')) {
			return;
		}

		$(element).find('.week').each(function () {
			const week = $(this);
			let repeat_list = [];
			let repeat_idxlist = [];

			// 「.repeat_first以外の.repeat」（A）に縦位置を揃えるダミーを追加
			week.find('.repeat:not(.repeat_first)').each(function (index, element) {
				const that = $(this);
				// Aより上にある.pcveのdata-idxを取得
				let current_siblings_idxlist = [];
				that.prevAll().each(function (index, element) {
					current_siblings_idxlist.push($(element).attr('data-idx'));
				});
				// Aと同じdata-idxで、週の最初の.pcve（B）を取得
				const current_repeatfirst = week.find('.repeat[data-idx=' + that.attr('data-idx') + ']').first();
				// Bより上にある.pcveのdata-idxを取得
				const siblings_idxlist = [];
				current_repeatfirst.prevAll().each(function (index, element) {
					siblings_idxlist.push($(element).attr('data-idx'));
				});
				// BにあってAにないもののダミーが必要なので差分をとる
				const diff_idxlist = siblings_idxlist.filter(function (_v) {
					return current_siblings_idxlist.indexOf(_v) == -1;
				});
				// Aより上にイベントがなく、かつ、Bより上にイベントがある場合（重なりがある場合）に、ダミーを追加
				$.each(diff_idxlist.reverse(), function (index, value) {
					const dummy = $('<div class="pcve dummy"></div>');
					dummy.attr('data-idx', value);
					that.before(dummy);
				});
			});


			// 週の最初の.pcveをバンド化して、横幅を設定する
			// 複数日イベントのidxを取得する
			week.find('.repeat').each(function () {
				repeat_idxlist.push($(this).attr('data-idx'));
			});
			repeat_list = Array.from(new Set(repeat_idxlist));
			// 同じidxを持つ複数日イベントの最初と最後を確定し
			// まとめて表示するバンドの横幅を算出
			$.each(repeat_list, function (index, value) {
				const repeatfirst = week.find('.repeat[data-idx=' + value + ']').first();
				const repeatlast = week.find('.repeat[data-idx=' + value + ']:not(.dummy)').last();
				const band_width = repeatlast.offset().left - repeatfirst.offset().left + repeatlast.outerWidth();

				repeatfirst.addClass('band').css('width', band_width);
				if (repeatlast.hasClass('repeat_last')) {
					repeatfirst.css({
						'borderTopRightRadius': '6px',
						'borderBottomRightRadius': '6px'
					});
				}

				// 隠しているpcveの高さをバンドの高さに合わせる
				// pcveの文字数が多い場合に、bandより.repeatの高さが高くなることがある
				// このとき、バンドの下側にムダなアキがでるので、強制的に高さをバンドに合わせる
				week.find('.repeat[data-idx=' + value + ']:not(.repeat_first), .dummy[data-idx=' + value + ']')
					.css({
						'height': repeatfirst.outerHeight(false) + 'px'
					});
			});
		});
	}
});
