<?php

function _get($key)
{
	if (isset($_GET[$key])) {

		return trim($_GET[$key]);

	} else {

		return NULL;

	}
}

function _post($key)
{
	if (isset($_POST[$key])) {

		return $_POST[$key];

	} else {

		return NULL;
		
	}
}

?>