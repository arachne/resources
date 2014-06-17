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
 * @author Jáchym Toušek
 */
class ResourcesMacros extends \Latte\Macros\MacroSet
{

	/**
	 * @param \Latte\Compiler $compiler
	 */
	public static function install(\Latte\Compiler $compiler)
	{
		$me = new static($compiler);
		$me->addMacro('resources', 'echo $_presenter->getContext()->getByType("Arachne\Resources\ResourcesLoader")->getTags(":" . $_presenter->getName() . ":" . $_presenter->getView(), %node.word)');
	}

}
