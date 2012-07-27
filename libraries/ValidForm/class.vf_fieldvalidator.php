<?php
/***************************
 * ValidForm Builder - build valid and secure web forms quickly
 * 
 * Copyright (c) 2009-2012, Felix Langfeldt <flangfeldt@felix-it.com>.
 * All rights reserved.
 * 
 * This software is released under the GNU GPL v2 License <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * 
 * @package    ValidForm
 * @author     Felix Langfeldt <flangfeldt@felix-it.com>
 * @copyright  2009-2012 Felix Langfeldt <flangfeldt@felix-it.com>
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU GPL v2
 * @link       http://code.google.com/p/validformbuilder/
 ***************************/
 
require_once('class.classdynamic.php');
require_once('class.vf_validator.php');

/**
 * 
 * FieldValidator Class
 * 
 * @package ValidForm
 * @author Felix Langfeldt
 * @version Release: 0.2.2
 *
 */
class VF_FieldValidator extends ClassDynamic {
	protected $__fieldname;
	protected $__type;
	protected $__fieldhint;
	protected $__validvalue;
	protected $__minlength;
	protected $__maxlength;
	protected $__matchwith;
	protected $__targetfield;
	protected $__required = FALSE;
	protected $__maxfiles = 1;
	protected $__maxsize = 3000;
	protected $__filetypes;
	protected $__validation;
	protected $__minlengtherror = "The input is too short. The minimum is %s characters.";
	protected $__maxlengtherror = "The input is too long. The maximum is %s characters.";
	protected $__matchwitherror = "The values do not match.";
	protected $__requirederror = "This field is required.";
	protected $__typeerror;
	protected $__overrideerror;
	protected $__maxfileserror = "Too many files selected. The maximum is %s files.";
	protected $__maxsizeerror = "The filesize is too big. The maximum is %s KB.";
	protected $__filetypeerror = "Invalid file types selected. Only types of %s are permitted.";
	protected $__hinterror = "The value is the hint value. Enter your own value.";
	protected $__error;
	
	public function __construct($fieldName, $fieldType, $validationRules, $errorHandlers, $fieldHint = NULL) {
		foreach ($validationRules as $key => $value) {
			$property = strtolower("__" . $key);
			if (property_exists($this, $property)) {
				$this->$property = $value;
			}
		}
		
		foreach ($errorHandlers as $key => $value) {
			$property = strtolower("__" . $key . "error");
			if (property_exists($this, $property)) {
				$this->$property = $value;
			}
		}
		
		$this->__fieldname = str_replace("[]", "", $fieldName);
		$this->__type = $fieldType;
		$this->__fieldhint = $fieldHint;
	}
	
	public function getValue($intDynamicPosition = 0) {
		if (isset($this->__overrideerror)) {
			$strReturn = NULL;
		} else {
			$strFieldName = ($intDynamicPosition > 0) ? $this->__fieldname . "_" . $intDynamicPosition : $this->__fieldname;
			$strReturn = (array_key_exists($strFieldName, $_REQUEST)) ? $_REQUEST[$strFieldName] : NULL;
		}
		
		return $strReturn;
	}
	
	public function validate($intDynamicPosition = 0) {
		$this->__error = "";
		
		$value = $this->getValue($intDynamicPosition);

		//*** Check "required" option.
		if (is_array($value)) {
			$blnEmpty = TRUE;
			foreach ($value as $valueItem) {
				if (is_object($this->__targetfield)) {
					
					if ($valueItem == $this->__targetfield->getName()) {
						// Validate target field and set error/validvalue
						$this->__targetfield->getValidator()->validate();
						$this->__error = $this->__targetfield->getValidator()->getError();
						$this->__validvalue = $this->__targetfield->getValidator()->getValidValue();
						// print_r($this->__targetfield->getValidator());

						if(!empty($this->__validvalue)) {
							$blnEmpty = FALSE;
						}
					}
				}

				if (!empty($valueItem)) {
					$blnEmpty = FALSE;
					break;
				}
			}

			if ($blnEmpty) {
				if ($this->__required) {
					$this->__validvalue = NULL;
					$this->__error = $this->__requirederror;
				} else {
					$this->__validvalue = "";
					return TRUE;
				}
			}
		} else {
			if (empty($value)) {
				if ($this->__required) {
					$this->__validvalue = NULL;
					$this->__error = $this->__requirederror;
				} else {
					$this->__validvalue = NULL;
					
					if (empty($this->__matchwith)) return TRUE;
				}
			}
		}

		//*** Check if value is hint value.
		if (empty($this->__error)) {
			if (!empty($this->__fieldhint) && !is_array($value)) {
				if ($this->__fieldhint == $value) {
					$this->__validvalue = NULL;
					$this->__error = $this->__hinterror;
				}
			}
		}
						
		//*** Check minimum input length.
		if (empty($this->__error)) {
			if ($this->__minlength > 0	&& is_array($value)) {
				if (count($value) < $this->__minlength) {
					$this->__validvalue = NULL;
					$this->__error = sprintf($this->__minlengtherror, $this->__minlength);
				}
			} else if ($this->__minlength > 0
					&& strlen($value) < $this->__minlength) {
				$this->__validvalue = NULL;
				$this->__error = sprintf($this->__minlengtherror, $this->__minlength);
			}
		}

		//*** Check maximum input length.
		if (empty($this->__error)) {
			if ($this->__maxlength > 0	&& is_array($value)) {
				if (count($value) > $this->__maxlength) {
					$this->__validvalue = NULL;
					$this->__error = sprintf($this->__maxlengtherror, $this->__maxlength);
				}
			} else if ($this->__maxlength > 0
					&& strlen($value) > $this->__maxlength) {
				$this->__validvalue = NULL;
				$this->__error = sprintf($this->__maxlengtherror, $this->__maxlength);
			}
		}
		
		//*** Check matching values.
		if (empty($this->__error)) {
			if (!empty($this->__matchwith)) {
				$matchValue = $this->__matchwith->getValue();
				if (empty($matchValue)) $matchValue = NULL;
				if (empty($value)) $value = NULL;
				
				if ($matchValue !== $value) {
					$this->__validvalue = NULL;
					$this->__error = $this->__matchwitherror;
				} else if (is_null($value)) {
					return TRUE;
				}
			}
		}
		
		//*** Check specific types.
		if (empty($this->__error)) {
			switch ($this->__type) {
				case VFORM_CUSTOM:
				case VFORM_CUSTOM_TEXT:
					$blnValidType = VF_Validator::validate($this->__validation, $value);
					break;
				default:
				//echo $this->__type;
					$blnValidType = VF_Validator::validate($this->__type, ($this->__type == VFORM_CAPTCHA) ? $this->__fieldname : $value);
			}

			if (!$blnValidType) {
				$this->__validvalue = NULL;
				$this->__error = $this->__typeerror;
			} else {
				$this->__validvalue = $value;
			}
		}
		
		//*** Override error.
		if (isset($this->__overrideerror)) {
			$this->__validvalue = NULL;
			$this->__error = $this->__overrideerror;
		}
		
		return (is_null($this->__validvalue)) ? FALSE : TRUE;
	}
	
	public function setError($strError) {
		$this->__overrideerror = $strError;
	}
	
	public function getCheck() {
		$strReturn = "";
	
		switch ($this->__type) {
			case VFORM_CUSTOM:
			case VFORM_CUSTOM_TEXT:
				$strReturn = $this->__validation;
				break;
			default:
				$strReturn = VF_Validator::getCheck($this->__type);
		}
		
		return $strReturn;
	}
	
}

?>