<?php

/*
 * ======================================================================
 * Paginator
 * ======================================================================
 *
 * Developer:    Nick Tsai
 * Version:      1.0.2
 * Last Updated: 2014/12/05
 *
 * Demo:

	$rows_count = Model::getListCount();

	$data = Paginator::make($rows_count, $per_page, _get('page'), $buttons_count);

	$list1 = Model::getListWithLimit(
		Paginator::getLimit(), Paginator::getPerpage);
	$list2 = Model::getListWithLimit(
		$data['getLimit'], $data['getPerpage']);

	# Get Current Page to avoid page out of range
	$list3 = Model::getListWithPage(
		Paginator::getCurrentPage(), Paginator::getPerpage);

 *
 * Demo in View: (Echo HTML)

 	<?=Paginator::html('bootstrap')?>

 *
 * Demo in View: (Template)

	<nav>
		<ul class="pagination">

			<?php if(Paginator::getCurrentPage()==1) { ?>
			<li class="disabled"><a><span aria-hidden="true">&nbsp;<span class="glyphicon glyphicon-step-backward" aria-hidden="true"></span></span></a></li>
			<li class="disabled"><a><span aria-hidden="true">&laquo;</span><span class="sr-only">Previous</span></a></li>
			<?php } else { ?>
			<li><a href="<?=Route::getUrl().Route::getUrlParam('page',1)?>"><span aria-hidden="true">&nbsp;<span class="glyphicon glyphicon-step-backward" aria-hidden="true"></span></span></a></li>
			<li><a href="<?=Route::getUrl().Route::getUrlParam('page',Paginator::getCurrentPage()-1)?>"><span aria-hidden="true">&laquo;</span><span class="sr-only">Previous</span></a></li>
			<?php } ?>

			<?php for ($i=Paginator::getButtonFrom(); $i <= Paginator::getButtonTo(); $i++):?>
			<li <?php if(Paginator::getCurrentPage()==$i) echo 'class="active"'; ?>><a href="<?=Route::getUrl().Route::getUrlParam('page',$i)?>"><?=$i?></a></li>
			<?php endfor?>

			<?php if(Paginator::getCurrentPage()==Paginator::getLastPage()) { ?>
			<li class="disabled"><a><span aria-hidden="true">&raquo;</span><span class="sr-only">Next</span></a></li>
			<li class="disabled"><a><span aria-hidden="true"><span class="glyphicon glyphicon-step-forward" aria-hidden="true"></span>&nbsp;</span></a></li>
			<?php } else { ?>
			<li><a href="<?=Route::getUrl().Route::getUrlParam('page',Paginator::getCurrentPage()+1)?>"><span aria-hidden="true">&raquo;</span><span class="sr-only">Next</span></a></li>
			<li><a href="<?=Route::getUrl().Route::getUrlParam('page',Paginator::getLastPage())?>"><span aria-hidden="true"><span class="glyphicon glyphicon-step-forward" aria-hidden="true"></span>&nbsp;</span></a></li>
			<?php } ?>

		</ul>
	</nav>

 *
 */

class Paginator
{

	# Setting

	private static $_current_page_get_key = 'page';


	# Pagination

	private static $_current_page;		# The zero-based current page number

	private static $_last_page;			# The number of total pages

	private static $_per_page = 10;		# The number of items per page

	private static $_total;				# Total number of items

	private static $_limit = 0;			# The limit of the data in MySQL


	# Page Buttons

	private static $_btn_num = 5;		# The number of Pagination Buttons

	private static $_btn_from;			# The page number starts from Buttons

	private static $_btn_to;			# The page number ends to Buttons

	
	/**
	 * ======================================================================
	 * Set Current Page $_GET Key
	 * ======================================================================
	 *
	 * @param (string) $name: The key name
	 *
	 * @return (object) Self 
	 *
	 */
	public static function setGetKey($name)
	{
		self::$_current_page_get_key = $name;

		return new self;
	}

	/**
	 * ======================================================================
	 * Pagination Make
	 * ======================================================================
	 *
	 * @param (integer) $total_rows: Total number of items.
	 * @param (integer) $per_page: The number of items per page.
	 * @param (integer) $current_page: The zero-based current page number.
	 * @param (boolean/integer) $make_btn: 
	 * 	boolean: true: Process and use default pagination button numbers
	 *  boolean: false: Do not process pagination button data
	 *  integer: >= 1: Process and use this integer as pagination button numbers
	 *
	 * @return (mixed) Pagination array of data
	 *
	 */
	public static function make($total_rows, $per_page=NULL, $current_page=NULL, $make_btn=true)
	{
		
		# Parameters Initialization
		
		self::$_total = (int)$total_rows;
		
		self::$_per_page = ($per_page) ? $per_page : self::$_per_page;

		# TotalPages
		self::$_last_page = (self::$_total==0) ? 1 : ceil(self::$_total / self::$_per_page);

		# CurrentPage
		self::$_current_page = ($current_page) ? (int)$current_page : (int)_get(self::$_current_page_get_key);
		
		if (self::$_current_page < 1)

			self::$_current_page = 1;
		
		elseif (self::$_current_page > self::$_last_page) 

			self::$_current_page = self::$_last_page;

		# Limit
		self::$_limit = self::$_per_page * (self::$_current_page - 1);


		# Button Making
		if ($make_btn) {

			# Use default $_btn_num if param is true or assign a new $_btn_num if is integer
			self::$_btn_num = ($make_btn===true) ? self::$_btn_num : (int)$make_btn;

			# All pages separate to three area (Before-Front, Front-to-Back, After-Back) 

			$offset_to_front = intval(self::$_btn_num / 2);

		    $offset_to_back = (self::$_last_page > $offset_to_front) ? self::$_last_page-$offset_to_front : self::$_last_page;

		    # Before-Front
		    if(self::$_current_page <= $offset_to_front)
		    {
				self::$_btn_from = 1;
				self::$_btn_to = min(self::$_last_page, self::$_btn_num);
		    }
		    # After-Back
		    elseif(self::$_current_page >= $offset_to_back)
		    {
				self::$_btn_from = (self::$_last_page <= self::$_btn_num) ? 1 : self::$_last_page - self::$_btn_num + 1;
				self::$_btn_to = self::$_last_page;
		    }
		    # Front-to-Back
		    else
		    {
				self::$_btn_from = self::$_current_page - $offset_to_front;
				self::$_btn_to = self::$_current_page + $offset_to_front;
		    }
		}

		return self::getAll();

	}

	public static function getAll()
	{
		$data = array();

		# Pagination
		$data['getCurrentPage'] = self::$_current_page;
		$data['getLastPage'] = self::$_last_page;
		$data['getPerPage'] = self::$_per_page;
		$data['getTotal'] = self::$_total;
		$data['getLimit'] = self::$_limit;

		# Page Buttons
		$data['getButtonCount'] = self::$_btn_num;
		$data['getButtonFrom'] = self::$_btn_from;
		$data['getButtonTo'] = self::$_btn_to;

		return $data;
	}

	/**
	 * ======================================================================
	 * Get Pagination Data
	 * ======================================================================
	 */
	public static function getCurrentPage()
	{
		return self::$_current_page;
	}

	public static function getLastPage()
	{
		return self::$_last_page;
	}

	public static function getPerPage()
	{
		return self::$_per_page;
	}

	public static function getTotal()
	{
		return self::$_total;
	}

	public static function getLimit()
	{
		return self::$_limit;
	}

	/**
	 * ======================================================================
	 * Get Page Button Data
	 * ======================================================================
	 */
	public static function getButtonCount()
	{
		return self::$_btn_num;
	}

	public static function getButtonFrom()
	{
		return self::$_btn_from;
	}

	public static function getButtonTo()
	{
		return self::$_btn_to;
	}

	/**
	 * ======================================================================
	 * Print HTML of Pagination Button Bar
	 * ======================================================================
	 *
	 * @param (string) $type: HTML DOM Type
	 *
	 * Example in View:
	 * <?=Paginator::html('bootstrap')?>
	 *
	 */
	public static function html($type='')
	{
		
		switch ($type) {		
			
			case 'bootstrap':
			case 'bootstrap3':
			default:

				echo '<nav>';
		        echo '<ul class="pagination">';

		        if (self::getCurrentPage()==1) {

		        	echo '<li class="disabled"><a><span aria-hidden="true">&nbsp;<span class="glyphicon glyphicon-step-backward" aria-hidden="true"></span></span></a></li>';
				    echo '<li class="disabled"><a><span aria-hidden="true">&laquo;</span><span class="sr-only">Previous</span></a></li>';
				
				} else {

					echo '<li><a href="'.Route::getUrl().Route::getUrlParam('page',1).'"><span aria-hidden="true">&nbsp;<span class="glyphicon glyphicon-step-backward" aria-hidden="true"></span></span></a></li>';
				    echo '<li><a href="'.Route::getUrl().Route::getUrlParam('page',self::getCurrentPage()-1).'"><span aria-hidden="true">&laquo;</span><span class="sr-only">Previous</span></a></li>';
				}

				for ($i=self::getButtonFrom(); $i <= self::getButtonTo(); $i++) { 

					$active = (self::getCurrentPage()==$i) ? ' class="active"' : NULL;
					echo '<li'.$active.'><a href="'.Route::getUrl().Route::getUrlParam('page',$i).'">'.$i.'</a></li>';
				}

				if(self::getCurrentPage()==self::getLastPage()) {

					echo '<li class="disabled"><a><span aria-hidden="true">&raquo;</span><span class="sr-only">Next</span></a></li>';
				    echo '<li class="disabled"><a><span aria-hidden="true"><span class="glyphicon glyphicon-step-forward" aria-hidden="true"></span>&nbsp;</span></a></li>';
				
				} else {

					echo '<li><a href="'.Route::getUrl().Route::getUrlParam('page',self::getCurrentPage()+1).'"><span aria-hidden="true">&raquo;</span><span class="sr-only">Next</span></a></li>';
				    echo '<li><a href="'.Route::getUrl().Route::getUrlParam('page',self::getLastPage()).'"><span aria-hidden="true"><span class="glyphicon glyphicon-step-forward" aria-hidden="true"></span>&nbsp;</span></a></li>';
				}

				echo '</ul>';
			    echo '</nav>';
				
				break;
		}

		
	}

	/**
	 * ======================================================================
	 * Private $_GET Function
	 * ======================================================================
	 */
	private static function _get($key)
	{
		if (isset($_GET[$key])) 
			return $_GET[$key];
		else
			return false;
	}

}

?>