<?php

/**
 * This file is part of the Resources extenstion
 *
 * Copyright (c) Jáchym Toušek (enumag@gmail.com)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Arachne\Resources;

/**
 * The exception that is thrown when a method call is invalid for the object's
 * current state, method has been invoked at an illegal or inappropriate time.
 */
class InvalidStateException extends \RuntimeException
{
}

/**
* The exception that is thrown when directory cannot be found.
*/
class DirectoryNotFoundException extends \RuntimeException
{
}

/**
* The exception that is thrown when file cannot be found.
*/
class FileNotFoundException extends \RuntimeException
{
}
