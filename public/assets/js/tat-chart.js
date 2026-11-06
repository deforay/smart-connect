// Each stage averages only the samples with valid dates for that stage, so the
// stacked bar height is not the turnaround time. Read the real total from the table.
function getTATChartTotals(idTable) {
  var totals = [];
  for (var i = 0; i < $("#" + idTable + " tbody tr").length; i++) {
    totals[i] = $("#" + idTable).find("#td-" + i + "-4").text();
  }
  return totals;
}

// Draws the stacked turnaround-time chart for the table idTable.
// labels holds the translated tatInDays and total strings. If onPointClick is
// null, the bars are not clickable.
function drawTATChart(idTable, idChart, chartTitle, series, labels, onPointClick) {
  var tatTotals = getTATChartTotals(idTable);
  $('#' + idChart).highcharts({
    chart: {
      type: 'column'
    },
    title: {
      text: ''
    },
    exporting: {
      chartOptions: {
        subtitle: {
          text: chartTitle
        }
      },
      width: 2000
    },
    credits: {
      enabled: false
    },
    xAxis: {
      categories: getTATChartData(idTable)
    },
    yAxis: {
      min: 0,
      title: {
        text: labels.tatInDays
      },
      stackLabels: {
        enabled: true,
        formatter: function() {
          return tatTotals[this.x];
        },
        style: {
          fontWeight: 'bold',
          color: (Highcharts.theme && Highcharts.theme.textColor) || 'gray'
        }
      }
    },
    legend: {
      align: 'center',
      x: 0,
      verticalAlign: 'top',
      y: 0,
      floating: true,
      backgroundColor: (Highcharts.theme && Highcharts.theme.background2) || 'white',
      borderColor: '#CCC',
      borderWidth: 1,
      shadow: false
    },
    tooltip: {
      headerFormat: '<b>{point.x}</b><br/>',
      pointFormatter: function() {
        return this.series.name + ': ' + this.y + '<br/>' + labels.total + ': ' + tatTotals[this.index];
      }
    },
    plotOptions: {
      column: {
        stacking: 'normal',
        dataLabels: {
          enabled: true,
          color: (Highcharts.theme && Highcharts.theme.dataLabelsColor) || 'white'
        },
        cursor: onPointClick ? 'pointer' : 'default',
        point: {
          events: onPointClick ? { click: onPointClick } : {}
        }
      }
    },
    series: series
  });
}
