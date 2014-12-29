<?php namespace Danzabar\CLI\Input;

use Danzabar\CLI\Tools\ParamBag,
	Danzabar\CLI\Input\Traits\ExpectationTrait,
	Danzabar\CLI\Input\Traits\ValidationTrait;


/**
 * The input argument class is a param bag that contains arguments from the user
 *
 * @package CLI
 * @subpackage Input
 * @author Dan Cox
 */
class InputArgument extends ParamBag
{
	use ExpectationTrait, ValidationTrait;

	/**
	 * Class constants for validation
	 *
	 */
	const Required 		= 'required';
	const Optional 		= 'optional';

	/**
	 * An array of expected arguments
	 *
	 * @var Array
	 */
	protected static $expected = Array();

	/**
	 * An array of argument positions
	 *
	 * @var string
	 */
	protected static $varPosition = Array();

} // END class InputArgument