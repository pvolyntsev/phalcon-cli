<?php

namespace Danzabar\CLI\Tools;

/**
 * Class PhpFileClassReader
 * @package CLI
 * @subpackage Tools
 * @author Pavel Volyntsev
 * @author Jarret Byrne
 * @see http://jarretbyrne.com/2015/06/197/
 *
 * @usage \Danzabar\CLI\Tools\PhpFileClassReader::getPHPFileClasses('/path/to/file.php');
 * @usage \Danzabar\CLI\Tools\PhpFileClassReader::getPHPCodeClasses('<?php class A extends B { }');
 */
class PhpFileClassReader {

    public static function getPHPFileClasses($pathToFile)
    {
        //Grab the contents of the file
        return self::getPHPCodeClasses(file_get_contents($pathToFile));
    }

    public static function getPHPCodeClasses($phpCode)
    {
        $classes = [];

        //Start with a blank namespace and class
        $namespace = $class = "";

        //Set helper values to know that we have found the namespace/class token and need to collect the string values after them
        $getting_namespace = $getting_class = false;

        $tokens = token_get_all($phpCode);

        //Go through each token and evaluate it as necessary
        foreach($tokens as $token) {

            //If this token is the namespace declaring, then flag that the next tokens will be the namespace name
            if (is_array($token) && $token[0] == T_NAMESPACE)
                $getting_namespace = true;

            //If this token is the class declaring, then flag that the next tokens will be the class name
            if (is_array($token) && $token[0] == T_CLASS)
                $getting_class = true;

            //While we're grabbing the namespace name...
            if ($getting_namespace === true) {

                //If the token is a string or the namespace separator...
                if(is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {

                    //Append the token's value to the name of the namespace
                    $namespace .= $token[1];

                }
                else if ($token === ';') {

                    //If the token is the semicolon, then we're done with the namespace declaration
                    $getting_namespace = false;

                }
            }

            //While we're grabbing the class name...
            if ($getting_class === true) {

                //If the token is a string, it's the name of the class
                if(is_array($token) && $token[0] == T_STRING) {

                    //Store the token's value as the class name
                    $class = $token[1];

                    //Build the fully-qualified class name and return it
                    $classes[] =  $namespace ? $namespace . '\\' . $class : $class;

                    $class = '';
                    $getting_class = $getting_namespace = false;
                }
            }
        }

        return $classes;
    }
}