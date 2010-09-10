<?php
/**
\author Jonathon Wallen info@cub3d.com www.cub3d.com
\brief A class designed to generate HTML forms on the fly.

\par Usage.
	AutoForm works by using the meta information of a mysql database table to (almost) intelligently create html form elements.
	\code
	<?php

	require 'class.AutoForm.php';

	$result = mysql_query('SHOW FULL COLUMNS FROM tablename');
	$columns = mysql_fetch_assoc($result);
	mysql_free_result($result);


	// to get a blank form
	$formObj = new AutoForm($columns);

	# OR

	// If you want a form populated with data from a database record
	$result = mysql_query('select * from tablename where id=$id');
	$data = mysql_fetch_assoc($result);
	$formObj = new AutoForm($columns,$data);

	# OR

	// If you want a form populated from the $_POST array
	$formObj = new AutoForm($row,$_POST);


	// you can create the form using a couple of different methods

	// no matter which method you must first call buildForm()
	$form = $formObj->buildForm();


	// the buildform function returns a string of html
	echo "<form action='/path/to/script.php' method='post'>";
	echo $form;
	echo "<div class='formElem'><input type='submit' value='submit'></div>";
	echo "</form>";

	// The buildForm function also populates the _properties array with html form elements 
	//So we can get that array and cycle through constructing the form manually.

	$elements = $formObj->getProperties();
	echo "<table><form action='/path/to/script.php' method='post'>";
	
	foreach ($elements as $element) {
		printf("<tr><td>%s</td><td>%s</td><td>%s</td></tr>",$element['Field'],$element['HTMLElement'],$element['Comment']);
	}

	echo "<tr><td></td><td><input type='submit' value='submit'></td><td></td></tr>";
	echo "</form></table>";

	?>
	\endcode
	
	If you use this class show your appreciation by linking to www.cub3d.com.. thanks!
	
*/
class AutoForm {
	protected $_properties = array();	//!< Array holding arrays of column properties for each column.
	protected $_baseElements = array();	//!< Array holding the default form elements for each column type.
	protected $_ignore = array();		//!< Array holding the columns to be ignored.
	protected $filled;					//!< 1 or 0: Denotes whether this form is to be filled out or provided blank.
	
	/**
	
	\param Array columns - The associative array returned by show full columns query on a database table.
	\param Array values (optional) - The associative array of values for each form field. Either from a select * from tableName or from $_REQUEST array.
	*/
	public function __construct(array $columns, array $values = null) {
		$this->init();
		if (is_array($values)) {
			$this->_values = $values;
		}
		foreach ($columns as $row) {
			
			$key = $row['Field'];
			$this->_properties[$key] = array();
			$this->_properties[$key]['Field'] = $row['Field'];
			$this->_properties[$key]['Type'] = substr ($row['Type'],0,strcspn ( $row['Type'], "("));
			$this->_properties[$key]['Default'] = $row['Default'];
			$this->_properties[$key]['Extra'] = $row['Extra'];
			$this->_properties[$key]['Comment'] = $row['Comment'];
			$this->_properties[$key]['Null'] = $row['Null'];
			$this->_properties[$key]['Required'] = (!$row['Null']) ? true:false;
			$this->_properties[$key]['Attributes'] = null;
			$this->_properties[$key]['ElementType'] = ($row['Extra']=='auto_increment') ? 'hidden': $this->_baseElements[$this->_properties[$key]['Type']];
			if (isset($row['Error'])) $this->_properties[$key]['Error'] = $row['Error'];
			switch ($this->_properties[$key]['Type']) {
				case 'enum' : $this->_properties[$key]['values'] = str_replace ("'","",trim(strstr($row['Type'], '('),'()'));
				break;
				case 'set' : $this->_properties[$key]['values'] = str_replace ("'","",trim(strstr($row['Type'], '('),'()'));
				break;
				//case 'date' : $this->_properties[$key]['values'] = 'CURRENT_DATE';
				//break;
				case 'datetime' : $this->_properties[$key]['values'] = 'NOW()';
				break;
				case 'timestamp' : $this->_properties[$key]['values'] = 'NOW()';
				break;
				default : {
					$this->_properties[$key]['values'] = '';
				}
			}
		}		
		if (is_array($values)) {
			$this->filled = 1;
			$this->fillValues($values);
			
		} else {
			$this->filled = 0;
		}
	}
	
	/**
	fillValues is used by the construct function to assign any values it may find in the table column or array arguments.
	*/
	protected function fillValues($valuesArray) {
		
		foreach ($this->_properties as $row) {
			//printf("<pre>%s</pre>",print_r($row,1));
			if (!isset($valuesArray[$row['Field']])) continue;
			if (empty ($row['values'])) $this->_properties[$row['Field']]['values'] = htmlspecialchars(str_replace('<br />','',stripslashes ($valuesArray[$row['Field']])), ENT_QUOTES);
			
			else if ($row['ElementType'] == 'select' || $row['ElementType'] == 'checkbox') {
				
				if (!is_array ($valuesArray[$row['Field']])) $valuesArray[$row['Field']] = explode(',',$valuesArray[$row['Field']]);
				foreach ($valuesArray[$row['Field']] as $candidate) {
					
					$tmp = explode(',',$this->_properties[$row['Field']]['values']);
					$tmpkey = array_search($candidate,$tmp);
					$tmp[$tmpkey] = $candidate."=ON";
					$this->_properties[$row['Field']]['values'] = implode(',',$tmp);
					
					// $this->_properties[$row['Field']]['values'] = str_replace($candidate, $candidate."=ON", $this->_properties[$row['Field']]['values']);
				}
			}
		}
	}
	
	/**
	init is called by construct to set some basic rules.
	*/
	protected function init() {
		$this->_baseElements['tinyint'] = "text";
		$this->_baseElements['smallint'] = "text";
		$this->_baseElements['mediumint'] = "text";
		$this->_baseElements['int'] = "text";
		$this->_baseElements['bigint'] = "text";
		$this->_baseElements['integer'] = "text";
		$this->_baseElements['real'] = "text";
		$this->_baseElements['double'] = "text";
		$this->_baseElements['decimal'] = "text";
		$this->_baseElements['float'] = "text";
		$this->_baseElements['numeric'] = "text";
		$this->_baseElements['char'] = "text";
		$this->_baseElements['varchar'] = "text";
		$this->_baseElements['date'] = "hidden";
		$this->_baseElements['time'] = "hidden";
		$this->_baseElements['datetime'] = "hidden";
		$this->_baseElements['timestamp'] = "hidden";
		$this->_baseElements['tinyblob'] = "text";
		$this->_baseElements['blob'] = "textarea";
		$this->_baseElements['mediumblob'] = "text";
		$this->_baseElements['longblob'] = "textarea";
		$this->_baseElements['tinytext'] = "text";
		$this->_baseElements['text'] = "textarea";
		$this->_baseElements['mediumtext'] = "text";
		$this->_baseElements['longtext'] = "textarea";
		$this->_baseElements['enum'] = "select";
		$this->_baseElements['set'] = "checkbox";

	}
	
	public function filled() { return $this->filled; }
	
	/**
	\param array items - an array of fieldnames you don't want showing up in the form
	use this method to remove any table columns from the form
	*/
	public function addIgnore(array $items) {
		if (is_array($items)) {
			foreach ($items as $item) {
				if (!in_array($item,$this->_ignore)) {
					$this->_ignore[] = $item;
					unset($this->_properties[$item]);
				}
			}
		}
	}
	
	/**
	\param string item name
	\param array item properties
	Use this function to add an item to the form.
	*/
	public function addElement($item,array $args) {
		if (!array_key_exists($item,$this->_properties)) {
			foreach ($args as $key => $val) {
				$this->_properties[$item][$key]=$val;
			}
		}
	}
	
	/**
	\param item name
	\param item type
	Use this function to change the form element type of an item.
	For example if the default of an enum column is radio button type
	you can use this function to change it to select list instead for a specific item.
	*/
	public function setElementType($item,$elementType) {
		if (array_key_exists($item,$this->_properties)) {
			$this->_properties[$item]['ElementType'] = $elementType;
		} else {
			throw new Exception("$item does not exist.");
		}
	}
	
	/**
	\param item name
	\param item attributes (string)
	Use this function to add attributes to a form element.. such as length, maxlength, javascript, styles etc. 
	*/
	public function setAttributes($item,$attributes) {
		$this->_properties[$item]['Attributes'] = ' '.$attributes;
	}
	
	/**
	\param string item name
	\param string item value
	Use this function to manually set an elements value
	*/
	public function setValues($item,$values) {
		if (array_key_exists($item,$this->_properties)) {
			$this->_properties[$item]['values']=$val;
		}
	}
	
	/**
	\return array
	returns the properties array.
	*/
	public function getProperties() {
		return $this->_properties;
	}
	
	public function __toString() {
		return sprintf("<pre>%s</pre>",print_r($this->_properties,1));
	}
	
	/**
	buildForm cycles through the table columns building each element unless the column is in the _ignore array.
	*/
	public function buildForm() {
		$form = '';

		foreach ($this->_properties as $row) {
			if (!in_array($row['Field'],$this->_ignore)) {
				$elem = $this->buildElement($row);
				$row['Comment'] != '' ? $comment = $row['Comment']."<br />": $comment = '';
				$this->_properties[$row['Field']]['HTMLElement']=$elem;
				if ($row['ElementType']=='hidden')
					$form .= $elem;
				else 
					$form .= sprintf("<div class='formElem'>\n%s<br />\n%s\n%s</div>\n",ucwords (str_replace ("_"," ",$row['Field'])),$comment,$elem);
			}
		}
		return $form;
	}
	
	/**
	buildElement is used by buildForm to switch between form element types in accordance with the column type.
	*/
	protected function buildElement($row) {
		$elem = null;
		switch ($row['ElementType']) {
			case 'textarea' 		: $elem = $this->buildTextarea($row);
			break;
			case 'hidden' 			: $elem = $this->buildHidden($row);
			break;
			case 'select' 			: $elem = $this->buildSelect($row);
			break;
			case 'checkbox' 		: $elem = $this->buildCheckbox($row);
			break;
			case 'radio' 			: $elem = $this->buildRadio($row);
			break;
			case 'password'			: $elem = $this->buildPassword($row);
			break;
			case 'checkboxsingle'	: $elem = $this->buildCheckboxSingle($row);
			break;
			case 'text' 			: $elem = $this->buildText($row);
			break;
			default 				: $elem = $this->buildText($row);
			break;
					
		}
		return $elem;
	}
	
	/**
	Returns a html input element of text type.
	*/
	protected function buildText($row) {
		$elem = sprintf("<input type='text' name='%s' value='%s'%s />",$row['Field'],$row['values'],$row['Attributes']);
		return $elem;
	}
	
	/**
	Returns a html input element of password type.
	*/
	protected function buildPassword($row) {
		$elem = sprintf("<input type='password' name='%s' value=''%s /><br />",$row['Field'],$row['Attributes']);
		$elem .= sprintf("<input type='password' name='confirm_%s' value=''%s />",$row['Field'],$row['Attributes']);
		return $elem;
	}
	
	/**
	Returns a html input element of radio type.
	*/
	protected function buildRadio($row) {
		if (!$this->filled) $$row['Default'] = " checked='checked'";
		$vals = explode (',',$row['values']);
		foreach ($vals as $value) {
			if ($pos = strpos($value,"=ON")) {
				$value = substr($value,0,$pos);
				$$value = " checked='checked'";
			}
			$elem .= sprintf("<input type='radio' name='%s' value='%s'%s />%s<br />\n",$row['Field'],$value,$$value,$value);
		}
		return $elem;
	}
	
	/**
	Returns a html form element of check box type.
	*/
	protected function buildCheckbox($row) {
		$vals = explode (',',$row['values']);
		if (!$this->filled) $$row['Default'] = " checked='checked'";
		$elem = '';
		foreach ($vals as $value) {
			if ($this->filled) {
				if ($pos = strpos($value,"=ON")) {
					$value = substr ($value,0,$pos);
					$$value = " checked='checked'";
				}
			}
			if (!isset($$value)) $$value = '';
			$elem .= sprintf("<input type='checkbox' name='%s' value='%s'%s />%s<br />\n",$row['Field']."[]",$value,$$value,$value);
		}
		return $elem;
	}
	
	/**
	Returns a html form element of checkbox type. 
	This function is used for those occasions where you want a single checkbox to represent an on/off switch.
	The value of this form element will always be 1 (on). We assume the default value for the database field is 0 (off).
	*/
	protected function buildCheckboxSingle($row) {
		$checked='';
		if ($this->filled) {
			if ($row['values']==1) $checked = " checked='checked'";
		}
		$elem = sprintf("<input type='checkbox' name='%s' value='1'%s />\n",$row['Field'],$checked);
		return $elem;
	}
	
	
	/**
	Returns a html form element of select type.
	*/
	protected function buildSelect($row) {
		//echo $row['values'].'<br>';
		$elem = "<select name='".$row['Field']."'".$row['Attributes'].">\n";
		$vals = explode (',',$row['values']);
		if (!$this->filled) $$row['Default'] = " selected='selected'";
		
		foreach ($vals as $value) {
			if ($this->filled) {
				if ($pos = strpos($value,"=ON")) {
					$value = substr ($value,0,$pos);
					$$value = " selected='selected'";
				}
			}
			if (!isset($$value)) $$value = '';
			$elem .= sprintf("<option value='%s'%s>%s</option>\n",$value,$$value,$value);
		}
		$elem .= "</select>\n";
		return $elem;
	}
	
	/**
	Returns a html form element of hidden type.
	*/
	protected function buildHidden($row) {
		$elem = sprintf("<input type='hidden' name='%s' value='%s'>",$row['Field'],$row['values']);
		return $elem;
	}
	
	/**
	Returns a html text area element.
	*/
	protected function buildTextarea($row) {
		$elem = sprintf("<textarea name='%s' cols='50' rows='10'%s>%s</textarea>",$row['Field'],$row['Attributes'],$row['values']);
		return $elem;
	}

}

?>