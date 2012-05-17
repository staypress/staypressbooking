function bookingMoveMonth() {

	var movingto = jQuery(this).attr('id');
	movingto = movingto.split('-');
	if(movingto.length == 2) {
		jQuery('div.month').load(ajaxurl, {action: '_bookingmovemonth', year: movingto[0], month: movingto[1], nocache: new Date().getTime()},
		function() {
			jQuery('a.previousmonth').unbind('click');
			jQuery('a.nextmonth').unbind('click');
			jQuery('a.previousmonth').click(bookingMoveMonth);
			jQuery('a.nextmonth').click(bookingMoveMonth);
		}
		);
	}

	return false;
}

function bookingDeleteBooking() {
	if(confirm(booking.deletebooking)) {
		bookingid = jQuery(this).attr('id');
		bookingid = bookingid.replace(/delete-/,'');

		jQuery.getJSON(ajaxurl, { _ajax_nonce: booking.deletebookingnonce, action: '_deletebooking', id: bookingid, nocache: new Date().getTime() },
						function(data){
							if(data.errorcode != '200' && data.message != null) {
								alert(data.message);
							} else {
								booking.deletebookingnonce = data.newnonce;
								jQuery('#booking-' + data.id).fadeOut('slow', function() { jQuery(this).remove(); });
							}
						});
	}

	return false;
}

function bookingStartDate(thedate, inst) {

	if(thedate != '') {
		splitdate = thedate.split('-');

		if(splitdate.length == 3) {
			jQuery('#startdate-day').val(splitdate[2]);
			jQuery('#startdate-month').val(splitdate[1]);
			jQuery('#startdate-year').val(splitdate[0]);
		}

	}

}

function bookingEndDate(thedate, inst) {

	if(thedate != '') {
		splitdate = thedate.split('-');

		if(splitdate.length == 3) {
			jQuery('#enddate-day').val(splitdate[2]);
			jQuery('#enddate-month').val(splitdate[1]);
			jQuery('#enddate-year').val(splitdate[0]);
		}

	}
}

function bookingChangeDateDropDowns() {
	// get the settings
	year = jQuery('#startdate-year').val();
	mon = jQuery('#startdate-month').val();
	day = jQuery('#startdate-day').val();
	// make a date
	startdate = new Date(year, (mon - 1), day);
	//check its the same and reset if not
	if(day != startdate.getDate()) {
		jQuery('#startdate-year').val(startdate.getFullYear());
		jQuery('#startdate-month').val(startdate.getMonth() + 1);
		jQuery('#startdate-day').val(startdate.getDate());
	}
	jQuery('#startdate').val(startdate.getFullYear() + '-' + (startdate.getMonth() + 1) + '-' + startdate.getDate());

	year = jQuery('#enddate-year').val();
	mon = jQuery('#enddate-month').val();
	day = jQuery('#enddate-day').val();

	enddate = new Date(year, (mon - 1), day);

	if(day != enddate.getDate()) {
		jQuery('#enddate-year').val(enddate.getFullYear());
		jQuery('#enddate-month').val(enddate.getMonth() + 1);
		jQuery('#enddate-day').val(enddate.getDate());
	}
	jQuery('#enddate').val(enddate.getFullYear() + '-' + (enddate.getMonth() + 1) + '-' + enddate.getDate());

	return false;
}

function booking_removeMessageBox() {
	jQuery('#upmessage').fadeOut('slow', function() { jQuery(this).remove(); });

	return false;
}

function booking_toggleSidebarBox() {
	jQuery(this).siblings('div.innersidebarbox').toggleClass('shrunk');
	jQuery(this).siblings('h2.rightbarheading').toggleClass('shrunk');
}

function bookingFilterNotesAll() {
	jQuery('#bookingnotesinner a.bookingnotefilter').removeClass('selected');
	jQuery('#filterallbookingnotes').addClass('selected');

	jQuery('#bookingnotesinner tr.bookingnote').show();

	return false;
}

function bookingFilterNotesPayment() {
	jQuery('#bookingnotesinner a.bookingnotefilter').removeClass('selected');
	jQuery('#filterpaymentbookingnotes').addClass('selected');

	jQuery('#bookingnotesinner tr.bookingnote').show();
	jQuery('tr.bookingnote:not(.bookingnotepayment)').hide();

	return false;
}

function bookingFilterNotesNote() {
	jQuery('#bookingnotesinner a.bookingnotefilter').removeClass('selected');
	jQuery('#filternotebookingnotes').addClass('selected');

	jQuery('#bookingnotesinner tr.bookingnote').show();
	jQuery('tr.bookingnote:not(.bookingnotenote)').hide();

	return false;
}

function bookingFullNoteType() {

	notetype = jQuery(this).val();

	jQuery('div.additionalnoteinfo:not(.' + notetype + 'info)').hide();
	jQuery('div.additionalnoteinfo.' + notetype + 'info').show();

}


function bookingReady() {

	jQuery('#startdate').datepicker({
				showOn: 'button',
				buttonImage: booking.calendarimage,
				buttonImageOnly: true,
				showButtonPanel: true,
				dateFormat: 'yy-m-d',
				gotoCurrent: true,
				onSelect: bookingStartDate
			});

	jQuery('#enddate').datepicker({
				showOn: 'button',
				buttonImage: booking.calendarimage,
				buttonImageOnly: true,
				showButtonPanel: true,
				dateFormat: 'yy-m-d',
				gotoCurrent: true,
				onSelect: bookingEndDate
			});

	jQuery('.datefield').change(bookingChangeDateDropDowns);

	jQuery('a.delete').click(bookingDeleteBooking);

	// click to remove - or auto remove in 60 seconds
	jQuery('a#closemessage').click(booking_removeMessageBox);
	setTimeout('booking_removeMessageBox()', 60000);

	jQuery('a.previousmonth').click(bookingMoveMonth);
	jQuery('a.nextmonth').click(bookingMoveMonth);

	jQuery('div.sidebarbox div.handlediv').click(booking_toggleSidebarBox);

	// Notes
	jQuery('a#filterallbookingnotes').click(bookingFilterNotesAll);
	jQuery('a#filterpaymentbookingnotes').click(bookingFilterNotesPayment);
	jQuery('a#filternotebookingnotes').click(bookingFilterNotesNote);

	jQuery('#addfullnotetype').change(bookingFullNoteType);

}

jQuery(document).ready(bookingReady);