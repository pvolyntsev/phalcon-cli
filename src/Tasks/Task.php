<?php

namespace Danzabar\CLI\Tasks;

use Danzabar\CLI\Input\InputOption;
use \Phalcon\CLI\Task as PhalTask;
use \Danzabar\CLI\Input\InputArgument;
use \Danzabar\CLI\Output\Output;
use \Danzabar\CLI\Input\Input;
use Danzabar\CLI\Tasks\Helpers;

/**
 * The command class deals with executing CLI based commands, through phalcon task
 *
 * @package CLI
 * @subpackage Command
 * @author Dan Cox
 *
 * @property InputArgument $argument
 * @property InputOption $option
 * @property Output $output
 * @property Input $input
 * @property Helpers $helpers
 */
class Task extends PhalTask
{

    /**
     * The command name
     *
     * @var string
     */
    protected $name;

    /**
     * The command description
     *
     * @var string
     */
    protected $description;

    /**
     * Returns the output instance
     *
     * @return Output
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Returns the input instance
     *
     * @return Input
     */
    public function getInput()
    {
        return $this->input;
    }
} // END class Command extends Task
