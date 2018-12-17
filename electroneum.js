window.error_text = "";
function checkelectroniumdata(val)
{
   if(val == 2)
   {
	  returnurl = jQuery("#return_url").val();
	  window.location.href = returnurl+"&cancelorder=yes";
   }
   if(val == 1)
   { 
    
	 
	 //jQuery("#paymentprogress").show();
	
	 jQuery.ajax({
		type: "POST",
		cache: false,
		dataType: "json",
		url: window.siteurl + "index.php?option=com_virtuemart&view=cart&vmtask=electroneumajax",
		data : jQuery("#electroniumform :input").serialize(),
 	 }).done(
	 function (data, textStatus){
		
		 if(data.success == 0)
		 {
			 errorstring = '<div class="uk-alert" uk-alert><a href="" class="uk-alert-close uk-close"></a><p>'+data.message+'</p></div>';
			 //jQuery("#error_div").html(errorstring);
			 
			  setTimeout(function(){
				checkelectroniumdata(1)
			 }, 5000);
			 
			 //jQuery("#electroniumform").submit();
			 //jQuery("#paymentprogress").hide();
		 }
		 if(data.success == 1)
		 {
			
			 jQuery("#paymentqr_div").hide();
			 jQuery("#successdiv").show();
			 jQuery("#checkmark").show();
			 setTimeout(function(){
				 jQuery("#electroniumform").submit();
			 }, 3000);
		 }

	 });	
	 
   } 
}

function openpayment()
{
	 code = "";
	 paymentid = jQuery("#paymentid").val();
	 outlet = jQuery("#outlet").val();
	 total = jQuery("#etn").val();
	 code = "etn-it-"+outlet+"/"+paymentid+'/'+total;
	 window.open("https://link.electroneum.com/jWEpM5HcxP?vendor=" + code);
}

