
function setViewToCurrentYears(){
  	var startyear = parseInt(startDate / 100);
    var endyear = parseInt(endDate / 100);
  	$('.mpr-calendar h5 span').eq(0).html(startyear);
  	$('.mpr-calendar h5 span').eq(1).html(endyear);
}

function paintMonths(){
    $('.mpr-calendar').each(function(){
      var $cal = $(this);
      var year = $('h5 span',$cal).html();
      $('.mpr-month',$cal).each(function(i){
        if((i+1) > 9)
          cDate = parseInt("" + year + (i+1));
        else
          cDate = parseInt("" + year+ '0' + (i+1));
        if(cDate >= startDate && cDate <= endDate){
            $(this).addClass('mpr-selected');
        }else{
          $(this).removeClass('mpr-selected');
        }
      });
    });
    
  $('.mpr-calendar .mpr-month').css("background","");
    //Write Text
    var startyear = parseInt(startDate / 100);
    var startmonth = parseInt(safeRound((startDate / 100 - startyear)) * 100);
    var endyear = parseInt(endDate / 100);
    var endmonth = parseInt(safeRound((endDate / 100 - endyear)) * 100);
    $('.mrp-monthdisplay .mrp-lowerMonth').html(MONTHS[startmonth - 1] + " " + startyear);
    $('.mrp-monthdisplay .mrp-upperMonth').html(MONTHS[endmonth - 1] + " " + endyear);
    //$('#mrp-lowerDate').val(startDate);
    //$('#mrp-upperDate').val(endDate);
    
    if(startmonth < 10)
  	startmonth = '0' +startmonth;
    else
    startmonth = startmonth;
    
    if(endmonth < 10){
    endmonth = '0' +endmonth;    
    }
    else{
    endmonth = endmonth;    
    }
     $('#mrp-lowerDate').val(startyear+'-'+startmonth);
    $('#mrp-upperDate').val(endyear+'-'+endmonth);
   
    
  	if(startyear == parseInt($('.mpr-calendar:first h5 span').html()))
  		//$('.mpr-calendar:first .mpr-selected:first').css("background","#40667A");
        
         $('.mpr-month').css("color","black");
    if(endyear == parseInt($('.mpr-calendar:last h5 span').html()))
      //$('.mpr-calendar:last .mpr-selected:last').css("background","#40667A");
      $('.mpr-month').css("color","black");
      $('.mpr-calendar:first .mpr-selected:first').css({"background-color": "#40667A","color":"#fff"});
      $('.mpr-calendar:last .mpr-selected:last').css({"background-color": "#40667A","color":"#fff"});
      
  }

  function safeRound(val){
    return Math.round(((val)+ 0.00001) * 100) / 100;
  }
  
