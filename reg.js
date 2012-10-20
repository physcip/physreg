function translate(msg)
{
	if (strings[msg] !== undefined)
		msg = strings[msg];
	return msg;
}

function errormsg(msg)
{
	alert(translate(msg));
}

function localize(lang)
{
	currentlang = lang;
	$.get(lang + '.json', { },
		function(data)
		{
			strings = data;
			btnReg = /^BTN_/;
			$.each(strings, function(k,v)
			{
				$('#str_' + k).html(v);
				
				if (btnReg.test(k))
					$('.' + k).val(v);
			});
		},
		"json");
}

function loaded()
{
	localize('de');
	
	$.get('register.php?action=ipcheck', { },
		function(data)
		{
			if (data['error'])
			{
				$('#status').html(translate(data['errormsg']));
				$('#status').show();
			}
			else
			{
				$('#step1').show();
				$('#rususer').focus();
			}
		},
		"json");
}

function clear_form()
{
	$('#step1').hide();
	$('#step2').hide();
	$('#step3').hide();
	$('#status').hide();
	$('#success').hide();
	
	$('#rususer').val('');
	$('#ruspw').val('');
	$('#email').val('');
	$('#cipuser').val('');
	$('#cippwtemp').val('');
	$('#newpw').val('');
	$('#newpw2').val('');
	
	$('#step1').show();
	$('#rususer').focus();
}

function step1()
{
	if ($('#rususer').val().trim() == '')
	{
		errormsg("USERNAME_MISSING");
		$('#rususer').focus();
		return;
	}
	if ($('#ruspw').val().trim() == '')
	{
		errormsg("PASSWORD_MISSING");
		$('#ruspw').focus();
		return;
	}
	
	$('#statushead').html(strings['VALIDATING']);
	$('#step1').hide();
	$('#status').show();
	
	$.post('register.php?action=checkuser', { rususer: $('#rususer').val(), ruspw: $('#ruspw').val() },
		function(data)
		{
			if (data['error'])
			{
				$('#status').hide();
				$('#step1').show();
				errormsg(data['errormsg']);
			}
			else
			{
				$('#cipuser').val(data['cipuser']);
				
				$('#status').hide();
				$('#step2').show();
			}
		},
		"json");
}

function step2()
{
	if ($('#newpw').val() == '')
	{
		errormsg("PW_MISSING");
		$('#newpw').focus();
		return;
	}
	if ($('#newpw2').val() == '')
	{
		errormsg("PW2_MISSING");
		$('#newpw2').focus();
		return;
	}
	if ($('#newpw').val() != $('#newpw2').val())
	{
		errormsg("PW_MISMATCH");
		$('#newpw2').focus();
		return;
	}
	
	$('#step2').hide();
	$('#step3').show();
}

function step3()
{
	var emailReg = /^.+@[a-zA-Z0-9\.-]+\.+[a-zA-Z0-9]+$/;
	if ($('#email').val().trim() == '' || !emailReg.test($('#email').val()))
	{
		errormsg("EMAIL_INVALID");
		$('#email').focus();
		return;
	}
	
	$('#step3').hide();
	$('#statushead').html(strings['CREATING_USER']);
	$('#status').show();
	
	$.post('register.php?action=createuser', { rususer: $('#rususer').val(), ruspw: $('#ruspw').val(), email: $('#email').val(), password: $('#newpw').val(), lang: currentlang },
		function(data)
		{
			if (data['error'])
			{
				$('#status').hide();
				$('#step3').show();
				errormsg(data['errormsg']);
			}
			else
			{
				$('#status').hide();
				$('#success').show();
				
				clear_form();
			}
		},
		"json");
}