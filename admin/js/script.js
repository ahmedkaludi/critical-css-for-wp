function ccfwpGetParamByName(name, url) {
    if (!url) url = window.location.href;
    name = name.replace(/[\[\]]/g, "\\$&");
    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, " "));
}
function ccfwpIsEmail(email) {
	var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
	return regex.test(email);
}
jQuery( document ).ready(function($) {
		
	jQuery("#table_page_cc_style_all").DataTable({
		serverSide: true,
		processing: true,
        fixedColumns: true,		
		ajax: {
			type: "GET",
			url: ajaxurl+"?action=ccfwp_showdetails_data&ccfwp_security_nonce="+ccfwp_localize_data.ccfwp_security_nonce
		}
	});
	jQuery("#table_page_cc_style_completed").DataTable({
		serverSide: true,
		processing: true,
        fixedColumns: true,		
		ajax: {
			type: "GET",
			url: ajaxurl+"?action=ccfwp_showdetails_data_completed&ccfwp_security_nonce="+ccfwp_localize_data.ccfwp_security_nonce
		}
	});
	jQuery("#table_page_cc_style_failed").DataTable({
		serverSide: true,
		processing: true,
        fixedColumns: true,		
		ajax: {
			type: "GET",
			url: ajaxurl+"?action=ccfwp_showdetails_data_failed&ccfwp_security_nonce="+ccfwp_localize_data.ccfwp_security_nonce
		}
	});
	jQuery("#table_page_cc_style_queue").DataTable({
		serverSide: true,
		processing: true,
        fixedColumns: true,		
		ajax: {
			type: "GET",
			url: ajaxurl+"?action=ccfwp_showdetails_data_queue&ccfwp_security_nonce="+ccfwp_localize_data.ccfwp_security_nonce
		}
	});

	//query form send starts here

    $(".ccfwp-send-query").on("click", function(e){
		e.preventDefault();   
		var message     = $("#ccfwp_query_message").val();  
		var email       = $("#ccfwp_query_email").val();  
		var premium_cus = $("#ccfwp_query_premium_cus").val(); 
		
		if($.trim(message) !='' && premium_cus && $.trim(email) !='' && ccfwpIsEmail(email) == true){
			
		 $.ajax({
						type: "POST",    
						url:ajaxurl,                    
						dataType: "json",
						data:{action:"ccfwp_send_query_message", premium_cus:premium_cus,message:message,email:email, ccfwp_security_nonce:ccfwp_localize_data.ccfwp_security_nonce},
						success:function(response){                       
						  if(response['status'] =='t'){
							$(".ccfwp-query-success").show();
							$(".ccfwp-query-error").hide();
						  }else{                                  
							$(".ccfwp-query-success").hide();  
							$(".ccfwp-query-error").show();
						  }
						},
						error: function(response){                    
							console.log(response);
						}
						});   
		}else{
			
			if($.trim(message) =='' && premium_cus =='' && $.trim(email) ==''){
				alert('Please enter the message, email and select customer type');
			}else{
			
			if(premium_cus ==''){
				alert('Select Customer type');
			}
			if($.trim(message) == ''){
				alert('Please enter the message');
			}
			if($.trim(email) == ''){
				alert('Please enter the email');
			}
			if(ccfwpIsEmail(email) == false){
				alert('Please enter a valid email');
			}
				
			}
			
		}                        

	});

	// tabs section for datatable starts here
	$('.cwvpb-global-container').hide();
	$('.cwvpb-global-container:first').show();
	$('#cwvpb-global-tabs a:first').addClass('cwvpb-global-selected');
	
	$('#cwvpb-global-tabs a').click(function(){
		var t = $(this).attr('data-id');
		
	  if(!$(this).hasClass('cwvpb-global-selected')){ 
		$('#cwvpb-global-tabs a').removeClass('cwvpb-global-selected');           
		$(this).addClass('cwvpb-global-selected');

		$('.cwvpb-global-container').hide();
		$('#'+t).show();
	 }
	});

	// tabs section for datatable ends here

	$(".ccfwp-resend-urls").on("click", function(e){
		e.preventDefault();
		var current = $(this);
		current.addClass('updating-message');		
		$.ajax({
			url: ajaxurl,
			type:'post',
			dataType: 'json',
			data: {'ccfwp_security_nonce': ccfwp_localize_data.ccfwp_security_nonce, 
					action: 'ccfwp_resend_urls_for_cache'},
			success: function(response){
				current.removeClass('updating-message');		
				if(response.status){
					location.reload(true);
				}else{
					alert('something went wrong');
				}
			}
		})

	})
	$(".ccfwp-advance-toggle").on("click", function(e){
		e.preventDefault();
		$(".ccfwp-advance-btn-div").toggleClass('cwvpb-display-none');		
	});

	$(document).on("click", ".cwvpb-resend-single-url", function(e) {
		e.preventDefault();
		
		var current = $(this);
		var url_id = $(this).attr('data-id');
		var d_section = $(this).attr('data-section');
		current.addClass('cwvpb-display-none');
		current.after('<span class="spinner is-active"></span>');		
		
		$.ajax({
			url: ajaxurl,
			type:'post',
			dataType: 'json',
			data: {'ccfwp_security_nonce': ccfwp_localize_data.ccfwp_security_nonce,
			action: 'ccfwp_resend_single_url_for_cache',
			url_id: url_id
			},
			success: function(response){
				current.removeClass('updating-message');	
				if(response.status){
					
					if(d_section == 'all'){
						current.parent().parent().parent().find(".cwvpb-status-t").text('queue');						
						$(current).next('span').remove();
						current.remove();
					}
					if(d_section == 'failed'){
						current.parent().parent().parent().remove();
					}

				}else{
					current.removeClass('cwvpb-display-none');		
					alert('something went wrong');
				}
			}
		});

	}
	);

	function ccfwp_recheck_urls(current, page){
		var new_page = page;
		current.addClass('updating-message');		
		$.ajax({
			url: ajaxurl,
			type:'post',
			dataType: 'json',
			data: {'ccfwp_security_nonce': ccfwp_localize_data.ccfwp_security_nonce, 
					action: 'ccfwp_recheck_urls_cache', page:new_page},
			success: function(response){
				current.removeClass('updating-message');	
				if(response.status){
					if(response.count > 0){
						new_page++;
						ccfwp_recheck_urls(current, new_page);
					}else{
						alert('Recheck is done');	
						location.reload(true);
					}										
				}else{
					alert('something went wrong');
				}
			}
		});
	}

	$(document).on("click", ".cwb-copy-urls-error", function(e){
		e.preventDefault();
		var element = $(this).parent().find(".cwb-copy-urls-text");
		var $temp = $("<input>");
		$("body").append($temp);
		$temp.val($(element).val()).select();
		document.execCommand("copy");
		$temp.remove();
		$('<div>Copied!</div>').insertBefore($(this)).delay(3000).fadeOut();
	});
	$(".ccfwp-recheck-url-cache").on("click", function(e){
		e.preventDefault();
		if(!confirm('It will check all cached urls. if any one has issue will optimize it again. Proceed?')){
			return false;
		}	
		var current = $(this);		
		var page    = 0;
		ccfwp_recheck_urls(current, page);		
	});

	$(".ccfwp-reset-url-cache").on("click", function(e){
		e.preventDefault();
		if(!confirm('Are you sure? It will start optimize process from beginning again.')){
			return false;
		}	
		var current = $(this);
		current.addClass('updating-message');		
		
		$.ajax({
			url: ajaxurl,
			type:'post',
			dataType: 'json',
			data: {'ccfwp_security_nonce': ccfwp_localize_data.ccfwp_security_nonce, action: 'ccfwp_reset_urls_cache'},
			success: function(response){
				current.removeClass('updating-message');
				console.log(response.status);	
				if(response.status){
					location.reload(true);
				}else{
					alert('something went wrong');
				}
			}
		})

	});
});
