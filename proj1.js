$(document).ready(function ()
{
$('input:text').bind("mouseover keyup",val);
function val()
{
var cur=$(this);
cur.next('p').remove();	

if(cur.hasClass('require'))
	{
		
			if($.trim(cur.val())=='')
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  *To pole jest wymagane</p>');
				cur.data('valid',false);
			}
			else if(cur.val().length<2)
			{
				cur.after('<p style="display:inline;">  *Podana nazwa jest za krótka</p>');
				cur.data('valid',false);
			}
			else
			{
				cur.data('valid',true);
			}
	}
if(cur.hasClass('number'))
	{
		if(isNaN(cur.val()))
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  *To pole nie jest liczba</p>');
				cur.data('valid',false);
			}
		else
			{
				cur.data('valid',true);
			}
	}

	
		$('#oblicz').click(function(){
			if($('#time').val()=="14 day's")
				{
					var first;
					var rate;
					var fee;
					first=$('#amount').val();
					first=parseFloat(first);
					rate=first*0.01;
					fee=first*0.001;
					rate=parseFloat(rate);
					fee=parseFloat(fee);
					rate=rate.toFixed(2);
					fee=fee.toFixed(2);				
					var date=$('#time').val();
					var amount;
					amount=parseFloat(rate)+parseFloat(fee);
					amount=amount.toFixed(2);
					var total;
					total=parseFloat(first)+parseFloat(rate)+parseFloat(fee);
					total=total.toFixed(2);
					$('#result').html('Your loan: ' + first +'<br>Rate of loan: ' 
					+ rate +'<br>Fee for loan: ' + fee 
					+ '<br> Length of loan: ' + date + '<br> Total amount: ' + amount 
					+ '<br> Total loan cost: ' + total);
					
					
				}
			else if($('#time').val()=="30 day's")
				{
					var first;
					var rate;
					var fee;
					first=$('#amount').val();
					first=parseFloat(first);
					rate=first*0.012;
					fee=first*0.0012;
					rate=parseFloat(rate);
					fee=parseFloat(fee);
					rate=rate.toFixed(2);
					fee=fee.toFixed(2);				
					var date=$('#time').val();
					var amount;
					amount=parseFloat(rate)+parseFloat(fee);
					amount=amount.toFixed(2);
					var total;
					total=parseFloat(first)+parseFloat(rate)+parseFloat(fee);
					total=total.toFixed(2);
					$('#result').html('Your loan: ' + first +'<br>Rate of loan: ' 
					+ rate +'<br>Fee for loan: ' + fee 
					+ '<br> Length of loan: ' + date + '<br> Total amount: ' + amount 
					+ '<br> Total loan cost: ' + total)
				}
			else if($('#time').val()=="2 month's")
				{
					var first;
					var rate;
					var fee;
					first=$('#amount').val();
					first=parseFloat(first);
					rate=first*0.02;
					fee=first*0.002;
					rate=parseFloat(rate);
					fee=parseFloat(fee);
					rate=rate.toFixed(2);
					fee=fee.toFixed(2);				
					var date=$('#time').val();
					var amount;
					amount=parseFloat(rate)+parseFloat(fee);
					amount=amount.toFixed(2);
					var total;
					total=parseFloat(first)+parseFloat(rate)+parseFloat(fee);
					total=total.toFixed(2);
					$('#result').html('Your loan: ' + first +'	<br>Rate of loan: ' 
					+ rate +'<br>Fee for loan: ' + fee 
					+ '<br> Length of loan: ' + date + '<br> Total amount: ' + amount 
					+ '<br> Total loan cost: ' + total)
				}
			else if($('#time').val()=="3 month's")
				{
					var first;
					var rate;
					var fee;
					first=$('#amount').val();
					first=parseFloat(first);
					rate=first*0.025;
					fee=first*0.0025;
					rate=parseFloat(rate);
					fee=parseFloat(fee);
					rate=rate.toFixed(2);
					fee=fee.toFixed(2);				
					var date=$('#time').val();
					var amount;
					amount=parseFloat(rate)+parseFloat(fee);
					amount=amount.toFixed(2);
					var total;
					total=parseFloat(first)+parseFloat(rate)+parseFloat(fee);
					total=total.toFixed(2);
					$('#result').html('Your loan: ' + first +'<br>Rate of loan: ' 
					+ rate +'<br>Fee for loan: ' + fee 
					+ '<br> Length of loan: ' + date + '<br> Total amount: ' + amount 
					+ '<br> Total loan cost: ' + total)
				}
			else if($('#time').val()=="6 month's")
				{
					var first;
					var rate;
					var fee;
					first=$('#amount').val();
					first=parseFloat(first);
					rate=first*0.035;
					fee=first*0.0035;
					rate=parseFloat(rate);
					fee=parseFloat(fee);
					rate=rate.toFixed(2);
					fee=fee.toFixed(2);				
					var date=$('#time').val();
					var amount;
					amount=parseFloat(rate)+parseFloat(fee);
					amount=amount.toFixed(2);
					var total;
					total=parseFloat(first)+parseFloat(rate)+parseFloat(fee);
					total=total.toFixed(2);
					$('#result').html('Your loan: ' + first +'<br>Rate of loan: ' 
					+ rate +'<br>Fee for loan: ' + fee 
					+ '<br> Length of loan: ' + date + '<br> Total amount: ' + amount 
					+ '<br> Total loan cost: ' + total)
				}
				else if($('#time').val()=="one year")
				{
					var first;
					var rate;
					var fee;
					first=$('#amount').val();
					first=parseFloat(first);
					rate=first*0.045;
					fee=first*0.0045;
					rate=parseFloat(rate);
					fee=parseFloat(fee);
					rate=rate.toFixed(2);
					fee=fee.toFixed(2);				
					var date=$('#time').val();
					var amount;
					amount=parseFloat(rate)+parseFloat(fee);
					amount=amount.toFixed(2);
					var total;
					total=parseFloat(first)+parseFloat(rate)+parseFloat(fee);
					total=total.toFixed(2);
					$('#result').html('Your loan: ' + first +'<br>Rate of loan: ' 
					+ rate +'<br>Fee for loan: ' + fee 
					+ '<br> Length of loan: ' + date + '<br> Total amount: ' + amount 
					+ '<br> Total loan cost: ' + total)
				
				}});//Koniec funkcji obliczajacej finanse
				
				//Walidacja drugiego kroku
if(cur.hasClass('post'))
	{
		if(cur.val().length<5)
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  * Post code is too short </p>');
				cur.data('valid',false);
			}
		else
			{
				cur.data('valid',true);
			}
	}
//Funkcje sprawdzajace trzeciego kroku	
		
if(cur.hasClass('address'))
	{
		var email=/^([a-z0-9_\.-]+)@([\da-z\.-]+)\.([a-z\.]{2,6})$/;
		if(!email.test(cur.val()))
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  * Put correct e-mail address </p>');
				cur.data('valid',false);
			}
		else if(cur.val()=='')
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  * Put Your e-mail address </p>');
				cur.data('valid',false);
			}
		else
			{
				cur.data('valid',true);
			}
	}
	
if(cur.hasClass('addressc'))
	{
		var email=/^([a-z0-9_\.-]+)@([\da-z\.-]+)\.([a-z\.]{2,6})$/;
		if(!email.test(cur.val()))
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  * Put correct e-mail address </p>');
				cur.data('valid',false);
			}
		else if($('#address').val()!=cur.val())
		{
			cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  * Put this same e-mail address  </p>');
				cur.data('valid',false);
			
		}
		else if(cur.val()=='')
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  * Put Your e-mail address </p>');
				cur.data('valid',false);
			}
		else
			{
				cur.data('valid',true);
			}
	}
if(cur.hasClass('nino'))
	{
		var email=/^([a-zA-Z]{2})([0-9]{10})$/;
		if(!email.test(cur.val()))
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  * Wrong NINo. </p>');
				cur.data('valid',false);
			}
		else if(cur.val()=='')
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  * Put NINo. </p>');
				cur.data('valid',false);
			}
		else
			{
				cur.data('valid',true);
			}
	}
if(cur.hasClass('dob'))
	{
		
		if(isNaN(cur.val()))
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  * It\'s not a number </p>');
				cur.data('valid',false);
			}
		else
			{
				cur.data('valid',true);
			}
	}
	
	if(cur.hasClass('incoming'))
	{
		
		if(isNaN(cur.val()))
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  * It\'s not a number </p>');
				cur.data('valid',false);
			}
		else if(cur.val()=='')
			{
				cur.after('<p style="display:inline; color:ORANGE; padding-left:5px;">  * Put Your monthly incoming </p>');
				cur.data('valid',false);
			}
		else
			{
				cur.data('valid',true);
			}
	}
//Koniec skryptu warunkowego!!!!!!!!!!!
	}
	

	
	//Funkcja sprawdzania klawisza dla pierwszego kroku
	$('#next1').click(function()
		{
			$('#info').html('');
			var data=true;
			$('.loan').each(function() {
                var current=$(this);
				if(current.data('valid')!=true)
					{
						data=false;
					}
					
				});
		
		if(data){
			$('#info').html('');
			$('#calc').hide('slow');
			$('#first').show('slow');
			
		}
		else
		$('#info').html('<div class="ui-widget"><div class="ui-state-highlight ui-corner-all" style="margin-top: 20px; padding: 0 .7em;"><p><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span><strong>Sorry!</strong> Please, correct Your data if You want to continue.</p></div>');
		});
		
//Funkcja sprawdzania klawisza dla drugiego kroku
	$('#next2').click(function()
		{
			$('#info2').html('');
			var data2=true;
			$('.require').each(function() {
                var current=$(this);
				if(current.data('valid')!=true)
					{
						data2=false;
					}
					
				});
				$('.post').each(function() {
                var current=$(this);
				if(current.data('valid')!=true)
					{
						data2=false;
					}
					
				});
		
		

		if(data2)
		{
				$('#info2').html('');
			$('#first').hide('slow');
			$('#second').show('slow');
		}
		else
			$('#info2').html('<div class="ui-widget"><div class="ui-state-highlight ui-corner-all" style="margin-top: 20px; padding: 0 .7em;"><p><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span><strong>Sorry!</strong> Please, correct Your data if You want to continue.</p></div>');
			
		});

//Funkcja sprawdzania klawisza dla trzeciego kroku
	$('#next3').click(function()
		{
			3
			
			var data3=true;

			$('#info3').html('');
			$('.nino').each(function() {
                var current=$(this);
				if(current.data('valid')!=true)
					{
						data3=false;
					}
					
				});
			$('#info3').html('');
			$('.dob').each(function() {
                var current=$(this);
				if(current.data('valid')!=true)
					{
						data3=false;
					}
					
				});
			$('#info3').html('');
			$('.address').each(function() {
                var current=$(this);
				if(current.data('valid')!=true)
					{
						data3=false;
					}
					
				});
			$('#info3').html('');
			$('.addressc').each(function() {
                var current=$(this);
				if(current.data('valid')!=true)
					{
						data3=false;
					}
					
				});
			
		
				
					
		if(data3)
		{
			$('#info3').html('');
			$('#second').hide('slow');
			$('#third').show('slow');
		}
		else
		$('#info3').html('<div class="ui-widget"><div class="ui-state-highlight ui-corner-all" style="margin-top: 20px; padding: 0 .7em;"><p><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span><strong>Sorry!</strong> Please, correct Your data if You want to continue.</p></div>');
});

//Walidacja czwartego kroku
$('#next4').click(function()
		{
			$('#info4').html('');
			
			
			//Wyświetlanie danych!!!!!!!!!!!
			$('#res1').html($('#amount').val());
			$('#res2').html($('#time').val());
			
			//Wyświetlanie danych!!!!!!!!!!! dla LAST!!!!
			
			
			var data4=true;
			$('.incoming').each(function() {
                var current=$(this);
				if(current.data('valid')!=true)
					{
						data4=false;
					}
				if(!$('#reg').is(':checked'))
				{
					data4=false;
				}	
				if(!$('#reg2').is(':checked'))
				{
					data4=false;
				}	
				});

		
		if(data4){
			$('#info3').html('');
			$('#third').hide('slow');
			$('#last').show('slow');
			
		}
		else
		
		$('#info4').html('<div class="ui-widget"><div class="ui-state-highlight ui-corner-all" style="margin-top: 20px; padding: 0 .7em;"><p><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span><strong>Sorry!</strong> Please, correct Your data or check all checkbox if You want to continue.</p></div>');
		});
		
		
//OBSŁUGA KLAWISZY COFANIA!!!!!!

$('#back').click(function()
{
	$('#first').hide('slow'); $('#calc').show('slow');
	});
	
$('#back1').click(function()
{
	$('#second').hide('slow'); $('#first').show('slow');
});
$('#back2').click(function()
{
	$('#third').hide('slow'); $('#second').show('slow');
});
$('#back_loan').click(function()
{
	$('#last').hide('slow'); $('#calc').show('slow');

});

//OBSŁUGA KLAWISZY COFANIA!!!!!!

});




//JQUERY UI THEME!!!!!!!!!!!!!!!!!
//Slider rate
 $(function() {
    $( "#slider" ).slider({
         range: "min",
      value: 500,
      min: 500,
      max: 50000,
	  step:100,
      slide: function( event, ui ) {
        $( "#amount" ).val(ui.value);
      }
    });
    $( "#amount" ).val( $( "#slider" ).slider( "values", 1 ) );
  });
 //Tool tip
   $(function() {
    $( document ).tooltip();
  });
