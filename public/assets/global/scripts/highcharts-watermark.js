(function (H) {
	H.Chart.prototype.callbacks.push(function (chart) {
		var opt = chart.options.watermark;
		if (!opt || !opt.text) return;
		opt = $.extend({}, {opacity: 0.5, top: false}, opt);
		chart.watermark = chart.renderer.text(opt.text, (chart.plotBox.width - opt.width)/2 + chart.plotBox.x, (chart.plotBox.height - opt.height)/2 + chart.plotBox.y, opt.width, opt.height).attr({rotation: -25}).css({opacity: opt.opacity,color: '#999999',fontFamily:'Helvetica',fontSize: '22px'}).add();
		chart.watermark.toFront();
		$(chart).on('redraw', function() {
		      chart.watermark.attr({});
		});
	});
}(Highcharts));