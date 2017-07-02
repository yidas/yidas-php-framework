<?php

class Controller
{
	public function run($function_name=NULL)
	{
		if (!$function_name) {
			
			$function_name = 'index';

		}

		$this->$function_name();

	}
}

?>