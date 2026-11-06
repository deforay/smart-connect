// Each stage averages only the samples with valid dates for that stage, so the
// stacked bar height is not the turnaround time. Read the real total from the table.
function getTATChartTotals(idTable) {
  var totals = [];
  for (var i = 0; i < $("#" + idTable + " tbody tr").length; i++) {
    totals[i] = $("#" + idTable).find("#td-" + i + "-4").text();
  }
  return totals;
}
