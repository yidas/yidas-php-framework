<?php

# Route Get Name Setting
Route::setRouteKey('r');

# Route


Route::get('login','Login_Controller@index');

Route::post('login/login','Login_Controller@login');

Route::get('login/logout','Login_Controller@logout');

# Routes with Login Session
if (Auth::checkIsLogin()) {
	
	# Admin Routes
	if (Auth::checkGroup('admin')) {

		Route::get('index');

	} else { # User Routes

		Route::get('index');

	}

}


# Authorization

	# Allowed Routes in not Login Statement
	$routes_notlogin = array('login','login/login');

	# Route Setting without login
	if (Auth::checkIsLogin() != true && !in_array(Route::getRouteName(), $routes_notlogin)) {

		Route::to('login');

	}

	# Route Protection
	if (!in_array(Route::getRouteName(), $routes_notlogin)) { Auth::checkIsLogin(1); }

# Default Route
Route::setExceptionalRouteName('index');

# Routes Execution
Route::run();

?>