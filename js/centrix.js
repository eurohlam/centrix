(function($) {

    $("#centrixForm").submit(function(event) {

        // Prevent spam click and default submit behaviour
        $("#btnSubmit").attr("disabled", true);
        event.preventDefault();

        // get basic info from FORM
        var name = $("input#name").val();
        var surname = $("input#surname").val();
		var middlename = $("input#middlename").val();
        var dateofbirth = $("input#dateofbirth").val();
        var city = $("input#city").val();
        var suburb = $("input#suburb").val();
        var streetnumber = $("input#streetnumber").val();
        var street = $("input#street").val();
        var postcode = $("input#postcode").val();
        var country = $("input#country").val();
        var addresstype = $("input[name='addresstype']:checked").val();

		//get emails from FORM for sending resulted PDF
        var clientemail = $("input#clientemail").val();
        var agentemail = $("input#agentemail").val();
        var adminemail = $("input#adminemail").val();
        var emailList = [clientemail, agentemail, adminemail];

		//get NZ specific fields from FORM
        var nz_passportnumber = $("input#nz_passportnumber").val();
        var nz_passportexpiry = $("input#nz_passportexpiry").val();
        var nz_driverlicensenumber = $("input#nz_driverlicensenumber").val();
        var nz_driverlicenseversion = $("input#nz_driverlicenseversion").val();

		//get AU specific fields from FORM
     /*   var au_passportnumber = $("input#au_passportnumber").val();
        var au_passportgender = $("select#au_passportgender").val();
        var au_citizenshipacquisitiondate = $("input#au_citizenshipacquisitiondate").val();
        var au_citizenshipbydescent = $("input#au_citizenshipbydescent").is(":checked");
        var au_citizenshipstocknumber = $("input#au_citizenshipstocknumber").val();
        var au_driverlicensenumber = $("input#au_driverlicensenumber").val();
        var au_driverlicensestate = $("select#au_driverlicensestate").val();
        var au_visacountryofissue = $("input#au_visacountryofissue").val();
        var au_visapassportnumber = $("input#au_visapassportnumber").val();
        var au_immicardnumber = $("input#au_immicardnumber").val(); */


		//prepare json data
        var data = { 'details' : {}};

        data.details.name = { 'given' : name, 'family': surname };
		if ( middlename ) {
			data.details.name.middle = middlename;
		}
        data.details.dateofbirth = dateofbirth;
        data.details.address = { 'city' : city,
                                 'suburb' : suburb,
                                 'postcode' : postcode,
                                 'country' : country,
                                 'streetname' : street,
                                 'streetnumber' : streetnumber,
                                 'addresstype' : addresstype};

        if ( nz_passportnumber ) {
            data.details.passport = { 'number' : nz_passportnumber,
    								  'expiry' : nz_passportexpiry };
        };
        if ( nz_driverlicensenumber ) {
            data.details.driverslicence = { 'number' : nz_driverlicensenumber,
    								        'version' : nz_driverlicenseversion };
        };
        /*
        if ( au_passportnumber ) {
            data.details.australianpassport = { 'number' : au_passportnumber,
    								  			'gender' : au_passportgender };
        };
        if ( au_visapassportnumber ) {
            data.details.visa = { 'passportnumber' : au_visapassportnumber,
    							  'countryofissue' : au_visacountryofissue };
        };
        if ( au_driverlicensenumber ) {
            data.details.australiandriverslicence = { 'number' : au_driverlicensenumber,
    							  'state' : au_driverlicensestate };
        };
        if ( au_citizenshipacquisitiondate ) {
			if ( au_citizenshipbydescent == true ) {
            	data.details.citizenshipbydescent = { 'acquisitiondate' : au_citizenshipacquisitiondate };
			} else {
            	data.details.australiancitizenship = { 'acquisitiondate' : au_citizenshipacquisitiondate,
    								  				   'stocknumber' : au_citizenshipstocknumber };
			}
        };
        if ( au_immicardnumber ) {
            data.details.immicard = { 'number' : au_immicardnumber };
        }; */


        var requestData = JSON.stringify(data);
        console.log("centrix request: " + requestData);
        showAlert("success", "<strong>Connecting to centrix service. Please, wait for a moment ...</strong>");

        $.ajax({
            url: "/wp-admin/admin-ajax.php",
            type: "POST",
            dataType: "JSON",
            data: {
                'action': 'centrix_send_request',
                'requestData': requestData,
                'soapAction': 'http://centrix.co.nz/IConsumers/GetCreditReportProducts'
            },
            cache: false,
            success: function(data) {
                console.log("centrix response: " + JSON.stringify(data));
                showAlert("success", "<strong>Verification completed successfully. Getting resulted PDF...</strong>");

                //open pdf in new tab
                window.open(data.pdfUrl, '_blank');
                //send email
                sendEmail(emailList, data.pdfPath);

                // Enable button
                $("#btnSubmit").attr("disabled", false);

            },
            error: function() {
                // Fail message
                showAlert("error", "<strong>It seems that centrix service is not responding. Please try again later</strong>");
                // Enable button
                $("#btnSubmit").attr("disabled", false);
            },
        });
    }); //centrixForm submit


    function sendEmail(emailList, filepath) {
        console.log("Sending email to: " + JSON.stringify(emailList));
        console.log("Attachment: " + filepath);
        $.ajax({
            url: "/wp-admin/admin-ajax.php",
            type: "POST",
            dataType: "JSON",
            data: {
                'action': 'centrix_send_email',
                'emaillist': emailList,
                'filepath' : filepath
            },
            cache: false,
            success: function(data) {
                console.log("centrix response: " + JSON.stringify(data));
                showAlert("success", "<strong>Verification completed successfully. Resulted PDF has been sent by email</strong>");
            },
            error: function() {
                // Fail message
                showAlert("error", "<strong>Couldn't send resulted PDF by email. Please, check settings of email server</strong>");
            },
        });
    } //sendEmail

    function showAlert(type, text) {
        if (type == 'error') {
            $('#success').html("<div class='alert alert-danger'>");
            $('#success > .alert-danger').html("<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>");
            $('#success > .alert-danger').append(text);
            $('#success > .alert-danger').append('</div>');
        } else {
            $('#success').html("<div class='alert alert-success'>");
            $('#success > .alert-success').html("<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>");
            $('#success > .alert-success').append(text);
            $('#success > .alert-success').append('</div>');
        }
    } //showAlert

    $("input#au_citizenshipbydescent").change(function (event) {
        var au_citizenshipbydescent = $("input#au_citizenshipbydescent").is(":checked");
        if(this.checked) {
            $("#div_au_citizenshipstocknumber").hide();
        } else {
            $("#div_au_citizenshipstocknumber").show();
        }
    });

}) ( jQuery );
