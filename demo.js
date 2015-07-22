function check_file_type()
{
	var filename = document.getElementById("fileToUpload").value;
	var filetype = filename.split('.').pop().toLowerCase();
	if ((filetype != 'jpg') && (filetype != 'png') && (filetype != 'gif') && (filetype != 'jpeg'))
	{
		// not image file
		document.getElementById("submit_button").disabled = true;
	}
	else
	{
		// yes image file
		document.getElementById("submit_button").disabled = false;
	}
}
