<?php

class Login_Controller extends Controller
{
	public function index()
	{
		
		View::display('login.php');

		exit;
	}

	public function login()
	{

		$result = Auth::login(_post('username'), _post('password'));

		switch ($result) {

			case "200":

				Route::to('index');

				break;

			case "403":

				echo 'Locked cause Failed Login'; exit;

				break;

			case "401":
			case "402":

				echo 'Failed Login'; exit;

				break;

			default:
				# code...
				break;
		}
		
		
	}

	public function logout()
	{
		$auth = new Auth;

		$auth->logout();

		Route::to('login');
	}
}

?>