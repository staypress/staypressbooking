function bookingPublicStartDate(thedate, inst) {



}

function bookingPublicEndDate(thedate, inst) {


}

function bookingPublicReady() {

	jQuery('.availfrom').datepicker({
				showOn: 'both',
				buttonImage: booking.calendarimage,
				buttonImageOnly: true,
				showButtonPanel: true,
				dateFormat: 'yy-m-d',
				gotoCurrent: true,
				constrainInput: true,
				onSelect: bookingPublicStartDate
			});

	jQuery('.availto').datepicker({
				showOn: 'both',
				buttonImage: booking.calendarimage,
				buttonImageOnly: true,
				showButtonPanel: true,
				dateFormat: 'yy-m-d',
				gotoCurrent: true,
				constrainInput: true,
				onSelect: bookingPublicEndDate
			});

}

jQuery(document).ready(bookingPublicReady);