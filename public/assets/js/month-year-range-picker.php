<?php
header('Content-Type: text/javascript');

if (empty($startYear) || empty($startMonth) || empty($endYear) || empty($endDate)) {

  $startYear = date("Y", strtotime("-1 year"));
  $startMonth = date('m', strtotime('+1 month', strtotime('-1 year')));
  $endYear = date('Y');
  $endMonth = date('m');

  $startDate = date('Y-m', strtotime('+1 month', strtotime('-1 year')));
  $endDate = date('Y-m');
}


?>

var MONTHS = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

    $(function () {
      startMonth = <?php echo $startMonth; ?>;
      startYear = <?php echo $startYear; ?>;
      endMonth = <?php echo $endMonth ?>;
      endYear = <?php echo $endYear ?>;
      fiscalMonth = 7;
      if(startMonth < 10){
        startDate = parseInt("" + startYear + '0' + startMonth + "");
      } else{
        startDate = parseInt("" + startYear  + startMonth + "");
      }
        
      if(endMonth < 10){
        endDate = parseInt("" + endYear + '0' + endMonth + "");
      } else{
        endDate = parseInt("" + endYear + endMonth + "");
      }
        

      content = '<div class="row mpr-calendarholder">';
      calendarCount = endYear - startYear;
      if(calendarCount == 0)
        calendarCount++;
      var d = new Date();
      for(y = 0; y < 2; y++){
        content += '<div class="col-xs-6" ><div class="mpr-calendar row" id="mpr-calendar-' + (y+1) + '">'
                   + '<h5 class="col-xs-12"><i class="mpr-yeardown fa fa-chevron-circle-left"></i><span>' 
                   + (startYear + y).toString() 
                   + '</span><i class="mpr-yearup fa fa-chevron-circle-right"></i></h5><div class="mpr-monthsContainer"><div class="mpr-MonthsWrapper">';
        for(m=0; m < 12; m++){
          var monthval;
          if((m+1) < 10){
            monthval = "0" + (m+1);
          } else {
            monthval = "" + (m+1);
          }
          content += '<span data-month="' + monthval  + '" class="col-xs-3 mpr-month">' + MONTHS[m] + '</span>';
        }
        content += '</div></div></div></div>';
      }
      content += '<div class="col-xs-1">';
      //content += '<button class="btn btn-info mpr-fiscal-ytd">Fiscal YTD</button>';
      //content += '<button class="btn btn-info mpr-ytd">YTD</button>';
      //content += '<button class="btn btn-info mpr-prev-fiscal">Previous FY</button>';
      //content += '<button class="btn btn-info mpr-prev-year">Previous Year</button>';
      content += '<button class="btn btn-info mpr-close">Apply</button>';
      content += '</div>';
      content += '</div>';

      $(document).on('click','.mpr-month',function(e){
        e.stopPropagation();
                    $month = $(this);
            var monthnum = $month.data('month');
            var year = $month.parents('.mpr-calendar').children('h5').children('span').html();
            if($month.parents('#mpr-calendar-1').size() > 0){
              //Start Date
              startDate = parseInt("" + year + monthnum);
              if(startDate > endDate){

                if(year != parseInt(endDate/100))
                  $('.mpr-calendar:last h5 span').html(year);
                   endDate = startDate;
              }
            }else{
              //End Date
              endDate = parseInt("" + year + monthnum);
              if(startDate > endDate){
                if(year != parseInt(startDate/100))
                  $('.mpr-calendar:first h5 span').html(year);
                startDate = endDate;
              }
            }

            paintMonths();
      });


      $(document).on('click','.mpr-yearup',function(e){
            $('.mpr-month').css("color","black");
            e.stopPropagation();
                    var year = parseInt($(this).prev().html());
            year++;
            $(this).prev().html(""+year);
                    $(this).parents('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175,function(){
            paintMonths();
            $(this).parents('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
          });
      });

      $(document).on('click','.mpr-yeardown',function(e){
            $('.mpr-month').css("color","black");
            e.stopPropagation();
                    var year = parseInt($(this).next().html());
            year--;
            $(this).next().html(""+year);
                    //paintMonths();
          $(this).parents('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175,function(){
            paintMonths();
            $(this).parents('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
          });
      });

      $(document).on('click','.mpr-ytd', function(e){
        e.stopPropagation();
        var d = new Date();
        startDate = parseInt(d.getFullYear() + "01");
        var month = d.getMonth() + 1;
        if(month < 9)
          month = "0" + month;
        endDate = parseInt("" + d.getFullYear() + month);
        $('.mpr-calendar').each(function(){
          var $cal = $(this);
          var year = $('h5 span',$cal).html(d.getFullYear());
        });
        $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175,function(){
            paintMonths();
            $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
        });
      });

      $(document).on('click','.mpr-prev-year', function(e){
        e.stopPropagation();
        var d = new Date();
        var year = d.getFullYear()-1;
        startDate = parseInt(year + "01");
        endDate = parseInt(year + "12");
        $('.mpr-calendar').each(function(){
          var $cal = $(this);
          $('h5 span',$cal).html(year);
        });
        $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175,function(){
            paintMonths();
            $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
        });
      });

      $(document).on('click','.mpr-fiscal-ytd', function(e){
        e.stopPropagation();
        var d = new Date();
        var year;
        if((d.getMonth()+1) < fiscalMonth)
          year = d.getFullYear() - 1;
        else
          year = d.getFullYear();
        if(fiscalMonth < 10)
          fm = "0" + fiscalMonth;
        else
          fm = fiscalMonth;
        if(d.getMonth()+1 < 10)
          cm = "0" + (d.getMonth()+1);
        else
          cm = (d.getMonth()+1);
        startDate = parseInt("" + year + fm);
        endDate = parseInt("" + d.getFullYear() + cm);
        $('.mpr-calendar').each(function(i){
          var $cal = $(this);
          if(i == 0)
            $('h5 span',$cal).html(year);
          else
            $('h5 span',$cal).html(d.getFullYear());
        });
        $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175,function(){
            paintMonths();
            $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
        });
      });

      $(document).on('click','.mpr-prev-fiscal', function(){
        var d = new Date();
        var year;
        if((d.getMonth()+1) < fiscalMonth)
          year = d.getFullYear() - 2;
        else
          year = d.getFullYear() - 1;
        if(fiscalMonth < 10)
          fm = "0" + fiscalMonth;
        else
          fm = fiscalMonth;
        if(fiscalMonth -1 < 10)
          efm = "0" + (fiscalMonth-1);
        else
          efm = (fiscalMonth-1);
        startDate = parseInt("" + year + fm);
        endDate = parseInt("" + (d.getFullYear() - 1) + efm);
        $('.mpr-calendar').each(function(i){
          var $cal = $(this);
          if(i == 0)
            $('h5 span',$cal).html(year);
          else
            $('h5 span',$cal).html(d.getFullYear()-1);
        });
        $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175,function(){
            paintMonths();
            $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
        });
      });

      var mprVisible = false;
      var mprpopover = $('.mrp-container').popover({
        container: "body",
        placement: "bottom",
        html: true,
        content: content
      }).on('show.bs.popover', function () {
        $('.popover').remove();
        var waiter = setInterval(function(){
          if($('.popover').size() > 0){
            clearInterval(waiter);
            setViewToCurrentYears();
                    paintMonths();
          }
        },50);
      }).on('shown.bs.popover', function(){
        mprVisible = true;
      }).on('hidden.bs.popover', function(){
        mprVisible = false;
      });

      $(document).on('click','.mpr-calendarholder',function(e){
        e.preventDefault();
        e.stopPropagation();
      });
      $(document).on("click",".mrp-container",function(e){
        if(mprVisible){
          e.preventDefault();
            e.stopPropagation();
          mprVisible = false;
        }
      });

      $(document).on("click",function(e){

        if(mprVisible){
            $('.mpr-calendarholder').parents('.popover').fadeOut(200,function(){
            $('.mpr-calendarholder').parents('.popover').remove();
            $('.mrp-container').trigger('click');
          });
          mprVisible = false;
        }
      });

      $(document).on('click','.mpr-close', function(e){
        //console.log(e);
        if(mprVisible){
            $('.mpr-calendarholder').parents('.popover').fadeOut(200,function(){
            $('.mpr-calendarholder').parents('.popover').remove();
            $('.mrp-container').trigger('click');
          });
          mprVisible = false;
        }
      });

    });

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